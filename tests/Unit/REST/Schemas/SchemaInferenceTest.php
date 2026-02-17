<?php
namespace Test\Resta\Unit\REST\Schemas;

use PHPUnit\Framework\TestCase;
use Test\Resta\Fixtures\Routes\TestPhpDocArrayRoute;
use Test\Resta\Fixtures\Routes\TestPhpDocAssociativeRoute;
use Test\Resta\Fixtures\Routes\TestPhpDocGenericRoute;
use Test\Resta\Fixtures\Routes\TestPrimitiveArrayRoute;
use Test\Resta\Fixtures\Routes\TestIntegerArrayRoute;
use Test\Resta\Fixtures\Routes\TestGenericPrimitiveRoute;
use Test\Resta\Fixtures\Routes\TestAssociativePrimitiveRoute;
use Test\Resta\Fixtures\Routes\TestStringReturnRoute;
use Test\Resta\Fixtures\Routes\TestIntReturnRoute;
use Test\Resta\Fixtures\Routes\TestBoolReturnRoute;
use Test\Resta\Fixtures\Routes\TestNullableStringReturnRoute;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Schemas\ObjectType;
use Wp\Resta\REST\Schemas\SchemaInference;

class SchemaInferenceTest extends TestCase
{
    private SchemaInference $inference;

    protected function setUp(): void
    {
        $this->inference = new SchemaInference();
    }

    public function testInferSchemaFromConstant()
    {
        // SCHEMA 定数が定義されている場合
        $route = new class extends AbstractRoute {
            public const SCHEMA = [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
            ];

            public function callback(): array
            {
                return [];
            }
        };

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('name', $schema['properties']);
    }

    public function testInferSchemaFromObjectTypeReturnType()
    {
        // ObjectType を返す場合、$ref で参照される
        $route = new TestUserRoute();

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertArrayHasKey('$ref', $schema);
        $this->assertStringContainsString('TestUserType', $schema['$ref']);
        // type や properties は含まれない（$ref のみ）
        $this->assertArrayNotHasKey('type', $schema);
        $this->assertArrayNotHasKey('properties', $schema);
    }

    public function testInferSchemaReturnsNullWhenNoSchemaAvailable()
    {
        // SCHEMA 定数もなく、戻り値の型も builtin の場合
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                return [];
            }
        };

        $schema = $this->inference->inferSchema($route);

        $this->assertEquals($schema, ['type' => 'array']);
    }

    public function testInferSchemaReturnsNullWhenNoCallbackMethod()
    {
        // callback メソッドがない場合
        $route = new class extends AbstractRoute {
            // callback メソッドなし
        };

        $schema = $this->inference->inferSchema($route);

        $this->assertNull($schema);
    }

    public function testSchemaConstantHasPriorityOverReturnType()
    {
        // SCHEMA 定数と戻り値の型の両方がある場合、SCHEMA が優先される
        $route = new class extends AbstractRoute {
            public const SCHEMA = [
                'type' => 'object',
                'properties' => [
                    'fromConstant' => ['type' => 'string'],
                ],
            ];

            public function callback(): object
            {
                return new class extends ObjectType {
                    public int $id;
                };
            }
        };

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertArrayHasKey('fromConstant', $schema['properties']);
        $this->assertArrayNotHasKey('id', $schema['properties'] ?? []);
    }

    public function testInferSchemaFromPhpDocArrayAnnotation()
    {
        // @return TestUser[] 形式のPHPDocからスキーマを推論
        $route = new TestPhpDocArrayRoute();

        $schema = $this->inference->inferSchema($route);

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

        $schema = $this->inference->inferSchema($route);

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

        $schema = $this->inference->inferSchema($route);

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

        $schema = $this->inference->inferSchema($route);

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

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('integer', $schema['items']['type']);
    }

    public function testInferSchemaFromPhpDocGenericPrimitive()
    {
        // @return array<string> 形式のPHPDocからスキーマを推論
        $route = new TestGenericPrimitiveRoute();

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('string', $schema['items']['type']);
    }

    public function testInferSchemaFromPhpDocAssociativePrimitive()
    {
        // @return array<int, string> 形式のPHPDocからスキーマを推論
        $route = new TestAssociativePrimitiveRoute();

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertEquals('string', $schema['items']['type']);
    }

    public function testInferSchemaFromStringReturnType()
    {
        // callback(): string からスキーマを推論
        $route = new TestStringReturnRoute();

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertEquals('string', $schema['type']);
    }

    public function testInferSchemaFromIntReturnType()
    {
        // callback(): int からスキーマを推論
        $route = new TestIntReturnRoute();

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertEquals('integer', $schema['type']);
    }

    public function testInferSchemaFromBoolReturnType()
    {
        // callback(): bool からスキーマを推論
        $route = new TestBoolReturnRoute();

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertEquals('boolean', $schema['type']);
    }

    public function testInferSchemaFromNullableStringReturnType()
    {
        // callback(): ?string からスキーマを推論
        $route = new TestNullableStringReturnRoute();

        $schema = $this->inference->inferSchema($route);

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
