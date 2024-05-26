<?php
namespace Test\Resta;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Attributes\Schema\Property;
use Wp\Resta\REST\Schemas\ArrayType;
use Wp\Resta\REST\Schemas\ObjectType;

class SchemaTest extends TestCase
{
    public function testArrayTypeSchema()
    {
        $newType = new class() extends ArrayType {
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
                ]
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
            $newType::describe()
        );
    }
}
