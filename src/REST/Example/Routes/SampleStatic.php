<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\EnvelopeRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

#[RouteMeta(tags: ['サンプルAPI'])]
class SampleStatic extends EnvelopeRoute
{
    protected $body = [
        'name' => 'static_parameter',
        'body' => 'this is sttatic parameter',
    ];
}
