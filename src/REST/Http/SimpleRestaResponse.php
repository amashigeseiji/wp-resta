<?php
namespace Wp\Resta\REST\Http;

/**
 * RestaResponseInterface のシンプルな実装
 *
 * AbstractRoute から返される標準的なレスポンス。
 * 純粋な PHP データ（配列、文字列、オブジェクトなど）を保持する。
 */
class SimpleRestaResponse implements RestaResponseInterface
{
    /**
     * @param mixed $data レスポンスデータ（配列、文字列、オブジェクトなど）
     * @param int $status HTTP ステータスコード
     * @param array<string, string> $headers HTTP ヘッダー
     */
    public function __construct(
        private mixed $data,
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
