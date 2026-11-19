<?php

namespace Wp\Resta\OpenApi;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
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
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Schemas\ObjectType;

/**
 * docblock に書かれた配列の形 → OpenAPI スキーマ変換ユーティリティ
 *
 * PHP の型が array のとき、要素の型や array shape は docblock にしか書けない。
 * callback の @return、静的なルートの body の @var、ObjectType のプロパティの @var / @param を、同じ規則で読む
 */
class PhpDocToOpenApiSchema
{
    private static ?Lexer $lexer = null;
    private static ?PhpDocParser $parser = null;

    /**
     * docblock の @return / @var から、配列のスキーマを作る。タグが無い、配列の形でない、のときは null
     *
     * @param ReflectionClass<object>|null $scope クラス名の解決に使う、docblock の書かれたクラス
     * @return array<string, mixed>|null
     */
    public static function fromReturnOrVar(string $docComment, ?ReflectionClass $scope): ?array
    {
        foreach (self::parse($docComment)->getTags() as $tag) {
            if ($tag->value instanceof ReturnTagValueNode || $tag->value instanceof VarTagValueNode) {
                return self::arraySchema($tag->value->type, $scope);
            }
        }
        return null;
    }

    /**
     * docblock の @param ... $name から、配列のスキーマを作る。コンストラクタで宣言したプロパティに使う
     *
     * @param ReflectionClass<object>|null $scope クラス名の解決に使う、docblock の書かれたクラス
     * @return array<string, mixed>|null
     */
    public static function fromParam(string $docComment, string $name, ?ReflectionClass $scope): ?array
    {
        foreach (self::parse($docComment)->getParamTagValues() as $param) {
            if ($param->parameterName === '$' . $name) {
                return self::arraySchema($param->type, $scope);
            }
        }
        return null;
    }

    private static function parse(string $docComment): PhpDocNode
    {
        if (self::$lexer === null || self::$parser === null) {
            $config = new ParserConfig(usedAttributes: []);
            $constExprParser = new ConstExprParser($config);
            self::$lexer = new Lexer($config);
            self::$parser = new PhpDocParser($config, new TypeParser($config, $constExprParser), $constExprParser);
        }
        return self::$parser->parse(new TokenIterator(self::$lexer->tokenize($docComment)));
    }

    /**
     * PHP の型が array の値の、docblock に書かれた形。
     * 配列の形（T[]、array<...>、list<...>、array{...}、array）でなければ null
     *
     * @param ReflectionClass<object>|null $scope
     * @return array<string, mixed>|null
     */
    private static function arraySchema(TypeNode $type, ?ReflectionClass $scope): ?array
    {
        $members = self::bodyTypeNodes($type, $scope);
        if ($members === []) {
            return null;
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
            /** @var array<string, mixed> anyOf。配列の形が 2 つ以上あるので stdClass にはならない */
            return self::unionSchema($members, $scope);
        }
        $schema = self::schemaOf($members[0], $scope);
        if ($schema === null && $members[0] instanceof ArrayShapeNode) {
            // キー無しの shape（array{T, U}）は扱わない。値が任意の object として出す（#101）
            return PhpTypeToOpenApiSchema::untypedObject();
        }
        return $schema instanceof \stdClass ? null : $schema;
    }

    /**
     * PHPDoc の型のうち、値の形を表す側の型。
     * null と RestaResponseInterface は PHP の型の側で扱うので除く
     *
     * @param ReflectionClass<object>|null $scope
     * @return list<TypeNode>
     */
    private static function bodyTypeNodes(TypeNode $type, ?ReflectionClass $scope): array
    {
        if ($type instanceof NullableTypeNode) {
            return self::bodyTypeNodes($type->type, $scope);
        }
        if (!$type instanceof UnionTypeNode) {
            return [$type];
        }
        $candidates = [];
        foreach (self::flatten($type->types) as $member) {
            if ($member instanceof NullableTypeNode) {
                $member = $member->type;
            }
            if ($member instanceof IdentifierTypeNode) {
                if ($member->name === 'null') {
                    continue;
                }
                $class = self::resolveClassName($member->name, $scope);
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
     * @param ReflectionClass<object>|null $scope
     * @return array<string, mixed>|\stdClass|null
     */
    private static function schemaOf(TypeNode $type, ?ReflectionClass $scope): array|\stdClass|null
    {
        // T[]
        if ($type instanceof ArrayTypeNode) {
            return ['type' => 'array', 'items' => self::schemaOrAny($type->type, $scope)];
        }
        // array<T>、array<K, T>、list<T>
        if ($type instanceof GenericTypeNode) {
            return self::genericSchema($type, $scope);
        }
        // array{k: T, j?: U}
        if ($type instanceof ArrayShapeNode) {
            return self::shapeSchema($type, $scope);
        }
        // T|U、'a'|'b'
        if ($type instanceof UnionTypeNode) {
            return self::unionSchema($type->types, $scope);
        }
        // ?T
        if ($type instanceof NullableTypeNode) {
            $schema = self::schemaOf($type->type, $scope);
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
            $className = self::resolveClassName($type->name, $scope);
            if ($className && is_subclass_of($className, ObjectType::class)) {
                return ['$ref' => $className::getSchemaId()];
            }
        }
        return null;
    }

    /**
     * 解決できない型を、任意の値のスキーマ {} にする。PHP の空配列は JSON で [] になるので stdClass
     *
     * @param ReflectionClass<object>|null $scope
     * @return array<string, mixed>|\stdClass
     */
    private static function schemaOrAny(TypeNode $type, ?ReflectionClass $scope): array|\stdClass
    {
        return self::schemaOf($type, $scope) ?? new \stdClass();
    }

    /**
     * array<T>、array<int, T>、list<T>、non-empty-list<T> は配列、array<string, T> は値が T の object
     *
     * @param ReflectionClass<object>|null $scope
     * @return array<string, mixed>|null
     */
    private static function genericSchema(GenericTypeNode $type, ?ReflectionClass $scope): ?array
    {
        $name = $type->type->name;
        $arguments = $type->genericTypes;

        if (in_array($name, ['list', 'non-empty-list'], true) && count($arguments) === 1) {
            return ['type' => 'array', 'items' => self::schemaOrAny($arguments[0], $scope)];
        }
        if (!in_array($name, ['array', 'non-empty-array'], true)) {
            return null;
        }
        if (count($arguments) === 1) {
            return ['type' => 'array', 'items' => self::schemaOrAny($arguments[0], $scope)];
        }
        if (count($arguments) !== 2) {
            return null;
        }
        [$key, $value] = $arguments;
        if ($key instanceof IdentifierTypeNode && $key->name === 'string') {
            return ['type' => 'object', 'additionalProperties' => self::schemaOrAny($value, $scope)];
        }
        return ['type' => 'array', 'items' => self::schemaOrAny($value, $scope)];
    }

    /**
     * array{k: T, j?: U} は object。? 付きのキーは required に入れない。
     * キー無しの要素や整数のキーを含む shape は扱わない
     *
     * @param ReflectionClass<object>|null $scope
     * @return array<string, mixed>|null
     */
    private static function shapeSchema(ArrayShapeNode $shape, ?ReflectionClass $scope): ?array
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
            $properties[$name] = self::schemaOrAny($item->valueType, $scope);
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
     * @param ReflectionClass<object>|null $scope
     * @return array<string, mixed>|\stdClass
     */
    private static function unionSchema(array $types, ?ReflectionClass $scope): array|\stdClass
    {
        $members = [];
        /** @var array<string, int> $enumAt リテラルの型 => $members の位置 */
        $enumAt = [];
        foreach (self::flatten($types) as $type) {
            $literal = $type instanceof ConstTypeNode ? self::literalOf($type) : null;
            if ($literal === null) {
                $members[] = self::schemaOrAny($type, $scope);
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
            return ['integer', self::intOf($expr->value)];
        }
        return null;
    }

    /**
     * 括弧で入れ子になった union（(A|B)|C）を、1 段の並びにする。括弧の有無で出力を変えない
     *
     * @param array<TypeNode> $types
     * @return list<TypeNode>
     */
    private static function flatten(array $types): array
    {
        $flat = [];
        foreach ($types as $type) {
            if ($type instanceof UnionTypeNode) {
                array_push($flat, ...self::flatten($type->types));
            } else {
                $flat[] = $type;
            }
        }
        return $flat;
    }

    /**
     * docblock の整数リテラルの値。PHP と同じく、0x / 0b / 0o / 先頭の 0（8 進）と、桁区切りの _ を読む
     */
    private static function intOf(string $literal): int
    {
        $digits = str_replace('_', '', $literal);
        $sign = 1;
        if ($digits !== '' && ($digits[0] === '-' || $digits[0] === '+')) {
            $sign = $digits[0] === '-' ? -1 : 1;
            $digits = substr($digits, 1);
        }
        $prefix = strtolower(substr($digits, 0, 2));
        $value = match (true) {
            $prefix === '0x' => hexdec(substr($digits, 2)),
            $prefix === '0b' => bindec(substr($digits, 2)),
            $prefix === '0o' => octdec(substr($digits, 2)),
            strlen($digits) > 1 && $digits[0] === '0' => octdec($digits),
            default => (int) $digits,
        };
        return $sign * (int) $value;
    }

    /**
     * クラス名を解決（短縮名から完全修飾名へ）
     *
     * @param string $className 短縮名または完全修飾名
     * @param ReflectionClass<object>|null $scope
     * @return class-string|null
     */
    private static function resolveClassName(string $className, ?ReflectionClass $scope): ?string
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
        $declaringClass = $scope;
        if ($declaringClass === null) {
            return null;
        }
        $useStatements = self::parseUseStatements($declaringClass);

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
    private static function parseUseStatements(ReflectionClass $class): array
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
