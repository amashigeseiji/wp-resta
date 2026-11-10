<?php
namespace Wp\Resta\REST;

use LogicException;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
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
            $files = glob("{$dir}/*.php") ?: [];
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
        foreach ($this->routes as $route) {
            register_rest_route(
                $route->getNamespace(),
                $route->getRouteRegex(),
                [
                    'methods' => $route->getMethods(),
                    // ルートはプロセス内でシングルトンなので、callback 内で $this->status などに
                    // 代入した値が次のリクエスト（/batch/v1、rest_preload_api_request、
                    // rest_do_request() の内部呼び出し）に持ち越されないよう、リクエストごとに clone する。
                    // 浅いコピーなのでコンストラクタ注入されたサービスは共有され、クラス宣言の初期値や
                    // setNamespace() の名前空間はそのまま引き継がれる。
                    'callback' => function (WP_REST_Request $request) use ($route, $handler): WP_REST_Response {
                        return $handler->handle($request, clone $route);
                    },
                    // 認可は invoke() の中で authorize() が行う（spec「HTTP 実行のパイプライン」）。
                    // WP コアに渡す permission_callback は常に許可で、WP 5.5 以降の必須引数を満たすためだけにある
                    'permission_callback' => '__return_true',
                    'args' => $route->getArgs(),
                    'schema' => [$route, 'getSchema'],
                ],
            );
        }
    }
}
