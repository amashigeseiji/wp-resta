<?php
namespace Test\Resta\Fixtures\StatefulRoutes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\WpRestaRequest;

/**
 * 引数を WpRestaRequest で宣言し、元の WP_REST_Request を取り出すルート
 */
class TestWpRequestRoute extends AbstractRoute
{
    protected const ROUTE = 'wp-request';

    public static ?\WP_REST_Request $lastWpRequest = null;

    /**
     * @return array{received: bool}
     */
    public function callback(WpRestaRequest $request): array
    {
        self::$lastWpRequest = $request->getWpRequest();
        return ['received' => true];
    }
}
