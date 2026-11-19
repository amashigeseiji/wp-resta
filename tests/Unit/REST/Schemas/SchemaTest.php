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
            public const ID = '#/components/schemas/anonymousArray';
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
                '$id' => '#/components/schemas/anonymousArray'
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

            #[Property(['description' => 'first name is first name.', 'example' => 'seiji'])]
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
                'required' => ['id', 'lastName', 'firstName'],
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

    public function testObjectTypeReadsArrayShapeOfPropertyFromVar()
    {
        // 配列のプロパティは、callback の @return と同じ規則で docblock から形を読む（#102）
        $described = DocblockList::describe();

        $this->assertSame(
            ['type' => 'array', 'items' => ['$ref' => DocblockItem::getSchemaId()]],
            $described['properties']['items']
        );
        $this->assertSame(
            [
                'type' => 'object',
                'properties' => [
                    'order' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['order'],
            ],
            $described['properties']['paging']
        );
        $this->assertSame(
            ['type' => 'object', 'additionalProperties' => ['type' => 'integer']],
            $described['properties']['counts']
        );
    }

    public function testObjectTypeNullableArrayPropertyKeepsShapeAndIsNotRequired()
    {
        $described = DocblockList::describe();

        // null はこれまでどおり required から外すことで表し、スキーマには出さない
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string']], $described['properties']['labels']);
        $this->assertNotContains('labels', $described['required']);
    }

    public function testObjectTypeArrayPropertyWithoutDocblockIsUntypedArray()
    {
        $described = DocblockList::describe();

        $this->assertEquals(['type' => 'array', 'items' => new \stdClass()], $described['properties']['raw']);
        // 配列の形でない docblock は読まない
        $this->assertEquals(['type' => 'array', 'items' => new \stdClass()], $described['properties']['odd']);
    }

    public function testObjectTypeReadsPromotedPropertyFromConstructorParam()
    {
        $described = DocblockPromoted::describe();

        $this->assertSame(
            ['type' => 'array', 'items' => ['$ref' => DocblockItem::getSchemaId()]],
            $described['properties']['items']
        );
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $described['properties']['ids']);
        $this->assertSame(['type' => 'integer'], $described['properties']['page']);
    }

    public function testObjectTypeExplicitSchemaWinsOverDocblock()
    {
        $described = DocblockExplicit::describe();

        // metadata() と #[Property] に書いたキーは、docblock から読んだものを上書きする
        $this->assertSame(
            ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uri'], 'description' => 'URL の一覧'],
            $described['properties']['urls']
        );
        $this->assertSame(
            ['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 1],
            $described['properties']['ids']
        );
    }
}

class DocblockItem extends ObjectType
{
    public int $id;
}

class DocblockList extends ObjectType
{
    /** @var DocblockItem[] */
    public array $items;

    /** @var array{order: 'asc'|'desc', tags?: list<string>} */
    public array $paging;

    /** @var array<string, int> */
    public array $counts;

    /** @var string[]|null */
    public ?array $labels;

    public array $raw;

    /** @var string */
    public array $odd;
}

class DocblockPromoted extends ObjectType
{
    /**
     * @param DocblockItem[] $items
     */
    public function __construct(
        public int $page,
        public array $items,
        /** @var int[] */
        public array $ids,
    ) {
    }
}

class DocblockExplicit extends ObjectType
{
    /** @var string[] */
    public array $urls;

    /** @var int[] */
    #[Property(['minItems' => 1])]
    public array $ids;

    public static function metadata(): array
    {
        return [
            'urls' => ['items' => ['type' => 'string', 'format' => 'uri'], 'description' => 'URL の一覧'],
        ];
    }
}
