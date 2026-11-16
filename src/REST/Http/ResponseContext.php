<?php
namespace Wp\Resta\REST\Http;

/**
 * このリクエストのレスポンスの、ステータスとヘッダの書き込み先。
 *
 * callback の引数に宣言すると受け取れる。レスポンスボディは callback の戻り値で、ここには書かない。
 * PHP の header() / http_response_code() に当たるものを、リクエストごとのオブジェクトとして受け取る形。
 * 返す必要は無い（返すこともできない）。callback が終わった後で、実行者（RequestHandler）がここに書かれた値を読む。
 *
 * <code>
 * public function post(string $title, ResponseContext $response): Note
 * {
 *     $note = $this->service->create($title);
 *     $response->status(201);
 *     $response->header('Location', rest_url("myroute/notes/{$note->id}"));
 *     return $note;
 * }
 * </code>
 *
 * エラーは HttpException を投げる。投げた場合、ここに書いた値は使われない。
 * どのレスポンスにも付ける既定のヘッダは、ルートの HEADERS 定数で宣言する。
 *
 * callback を直接呼ぶテストでは、new して渡し、getStatus() / getHeaders() で書かれた値を確かめられる。
 */
class ResponseContext
{
    private int $status = 200;

    /** @var array<string, string> */
    private array $headers = [];

    /**
     * レスポンスのステータスコード。呼ばなければ 200
     */
    public function status(int $status): void
    {
        $this->status = $status;
    }

    /**
     * レスポンスヘッダ。同じ名前で呼び直すと上書きする。ルートの HEADERS 定数に同名のヘッダがあれば、こちらが優先される
     */
    public function header(string $name, string $value): void
    {
        $this->headers = Headers::merge($this->headers, [$name => $value]);
    }

    /**
     * status() で書かれたステータスコード。書かれていなければ 200
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * header() で書かれたヘッダ。ルートの HEADERS 定数は含まない
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
