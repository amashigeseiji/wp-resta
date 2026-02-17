<?php
namespace Wp\Resta\REST\Schemas;

use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\ParserConfig;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Wp\Resta\REST\RouteInterface;

/**
 * Route の callback メソッドからスキーマを自動推論
 */
class SchemaInference
{
    private readonly Lexer $lexer;
    private readonly PhpDocParser $phpDocParser;
    private readonly TypeParser $typeParser;
    private ReflectionMethod $target;

    public function __construct()
    {
        $config = new ParserConfig(usedAttributes: []);
        $this->lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $this->typeParser = new TypeParser($config, $constExprParser);
        $this->phpDocParser = new PhpDocParser($config, $this->typeParser, $constExprParser);
    }

    /**
     * Route からスキーマを推論
     *
     * 優先順位：
     * 1. getSchema() メソッド（明示的定義）
     * 2. callback の戻り値の型（DTO クラス / プリミティブ型 / array など）
     * 3. callback の PHPDoc @return アノテーション（配列型・array shape など）
     * 4. 上記いずれからもスキーマを決定できない場合のみ null（プリミティブ型 / array の戻り値型は type 情報としてフォールバック推論される）
     *
     * @param RouteInterface $route
     * @return array<string, mixed>|null
     */
    public function inferSchema(RouteInterface $route): ?array
    {
        // 1. getSchema() が定義されていれば、それを使う（最優先・後方互換性）
        $schema = $route->getSchema();
        if ($schema !== null) {
            return $schema;
        }

        // callback メソッドが存在するかチェック
        if (!method_exists($route, 'callback')) {
            return null;
        }

        $this->target = new ReflectionMethod($route, 'callback');

        // 2. 戻り値の型が DTO クラスか？
        $returnType = $this->target->getReturnType();
        if ($returnType && $returnType instanceof ReflectionNamedType) {
            $schema = null;
            if (!$returnType->isBuiltin()) {
                $typeName = $returnType->getName();

                // BaseSchema を継承するスキーマはIDつきなので $ref のみ
                if (is_subclass_of($typeName, BaseSchema::class)) {
                    $schema = ['$ref' => $typeName::getSchemaId()];
                }
            } elseif ($returnType->getName() === 'array') {
                // 3. 戻り値が array の場合、PHPDoc から要素型を推論
                $schema = $this->inferFromPhpDoc();
                if ($schema === null) {
                    $schema = ['type' => 'array'];
                }
            } else {
                // 4. プリミティブ型（string, int, bool, float など）
                $primitiveType = $this->mapPrimitiveType($returnType->getName());
                if ($primitiveType !== null) {
                    $schema = ['type' => $primitiveType];
                }
            }
            // nullable型の場合
            if ($returnType->allowsNull() && $schema) {
                return [
                    'anyOf' => [
                        $schema,
                        ['type' => 'null'],
                    ],
                ];
            }

            return $schema;
        }

        // 5. フォールバック：推論できない
        return null;
    }

    /**
     * PHPDoc の @return アノテーションから配列スキーマを推論
     *
     * 対応する形式：
     * - Post[]
     * - array<Post>
     * - array<int, Post>
     * - array<string, Post>
     *
     * @return array<string, mixed>|null
     */
    private function inferFromPhpDoc(): ?array
    {
        $docComment = $this->target->getDocComment();
        if ($docComment === false) {
            return null;
        }

        // PHPDoc をパース
        $tokens = new TokenIterator($this->lexer->tokenize($docComment));
        $phpDocNode = $this->phpDocParser->parse($tokens);

        // @return タグを探す
        foreach ($phpDocNode->getTags() as $tag) {
            if ($tag->name === '@return' && $tag->value instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode) {
                $returnType = $tag->value->type;

                // Post[] 形式
                if ($returnType instanceof ArrayTypeNode) {
                    return $this->inferFromArrayTypeNode($returnType);
                }

                // array<Post> または array<string, Post> 形式
                if ($returnType instanceof GenericTypeNode) {
                    return $this->inferFromGenericTypeNode($returnType);
                }
                // todo `array{keys: string[], posts: Post[]}` のような記述(ArrayShapeNode) 未対応
                if ($returnType instanceof ArrayShapeNode) {
                    return $this->inferFromArrayShapeNode($returnType);
                }
            }
        }

        return null;
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
        if ($type instanceof IdentifierTypeNode) {
            return $this->resolveIdentifierTypeNode($type, $isObject);
        }

        return null;
    }

    /**
     * todo `array{keys: string[], posts: Post[]}` のような記述(ArrayShapeNode)
     * 未対応
     *
     * @return array{type: "object"}
     */
    private function inferFromArrayShapeNode(ArrayShapeNode $typeNode): array
    {
        return [
            'type' => 'object'
        ];
    }

    /**
     * PHP プリミティブ型を JSON Schema 型にマッピング
     *
     * @param string $phpType PHP の型名
     * @return string|null JSON Schema の型名、または null（プリミティブ型でない場合）
     */
    private function mapPrimitiveType(string $phpType): ?string
    {
        return match ($phpType) {
            'string' => 'string',
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'null' => 'null',
            default => null,
        };
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
            return class_exists($fqcn) ? $fqcn : null;
        }

        // 既にクラスが存在する（完全修飾名として）
        if (class_exists($className)) {
            return $className;
        }

        $context = $this->target;

        // use 文を解析して完全修飾名に変換
        $declaringClass = $context->getDeclaringClass();
        $useStatements = $this->parseUseStatements($declaringClass);

        // use 文にエイリアスがある場合
        if (isset($useStatements[$className])) {
            $fqcn = $useStatements[$className];
            return class_exists($fqcn) ? $fqcn : null;
        }

        // 同じ名前空間内のクラスを試す
        $namespace = $declaringClass->getNamespaceName();
        if ($namespace) {
            $fqcn = $namespace . '\\' . $className;
            if (class_exists($fqcn)) {
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
