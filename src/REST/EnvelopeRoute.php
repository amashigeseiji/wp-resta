<?php
namespace Wp\Resta\REST;

use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\Http\RestaRequestInterface;

/**
 * エンベロープパターンを使用するルート基底クラス
 *
 * このクラスを継承すると：
 * 1. callback() の戻り値が自動的に EnvelopeResponse でラップされる
 * 2. OpenAPI スキーマも自動的にエンベロープ構造に変換される
 * 3. invoke() の戻り値型が EnvelopeResponse として保証される
 *
 * 使用例：
 * ```php
 * class Posts extends EnvelopeRoute
 * {
 *     public const SCHEMA = [
 *         'type' => 'array',
 *         'items' => ['$ref' => '#/components/schemas/Post']
 *     ];
 *
 *     public function callback(): array
 *     {
 *         return $posts;  // 自動的にEnvelopeResponseでラップされる
 *     }
 * }
 * ```
 */
abstract class EnvelopeRoute extends AbstractRoute
{
    /**
     * リクエストを処理して EnvelopeResponse を返す
     *
     * callback() の結果を自動的に EnvelopeResponse でラップします。
     */
    public function invoke(RestaRequestInterface $request): EnvelopeResponse
    {
        if (is_callable([$this, 'callback'])) {
            try {
                $result = $this->invokeCallback(
                    new \ReflectionMethod($this, 'callback'),
                    $request
                );

                // 既に EnvelopeResponse の場合はそのまま返す
                if ($result instanceof EnvelopeResponse) {
                    return $result;
                }

                // その他の値は自動的に EnvelopeResponse でラップ
                $this->body = $result;
            } catch (\Exception $e) {
                if ($this->status === 200) {
                    $this->status = 500;
                }
                $this->body = [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ];
            }
        }

        return new EnvelopeResponse($this->body, [], $this->status, $this->headers);
    }

    /**
     * OpenAPI スキーマを取得
     *
     * SCHEMA 定数で定義されたスキーマを自動的にエンベロープ構造でラップします。
     */
    public function getSchema(): array|null
    {
        $schema = static::SCHEMA;
        if ($schema === null) {
            return null;
        }

        // スキーマを自動的にエンベロープ構造でラップ
        return [
            '$schema' => $schema['$schema'] ?? 'http://json-schema.org/draft-04/schema#',
            'type' => 'object',
            'properties' => [
                'data' => $this->extractDataSchema($schema),
                'meta' => [
                    'type' => 'object',
                    'description' => 'Response metadata',
                    'additionalProperties' => true
                ]
            ]
        ];
    }

    /**
     * スキーマからデータ部分を抽出
     *
     * $schema や title などのメタ情報を除外して、実際のデータ構造のみを抽出します。
     */
    private function extractDataSchema(array $schema): array
    {
        // $schema や title などのメタ情報は除外
        $dataSchema = $schema;
        unset($dataSchema['$schema']);

        return $dataSchema;
    }
}
