<?php
namespace Wp\Resta\REST\Http;

/**
 * エンベロープパターンのレスポンス
 *
 * すべてのREST APIレスポンスを統一的な構造でラップします：
 * {
 *   "data": <actual data>,
 *   "meta": {
 *     "processed_at": "...",
 *     "version": "...",
 *     ...
 *   }
 * }
 */
class EnvelopeResponse implements RestaResponseInterface
{
    /**
     * @param mixed $data 実際のレスポンスデータ
     * @param array<string, mixed> $meta メタデータ
     * @param int $status HTTP ステータスコード
     * @param array<string, string> $headers HTTP ヘッダー
     */
    public function __construct(
        private mixed $data,
        private array $meta = [],
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * エンベロープ構造のデータを返す
     *
     * @return array{data: mixed, meta: array<string, mixed>}
     */
    public function getData(): array
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta,
        ];
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * メタデータを追加
     */
    public function addMeta(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }

    /**
     * メタデータを一括設定
     */
    public function setMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * ファクトリーメソッド：成功レスポンス
     */
    public static function success(mixed $data, array $meta = [], int $status = 200): self
    {
        return new self($data, $meta, $status);
    }

    /**
     * ファクトリーメソッド：エラーレスポンス
     */
    public static function error(string $message, int $code = 500, array $meta = []): self
    {
        return new self(
            ['error' => $message, 'code' => $code],
            array_merge($meta, ['status' => 'error']),
            $code
        );
    }
}
