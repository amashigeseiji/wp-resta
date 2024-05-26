<?php
namespace Wp\Resta\REST\Schemas;

use ReflectionClass;
use Wp\Resta\REST\Attributes\Schema\Property;

abstract class ArrayType implements SchemaInterface
{
    public const ID = null;
    public const DESCRIPTION = null;

    public static function describe(): array
    {
        $description = [
            'type' => 'array',
            'description' => static::DESCRIPTION ?: '',
            'items' => [],
        ];
        $reflection = new ReflectionClass(static::class);
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $attributes = $reflectionProperty->getAttributes(Property::class);
            if ($reflectionProperty->name === 'items' && count($attributes) > 0) {
                $description['items'] = $attributes[0]->newInstance()->toArray();
            }
        }
        if (static::ID) {
            $description['$id'] = static::ID;
        }
        return $description;
    }
}
