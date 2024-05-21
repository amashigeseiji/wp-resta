<?php
namespace Wp\Restafari\REST\Example\Routes;

use Wp\Restafari\REST\AbstractRoute;
use Wp\Restafari\REST\Attributes\RouteMeta;

#[RouteMeta(tags: ['サンプルAPI'])]
class SampleStatic extends AbstractRoute
{
    protected $body = [
        'name' => 'static_parameter',
        'body' => 'this is sttatic parameter',
    ];
}
