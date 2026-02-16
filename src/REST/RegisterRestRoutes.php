<?php
namespace Wp\Resta\REST;

use LogicException;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\WpRestaRequest;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ルート情報を一括して登録する
 *
 * {@see Wp\Resta\Hooks\InternalHooks} の {@see rest_api_init} フックを通じて
 * WordPress にルート情報を登録する。
 * {@see register_rest_route} に渡される callback は {@see WP_REST_Request}を受けとり
 * {@see WP_REST_Response} を返す。ここで wp-resta の内部処理と WordPress の表現の変換が
 * 行われている。
 */
class RegisterRestRoutes
{
    private Container $container;

    /**
     * @var array<string, RouteInterface[]>
     */
    public readonly Array $routes;

    public function __construct(Config $config)
    {
        $container = Container::getInstance();

        $routes = [];
        foreach ($config->routeDirectory as $routeDir) {
            $dir = $routeDir[0];
            $namespace = $routeDir[1];
            $apiNamespace = $routeDir[2] ?? 'default';
            $files = glob("{$dir}/*.php");
            foreach ($files as $file) {
                $basename = basename($file, '.php');
                /** @var class-string<RouteInterface> $class */
                $class = "{$namespace}{$basename}";
                if (!class_exists($class)) {
                    throw new LogicException("class \"{$class}\" does not exist or cannot load.");
                }
                if (!is_subclass_of($class, RouteInterface::class)) {
                    throw new LogicException("class \"{$class}\" does not implement RouteInterface.");
                }
                // namespace をセットする必要があるのでこのタイミングで初期化する
                $routeObject = $container->get($class);
                $routeObject->setNamespace($apiNamespace);
                $container->bind($class, $routeObject);
                if (!isset($routes[$apiNamespace])) {
                    $routes[$apiNamespace] = [];
                }
                $routes[$apiNamespace][] = $routeObject;
            }
        }
        $this->routes = $routes;
        $this->container = $container;
    }

    public function register() : void
    {
        foreach ($this->routes as $apiNamespace => $routes) {
            foreach ($routes as $route) {
                assert($route instanceof RouteInterface);
                register_rest_route(
                    $route->getNamespace(),
                    $route->getRouteRegex(),
                    [
                        [
                            'methods' => $route->getMethods(),
                            'callback' => function (WP_REST_Request $request) use($route): WP_REST_Response {
                                // WordPress Request → RestaRequest
                                $restaRequest = WpRestaRequest::fromWpRequest($request);

                                // DI container に登録
                                $this->container->bind(WP_REST_Request::class, $request);
                                $this->container->bind(RestaRequestInterface::class, $restaRequest);

                                // Before invoke hook
                                do_action('resta_before_invoke', $route, $restaRequest);

                                // AbstractRoute を実行（WordPress 非依存レイヤー）
                                $responseBefore = $route->invoke($restaRequest);

                                // After invoke hook - レスポンスを変換可能
                                $response = apply_filters('resta_after_invoke', $responseBefore, $route, $restaRequest);

                                // 型チェック
                                if (!$response instanceof RestaResponseInterface) {
                                    trigger_error(
                                        'resta_after_invoke hook must return RestaResponseInterface, got ' . get_debug_type($response),
                                        E_USER_WARNING
                                    );
                                    // フォールバック: 元のレスポンスにする
                                    $response = $responseBefore;
                                }

                                // RestaResponse → WordPress REST Response
                                // データを直接渡す - JSON encode/decode 不要！
                                $wpResponse = new WP_REST_Response(
                                    $response->getData(),
                                    $response->getStatusCode()
                                );

                                // ヘッダーをコピー
                                foreach ($response->getHeaders() as $name => $value) {
                                    $wpResponse->header($name, $value);
                                }

                                return $wpResponse;
                            },
                            'permission_callback' => [$route, 'permissionCallback'],
                            'args' => $route->getArgs(),
                        ],
                        'schema' => [$route, 'getSchema'],
                    ],
                );
            }
        }
    }
}
