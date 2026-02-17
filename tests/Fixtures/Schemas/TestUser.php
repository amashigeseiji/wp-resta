<?php
namespace Test\Resta\Fixtures\Schemas;

use Wp\Resta\REST\Schemas\ObjectType;

class TestUser extends ObjectType
{
    public int $id;
    public string $name;
    public string $email;

    public static function metadata(): array
    {
        return [
            'id' => ['description' => 'User ID', 'example' => 1],
            'name' => ['description' => 'User name', 'example' => 'John Doe'],
            'email' => ['description' => 'User email', 'example' => 'john@example.com'],
        ];
    }
}
