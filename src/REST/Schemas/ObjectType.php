<?php
namespace Wp\Resta\REST\Schemas;

use ReflectionClass;
use Wp\Resta\REST\Attributes\Schema\Property;

abstract class ObjectType implements SchemaInterface
{
    public const ID = null;
    public const DESCRIPTION = null;

    public static function describe() : array
    {
        $properties = [];
        $reflection = new ReflectionClass(static::class);
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $attributes = $reflectionProperty->getAttributes(Property::class);
            if (count($attributes) > 0) {
                $prop = $attributes[0]->newInstance();
                $properties[$reflectionProperty->name] = $prop->toArray();
            }
        }

        $description = [
            'type' => 'object',
            'description' => static::DESCRIPTION ?: '',
            'properties' => $properties,
        ];
        if (static::ID) {
            $description['$id'] = static::ID;
        }

        return $description;
    }
}
