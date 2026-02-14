<?php
namespace Wp\Resta\REST;

use LogicException;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\WpRestaRequest;
use WP_REST_Request;
use WP_REST_Response;
use WPRestApi\PSR7\WP_REST_PSR7_Response;

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
            foreach ($routes as $route) {
                assert($route instanceof RouteInterface);
                register_rest_route(
                    $route->getNamespace(),
                    $route->getRouteRegex(),
                    [
                        [
                            'methods' => $route->getMethods(),
                            'callback' => function (WP_REST_Request $request) use($route) : WP_REST_Response {
                                $psr7request = WpRestaRequest::fromWpRequest($request);
                                $this->container->bind(WP_REST_Request::class, $request);
                                $this->container->bind(RestaRequestInterface::class, $psr7request);

                                // AbstractRoute を実行 (WordPress 非依存レイヤー)
                                // 戻り値: PSR-7 Response (body は JSON 文字列)
                                $response = $route->invoke($psr7request);

                                /**
                                 * PSR-7 Response → WordPress REST Response への変換
                                 *
                                 * AbstractRoute は WordPress 非依存のため、PSR-7 Response を返す。
                                 * PSR-7 では body は Stream (文字列) でなければならないため、
                                 * 配列データは JSON エンコードされている。
                                 *
                                 * 一方、WordPress REST API では WP_REST_Response が配列データを保持し、
                                 * フック（rest_request_after_callbacks など）で配列を直接操作できる必要がある。
                                 *
                                 * そのため、ここで JSON デコードして配列に戻し、
                                 * WP_REST_Response として WordPress に返す。
                                 *
                                 * この変換は Route.php の責務：WordPress システムとの境界
                                 */
                                $body = (string)$response->getBody();
                                $data = json_decode($body, true) ?? $body;

                                $wpResponse = new WP_REST_Response($data, $response->getStatusCode());

                                // PSR-7 のヘッダーを WP_REST_Response にコピー
                                foreach ($response->getHeaders() as $name => $values) {
                                    $wpResponse->header($name, implode(', ', $values));
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
