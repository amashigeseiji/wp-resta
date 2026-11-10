<?php
namespace Wp\Resta\REST\Http;

use WP_REST_Request;

/**
 * WordPress 実装の RestaRequestInterface
 *
 * WP_REST_Request をラップし、wp-resta が必要とする情報を提供する。
 * PSR-7 を経由せず、直接 WP_REST_Request を使用する。
 */
class WpRestaRequest implements RestaRequestInterface
{
    private function __construct(
        private WP_REST_Request $inner,
        private bool $pathParamsAreEncoded,
    ) {
    }

    /**
     * WP_REST_Request から WpRestaRequest を作成
     *
     * パスパラメータが届く形は URL の形式で異なる。パーマリンク形式（/wp-json/...）では WordPress が
     * rewrite の一致結果を urlencode() してから parse_str() するため、URL に書かれたとおりのパーセント
     * エンコードのまま届く。?rest_route= 形式では rest_route が $_GET から取られるので PHP がデコード済み。
     * 省略時は $_GET に rest_route があるかで判定する。
     *
     * @param WP_REST_Request $request WordPress の REST リクエスト
     * @param bool|null $pathParamsAreEncoded パスパラメータがパーセントエンコードのまま届いているか。null なら判定する
     * @return self
     */
    public static function fromWpRequest(WP_REST_Request $request, ?bool $pathParamsAreEncoded = null): self
    {
        return new self($request, $pathParamsAreEncoded ?? !isset($_GET['rest_route']));
    }

    public function getUrlParam(string $name): mixed
    {
        // WP_REST_Request は ArrayAccess を実装しているため、
        // 配列アクセスで URL パラメータを取得できる（パスパラメータ優先の順序はコアに任せる）
        $value = $this->inner[$name] ?? null;

        // どちらの URL 形式でも同じ値が callback に届くよう、エンコードのまま届くパスパラメータだけデコードする。
        // クエリパラメータとコアが型変換した値には触らない
        if ($this->pathParamsAreEncoded && is_string($value) && array_key_exists($name, $this->inner->get_url_params())) {
            return rawurldecode($value);
        }
        return $value;
    }
}
