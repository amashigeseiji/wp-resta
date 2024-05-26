<?php
namespace Wp\Resta\REST\Example\Routes;

use Psr\Http\Message\RequestInterface;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Route;
use Wp\Resta\REST\Example\Hoge;

#[RouteMeta(tags: ['サンプルAPI'])]
class Feed extends AbstractRoute
{
    protected const ROUTE = 'feed/[id]';
    protected const URL_PARAMS = [
        'id' => '\d+',
    ];

    public function callback(int $id, Hoge $hoge, RequestInterface $request, Route $route): array
    {
        return [$id, $id * 2, $hoge->getHoge(), $hoge->fuga, $route->routes];
    }
}
