<?php
namespace Wp\Resta\REST\Schemas;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\ParserConfig;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\OpenApi\PhpTypeToOpenApiSchema;
use Wp\Resta\REST\Callback;

/**
 * 操作の callback の戻り値型と docblock からレスポンスのスキーマを推論する
 */
class SchemaInference
{
    private readonly Lexer $lexer;
    private readonly PhpDocParser $phpDocParser;
    private readonly TypeParser $typeParser;
    private Callback $target;

    public function __construct()
    {
        $config = new ParserConfig(usedAttributes: []);
        $this->lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $this->typeParser = new TypeParser($config, $constExprParser);
        $this->phpDocParser = new PhpDocParser($config, $this->typeParser, $constExprParser);
    }

    /**
     * callback の定義からスキーマを推論
     *
     * 明示的な定義（ルートの SCHEMA / getSchema()）の優先は呼び出し側（ResponseSchema）が行う。
     *
     * 優先順位：
     * 1. callback の戻り値の型（DTO クラス / プリミティブ型 / array など）
     * 2. 戻り値の型が array のとき、callback の PHPDoc @return アノテーション（配列型・array shape・その入れ子）
     * 3. 上記いずれからもスキーマを決定できない場合のみ null（プリミティブ型 / array の戻り値型は type 情報としてフォールバック推論される）
     *
     * @return array<string, mixed>|null
     */
    public function inferSchema(Callback $callback): ?array
    {
        $this->target = $callback;

        // 1. 戻り値の型が DTO クラスか？
        [$returnType, $nullable] = self::bodyType($this->target->getReturnType());
        if ($returnType !== null) {
            $schema = null;
            if (!$returnType->isBuiltin()) {
                $typeName = $returnType->getName();

                // BaseSchema を継承するスキーマはIDつきなので $ref のみ
                if (is_subclass_of($typeName, BaseSchema::class)) {
                    $schema = ['$ref' => $typeName::getSchemaId()];
                }
            } elseif ($returnType->getName() === 'array') {
                // 2. 戻り値が array の場合、PHPDoc から要素型を推論
                $schema = $this->inferFromPhpDoc();
                if ($schema === null) {
                    $schema = PhpTypeToOpenApiSchema::untypedArray();
                }
            } else {
                // 3. プリミティブ型（string, int, bool, float など）
                $primitiveType = PhpTypeToOpenApiSchema::primitiveToOpenApiType($returnType->getName());
                if ($primitiveType !== null) {
                    $schema = ['type' => $primitiveType];
                }
            }
            // nullable型の場合
            if ($nullable && $schema) {
                // docblock の union から来た anyOf には、入れ子にせず null を並べる
                $members = array_keys($schema) === ['anyOf'] ? $schema['anyOf'] : [$schema];
                return ['anyOf' => [...$members, ['type' => 'null']]];
            }

            return $schema;
        }

        // 4. フォールバック：推論できない
        return null;
    }

    /**
     * 戻り値型のうち、レスポンスボディの形を表す側の型と nullable かどうか。
     *
     * `Post|RestaResponseInterface` のように RestaResponseInterface との union で宣言された callback
     * （`$this->response(null, 404)` を返す分岐があるルート）は、もう一方の型からスキーマを推論する。
     * RestaResponseInterface を除いて 1 つに決まらない union は推論しない
     *
     * @return array{0: ReflectionNamedType|null, 1: bool}
     */
    private static function bodyType(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type, $type->allowsNull()];
        }
        if (!$type instanceof ReflectionUnionType) {
            return [null, false];
        }
        $nullable = false;
        $candidates = [];
        foreach ($type->getTypes() as $member) {
            if (!$member instanceof ReflectionNamedType) {
                return [null, false];
            }
            $name = $member->getName();
            if ($name === 'null') {
                $nullable = true;
            } elseif ($member->isBuiltin() || !is_a($name, RestaResponseInterface::class, true)) {
                $candidates[] = $member;
            }
        }
        return count($candidates) === 1 ? [$candidates[0], $nullable] : [null, false];
    }

    /**
     * PHPDoc の @return アノテーションから配列スキーマを推論
     *
     * 戻り値型が array のときに読む。null と RestaResponseInterface は戻り値型の側で扱うので、ここでは除く。
     * 残りが配列の形（T[]、array<...>、list<...>、array{...}、array）でなければ推論しない
     *
     * @return array<string, mixed>|null
     */
    private function inferFromPhpDoc(): ?array
    {
        $docComment = $this->target->getDocComment();
        if ($docComment === null) {
            return null;
        }

        // PHPDoc をパース
        $tokens = new TokenIterator($this->lexer->tokenize($docComment));
        $phpDocNode = $this->phpDocParser->parse($tokens);

        // @return タグを探す。静的なルートの body プロパティでは @var がそれに当たる
        foreach ($phpDocNode->getTags() as $tag) {
            if (
                $tag->value instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode
                || $tag->value instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode
            ) {
                $members = $this->bodyTypeNodes($tag->value->type);
                if ($members === []) {
                    continue;
                }
                foreach ($members as $member) {
                    $isArray = $member instanceof ArrayTypeNode
                        || $member instanceof GenericTypeNode
                        || $member instanceof ArrayShapeNode
                        || ($member instanceof IdentifierTypeNode && $member->name === 'array');
                    if (!$isArray) {
                        return null;
                    }
                }
                if (count($members) > 1) {
                    return $this->unionSchema($members);
                }
                $schema = $this->schemaOf($members[0]);
                if ($schema === null && $members[0] instanceof ArrayShapeNode) {
                    // キー無しの shape（array{T, U}）は扱わない。これまでどおり値が任意の object として出す（#101）
                    return PhpTypeToOpenApiSchema::untypedObject();
                }
                return $schema;
            }
        }

        return null;
    }

    /**
     * PHPDoc の型のうち、レスポンスボディの形を表す側の型。
     * bodyType() と同じく null と RestaResponseInterface を除く
     *
     * @return list<TypeNode>
     */
    private function bodyTypeNodes(TypeNode $type): array
    {
        if ($type instanceof NullableTypeNode) {
            return $this->bodyTypeNodes($type->type);
        }
        if (!$type instanceof UnionTypeNode) {
            return [$type];
        }
        $candidates = [];
        foreach ($type->types as $member) {
            if ($member instanceof IdentifierTypeNode) {
                if ($member->name === 'null') {
                    continue;
                }
                $class = $this->resolveClassName($member->name);
                if ($class !== null && is_a($class, RestaResponseInterface::class, true)) {
                    continue;
                }
            }
            $candidates[] = $member;
        }
        return $candidates;
    }

    /**
     * PHPDoc の型 1 つをスキーマにする。入れ子は再帰で解決する。
     * 解決できない型は null。呼び出し側は、その位置を任意（{}）にして、解決できた部分を残す
     *
     * @return array<string, mixed>|\stdClass|null
     */
    private function schemaOf(TypeNode $type): array|\stdClass|null
    {
        // T[]
        if ($type instanceof ArrayTypeNode) {
            return ['type' => 'array', 'items' => $this->schemaOrAny($type->type)];
        }
        // array<T>、array<K, T>、list<T>
        if ($type instanceof GenericTypeNode) {
            return $this->genericSchema($type);
        }
        // array{k: T, j?: U}
        if ($type instanceof ArrayShapeNode) {
            return $this->shapeSchema($type);
        }
        // T|U、'a'|'b'
        if ($type instanceof UnionTypeNode) {
            return $this->unionSchema($type->types);
        }
        // ?T
        if ($type instanceof NullableTypeNode) {
            $schema = $this->schemaOf($type->type);
            return $schema === null ? null : ['anyOf' => [$schema, ['type' => 'null']]];
        }
        // 'a'、1
        if ($type instanceof ConstTypeNode) {
            $literal = self::literalOf($type);
            return $literal === null ? null : ['type' => $literal[0], 'enum' => [$literal[1]]];
        }
        if ($type instanceof IdentifierTypeNode) {
            if ($type->name === 'mixed') {
                return new \stdClass();
            }
            if ($type->name === 'array') {
                return PhpTypeToOpenApiSchema::untypedArray();
            }
            $primitive = PhpTypeToOpenApiSchema::primitiveToSchema($type->name);
            if ($primitive !== null) {
                return $primitive;
            }
            // ObjectType のサブクラスは $ref（スキーマの再利用）
            $className = $this->resolveClassName($type->name);
            if ($className && is_subclass_of($className, ObjectType::class)) {
                return ['$ref' => $className::getSchemaId()];
            }
        }
        return null;
    }

    /**
     * 解決できない型を、任意の値のスキーマ {} にする。PHP の空配列は JSON で [] になるので stdClass
     *
     * @return array<string, mixed>|\stdClass
     */
    private function schemaOrAny(TypeNode $type): array|\stdClass
    {
        return $this->schemaOf($type) ?? new \stdClass();
    }

    /**
     * array<T>、array<int, T>、list<T>、non-empty-list<T> は配列、array<string, T> は値が T の object
     *
     * @return array<string, mixed>|null
     */
    private function genericSchema(GenericTypeNode $type): ?array
    {
        $name = $type->type->name;
        $arguments = $type->genericTypes;

        if (in_array($name, ['list', 'non-empty-list'], true) && count($arguments) === 1) {
            return ['type' => 'array', 'items' => $this->schemaOrAny($arguments[0])];
        }
        if (!in_array($name, ['array', 'non-empty-array'], true)) {
            return null;
        }
        if (count($arguments) === 1) {
            return ['type' => 'array', 'items' => $this->schemaOrAny($arguments[0])];
        }
        if (count($arguments) !== 2) {
            return null;
        }
        [$key, $value] = $arguments;
        if ($key instanceof IdentifierTypeNode && $key->name === 'string') {
            return ['type' => 'object', 'additionalProperties' => $this->schemaOrAny($value)];
        }
        return ['type' => 'array', 'items' => $this->schemaOrAny($value)];
    }

    /**
     * array{k: T, j?: U} は object。? 付きのキーは required に入れない。
     * キー無しの要素や整数のキーを含む shape は扱わない
     *
     * @return array<string, mixed>|null
     */
    private function shapeSchema(ArrayShapeNode $shape): ?array
    {
        $properties = [];
        $required = [];
        foreach ($shape->items as $item) {
            $key = $item->keyName;
            if ($key instanceof IdentifierTypeNode) {
                $name = $key->name;
            } elseif ($key instanceof ConstExprStringNode) {
                $name = $key->value;
            } else {
                return null;
            }
            $properties[$name] = $this->schemaOrAny($item->valueType);
            if (!$item->optional) {
                $required[] = $name;
            }
        }
        if ($properties === []) {
            return null;
        }
        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    /**
     * union は anyOf。リテラルは、文字列と整数それぞれ 1 つの enum にまとめる。
     * まとめた結果が 1 つなら、anyOf で包まない
     *
     * @param array<TypeNode> $types
     * @return array<string, mixed>|\stdClass
     */
    private function unionSchema(array $types): array|\stdClass
    {
        $members = [];
        /** @var array<string, int> $enumAt リテラルの型 => $members の位置 */
        $enumAt = [];
        foreach ($types as $type) {
            $literal = $type instanceof ConstTypeNode ? self::literalOf($type) : null;
            if ($literal === null) {
                $members[] = $this->schemaOrAny($type);
                continue;
            }
            [$literalType, $value] = $literal;
            if (!isset($enumAt[$literalType])) {
                $enumAt[$literalType] = count($members);
                $members[] = ['type' => $literalType, 'enum' => []];
            }
            $members[$enumAt[$literalType]]['enum'][] = $value;
        }
        return count($members) === 1 ? $members[0] : ['anyOf' => $members];
    }

    /**
     * 文字列か整数のリテラルの、スキーマの型と値。それ以外は null
     *
     * @return array{0: 'string'|'integer', 1: string|int}|null
     */
    private static function literalOf(ConstTypeNode $type): ?array
    {
        $expr = $type->constExpr;
        if ($expr instanceof ConstExprStringNode) {
            return ['string', $expr->value];
        }
        if ($expr instanceof ConstExprIntegerNode) {
            return ['integer', (int) $expr->value];
        }
        return null;
    }

    /**
     * クラス名を解決（短縮名から完全修飾名へ）
     *
     * @param string $className 短縮名または完全修飾名
     * @return class-string|null
     */
    private function resolveClassName(string $className): ?string
    {
        // 先頭に \ がある場合は完全修飾名
        if (str_starts_with($className, '\\')) {
            $fqcn = ltrim($className, '\\');
            return (class_exists($fqcn) || interface_exists($fqcn)) ? $fqcn : null;
        }

        // 既にクラスが存在する（完全修飾名として）
        if ((class_exists($className) || interface_exists($className))) {
            return $className;
        }

        // use 文を解析して完全修飾名に変換
        $declaringClass = $this->target->getDeclaringClass();
        if ($declaringClass === null) {
            return null;
        }
        $useStatements = $this->parseUseStatements($declaringClass);

        // use 文にエイリアスがある場合
        if (isset($useStatements[$className])) {
            $fqcn = $useStatements[$className];
            return (class_exists($fqcn) || interface_exists($fqcn)) ? $fqcn : null;
        }

        // 同じ名前空間内のクラスを試す
        $namespace = $declaringClass->getNamespaceName();
        if ($namespace) {
            $fqcn = $namespace . '\\' . $className;
            if ((class_exists($fqcn) || interface_exists($fqcn))) {
                return $fqcn;
            }
        }

        return null;
    }

    /**
     * クラスの use 文を解析して、エイリアス → 完全修飾名のマップを作成
     *
     * @param ReflectionClass<object> $class
     * @return array<string, string> エイリアス => 完全修飾名
     */
    private function parseUseStatements(ReflectionClass $class): array
    {
        static $cache = [];
        $fileName = $class->getFileName();
        if ($fileName === false) {
            return [];
        }

        // 一度解析したファイルはメモリキャッシュから再利用
        if (isset($cache[$fileName])) {
            /** @var array<string, string> */
            return $cache[$fileName];
        }

        $content = file_get_contents($fileName);
        if ($content === false) {
            return [];
        }
        if (!array_key_exists($fileName, $cache)) {
            $cache[$fileName] = [];
        }

        $useStatements =& $cache[$fileName];

        // 基本的な use 文を抽出: use Foo\Bar\Baz;
        // as や { を含む行は後続の専用パターンで処理するため除外
        preg_match_all(
            '/^\s*use\s+([^\s;{]+)\s*;/m',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            // as を含む行をスキップ（エイリアス付き use 文は別途処理）
            if (strpos($match[0], ' as ') !== false) {
                continue;
            }

            $fqcn = $match[1];
            $parts = explode('\\', $fqcn);
            $alias = end($parts);
            $useStatements[$alias] = ltrim($fqcn, '\\');
        }

        // エイリアス付き use 文を抽出: use Foo\Bar\Baz as Qux;
        preg_match_all(
            '/^\s*use\s+([^\s]+)\s+as\s+([^\s;]+)\s*;/m',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $fqcn = $match[1];
            $alias = $match[2];
            $useStatements[$alias] = ltrim($fqcn, '\\');
        }

        // グループ use 文を抽出: use Foo\Bar\{Baz, Qux};
        preg_match_all(
            '/^\s*use\s+([^\s{]+)\s*\{\s*([^}]+)\s*\}\s*;/m',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $baseNamespace = rtrim($match[1], '\\');
            $classes = explode(',', $match[2]);

            foreach ($classes as $classSpec) {
                $classSpec = trim($classSpec);

                // グループ内のエイリアス: Baz as B
                if (preg_match('/^([^\s]+)\s+as\s+([^\s]+)$/', $classSpec, $aliasMatch)) {
                    $className = $aliasMatch[1];
                    $alias = $aliasMatch[2];
                    $fqcn = $baseNamespace . '\\' . $className;
                    $useStatements[$alias] = ltrim($fqcn, '\\');
                } else {
                    // 通常: Baz
                    $parts = explode('\\', $classSpec);
                    $alias = end($parts);
                    $fqcn = $baseNamespace . '\\' . $classSpec;
                    $useStatements[$alias] = ltrim($fqcn, '\\');
                }
            }
        }

        return $useStatements;
    }
}
