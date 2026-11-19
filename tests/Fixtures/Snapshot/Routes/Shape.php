<?php
namespace Test\Resta\Fixtures\Snapshot\Routes;

use Test\Resta\Fixtures\Snapshot\Schemas\Item;
use Wp\Resta\REST\AbstractRoute;

/** 入れ子の array shape の @return。プロパティ、required、要素の型、enum まで解決される（#102） */
class Shape extends AbstractRoute
{
    protected const ROUTE = 'shape';

    /**
     * @return array{page: int, items: Item[], order: 'asc'|'desc', tags?: array{name: string, slug: string}[]}
     */
    public function callback(): array
    {
        return ['page' => 1, 'items' => [], 'order' => 'asc'];
    }
}
