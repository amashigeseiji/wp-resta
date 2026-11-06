<?php
namespace Wp\Resta\REST\Exception;

use RuntimeException;
use Throwable;

/**
 * HTTP の意味を持つ例外
 *
 * callback や引数解決の途中で投げると、AbstractRoute::invoke() が捕まえて
 * WP_Error と同じ形（code / message / data.status）のレスポンスに変換する。
 * WordPress コアの wp/v2 と同じ形式なので、クライアントは区別なく扱える。
 */
class HttpException extends RuntimeException
{
    /**
     * @param string $message クライアントに返すメッセージ
     * @param int $status HTTP ステータスコード
     * @param string $errorCode WP_Error のコードに相当する識別子（rest_invalid_param など）
     * @param array<string, mixed> $data data に追加で載せる情報（status は invoke() が付ける）
     */
    public function __construct(
        string $message,
        private readonly int $status,
        private readonly string $errorCode,
        private readonly array $data = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
