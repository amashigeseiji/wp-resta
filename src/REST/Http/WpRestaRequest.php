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
        private WP_REST_Request $inner
    ) {
    }

    /**
     * WP_REST_Request から WpRestaRequest を作成
     *
     * @param WP_REST_Request $request WordPress の REST リクエスト
     * @return self
     */
    public static function fromWpRequest(WP_REST_Request $request): self
    {
        return new self($request);
    }

    public function getUrlParam(string $name): mixed
    {
        // WP_REST_Request は ArrayAccess を実装しているため、
        // 配列アクセスで URL パラメータを取得できる（パスパラメータ優先の順序はコアに任せる）
        $value = $this->inner[$name] ?? null;

        // パスパラメータは、パーマリンクが有効な環境では WordPress がパーセントエンコードのまま渡してくる
        // （rewrite の一致結果を urlencode してから parse_str するため）。?rest_route= 形式ではデコード済みで届く。
        // どちらの形式でも同じ値が callback に届くよう、ここでデコードして WP の差異を閉じる
        if (is_string($value) && array_key_exists($name, $this->inner->get_url_params())) {
            return rawurldecode($value);
        }
        return $value;
    }
}
