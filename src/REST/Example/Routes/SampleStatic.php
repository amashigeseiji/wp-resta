<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

#[RouteMeta(tags: ['サンプルAPI'])]
class SampleStatic extends AbstractRoute
{
    protected $body = [
        'name' => 'static_parameter',
        'body' => 'this is sttatic parameter',
    ];
}
