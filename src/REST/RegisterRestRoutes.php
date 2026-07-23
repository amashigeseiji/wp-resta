<?php
namespace Wp\Resta\REST;

use LogicException;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\Kernel\WpRestaServer;
use Wp\Resta\Lifecycle\RequestHandler;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ルート情報を一括して登録する
 *
 * rest_api_init フックを通じて WordPress にルート情報を登録する。
 * register_rest_route に渡される callback は WP_REST_Request を受け取り
 * WP_REST_Response を返す。ここで wp-resta の内部処理と WordPress の表現の変換が
 * 行われている。
 *
 * リクエスト処理の拡張は RouteInvocationEvent を購読することで行う。
 */
class RegisterRestRoutes
{
    private Container $container;

    /** @var RouteInterface[] */
    public readonly array $routes;

    public function __construct(
        Config $config,
    ) {
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
                $routes[] = $routeObject;
            }
        }
        $this->routes = $routes;
        $this->container = $container;
    }

    public function register() : void
    {
        $handler = $this->container->get(RequestHandler::class);
        WpRestaServer::setRequestHandler($handler);
        foreach ($this->routes as $route) {
            if ($route instanceof AbstractProxyRoute) {
                WpRestaServer::addProxyRoute($route);
                continue;
            }
            register_rest_route(
                $route->getNamespace(),
                $route->getRouteRegex(),
                [
                    'methods' => $route->getMethods(),
                    'callback' => function (WP_REST_Request $request) use ($route, $handler): WP_REST_Response {
                        return $handler->handle($request, $route);
                    },
                    'permission_callback' => [$route, 'permissionCallback'],
                    'args' => $route->getArgs(),
                    'schema' => [$route, 'getSchema'],
                ]
            );
        }
    }
}
