<?php
namespace Test\Resta\Unit\REST\Schemas;

use PHPUnit\Framework\TestCase;
use Test\Resta\Fixtures\Routes\TestPhpDocArrayRoute;
use Test\Resta\Fixtures\Routes\TestPhpDocAssociativeRoute;
use Test\Resta\Fixtures\Routes\TestPhpDocGenericRoute;
use Test\Resta\Fixtures\Routes\TestPhpDocUnionDictionaryRoute;
use Test\Resta\Fixtures\Routes\TestPhpDocUnionListRoute;
use Test\Resta\Fixtures\Routes\TestPrimitiveArrayRoute;
use Test\Resta\Fixtures\Routes\TestIntegerArrayRoute;
use Test\Resta\Fixtures\Routes\TestGenericPrimitiveRoute;
use Test\Resta\Fixtures\Routes\TestAssociativePrimitiveRoute;
use Test\Resta\Fixtures\Routes\TestStringReturnRoute;
use Test\Resta\Fixtures\Routes\TestIntReturnRoute;
use Test\Resta\Fixtures\Routes\TestBoolReturnRoute;
use Test\Resta\Fixtures\Routes\TestNullableStringReturnRoute;
use Test\Resta\Fixtures\Schemas\TestUser;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Schemas\ObjectType;
use Wp\Resta\REST\Schemas\SchemaInference;

class SchemaInferenceTest extends TestCase
{
    private SchemaInference $inference;

    protected function setUp(): void
    {
        $this->inference = new SchemaInference();
    }

    public function testInferSchemaFromObjectTypeReturnType()
    {
        // ObjectType を返す場合、$ref で参照される
        $route = new TestUserRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertArrayHasKey('$ref', $schema);
        $this->assertStringContainsString('TestUserType', $schema['$ref']);
        // type や properties は含まれない（$ref のみ）
        $this->assertArrayNotHasKey('type', $schema);
        $this->assertArrayNotHasKey('properties', $schema);
    }

    public function testUnionWithResponseInterfaceInfersFromTheOtherType()
    {
        // $this->response() を返す分岐のあるルートが戻り値型に RestaResponseInterface を含めても、もう一方の型から推論できる
        $route = new class extends AbstractRoute {
            public function callback(): TestUserType|RestaResponseInterface
            {
                return $this->response(null, 404);
            }
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame(['$ref' => TestUserType::getSchemaId()], $schema);
    }

    public function testPhpDocUnionWithResponseInterfaceInfersListFromTheOtherType()
    {
        // @return TestUser[]|RestaResponseInterface は TestUser[] として推論する
        $route = new TestPhpDocUnionListRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame(['type' => 'array', 'items' => ['$ref' => TestUser::getSchemaId()]], $schema);
    }

    public function testPhpDocUnionWithResponseInterfaceInfersDictionaryFromTheOtherType()
    {
        // @return array<string, TestUser>|RestaResponseInterface|null は辞書として推論し、null は anyOf に出る
        $route = new TestPhpDocUnionDictionaryRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame([
            'anyOf' => [
                ['type' => 'object', 'additionalProperties' => ['$ref' => TestUser::getSchemaId()]],
                ['type' => 'null'],
            ],
        ], $schema);
    }

    public function testPhpDocUnionOfTwoBodyTypesIsAnyOf()
    {
        $route = new class extends AbstractRoute {
            /** @return int[]|string[] */
            public function callback(): array
            {
                return [];
            }
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame([
            'anyOf' => [
                ['type' => 'array', 'items' => ['type' => 'integer']],
                ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ], $schema);
    }

    /**
     * @return の docblock の形から導出されるスキーマ（#102）
     *
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function phpDocReturnTypes(): array
    {
        $user = '\\' . TestUserType::class;
        $ref = ['$ref' => TestUserType::getSchemaId()];
        $any = new \stdClass();
        return [
            'array shape は properties と required を持つ object' => [
                "array{page: int, items: {$user}[], count: int}",
                [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'integer'],
                        'items' => ['type' => 'array', 'items' => $ref],
                        'count' => ['type' => 'integer'],
                    ],
                    'required' => ['page', 'items', 'count'],
                ],
            ],
            '? 付きのキーは required に入らない' => [
                'array{a: int, b?: string}',
                [
                    'type' => 'object',
                    'properties' => ['a' => ['type' => 'integer'], 'b' => ['type' => 'string']],
                    'required' => ['a'],
                ],
            ],
            'すべて ? 付きなら required を出さない' => [
                "array{'a-b'?: bool}",
                ['type' => 'object', 'properties' => ['a-b' => ['type' => 'boolean']]],
            ],
            'list<T> は T[] と同じ' => ["list<{$user}>", ['type' => 'array', 'items' => $ref]],
            'non-empty-list<T> は T[] と同じ' => ["non-empty-list<{$user}>", ['type' => 'array', 'items' => $ref]],
            '入れ子の list' => ["{$user}[][]", ['type' => 'array', 'items' => ['type' => 'array', 'items' => $ref]]],
            'array<string, T[]> は値が配列の object' => [
                "array<string, {$user}[]>",
                ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => $ref]],
            ],
            'array<int, T[]> は配列の配列' => [
                "array<int, {$user}[]>",
                ['type' => 'array', 'items' => ['type' => 'array', 'items' => $ref]],
            ],
            '3 段の入れ子' => [
                'array{tags: array{name: string, slug: string}[]}',
                [
                    'type' => 'object',
                    'properties' => [
                        'tags' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => ['name' => ['type' => 'string'], 'slug' => ['type' => 'string']],
                                'required' => ['name', 'slug'],
                            ],
                        ],
                    ],
                    'required' => ['tags'],
                ],
            ],
            '文字列リテラルの union は enum' => [
                "array{order: 'asc'|'desc'}",
                [
                    'type' => 'object',
                    'properties' => ['order' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
                    'required' => ['order'],
                ],
            ],
            '整数リテラルの union は integer の enum' => [
                'array<string, 1|2|3>',
                ['type' => 'object', 'additionalProperties' => ['type' => 'integer', 'enum' => [1, 2, 3]]],
            ],
            '整数リテラルは 16 進・2 進・8 進・符号・桁区切りを値として読む' => [
                'array<string, 0x10|0b11|0o17|017|-5|1_000>',
                ['type' => 'object', 'additionalProperties' => ['type' => 'integer', 'enum' => [16, 3, 15, 15, -5, 1000]]],
            ],
            '括弧で入れ子にした union は、括弧が無いときと同じ' => [
                "array{order: ('asc'|'desc')|'none'}",
                [
                    'type' => 'object',
                    'properties' => ['order' => ['type' => 'string', 'enum' => ['asc', 'desc', 'none']]],
                    'required' => ['order'],
                ],
            ],
            '要素の T|null と ?T は anyOf に null' => [
                'array{a: string|null, b: ?int}',
                [
                    'type' => 'object',
                    'properties' => [
                        'a' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                        'b' => ['anyOf' => [['type' => 'integer'], ['type' => 'null']]],
                    ],
                    'required' => ['a', 'b'],
                ],
            ],
            '要素の T|U は anyOf' => [
                "array<int|{$user}>",
                ['type' => 'array', 'items' => ['anyOf' => [['type' => 'integer'], $ref]]],
            ],
            'mixed は任意' => [
                'array{meta: mixed}',
                ['type' => 'object', 'properties' => ['meta' => $any], 'required' => ['meta']],
            ],
            '解決できない要素は、その位置だけ任意になる' => [
                'array{page: int, items: \stdClass[], when: \DateTimeInterface}',
                [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'integer'],
                        'items' => ['type' => 'array', 'items' => $any],
                        'when' => $any,
                    ],
                    'required' => ['page', 'items', 'when'],
                ],
            ],
            '値の型を解決できない array<string, T> は値が任意の object' => [
                'array<string, \stdClass>',
                ['type' => 'object', 'additionalProperties' => $any],
            ],
            'キー無しの shape は扱わない。値が任意の object のまま' => [
                'array{int, string}',
                ['type' => 'object', 'additionalProperties' => $any],
            ],
            '配列でない @return は読まない' => ['string', ['type' => 'array', 'items' => $any]],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('phpDocReturnTypes')]
    public function testInferSchemaFromPhpDocReturnType(string $docType, array $expected): void
    {
        $route = eval(sprintf('return new class extends \Wp\Resta\REST\AbstractRoute {
            /** @return %s */
            public function callback(): array { return []; }
        };', $docType));

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertEquals($expected, $schema);
    }

    public function testNullableArrayShapeIsAnyOfWithNull()
    {
        $route = new class extends AbstractRoute {
            /** @return array{a: int}|null */
            public function callback(): ?array
            {
                return null;
            }
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame([
            'anyOf' => [
                ['type' => 'object', 'properties' => ['a' => ['type' => 'integer']], 'required' => ['a']],
                ['type' => 'null'],
            ],
        ], $schema);
    }

    public function testParenthesizedPhpDocUnionIsTheSameAsWithoutParentheses()
    {
        $route = new class extends AbstractRoute {
            /** @return (int[]|string[])|null */
            public function callback(): ?array
            {
                return null;
            }
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame([
            'anyOf' => [
                ['type' => 'array', 'items' => ['type' => 'integer']],
                ['type' => 'array', 'items' => ['type' => 'string']],
                ['type' => 'null'],
            ],
        ], $schema);
    }

    public function testNullablePhpDocUnionPutsNullInTheSameAnyOf()
    {
        $route = new class extends AbstractRoute {
            /** @return int[]|array<string, string>|null */
            public function callback(): ?array
            {
                return null;
            }
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame([
            'anyOf' => [
                ['type' => 'array', 'items' => ['type' => 'integer']],
                ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
                ['type' => 'null'],
            ],
        ], $schema);
    }

    public function testNullableUnionWithResponseInterfaceIsAnyOfWithNull()
    {
        $route = new class extends AbstractRoute {
            public function callback(): TestUserType|RestaResponseInterface|null
            {
                return null;
            }
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame([
            'anyOf' => [
                ['$ref' => TestUserType::getSchemaId()],
                ['type' => 'null'],
            ],
        ], $schema);
    }

    public function testUnionOfTwoBodyTypesIsNotInferred()
    {
        $route = new class extends AbstractRoute {
            public function callback(): TestUserType|string
            {
                return 'x';
            }
        };

        $this->assertNull($this->inference->inferSchema($route->getOperation('GET')->getCallback()));
    }

    public function testInferSchemaReturnsSimpleArrayTypeWhenNoSchemaAvailable()
    {
        // SCHEMA 定数もなく、戻り値の型も builtin の場合
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                return [];
            }
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertEquals(['type' => 'array', 'items' => new \stdClass()], $schema, 'OpenAPI 3.0 の array には items が必須。要素型が無ければ {}');
    }

    public function testStaticRouteInfersSchemaFromBodyPropertyType()
    {
        // 静的なルートは、body プロパティの型が callback の戻り値型と同じようにスキーマになる
        $route = new class(new TestUserType()) extends AbstractRoute {
            public function __construct(public readonly TestUserType $body) {}
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame(['$ref' => TestUserType::getSchemaId()], $schema);
    }

    public function testStaticRouteInfersArraySchemaFromVarAnnotation()
    {
        $route = new TestUserListStaticRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertSame(['type' => 'array', 'items' => ['$ref' => TestUserType::getSchemaId()]], $schema);
    }

    public function testInferSchemaFromPhpDocArrayAnnotation()
    {
        // @return TestUser[] 形式のPHPDocからスキーマを推論
        $route = new TestPhpDocArrayRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        // $ref を使用することを確認
        $this->assertArrayHasKey('$ref', $schema['items']);
        $this->assertStringContainsString('TestUser', $schema['items']['$ref']);
    }

    public function testInferSchemaFromPhpDocGenericAnnotation()
    {
        // @return array<TestUser> 形式のPHPDocからスキーマを推論
        $route = new TestPhpDocGenericRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        // $ref を使用することを確認
        $this->assertArrayHasKey('$ref', $schema['items']);
        $this->assertStringContainsString('TestUser', $schema['items']['$ref']);
    }

    public function testInferSchemaFromPhpDocAssociativeAnnotation()
    {
        // @return array<string, TestUser> 形式のPHPDocからスキーマを推論
        $route = new TestPhpDocAssociativeRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('additionalProperties', $schema);
        // $ref を使用することを確認
        $this->assertArrayHasKey('$ref', $schema['additionalProperties']);
        $this->assertStringContainsString('TestUser', $schema['additionalProperties']['$ref']);
    }

    public function testInferSchemaFromPhpDocPrimitiveArray()
    {
        // @return string[] 形式のPHPDocからスキーマを推論
        $route = new TestPrimitiveArrayRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('string', $schema['items']['type']);
        $this->assertArrayNotHasKey('$ref', $schema['items']);
    }

    public function testInferSchemaFromPhpDocIntegerArray()
    {
        // @return int[] 形式のPHPDocからスキーマを推論
        $route = new TestIntegerArrayRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('integer', $schema['items']['type']);
    }

    public function testInferSchemaFromPhpDocGenericPrimitive()
    {
        // @return array<string> 形式のPHPDocからスキーマを推論
        $route = new TestGenericPrimitiveRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('string', $schema['items']['type']);
    }

    public function testInferSchemaFromPhpDocAssociativePrimitive()
    {
        // @return array<int, string> 形式のPHPDocからスキーマを推論
        $route = new TestAssociativePrimitiveRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('string', $schema['items']['type']);
    }

    public function testInferSchemaFromStringReturnType()
    {
        // callback(): string からスキーマを推論
        $route = new TestStringReturnRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('string', $schema['type']);
    }

    public function testInferSchemaFromIntReturnType()
    {
        // callback(): int からスキーマを推論
        $route = new TestIntReturnRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('integer', $schema['type']);
    }

    public function testInferSchemaFromBoolReturnType()
    {
        // callback(): bool からスキーマを推論
        $route = new TestBoolReturnRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertEquals('boolean', $schema['type']);
    }

    public function testInferSchemaFromNullableStringReturnType()
    {
        // callback(): ?string からスキーマを推論
        $route = new TestNullableStringReturnRoute();

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNotNull($schema);
        $this->assertArrayHasKey('anyOf', $schema);
        $this->assertCount(2, $schema['anyOf']);
        $this->assertEquals('string', $schema['anyOf'][0]['type']);
        $this->assertEquals('null', $schema['anyOf'][1]['type']);
    }
}

// Test fixture classes

/**
 * Test ObjectType for SchemaInference tests
 */
class TestUserType extends ObjectType
{
    public int $id;
    public string $name;

    public static function metadata(): array
    {
        return [
            'id' => ['description' => 'User ID'],
            'name' => ['description' => 'User name'],
        ];
    }
}

/**
 * Test Route that returns TestUserType
 */
class TestUserRoute extends AbstractRoute
{
    public function callback(): TestUserType
    {
        // このメソッドは実際には呼ばれない（Reflection で型情報を取得するだけ）
        return new TestUserType();
    }
}

class TestUserListStaticRoute extends AbstractRoute
{
    /** @var TestUserType[] */
    public readonly array $body;

    public function __construct()
    {
        $this->body = [];
    }
}
