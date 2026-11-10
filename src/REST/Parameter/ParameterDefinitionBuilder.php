<?php
namespace Wp\Resta\REST\Parameter;

use BackedEnum;
use LogicException;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use Wp\Resta\OpenApi\PhpTypeToOpenApiSchema;
use Wp\Resta\REST\Attributes\Param;
use Wp\Resta\REST\Callback;

/**
 * ROUTE、URL_PARAMS、callback のシグニチャから RouteParameters を組み立てる。
 *
 * - URL_PARAMS に書かれた宣言はそのまま使い、同名の callback 引数より優先する
 * - URL_PARAMS に無い callback 引数は、ビルトイン型と backed enum なら URL パラメータの宣言、
 *   それ以外のクラス型なら DI で解決する引数として宣言から外す
 * - パスかクエリかは ROUTE に [name] があるかで決まる
 */
final class ParameterDefinitionBuilder
{
    private readonly Lexer $lexer;
    private readonly PhpDocParser $phpDocParser;

    public function __construct()
    {
        $config = new ParserConfig(usedAttributes: []);
        $this->lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);
        $this->phpDocParser = new PhpDocParser($config, $typeParser, $constExprParser);
    }

    /**
     * @param array<string, string|array<string, mixed>> $urlParams
     * @param string $owner エラーメッセージに出す宣言元（ルートのクラス名）
     */
    public function build(string $route, array $urlParams, Callback $callback, string $owner): RouteParameters
    {
        $pathNames = self::pathNames($route);
        $definitions = [];

        foreach ($urlParams as $name => $type) {
            $definitions[$name] = $this->fromUrlParam($name, $type, in_array($name, $pathNames, true), $owner);
        }

        $docParams = $this->docParams($callback->getDocComment());
        foreach ($callback->getParameters() as $param) {
            if (isset($definitions[$param->name])) {
                continue;
            }
            $definition = $this->fromSignature($param, in_array($param->name, $pathNames, true), $docParams[$param->name] ?? null, $owner);
            if ($definition !== null) {
                $definitions[$param->name] = $definition;
            }
        }

        foreach ($pathNames as $name) {
            if (!isset($definitions[$name])) {
                throw new LogicException(sprintf('%s: ROUTE has [%s] but no parameter named "%s" is declared in URL_PARAMS or callback()', $owner, $name, $name));
            }
        }

        return new RouteParameters(array_values($definitions));
    }

    /** @return string[] ROUTE に現れる [name] の名前 */
    public static function pathNames(string $route): array
    {
        preg_match_all('/\[(\w+)\]/', $route, $m);
        return $m[1];
    }

    /**
     * URL_PARAMS の 1 エントリから。register_rest_route() に渡す形は従来の getArgs() と同じに保つ
     *
     * @param string|array<string, mixed> $type
     */
    private function fromUrlParam(string $name, string|array $type, bool $isPath, string $owner): ParameterDefinition
    {
        if (is_array($type)) {
            $coreArg = $type;
        } else {
            $coreArg = match ($type) {
                // 'string' は任意の文字列。regex による制約は付けない（#81）
                'string', '?string' => ['type' => 'string', 'required' => $type === 'string', 'regex' => null, 'description' => $name],
                'integer', '?integer' => ['type' => 'integer', 'required' => $type === 'integer', 'regex' => '\d+', 'description' => $name],
                default => ['type' => 'string', 'required' => true, 'regex' => $type, 'description' => $name],
            };
        }
        $coreArg = self::withCorePattern($coreArg);
        $regex = $coreArg['regex'] ?? null;

        return new ParameterDefinition(
            name: $name,
            type: is_string($coreArg['type'] ?? null) ? $coreArg['type'] : 'string',
            location: $isPath ? ParameterDefinition::PATH : ParameterDefinition::QUERY,
            required: (bool)($coreArg['required'] ?? false),
            regex: is_string($regex) ? $regex : null,
            description: is_string($coreArg['description'] ?? null) ? $coreArg['description'] : $name,
            enum: isset($coreArg['enum']) && is_array($coreArg['enum']) ? array_values($coreArg['enum']) : null,
            coreArg: $coreArg,
        );
    }

    /**
     * callback の引数 1 つから。URL パラメータでなければ null
     *
     * @param array{items: array<string, mixed>|null, description: string|null}|null $doc
     */
    private function fromSignature(ReflectionParameter $param, bool $isPath, ?array $doc, string $owner): ?ParameterDefinition
    {
        $name = $param->name;
        $type = $param->getType();
        if ($type === null || !($type instanceof ReflectionNamedType)) {
            throw new LogicException(sprintf('%s::callback() argument $%s must have a single named type to be a URL parameter', $owner, $name));
        }
        if ($param->isVariadic()) {
            throw new LogicException(sprintf('%s::callback() argument $%s: variadic parameters are not supported', $owner, $name));
        }

        $enum = null;
        $items = null;
        $bindType = null;
        if ($type->isBuiltin()) {
            $openApiType = $type->getName() === 'array' ? 'array' : PhpTypeToOpenApiSchema::primitiveToOpenApiType($type->getName());
            if ($openApiType === null || $openApiType === 'null') {
                throw new LogicException(sprintf('%s::callback() argument $%s: type %s cannot be a URL parameter', $owner, $name, $type->getName()));
            }
            if ($openApiType === 'array') {
                $items = $doc['items'] ?? [];
            }
        } else {
            $class = $type->getName();
            if (!is_subclass_of($class, BackedEnum::class)) {
                // クラス型は DI で解決する引数。URL パラメータではない
                return null;
            }
            $backing = (new ReflectionEnum($class))->getBackingType();
            $openApiType = $backing !== null && $backing->getName() === 'int' ? 'integer' : 'string';
            $enum = array_map(fn(BackedEnum $case) => $case->value, $class::cases());
            $bindType = $class;
        }

        $attr = null;
        foreach ($param->getAttributes(Param::class) as $attribute) {
            $attr = $attribute->newInstance();
        }

        $hasDefault = $param->isDefaultValueAvailable();
        if ($isPath && $hasDefault) {
            throw new LogicException(sprintf('%s::callback() argument $%s is a path parameter and cannot have a default value', $owner, $name));
        }
        $default = $hasDefault ? $param->getDefaultValue() : null;
        if ($default instanceof BackedEnum) {
            $default = $default->value;
        }

        $regex = $attr?->pattern ?? match ($openApiType) {
            'integer' => '\d+',
            default => null,
        };
        $description = $attr?->description ?? $doc['description'] ?? $name;

        $coreArg = [
            'type' => $openApiType,
            'required' => !$hasDefault,
            'regex' => $regex,
            'description' => $description,
        ];
        if ($enum !== null) {
            $coreArg['enum'] = $enum;
        }
        if ($attr?->min !== null) {
            $coreArg['minimum'] = $attr->min;
        }
        if ($attr?->max !== null) {
            $coreArg['maximum'] = $attr->max;
        }
        if ($items !== null) {
            $coreArg['items'] = $items;
        }
        $coreArg = self::withCorePattern($coreArg);

        return new ParameterDefinition(
            name: $name,
            type: $openApiType,
            location: $isPath ? ParameterDefinition::PATH : ParameterDefinition::QUERY,
            required: !$hasDefault,
            regex: $regex,
            description: $description,
            hasDefault: $hasDefault,
            default: $default,
            enum: $enum,
            minimum: $attr?->min,
            maximum: $attr?->max,
            items: $items,
            bindType: $bindType,
            coreArg: $coreArg,
        );
    }

    /**
     * string 型で regex があれば、WP コアにも pattern として渡して同じ制約を検証させる。
     * コアの rest_validate_json_schema_pattern() は部分一致で照合するため、全体一致にしてから渡す。
     * 明示された pattern は尊重する。コンパイルできない regex は pattern を付けず、wp-resta 側で 500 とログにする
     *
     * @param array<string, mixed> $arg
     * @return array<string, mixed>
     */
    private static function withCorePattern(array $arg): array
    {
        if (($arg['type'] ?? null) !== 'string'
            || !isset($arg['regex']) || !is_string($arg['regex'])
            || array_key_exists('pattern', $arg)
            || !ParameterRegex::isCompilable($arg['regex'])
        ) {
            return $arg;
        }
        $arg['pattern'] = ParameterRegex::anchor($arg['regex']);
        return $arg;
    }

    /**
     * docblock の @param から、配列の要素型と説明文を読む
     *
     * @return array<string, array{items: array<string, mixed>|null, description: string|null}>
     */
    private function docParams(?string $docComment): array
    {
        if ($docComment === null) {
            return [];
        }
        $tokens = new TokenIterator($this->lexer->tokenize($docComment));
        $node = $this->phpDocParser->parse($tokens);
        $result = [];
        foreach ($node->getParamTagValues() as $tag) {
            /** @var ParamTagValueNode $tag */
            $name = ltrim($tag->parameterName, '$');
            $description = trim($tag->description);
            $result[$name] = [
                'items' => self::itemsFromTypeNode($tag->type),
                'description' => $description === '' ? null : $description,
            ];
        }
        return $result;
    }

    /**
     * string[] / array<string> / list<string> の要素型。読めなければ null
     *
     * @return array<string, mixed>|null
     */
    private static function itemsFromTypeNode(TypeNode $type): ?array
    {
        $element = null;
        if ($type instanceof ArrayTypeNode) {
            $element = $type->type;
        } elseif ($type instanceof GenericTypeNode && in_array($type->type->name, ['array', 'list'], true) && $type->genericTypes !== []) {
            $element = end($type->genericTypes);
        }
        if (!$element instanceof IdentifierTypeNode) {
            return null;
        }
        return PhpTypeToOpenApiSchema::primitiveToSchema($element->name);
    }
}
