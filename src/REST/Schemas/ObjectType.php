<?php
namespace Wp\Resta\REST\Schemas;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use Wp\Resta\REST\Attributes\Schema\Property;

abstract class ObjectType extends BaseSchema
{
    public static function describe(): array
    {
        $properties = [];
        $required = [];
        $reflection = self::getReflection();

        // メタデータを取得（オプション）
        $metadata = static::metadata();

        foreach ($reflection->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isStatic()) {
                continue;
            }

            $name = $reflectionProperty->name;
            $type = $reflectionProperty->getType();

            // 1. #[Property] Attribute がある場合（後方互換性）
            $attributes = $reflectionProperty->getAttributes(Property::class);
            if (count($attributes) > 0) {
                $prop = $attributes[0]->newInstance();
                $propSchema = $prop->toArray();
                // type が定義されていない場合は型から推論する
                if (!array_key_exists('type', $propSchema)) {
                    $propSchema = array_merge(self::typeToSchema($type), $propSchema);
                }
            } else {
                // 2. プロパティの型から自動推論
                $propSchema = self::typeToSchema($type);
            }

            // 3. metadata() から追加情報を取得
            if (isset($metadata[$name])) {
                $propSchema = array_merge($propSchema, $metadata[$name]);
            }

            $properties[$name] = $propSchema;

            // nullable でなければ required
            if ($type && !$type->allowsNull()) {
                $required[] = $name;
            }
        }

        $description = [
            'type' => 'object',
            'description' => static::DESCRIPTION ?: '',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $description['required'] = $required;
        }

        $description['$id'] = self::getSchemaId();

        return $description;
    }

    /**
     * ReflectionType から OpenAPI スキーマの型に変換
     *
     * @param ReflectionType|null $type
     * @return array<string, mixed>
     */
    private static function typeToSchema(?ReflectionType $type): array
    {
        if (!$type) {
            return ['type' => 'string'];
        }

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            // プリミティブ型はそのまま OpenAPI の基本型にマッピングする
            return match ($typeName) {
                'int' => ['type' => 'integer'],
                'float' => ['type' => 'number'],
                'string' => ['type' => 'string'],
                'bool' => ['type' => 'boolean'],
                'array' => ['type' => 'array', 'items' => []],
                default => is_subclass_of($typeName, BaseSchema::class)
                    // BaseSchema のサブクラスのみ $ref で参照する
                    ? ['$ref' => $typeName::getSchemaId()]
                    // それ以外のクラス型は汎用的なオブジェクトとして扱う
                    : ['type' => 'object'],
            };
        }

        return ['type' => 'string'];
    }
}
