<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\EnvelopeRoute;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Hoge;
use Wp\Resta\REST\RegisterRestRoutes;

#[RouteMeta(tags: ['サンプルAPI'])]
class Feed extends EnvelopeRoute
{
    protected const ROUTE = 'feed/[id]';
    protected const URL_PARAMS = [
        'id' => '\d+',
    ];

    /**
     * @return array<mixed>
     */
    public function callback(int $id, Hoge $hoge, RegisterRestRoutes $route): array
    {
        return [$id, $id * 2, $hoge->getHoge(), $hoge->fuga, $route->routes];
    }
}
