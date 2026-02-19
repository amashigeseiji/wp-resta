<?php

namespace Test\Resta\Unit\OpenApi;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\OpenApi\PhpTypeToOpenApiSchema;

/**
 * テスト用フィクスチャ
 *
 * PHP の各型を持つプロパティを宣言し、ReflectionProperty::getType() で
 * ReflectionNamedType を取得するために使用する。
 */
class TypeFixtures
{
    public string $string;
    public int $int;
    public float $float;
    public bool $bool;
    public array $array;
    public ?string $nullableString;
    public ?int $nullableInt;
    public ?float $nullableFloat;
    public ?bool $nullableBool;
    public ?array $nullableArray;
}

class PhpTypeToOpenApiSchemaTest extends TestCase
{
    // -------------------------------------------------------------------------
    // ヘルパー
    // -------------------------------------------------------------------------

    private function reflectType(string $property): ?\ReflectionType
    {
        return (new ReflectionClass(TypeFixtures::class))
            ->getProperty($property)
            ->getType();
    }

    // -------------------------------------------------------------------------
    // primitiveToOpenApiType()
    // -------------------------------------------------------------------------

    public function testPrimitiveToOpenApiTypeString(): void
    {
        $this->assertSame('string', PhpTypeToOpenApiSchema::primitiveToOpenApiType('string'));
    }

    public function testPrimitiveToOpenApiTypeInt(): void
    {
        $this->assertSame('integer', PhpTypeToOpenApiSchema::primitiveToOpenApiType('int'));
    }

    public function testPrimitiveToOpenApiTypeIntegerAlias(): void
    {
        $this->assertSame('integer', PhpTypeToOpenApiSchema::primitiveToOpenApiType('integer'));
    }

    public function testPrimitiveToOpenApiTypeFloat(): void
    {
        $this->assertSame('number', PhpTypeToOpenApiSchema::primitiveToOpenApiType('float'));
    }

    public function testPrimitiveToOpenApiTypeDoubleAlias(): void
    {
        $this->assertSame('number', PhpTypeToOpenApiSchema::primitiveToOpenApiType('double'));
    }

    public function testPrimitiveToOpenApiTypeBool(): void
    {
        $this->assertSame('boolean', PhpTypeToOpenApiSchema::primitiveToOpenApiType('bool'));
    }

    public function testPrimitiveToOpenApiTypeBooleanAlias(): void
    {
        $this->assertSame('boolean', PhpTypeToOpenApiSchema::primitiveToOpenApiType('boolean'));
    }

    public function testPrimitiveToOpenApiTypeNull(): void
    {
        $this->assertSame('null', PhpTypeToOpenApiSchema::primitiveToOpenApiType('null'));
    }

    public function testPrimitiveToOpenApiTypeArrayReturnsNull(): void
    {
        // array はプリミティブ型スカラーではないため null
        $this->assertNull(PhpTypeToOpenApiSchema::primitiveToOpenApiType('array'));
    }

    public function testPrimitiveToOpenApiTypeUnknownReturnsNull(): void
    {
        $this->assertNull(PhpTypeToOpenApiSchema::primitiveToOpenApiType('SomeClass'));
    }

    public function testPrimitiveToOpenApiTypeObjectReturnsNull(): void
    {
        $this->assertNull(PhpTypeToOpenApiSchema::primitiveToOpenApiType('object'));
    }

    // -------------------------------------------------------------------------
    // primitiveToSchema()
    // -------------------------------------------------------------------------

    public function testPrimitiveToSchemaString(): void
    {
        $this->assertSame(['type' => 'string'], PhpTypeToOpenApiSchema::primitiveToSchema('string'));
    }

    public function testPrimitiveToSchemaInt(): void
    {
        $this->assertSame(['type' => 'integer'], PhpTypeToOpenApiSchema::primitiveToSchema('int'));
    }

    public function testPrimitiveToSchemaIntegerAlias(): void
    {
        $this->assertSame(['type' => 'integer'], PhpTypeToOpenApiSchema::primitiveToSchema('integer'));
    }

    public function testPrimitiveToSchemaFloat(): void
    {
        $this->assertSame(['type' => 'number'], PhpTypeToOpenApiSchema::primitiveToSchema('float'));
    }

    public function testPrimitiveToSchemaDoubleAlias(): void
    {
        $this->assertSame(['type' => 'number'], PhpTypeToOpenApiSchema::primitiveToSchema('double'));
    }

    public function testPrimitiveToBoolSchema(): void
    {
        $this->assertSame(['type' => 'boolean'], PhpTypeToOpenApiSchema::primitiveToSchema('bool'));
    }

    public function testPrimitiveToBooleanAliasSchema(): void
    {
        $this->assertSame(['type' => 'boolean'], PhpTypeToOpenApiSchema::primitiveToSchema('boolean'));
    }

    public function testPrimitiveToSchemaNull(): void
    {
        $this->assertSame(['type' => 'null'], PhpTypeToOpenApiSchema::primitiveToSchema('null'));
    }

    public function testPrimitiveToSchemaArrayReturnsNull(): void
    {
        $this->assertNull(PhpTypeToOpenApiSchema::primitiveToSchema('array'));
    }

    public function testPrimitiveToSchemaUnknownReturnsNull(): void
    {
        $this->assertNull(PhpTypeToOpenApiSchema::primitiveToSchema('SomeClass'));
    }

    // -------------------------------------------------------------------------
    // fromReflectionType() — null / 非対応型
    // -------------------------------------------------------------------------

    public function testFromReflectionTypeWithNullReturnsNull(): void
    {
        $this->assertNull(PhpTypeToOpenApiSchema::fromReflectionType(null));
    }

    public function testFromReflectionTypeWithClassTypeReturnsNull(): void
    {
        // クラス型（非ビルトイン）は null を返す
        $type = (new ReflectionClass(\stdClass::class))->getConstructor()?->getReturnType();
        // stdClass のプロパティから非ビルトイン型を取得する代わりに、
        // ビルトイン型でないプロパティを持つ匿名クラスを使う
        $obj = new class {
            public \stdClass $prop;
        };
        $reflType = (new ReflectionClass($obj))->getProperty('prop')->getType();

        $this->assertNull(PhpTypeToOpenApiSchema::fromReflectionType($reflType));
    }

    // -------------------------------------------------------------------------
    // fromReflectionType() — ビルトイン型（非 nullable）
    // -------------------------------------------------------------------------

    public function testFromReflectionTypeString(): void
    {
        $type = $this->reflectType('string');
        $this->assertSame(['type' => 'string'], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }

    public function testFromReflectionTypeInt(): void
    {
        $type = $this->reflectType('int');
        $this->assertSame(['type' => 'integer'], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }

    public function testFromReflectionTypeFloat(): void
    {
        $type = $this->reflectType('float');
        $this->assertSame(['type' => 'number'], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }

    public function testFromReflectionTypeBool(): void
    {
        $type = $this->reflectType('bool');
        $this->assertSame(['type' => 'boolean'], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }

    public function testFromReflectionTypeArray(): void
    {
        $type = $this->reflectType('array');
        $this->assertSame(['type' => 'array', 'items' => []], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }

    // -------------------------------------------------------------------------
    // fromReflectionType() — nullable 型 → anyOf
    // -------------------------------------------------------------------------

    public function testFromReflectionTypeNullableString(): void
    {
        $type = $this->reflectType('nullableString');
        $this->assertSame([
            'anyOf' => [
                ['type' => 'string'],
                ['type' => 'null'],
            ],
        ], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }

    public function testFromReflectionTypeNullableInt(): void
    {
        $type = $this->reflectType('nullableInt');
        $this->assertSame([
            'anyOf' => [
                ['type' => 'integer'],
                ['type' => 'null'],
            ],
        ], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }

    public function testFromReflectionTypeNullableFloat(): void
    {
        $type = $this->reflectType('nullableFloat');
        $this->assertSame([
            'anyOf' => [
                ['type' => 'number'],
                ['type' => 'null'],
            ],
        ], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }

    public function testFromReflectionTypeNullableBool(): void
    {
        $type = $this->reflectType('nullableBool');
        $this->assertSame([
            'anyOf' => [
                ['type' => 'boolean'],
                ['type' => 'null'],
            ],
        ], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }

    public function testFromReflectionTypeNullableArray(): void
    {
        $type = $this->reflectType('nullableArray');
        $this->assertSame([
            'anyOf' => [
                ['type' => 'array', 'items' => []],
                ['type' => 'null'],
            ],
        ], PhpTypeToOpenApiSchema::fromReflectionType($type));
    }
}
