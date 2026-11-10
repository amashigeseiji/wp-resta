<?php
namespace Wp\Resta\REST\Parameter;

use BackedEnum;
use LogicException;
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
    /**
     * @param array<string, string|array<string, mixed>> $urlParams
     * @param string $owner エラーメッセージに出す宣言元（`Route::method` の形）
     */
    public function build(string $route, array $urlParams, Callback $callback, string $owner): RouteParameters
    {
        $pathNames = self::pathNames($route);
        $definitions = [];

        foreach ($urlParams as $name => $type) {
            $definitions[$name] = $this->fromUrlParam($name, $type, in_array($name, $pathNames, true), $owner);
        }

        $docParams = self::docParams($callback->getDocComment());
        foreach ($callback->getParameters() as $param) {
            $isPath = in_array($param->name, $pathNames, true);
            if ($isPath && $param->isDefaultValueAvailable()) {
                // [id] のセグメントが無い URL はルートにマッチしないので、default が効く場面が無い。URL_PARAMS の有無によらず検査する
                throw new LogicException(sprintf('%s() argument $%s is a path parameter and cannot have a default value', $owner, $param->name));
            }
            if (isset($definitions[$param->name])) {
                continue;
            }
            $definition = $this->fromSignature($param, $isPath, $docParams[$param->name] ?? null, $owner);
            if ($definition !== null) {
                $definitions[$param->name] = $definition;
            }
        }

        foreach ($pathNames as $name) {
            if (!isset($definitions[$name])) {
                throw new LogicException(sprintf('%s: ROUTE has [%s] but no parameter named "%s" is declared in URL_PARAMS or the method signature', $owner, $name, $name));
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
     * URL_PARAMS の 1 エントリから。register_rest_route() に渡す形は 0.11 の getArgs() と同じに保つ
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
            throw new LogicException(sprintf('%s() argument $%s must have a single named type to be a URL parameter', $owner, $name));
        }
        if ($param->isVariadic()) {
            throw new LogicException(sprintf('%s() argument $%s: variadic parameters are not supported', $owner, $name));
        }

        $enum = null;
        $items = null;
        $bindType = null;
        if ($type->isBuiltin()) {
            $openApiType = $type->getName() === 'array' ? 'array' : PhpTypeToOpenApiSchema::primitiveToOpenApiType($type->getName());
            if ($openApiType === null || $openApiType === 'null') {
                throw new LogicException(sprintf('%s() argument $%s: type %s cannot be a URL parameter', $owner, $name, $type->getName()));
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
        if (!array_key_exists('sanitize_callback', $arg)) {
            // 型変換は RouteParameters::validate() が行う。コアの rest_parse_request_arg による変換を止めて、
            // RestaRequestInterface::getUrlParam() がクライアントの送った値をそのまま返すようにする
            $arg['sanitize_callback'] = null;
        }
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
     * docblock の @param から、配列の要素型と説明文を読む。
     *
     * docblock 全体を phpdoc-parser にかけると @return の array shape まで解析して 1 ルート 50 〜 130 µs かかり、
     * ルート登録のたびに全ルート分走るので、@param 行だけを正規表現で拾う。URL パラメータの配列は
     * クエリ文字列で渡せるスカラーの列だけなので、要素型はプリミティブの 1 段だけ読めれば足りる。
     * レスポンス側（@return）は SchemaInference が完全な文法で読む
     *
     * @return array<string, array{items: array<string, mixed>|null, description: string|null}>
     */
    private static function docParams(?string $docComment): array
    {
        if ($docComment === null) {
            return [];
        }
        // /** @param string[] $tags */ のような 1 行形式も読めるよう、開始・終了記号を先に取り除く
        $body = preg_replace('#^\s*/\*\*|\*/\s*$#', '', $docComment) ?? $docComment;
        // 型は array<string, float> のように山括弧の中に空白を含みうる。1 文字ずつ読む所有格の量指定子（++）にして、
        // array{a: string} のような読めない型でバックトラックが爆発して preg_match_all が失敗し、
        // 後続の @param まで失われることを防ぐ。読めない型の行は単に一致しない
        preg_match_all('/^\s*\*?\s*@param\s+((?:[^\s<]|<[^>]*>)++)\s+\$(\w+)[ \t]*(.*)$/m', $body, $matches, PREG_SET_ORDER);
        $result = [];
        foreach ($matches as [, $type, $name, $description]) {
            $description = trim($description);
            $result[$name] = [
                'items' => self::itemsFromDocType($type),
                'description' => $description === '' ? null : $description,
            ];
        }
        return $result;
    }

    /**
     * T[] / array<T> / list<T> / array<K, T> の要素型（T はプリミティブ）。それ以外は null（任意）
     *
     * @return array<string, mixed>|null
     */
    private static function itemsFromDocType(string $type): ?array
    {
        if (preg_match('/^(\w+)\[\]$/', $type, $m) === 1
            || preg_match('/^(?:array|list)<(?:\w+\s*,\s*)?(\w+)>$/', $type, $m) === 1
        ) {
            return PhpTypeToOpenApiSchema::primitiveToSchema($m[1]);
        }
        return null;
    }
}
