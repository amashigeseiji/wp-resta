<?php
namespace Wp\Resta\REST\Schemas;

use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\OpenApi\PhpDocToOpenApiSchema;
use Wp\Resta\OpenApi\PhpTypeToOpenApiSchema;
use Wp\Resta\REST\Callback;

/**
 * 操作の callback の戻り値型と docblock からレスポンスのスキーマを推論する
 */
class SchemaInference
{
    /**
     * callback の定義からスキーマを推論
     *
     * 明示的な定義（ルートの SCHEMA / getSchema()）の優先は呼び出し側（ResponseSchema）が行う。
     *
     * 優先順位：
     * 1. callback の戻り値の型（DTO クラス / プリミティブ型 / array など）
     * 2. 戻り値の型が array のとき、callback の PHPDoc @return アノテーション（配列型・array shape・その入れ子）
     * 3. 上記いずれからもスキーマを決定できない場合のみ null（プリミティブ型 / array の戻り値型は type 情報としてフォールバック推論される）
     *
     * @return array<string, mixed>|null
     */
    public function inferSchema(Callback $callback): ?array
    {
        // 1. 戻り値の型が DTO クラスか？
        [$returnType, $nullable] = self::bodyType($callback->getReturnType());
        if ($returnType !== null) {
            $schema = null;
            if (!$returnType->isBuiltin()) {
                $typeName = $returnType->getName();

                // BaseSchema を継承するスキーマはIDつきなので $ref のみ
                if (is_subclass_of($typeName, BaseSchema::class)) {
                    $schema = ['$ref' => $typeName::getSchemaId()];
                }
            } elseif ($returnType->getName() === 'array') {
                // 2. 戻り値が array の場合、PHPDoc（@return。静的なルートの body プロパティでは @var）から形を推論
                $docComment = $callback->getDocComment();
                $schema = $docComment === null
                    ? null
                    : PhpDocToOpenApiSchema::fromReturnOrVar($docComment, $callback->getDeclaringClass());
                if ($schema === null) {
                    $schema = PhpTypeToOpenApiSchema::untypedArray();
                }
            } else {
                // 3. プリミティブ型（string, int, bool, float など）
                $primitiveType = PhpTypeToOpenApiSchema::primitiveToOpenApiType($returnType->getName());
                if ($primitiveType !== null) {
                    $schema = ['type' => $primitiveType];
                }
            }
            // nullable型の場合
            if ($nullable && $schema) {
                // docblock の union から来た anyOf には、入れ子にせず null を並べる
                $members = array_keys($schema) === ['anyOf'] ? $schema['anyOf'] : [$schema];
                return ['anyOf' => [...$members, ['type' => 'null']]];
            }

            return $schema;
        }

        // 4. フォールバック：推論できない
        return null;
    }

    /**
     * 戻り値型のうち、レスポンスボディの形を表す側の型と nullable かどうか。
     *
     * `Post|RestaResponseInterface` のように RestaResponseInterface との union で宣言された callback
     * （`$this->response(null, 404)` を返す分岐があるルート）は、もう一方の型からスキーマを推論する。
     * RestaResponseInterface を除いて 1 つに決まらない union は推論しない
     *
     * @return array{0: ReflectionNamedType|null, 1: bool}
     */
    private static function bodyType(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type, $type->allowsNull()];
        }
        if (!$type instanceof ReflectionUnionType) {
            return [null, false];
        }
        $nullable = false;
        $candidates = [];
        foreach ($type->getTypes() as $member) {
            if (!$member instanceof ReflectionNamedType) {
                return [null, false];
            }
            $name = $member->getName();
            if ($name === 'null') {
                $nullable = true;
            } elseif ($member->isBuiltin() || !is_a($name, RestaResponseInterface::class, true)) {
                $candidates[] = $member;
            }
        }
        return count($candidates) === 1 ? [$candidates[0], $nullable] : [null, false];
    }
}
