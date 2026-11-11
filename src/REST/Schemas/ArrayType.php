<?php
namespace Wp\Resta\REST\Schemas;

use Wp\Resta\REST\Attributes\Schema\Property;

abstract class ArrayType extends BaseSchema
{
    public static function describe(): array
    {
        $description = [
            'type' => 'array',
            'description' => static::DESCRIPTION ?: '',
            // $items に #[Property] が無ければ要素型は任意（{}）。空配列は JSON で [] になり OpenAPI として不正
            'items' => new \stdClass(),
        ];
        $reflection = self::getReflection();
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $attributes = $reflectionProperty->getAttributes(Property::class);
            if ($reflectionProperty->name === 'items' && count($attributes) > 0) {
                $description['items'] = $attributes[0]->newInstance()->toArray();
            }
        }
        $description['$id'] = self::getSchemaId();
        return $description;
    }
}
