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
     * パスパラメータが届く形はリクエストの由来で異なる。
     * - パーマリンク形式（/wp-json/...）で HTTP から受けたリクエスト: WordPress が rewrite の一致結果を
     *   urlencode() してから parse_str() するため、URL に書かれたとおりのパーセントエンコードのまま届く
     * - ?rest_route= 形式: rest_route が $_GET から取られるので PHP がデコード済み
     * - rest_do_request() などで PHP から組み立てたリクエスト: 開発者が書いた文字列がそのまま届く
     * 省略時は、ルート文字列が HTTP リクエストの URL パスの末尾と一致するか（= 1 つ目の形）で判定する。
     *
     * @param WP_REST_Request $request WordPress の REST リクエスト
     * @param bool|null $pathParamsAreEncoded パスパラメータがパーセントエンコードのまま届いているか。null なら判定する
     * @return self
     */
    public static function fromWpRequest(WP_REST_Request $request, ?bool $pathParamsAreEncoded = null): self
    {
        return new self($request, $pathParamsAreEncoded ?? self::routeIsTakenFromRequestPath($request));
    }

    /**
     * ルート文字列が、この HTTP リクエストの URL パスからそのまま切り出されたものか
     */
    private static function routeIsTakenFromRequestPath(WP_REST_Request $request): bool
    {
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $route = rtrim($request->get_route(), '/');
        if (!is_string($uriPath) || $route === '') {
            return false;
        }
        return str_ends_with(rtrim($uriPath, '/'), $route);
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
