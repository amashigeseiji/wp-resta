<?php
namespace Wp\Resta\REST;

use LogicException;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\WpRestaRequest;
use WP_REST_Request;
use WP_REST_Response;

class Route
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

                                // AbstractRoute を実行（WordPress 非依存レイヤー）
                                $response = $route->invoke($restaRequest);

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
