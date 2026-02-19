<?php

namespace Wp\Resta\OpenApi;

use ReflectionNamedType;
use ReflectionType;

/**
 * PHP型 → OpenAPI スキーマ変換ユーティリティ
 *
 * ObjectType と SchemaInference で重複していた型マッピングロジックを統一する。
 * PHP のエイリアス (int/integer, float/double, bool/boolean) を一元的に正規化する。
 */
class PhpTypeToOpenApiSchema
{
    /**
     * PHP プリミティブ型名を OpenAPI の型文字列にマッピング
     *
     * PHP のエイリアスを正規化する：
     * - int / integer  → "integer"
     * - float / double → "number"
     * - bool / boolean → "boolean"
     * - string         → "string"
     * - null           → "null"
     *
     * @param string $phpType PHP の型名
     * @return string|null OpenAPI の型名。プリミティブ型でない場合は null
     */
    public static function primitiveToOpenApiType(string $phpType): ?string
    {
        return match ($phpType) {
            'string'           => 'string',
            'int', 'integer'   => 'integer',
            'float', 'double'  => 'number',
            'bool', 'boolean'  => 'boolean',
            'null'             => 'null',
            default            => null,
        };
    }

    /**
     * PHP プリミティブ型名から OpenAPI スキーマ配列を生成
     *
     * @param string $phpType PHP の型名
     * @return array<string, mixed>|null プリミティブ型でない場合は null
     */
    public static function primitiveToSchema(string $phpType): ?array
    {
        $type = self::primitiveToOpenApiType($phpType);
        if ($type === null) {
            return null;
        }
        return ['type' => $type];
    }

    /**
     * ReflectionType から OpenAPI スキーマを生成
     *
     * - ビルトインのプリミティブ型と array を処理する
     * - nullable 型 (?string 等) は anyOf: [..., {type: null}] として表現する
     * - 非ビルトイン型（クラス型）は null を返す（呼び出し元で $ref 等の処理を行うこと）
     * - UnionType / IntersectionType は null を返す
     *
     * @param ReflectionType|null $type
     * @return array<string, mixed>|null スキーマ。解決できない型は null
     */
    public static function fromReflectionType(?ReflectionType $type): ?array
    {
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        if (!$type->isBuiltin()) {
            return null;
        }

        $typeName = $type->getName();

        $schema = $typeName === 'array'
            ? ['type' => 'array', 'items' => []]
            : self::primitiveToSchema($typeName);

        if ($schema === null) {
            return null;
        }

        if ($type->allowsNull()) {
            return [
                'anyOf' => [
                    $schema,
                    ['type' => 'null'],
                ],
            ];
        }

        return $schema;
    }
}
