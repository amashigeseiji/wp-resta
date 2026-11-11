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

    public function testPhpDocUnionOfTwoBodyTypesIsNotInferred()
    {
        $route = new class extends AbstractRoute {
            /** @return int[]|string[] */
            public function callback(): array
            {
                return [];
            }
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        // 1 つに決まらないので要素型は不明のまま
        $this->assertEquals(['type' => 'array', 'items' => new \stdClass()], $schema);
    }

    /**
     * 未対応の @return の形では不正確なスキーマを出さず、「型が無い」ことが分かる出力にする（#101）
     *
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function unsupportedPhpDocReturnTypes(): array
    {
        $untypedArray = ['type' => 'array', 'items' => new \stdClass()];
        $untypedObject = ['type' => 'object', 'additionalProperties' => new \stdClass()];
        return [
            'array shape は値が任意の object' => ['array{page: int, items: TestUserType[]}', $untypedObject],
            '入れ子の list は要素型が任意' => ['TestUserType[][]', $untypedArray],
            'list<T> は要素型が任意' => ['list<TestUserType>', $untypedArray],
            'non-empty-list<T> は要素型が任意' => ['non-empty-list<TestUserType>', $untypedArray],
            'array<string, T[]> は値が任意の object' => ['array<string, TestUserType[]>', $untypedObject],
            'array<string, array{...}> は値が任意の object' => ['array<string, array{a: int}>', $untypedObject],
            'array<int, T[]> は要素型が任意' => ['array<int, TestUserType[]>', $untypedArray],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unsupportedPhpDocReturnTypes')]
    public function testUnsupportedPhpDocReturnTypeFallsBackToUntypedSchema(string $docType, array $expected): void
    {
        $route = eval(sprintf('return new class extends \Wp\Resta\REST\AbstractRoute {
            /** @return %s */
            public function callback(): array { return []; }
        };', $docType));

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertEquals($expected, $schema);
        $this->assertArrayNotHasKey('properties', $schema);
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

    public function testInferSchemaReturnsNullWhenNoCallbackMethod()
    {
        // callback メソッドがない場合
        $route = new class extends AbstractRoute {
            // callback メソッドなし
        };

        $schema = $this->inference->inferSchema($route->getOperation('GET')->getCallback());

        $this->assertNull($schema);
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
