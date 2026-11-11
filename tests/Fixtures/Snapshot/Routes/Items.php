<?php
namespace Test\Resta\Fixtures\Snapshot\Routes;

use Test\Resta\Fixtures\Snapshot\Schemas\Items as ItemsSchema;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\RouteMeta;

/** #[Envelope] と ArrayType のスキーマ。callback() 1 つで GET,POST */
#[Envelope]
#[RouteMeta(description: 'Snapshot item list', tags: ['snapshot'])]
class Items extends AbstractRoute
{
    protected const ROUTE = 'items';

    public function getMethods(): string
    {
        return 'GET,POST';
    }

    public function callback(string $q = ''): ItemsSchema
    {
        return new ItemsSchema();
    }
}
