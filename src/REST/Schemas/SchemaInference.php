<?php
namespace Wp\Resta\REST\Schemas;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Wp\Resta\REST\RouteInterface;

/**
 * Route の callback メソッドからスキーマを自動推論
 */
class SchemaInference
{
    /**
     * Route からスキーマを推論
     *
     * 優先順位：
     * 1. SCHEMA 定数（明示的定義）
     * 2. callback の戻り値の型（DTO クラス）
     * 3. フォールバック（推論不可 → null）
     *
     * @param RouteInterface $route
     * @return array<string, mixed>|null
     */
    public function inferSchema(RouteInterface $route): ?array
    {
        // 1. SCHEMA 定数が定義されていれば、それを使う（最優先・後方互換性）
        if ($route::SCHEMA !== null) {
            return $route::SCHEMA;
        }

        // callback メソッドが存在するかチェック
        if (!method_exists($route, 'callback')) {
            return null;
        }

        $callback = new ReflectionMethod($route, 'callback');

        // 2. 戻り値の型が DTO クラスか？
        $returnType = $callback->getReturnType();
        if ($returnType && $returnType instanceof ReflectionNamedType && !$returnType->isBuiltin()) {
            $typeName = $returnType->getName();

            // ObjectType を継承しているかチェック
            if (is_subclass_of($typeName, ObjectType::class)) {
                return $this->inferFromObjectType($typeName);
            }

            // ArrayType を継承しているかチェック
            if (is_subclass_of($typeName, ArrayType::class)) {
                return $this->inferFromArrayType($typeName);
            }
        }

        // 3. フォールバック：推論できない
        return null;
    }

    /**
     * ObjectType クラスからスキーマを推論
     *
     * @param class-string $className
     * @return array<string, mixed>
     */
    private function inferFromObjectType(string $className): array
    {
        return $className::describe();
    }

    /**
     * ArrayType クラスからスキーマを推論
     *
     * @param class-string $className
     * @return array<string, mixed>
     */
    private function inferFromArrayType(string $className): array
    {
        return $className::describe();
    }
}
