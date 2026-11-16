<?php
namespace Wp\Resta\REST\Http;

/**
 * このリクエストのレスポンスの、ステータスとヘッダの書き込み先。
 *
 * callback の引数に宣言すると受け取れる。レスポンスボディは callback の戻り値で、ここには書かない。
 * PHP の header() / http_response_code() に当たるものを、リクエストごとのオブジェクトとして受け取る形。
 * 返す必要は無い（返すこともできない）。callback が終わった後で、実行者がここに書かれた値を読む。
 *
 * <code>
 * public function post(string $title, ResponseContext $response): Note
 * {
 *     $note = $this->service->create($title);
 *     $response->status(201);
 *     $response->header('Location', "/notes/{$note->id}");
 *     return $note;
 * }
 * </code>
 *
 * エラーは HttpException を投げる。投げた場合、ここに書いた値は使われない。
 * どのレスポンスにも付ける既定のヘッダは、ルートの HEADERS 定数で宣言する。
 */
interface ResponseContext
{
    /**
     * レスポンスのステータスコード。呼ばなければ 200
     */
    public function status(int $status): void;

    /**
     * レスポンスヘッダ。同じ名前で呼び直すと上書きする。ルートの HEADERS 定数に同名のヘッダがあれば、こちらが優先される
     */
    public function header(string $name, string $value): void;
}
