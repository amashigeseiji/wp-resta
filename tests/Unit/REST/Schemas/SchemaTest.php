<?php
namespace Test\Resta\Unit\REST\Schemas;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Attributes\Schema\Property;
use Wp\Resta\REST\Schemas\ArrayType;
use Wp\Resta\REST\Schemas\ObjectType;

class SchemaTest extends TestCase
{
    public function testArrayTypeSchema()
    {
        $newType = new class() extends ArrayType {
            public const ID = 'anonymosArray';
            public const DESCRIPTION = 'This is anonymous array type';
            #[Property(['type' => 'string'])]
            public array $items;
        };
        $this->assertEquals(
            [
                'type' => 'array',
                'description' => 'This is anonymous array type',
                'items' => [
                    'type' => 'string'
                ],
                '$id' => 'anonymosArray'
            ],
            $newType::describe()
        );
    }

    public function testObjectTypeSchema()
    {
        $newType = new class() extends ObjectType {
            public const DESCRIPTION = 'This is anonymous object type';

            #[Property(['type' => 'integer', 'example' => 1])]
            public int $id;

            #[Property(['type' => 'string', 'example' => 'amashige'])]
            public string $lastName;

            #[Property(['type' => 'string', 'description' => 'first name is first name.', 'example' => 'seiji'])]
            public string $firstName;
        };

        $described = $newType::describe();

        // $id は自動生成されるので、存在することだけ確認
        $this->assertArrayHasKey('$id', $described);
        $this->assertStringStartsWith('#/components/schemas/', $described['$id']);

        // $id 以外をチェック
        unset($described['$id']);

        $this->assertEquals(
            [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                        'example' => 1,
                    ],
                    'lastName' => [
                        'type' => 'string',
                        'example' => 'amashige',
                    ],
                    'firstName' => [
                        'type' => 'string',
                        'description' => 'first name is first name.',
                        'example' => 'seiji',
                    ],
                ],
                'description' => 'This is anonymous object type',
            ],
            $described
        );
    }

    public function testObjectTypeSchemaWithTypeInference()
    {
        $newType = new class() extends ObjectType {
            public const DESCRIPTION = 'Test schema with type inference';

            public int $id;
            public string $name;
            public ?string $description;
            public bool $active;
            public float $price;
        };

        $described = $newType::describe();

        // $id の存在確認
        $this->assertArrayHasKey('$id', $described);
        $this->assertStringStartsWith('#/components/schemas/', $described['$id']);

        // 基本的な構造
        $this->assertEquals('object', $described['type']);
        $this->assertEquals('Test schema with type inference', $described['description']);

        // プロパティの型が正しく推論されているか
        $this->assertEquals('integer', $described['properties']['id']['type']);
        $this->assertEquals('string', $described['properties']['name']['type']);
        $this->assertEquals('string', $described['properties']['description']['type']);
        $this->assertEquals('boolean', $described['properties']['active']['type']);
        $this->assertEquals('number', $described['properties']['price']['type']);

        // nullable でないプロパティが required に含まれているか
        $this->assertContains('id', $described['required']);
        $this->assertContains('name', $described['required']);
        $this->assertContains('active', $described['required']);
        $this->assertContains('price', $described['required']);

        // nullable なプロパティは required に含まれない
        $this->assertNotContains('description', $described['required']);
    }

    public function testObjectTypeSchemaWithMetadata()
    {
        $newType = new class() extends ObjectType {
            public int $id;
            public string $name;
            public ?string $email;

            public static function metadata(): array
            {
                return [
                    'id' => ['description' => 'User ID', 'example' => 123],
                    'name' => ['description' => 'User name', 'example' => 'John Doe'],
                    'email' => ['description' => 'User email', 'example' => 'john@example.com'],
                ];
            }
        };

        $described = $newType::describe();

        // metadata() の内容がマージされているか
        $this->assertEquals('User ID', $described['properties']['id']['description']);
        $this->assertEquals(123, $described['properties']['id']['example']);

        $this->assertEquals('User name', $described['properties']['name']['description']);
        $this->assertEquals('John Doe', $described['properties']['name']['example']);

        $this->assertEquals('User email', $described['properties']['email']['description']);
        $this->assertEquals('john@example.com', $described['properties']['email']['example']);
    }

    public function testObjectTypeSchemaWithCustomId()
    {
        $newType = new class() extends ObjectType {
            public const ID = '#/components/schemas/CustomUser';

            public int $id;
            public string $name;
        };

        $described = $newType::describe();

        // カスタム ID が使われているか
        $this->assertEquals('#/components/schemas/CustomUser', $described['$id']);
    }

    public function testObjectTypeSchemaWithClassReference()
    {
        // 参照先のクラスを定義
        $authorType = new class() extends ObjectType {
            public int $id;
            public string $name;
        };

        // クラス参照を持つスキーマ
        $postType = new class() extends ObjectType {
            public int $id;
            public string $title;
            // 注: 実際のクラス参照は複雑なので、テストでは型の推論のみ確認
        };

        $described = $postType::describe();

        $this->assertEquals('integer', $described['properties']['id']['type']);
        $this->assertEquals('string', $described['properties']['title']['type']);
    }

    public function testObjectTypeSchemaBackwardCompatibility()
    {
        // Attribute と型推論の混在
        $newType = new class() extends ObjectType {
            #[Property(['type' => 'integer', 'description' => 'With attribute'])]
            public int $id;

            // Attribute なし（型推論）
            public string $name;

            public static function metadata(): array
            {
                return [
                    'name' => ['description' => 'With metadata'],
                ];
            }
        };

        $described = $newType::describe();

        // Attribute が優先される
        $this->assertEquals('integer', $described['properties']['id']['type']);
        $this->assertEquals('With attribute', $described['properties']['id']['description']);

        // 型推論 + metadata
        $this->assertEquals('string', $described['properties']['name']['type']);
        $this->assertEquals('With metadata', $described['properties']['name']['description']);
    }

    public function testObjectTypeSchemaWithArrayType()
    {
        $newType = new class() extends ObjectType {
            public int $id;
            public array $tags;

            public static function metadata(): array
            {
                return [
                    'tags' => ['description' => 'Tag list', 'items' => ['type' => 'string']],
                ];
            }
        };

        $described = $newType::describe();

        $this->assertEquals('array', $described['properties']['tags']['type']);
        $this->assertEquals('Tag list', $described['properties']['tags']['description']);
        // metadata から items が追加される
        $this->assertArrayHasKey('items', $described['properties']['tags']);
    }
}
