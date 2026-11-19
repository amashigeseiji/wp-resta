<?php
namespace Wp\Resta\REST\Schemas;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use Wp\Resta\OpenApi\PhpDocToOpenApiSchema;
use Wp\Resta\OpenApi\PhpTypeToOpenApiSchema;
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
            // 配列のプロパティは、docblock に書かれた形を読む。無ければ要素の型が任意の配列
            $typeSchema = self::arraySchemaFromDocblock($reflectionProperty) ?? self::typeToSchema($type);

            // 1. #[Property] Attribute がある場合（後方互換性）
            $attributes = $reflectionProperty->getAttributes(Property::class);
            if (count($attributes) > 0) {
                $prop = $attributes[0]->newInstance();
                $propSchema = $prop->toArray();
                // type が定義されていない場合は型から推論する
                if (!array_key_exists('type', $propSchema)) {
                    $propSchema = array_merge($typeSchema, $propSchema);
                }
            } else {
                // 2. プロパティの型から自動推論
                $propSchema = $typeSchema;
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
     * 型が array のプロパティの、docblock に書かれた形。
     * プロパティの @var を読む。コンストラクタで宣言したプロパティは、コンストラクタの @param も読む
     *
     * @return array<string, mixed>|null
     */
    private static function arraySchemaFromDocblock(ReflectionProperty $property): ?array
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType || $type->getName() !== 'array') {
            return null;
        }
        $class = $property->getDeclaringClass();
        $docComment = $property->getDocComment();
        if ($docComment !== false) {
            $schema = PhpDocToOpenApiSchema::fromReturnOrVar($docComment, $class);
            if ($schema !== null) {
                return $schema;
            }
        }
        if ($property->isPromoted()) {
            $constructorDoc = $class->getConstructor()?->getDocComment();
            if (is_string($constructorDoc)) {
                return PhpDocToOpenApiSchema::fromParam($constructorDoc, $property->name, $class);
            }
        }
        return null;
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

            // プリミティブ型（エイリアス int/integer 等も統一処理）
            $primitiveSchema = PhpTypeToOpenApiSchema::primitiveToSchema($typeName);
            if ($primitiveSchema !== null) {
                return $primitiveSchema;
            }

            if ($typeName === 'array') {
                return PhpTypeToOpenApiSchema::untypedArray();
            }

            // BaseSchema のサブクラスのみ $ref で参照する
            if (is_subclass_of($typeName, BaseSchema::class)) {
                return ['$ref' => $typeName::getSchemaId()];
            }

            // それ以外のクラス型は汎用的なオブジェクトとして扱う
            return ['type' => 'object'];
        }

        return ['type' => 'string'];
    }
}
