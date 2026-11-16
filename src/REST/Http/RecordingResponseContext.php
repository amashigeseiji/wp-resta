<?php
namespace Wp\Resta\REST\Http;

/**
 * ResponseContext の実装。callback が書いたステータスとヘッダを覚えておき、実行者（RequestHandler）が後で読む。
 *
 * @internal 利用者の callback は ResponseContext だけを知る。読む側のメソッドは実行者のためのもの
 */
class RecordingResponseContext implements ResponseContext
{
    private int $status = 200;

    /** @var array<string, string> */
    private array $headers = [];

    public function status(int $status): void
    {
        $this->status = $status;
    }

    public function header(string $name, string $value): void
    {
        $this->headers = Headers::merge($this->headers, [$name => $value]);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
