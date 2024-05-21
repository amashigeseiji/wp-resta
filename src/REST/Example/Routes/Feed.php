<?php
namespace Wp\Restafari\REST\Example\Routes;

use Wp\Restafari\REST\AbstractRoute;
use Wp\Restafari\REST\Attributes\RouteMeta;
use Wp\Restafari\REST\Route;
use Wp\Restafari\REST\Example\Hoge;
use WP_REST_Request;

#[RouteMeta(tags: ['サンプルAPI'])]
class Feed extends AbstractRoute
{
    protected const ROUTE = 'feed/[id]';
    protected const URL_PARAMS = [
        'id' => '\d+',
    ];

    public function callback(int $id, Hoge $hoge, WP_REST_Request $request, Route $route): array
    {
        return [$id, $id * 2, $hoge->getHoge(), $hoge->fuga, $route->routes];
    }
}
