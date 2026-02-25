<?php
namespace Wp\Resta\Kernel;

use Wp\Resta\Lifecycle\RequestHandler;
use Wp\Resta\REST\AbstractProxyRoute;
use WP_REST_Server;

/**
 * WP_REST_Server を拡張してプロキシルートを wp-resta のパイプラインに乗せるサーバー
 *
 * `wp_rest_server_class` フィルターでこのクラスに差し替える（WpKernelAdapter が登録）。
 * `match_request_to_handler()` をオーバーライドし、プロキシルートにマッチするリクエストを
 * インターセプトして RequestHandler::handle() に委譲する。
 *
 * これにより AbstractProxyRoute::invoke()/callback() が呼ばれ、
 * RequestHandler の状態遷移・イベントがプロキシルートでも機能する。
 *
 * ## 再帰防止
 *
 * AbstractProxyRoute::callback() は rest_do_request() で元のWPエンドポイントに転送するが、
 * そのまま呼ぶと match_request_to_handler() が再びプロキシを検知して無限ループになる。
 * これを防ぐため、synthetic callback の中で $forwarding フラグを true にしてから
 * RequestHandler::handle() を呼ぶ。$forwarding = true の間はプロキシチェックをスキップ
 * するため、内部の rest_do_request() は parent:: で元のWPハンドラーに到達する。
 */
class WpRestaServer extends WP_REST_Server
{
    /** @var AbstractProxyRoute[] */
    private static array $proxyRoutes = [];

    private static ?RequestHandler $requestHandler = null;

    /**
     * 再帰防止フラグ。
     * synthetic callback 実行中は true になり、プロキシチェックをスキップする。
     */
    private static bool $forwarding = false;

    public static function addProxyRoute(AbstractProxyRoute $route): void
    {
        self::$proxyRoutes[] = $route;
    }

    public static function setRequestHandler(RequestHandler $handler): void
    {
        self::$requestHandler = $handler;
    }

    /** @return AbstractProxyRoute[] */
    public static function getProxyRoutes(): array
    {
        return self::$proxyRoutes;
    }

    public static function clearProxyRoutes(): void
    {
        self::$proxyRoutes = [];
        self::$requestHandler = null;
        self::$forwarding = false;
    }

    /**
     * リクエストに対するハンドラーを返す
     *
     * プロキシルートにマッチする場合は RequestHandler を経由する synthetic callback を返す。
     * これにより AbstractProxyRoute::invoke()/callback() と RequestHandler の
     * 状態遷移・イベントがすべて機能する。
     *
     * $forwarding = true の間（内部の rest_do_request() 経由の呼び出し）は
     * プロキシチェックをスキップして parent:: に委譲する。
     *
     * @param \WP_REST_Request $request
     * @return array|\WP_Error
     */
    protected function match_request_to_handler($request)
    {
        if (!self::$forwarding && self::$requestHandler !== null) {
            foreach (self::$proxyRoutes as $proxyRoute) {
                if (!$this->matchesProxyPath($proxyRoute, $request->get_route())) {
                    continue;
                }

                $handler = self::$requestHandler;
                return [
                    $request->get_route(),
                    [
                        'methods'             => $proxyRoute->getMethods(),
                        'callback'            => function (\WP_REST_Request $wpRequest) use ($proxyRoute, $handler): \WP_REST_Response {
                            WpRestaServer::$forwarding = true;
                            try {
                                return $handler->handle($wpRequest, $proxyRoute);
                            } finally {
                                WpRestaServer::$forwarding = false;
                            }
                        },
                        'permission_callback' => [$proxyRoute, 'permissionCallback'],
                        'args'                => $proxyRoute->getArgs(),
                    ],
                ];
            }
        }

        return parent::match_request_to_handler($request);
    }

    /**
     * リクエストパスがプロキシルートの PROXY_PATH パターンにマッチするか確認する
     *
     * [param] プレースホルダーは URL_PARAMS の regex に変換される。
     */
    private function matchesProxyPath(AbstractProxyRoute $route, string $path): bool
    {
        $proxyPath = $route->getProxyPath();
        if (!$proxyPath) {
            return false;
        }

        $pattern = preg_quote($proxyPath, '@');
        foreach ($route->getArgs() as $param => $def) {
            $pattern = str_replace(
                preg_quote("[{$param}]", '@'),
                "(?P<{$param}>{$def['regex']})",
                $pattern
            );
        }

        return (bool) preg_match('@^' . $pattern . '$@', $path);
    }
}
