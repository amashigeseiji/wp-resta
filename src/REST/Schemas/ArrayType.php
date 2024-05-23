<?php
namespace Wp\Resta\REST\Schemas;

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
        if (static::ID) {
            $description['$id'] = static::ID;
        }
        return $description;
    }
}
