<?php
namespace Wp\Resta\REST;

use LogicException;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use WP_REST_Request;

/**
 * WordPress REST API へのプロキシルート
 *
 * PROXY_PATH で指定した WordPress REST API エンドポイントへ内部的にリクエストを転送する。
 * rest_do_request() を使うため、HTTP リクエストは発生しない。
 *
 * ## 使い方
 *
 * ```php
 * // シンプルなプロキシ
 * class Posts extends AbstractProxyRoute
 * {
 *     protected const PROXY_PATH = '/wp/v2/posts';
 * }
 *
 * // URL パラメータ展開 + 変換あり
 * class Post extends AbstractProxyRoute
 * {
 *     protected const ROUTE = 'post/[id]';
 *     protected const URL_PARAMS = ['id' => 'integer'];
 *     protected const PROXY_PATH = '/wp/v2/posts/[id]';
 *
 *     protected function transform(mixed $data): mixed
 *     {
 *         return ['id' => $data['id'], 'title' => $data['title']['rendered']];
 *     }
 * }
 * ```
 *
 * ## リファクタリング
 *
 * 実装の準備ができたら `extends AbstractProxyRoute` を `extends AbstractRoute` に変えて
 * `callback()` を実装するだけ。クライアント側は変更不要。
 */
abstract class AbstractProxyRoute extends AbstractRoute
{
    /**
     * プロキシ先の WordPress REST API パス
     *
     * URL_PARAMS と同様に [param] 形式のプレースホルダーが使える。
     * 例: '/wp/v2/posts', '/wp/v2/posts/[id]'
     */
    protected const PROXY_PATH = '';

    /**
     * レスポンスデータを変換する
     *
     * デフォルトはそのまま透過。フィールドの絞り込みや変換はここに書く。
     */
    protected function transform(mixed $data): mixed
    {
        return $data;
    }

    public function invoke(RestaRequestInterface $request): RestaResponseInterface
    {
        if (!static::PROXY_PATH) {
            throw new LogicException(static::class . '::PROXY_PATH が設定されていません。');
        }
        return parent::invoke($request);
    }

    public function callback(RestaRequestInterface $request): RestaResponseInterface
    {
        $path = $this->resolveProxyPath($request);
        $wpRequest = new WP_REST_Request($this->getMethods(), $path);

        foreach ($request->getQueryParams() as $key => $value) {
            $wpRequest->set_param($key, $value);
        }

        $wpResponse = rest_do_request($wpRequest);

        return new SimpleRestaResponse(
            $this->transform($wpResponse->get_data()),
            $wpResponse->get_status(),
            $wpResponse->get_headers()
        );
    }

    /**
     * PROXY_PATH 内の [param] プレースホルダーを実際の値に展開する
     */
    private function resolveProxyPath(RestaRequestInterface $request): string
    {
        $path = static::PROXY_PATH;
        preg_match_all('/\[(\w+)\]/', $path, $matches);
        foreach ($matches[1] as $param) {
            $path = str_replace("[{$param}]", (string) $request->getUrlParam($param), $path);
        }
        return $path;
    }
}
