<?php
namespace Test\Resta\Unit\REST\Schemas;

use PHPUnit\Framework\TestCase;
use Test\Resta\Fixtures\Routes\TestPhpDocArrayRoute;
use Test\Resta\Fixtures\Routes\TestPhpDocAssociativeRoute;
use Test\Resta\Fixtures\Routes\TestPhpDocGenericRoute;
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
        // ObjectType を返す場合
        $route = new TestUserRoute();

        $schema = $this->inference->inferSchema($route);

        $this->assertNotNull($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertEquals('User ID', $schema['properties']['id']['description']);
        $this->assertEquals('User name', $schema['properties']['name']['description']);
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

        $this->assertNull($schema);
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
        $this->assertEquals('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        // $ref を使用することを確認
        $this->assertArrayHasKey('$ref', $schema['items']);
        $this->assertStringContainsString('TestUser', $schema['items']['$ref']);
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
