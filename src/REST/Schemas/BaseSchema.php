<?php
namespace Wp\Resta\REST\Schemas;

use ReflectionClass;

abstract class BaseSchema implements SchemaInterface
{
    /**
     * @return ReflectionClass<static>
     */
    protected static function getReflection(): ReflectionClass
    {
        static $cache = [];
        $class = static::class;
        if (!isset($cache[$class])) {
            $cache[$class] = new ReflectionClass($class);
        }
        return $cache[$class];
    }

    /**
     * ID の生成：static::ID があればそれを使い、なければクラス名から自動生成
     */
    public static function getSchemaId(): string
    {
        $className = self::getReflection()->getShortName();
        return static::ID ?: "#/components/schemas/{$className}";
    }

    /**
     * オプション：プロパティのメタデータを定義
     *
     * @return array<string, array<string, mixed>>
     */
    public static function metadata(): array
    {
        return [];
    }

}
