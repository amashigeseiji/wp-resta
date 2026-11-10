<?php
namespace Wp\Resta\Lifecycle;

use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\WpRestaRequest;
use Wp\Resta\REST\RouteInterface;
use Wp\Resta\REST\RouteInvocationEvent;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WordPress から呼ばれる callback の実体。WP_REST_Request を wp-resta のリクエストに変換し、
 * ルートを実行し、RouteInvocationEvent を通した結果を WP_REST_Response に戻す。
 * WordPress の型に触れるのはこのクラスと WpRestaRequest だけ。
 */
class RequestHandler
{
    public function __construct(
        private DispatcherInterface $dispatcher,
    ) {}

    public function handle(WP_REST_Request $wpRequest, RouteInterface $route): WP_REST_Response
    {
        $request = WpRestaRequest::fromWpRequest($wpRequest);

        // callback 引数で WP_REST_Request / RestaRequestInterface を受け取れるように、現在のリクエストを bind する
        $container = Container::getInstance();
        $container->bind(WP_REST_Request::class, $wpRequest);
        $container->bind(RestaRequestInterface::class, $request);

        $response = $route->invoke($request);

        $event = new RouteInvocationEvent($request, $route, $response);
        $this->dispatcher->dispatch($event);

        return $this->toWpResponse($event->response);
    }

    private function toWpResponse(RestaResponseInterface $response): WP_REST_Response
    {
        $wpResponse = new WP_REST_Response($response->getData(), $response->getStatusCode());
        foreach ($response->getHeaders() as $name => $value) {
            $wpResponse->header($name, $value);
        }
        return $wpResponse;
    }
}
