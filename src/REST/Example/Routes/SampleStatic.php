<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\RouteMeta;

#[RouteMeta(tags: ['サンプルAPI'])]
#[Envelope]
class SampleStatic extends AbstractRoute
{
    /**
     * @return array{name: string, body: string}
     */
    public function callback(): array
    {
        return [
            'name' => 'static_parameter',
            'body' => 'this is sttatic parameter',
        ];
    }
}
