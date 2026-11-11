<?php
namespace Test\Resta\Fixtures\Snapshot\Routes;

use Test\Resta\Fixtures\Snapshot\Schemas\Item;
use Wp\Resta\REST\AbstractRoute;

/** array shape の @return はプロパティまで解決しない（#102）。値が任意の object として出る（#101） */
class Shape extends AbstractRoute
{
    protected const ROUTE = 'shape';

    /**
     * @return array{page: int, items: Item[]}
     */
    public function callback(): array
    {
        return ['page' => 1, 'items' => []];
    }
}
