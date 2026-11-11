<?php
namespace Wp\Resta\REST\Schemas;

use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
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
     * 2. callback の PHPDoc @return アノテーション（配列型・array shape など）
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
                $primitiveType = $this->mapPrimitiveType($returnType->getName());
                if ($primitiveType !== null) {
                    $schema = ['type' => $primitiveType];
                }
            }
            // nullable型の場合
            if ($nullable && $schema) {
                return [
                    'anyOf' => [
                        $schema,
                        ['type' => 'null'],
                    ],
                ];
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
     * 対応する形式：
     * - Post[]
     * - array<Post>
     * - array<int, Post>
     * - array<string, Post>
     * - 上記と null / RestaResponseInterface の union（Post[]|RestaResponseInterface）。戻り値型と同じ扱い
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

        // @return タグを探す
        foreach ($phpDocNode->getTags() as $tag) {
            if ($tag->name === '@return' && $tag->value instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode) {
                $returnType = $tag->value->type;
                if ($returnType instanceof UnionTypeNode) {
                    $returnType = $this->bodyTypeNode($returnType);
                }

                // Post[] 形式
                if ($returnType instanceof ArrayTypeNode) {
                    return $this->inferFromArrayTypeNode($returnType);
                }

                // array<Post> または array<string, Post> 形式
                if ($returnType instanceof GenericTypeNode) {
                    return $this->inferFromGenericTypeNode($returnType);
                }
                // array shape（array{page: int, items: Post[]}）はキーが文字列の object。プロパティの解決は未対応（#102）なので、
                // 値が任意の object として出す。{type: object} だけだと「キーを持たない object」と読まれる（#101）
                if ($returnType instanceof ArrayShapeNode) {
                    return PhpTypeToOpenApiSchema::untypedObject();
                }
            }
        }

        return null;
    }

    /**
     * PHPDoc の union のうち、レスポンスボディの形を表す側の型。
     * bodyType() と同じく null と RestaResponseInterface を除き、1 つに決まらなければ null
     */
    private function bodyTypeNode(UnionTypeNode $union): ?TypeNode
    {
        $candidates = [];
        foreach ($union->types as $member) {
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
        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * ArrayTypeNode (Post[]) からスキーマを生成
     *
     * @param ArrayTypeNode $typeNode
     * @return array<string, mixed>|null
     */
    private function inferFromArrayTypeNode(ArrayTypeNode $typeNode): ?array
    {
        // Post[] の Post 部分を取得
        $elementType = $typeNode->type;

        if ($elementType instanceof IdentifierTypeNode) {
            return $this->resolveIdentifierTypeNode($elementType);
        }

        return null;
    }

    /**
     * GenericTypeNode (array<Post>) からスキーマを生成
     *
     * @param GenericTypeNode $typeNode
     * @return array<string, mixed>|null
     */
    private function inferFromGenericTypeNode(GenericTypeNode $typeNode): ?array
    {
        // array<...> の array 部分を確認
        if (!($typeNode->type instanceof IdentifierTypeNode) || $typeNode->type->name !== 'array') {
            return null;
        }

        // ジェネリック引数を取得
        $genericTypes = $typeNode->genericTypes;

        $isObject = false;
        switch (count($genericTypes)) {
        case 1: // array<Post>
            $type = $genericTypes[0];
            break;
        case 2: // array<int, Post> または array<string, Post>
            $type = $genericTypes[1];
            if ($genericTypes[0] instanceof IdentifierTypeNode) {
                $isObject = $genericTypes[0]->name === 'string';
            }
            break;
        default: // 未対応、実質的にはない
            return null;
        }
        $schema = $type instanceof IdentifierTypeNode ? $this->resolveIdentifierTypeNode($type, $isObject) : null;
        if ($schema === null && $isObject) {
            // array<string, Post[]> や array<string, array{...}> のように値の型を解決できなくても、
            // キーが文字列なら object であることは分かる。呼び出し元の配列のフォールバックに乗せない（#101）
            return PhpTypeToOpenApiSchema::untypedObject();
        }
        return $schema;
    }

    /**
     * PHP プリミティブ型を JSON Schema 型にマッピング
     *
     * @param string $phpType PHP の型名
     * @return string|null JSON Schema の型名、または null（プリミティブ型でない場合）
     */
    private function mapPrimitiveType(string $phpType): ?string
    {
        return PhpTypeToOpenApiSchema::primitiveToOpenApiType($phpType);
    }

    /**
     * @todo このメソッドで解決されるのはいまのところ PHP primitive か ObjectType のサブクラスのみ
     * 配列などの場合再帰的な処理が必要になる
     *
     * @return array<string, mixed>|null
     */
    private function resolveIdentifierTypeNode(IdentifierTypeNode $type, bool $isObject = false): ?array
    {
        $key = $isObject ? 'additionalProperties' : 'items';
        // プリミティブ型の場合
        $primitiveType = $this->mapPrimitiveType($type->name);
        if ($primitiveType !== null) {
            return [
                'type' => $isObject ? 'object' : 'array',
                $key => [
                    'type' => $primitiveType,
                ],
            ];
        }

        // ObjectType のサブクラスの場合
        $className = $this->resolveClassName($type->name);
        if ($className && is_subclass_of($className, ObjectType::class)) {
            // $ref を使用（スキーマの再利用）
            $schemaId = $className::getSchemaId();
            return [
                'type' => $isObject ? 'object' : 'array',
                $key => [
                    '$ref' => $schemaId,
                ],
            ];
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
