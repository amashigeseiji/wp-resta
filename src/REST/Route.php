<?php
namespace Wp\Restafari\REST;

use LogicException;
use register_rest_route;
use Wp\Restafari\DI\Container;

class Route
{
    private Container $container;
    public readonly Array $routes;

    public function __construct()
    {
        $container = Container::getInstance();

        $routeSettings = $container->get('__routeDirectory');
        $routes = [];
        foreach ($routeSettings as $routeDir) {
            $dir = $routeDir[0];
            $namespace = $routeDir[1];
            $apiNamespace = $routeDir[2] ?? 'default';
            $files = glob(ABSPATH . "/{$dir}/*.php");
            foreach ($files as $file) {
                $basename = basename($file, '.php');
                $class = "{$namespace}{$basename}";
                if (!is_subclass_of($class, RouteInterface::class)) {
                    throw new LogicException("{$class} が RouteInterface を実装していません。");
                }
                $container->bind($class);
                if (!isset($routes[$apiNamespace])) {
                    $routes[$apiNamespace] = [];
                }
                $routes[$apiNamespace][] = $class;
            }
        }
        $this->routes = $routes;
        $this->container = $container;
    }

    public function register() : void
    {
        /**
         * ルート定義が /path/to/[id] のとき、id は埋め込みパラメータだが、クエリとしても受けとることができる。
         * /path/to/123?id=456 というリクエストがきたとき、デフォルトではクエリパラメータが優先されるが埋め込みパラメータを優先する。
         * 理由は、正規のURL /path/to/123 にクエリを付け加えることを防ぐことはできないのに、正規URLにたいして任意のidを入れることができてしまうため。
         */
        add_filter('rest_request_parameter_order', function($order) {
            if ($order[0] === 'GET' && $order[1] === 'URL') {
                $order[0] = 'URL';
                $order[1] = 'GET';
            }
            return $order;
        });
        foreach ($this->routes as $apiNamespace => $routes) {
            foreach ($routes as $routeName) {
                $route = $this->container->get($routeName);
                assert($route instanceof RouteInterface);
                if ($route instanceof AbstractRoute) {
                    $route->namespace = $apiNamespace;
                }
                register_rest_route(
                    $route->getNamespace(),
                    $route->getRouteRegex(),
                    [
                        [
                            'methods' => $route->getMethods(),
                            'callback' => [$route, 'invoke'],
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