<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

/**
 * 同じ URL で GET と POST を分けるサンプル。HTTP メソッド名のメソッドがそれぞれ 1 つの操作になる
 */
#[RouteMeta(
    description: "同じ URL で GET と POST を分けるサンプルです",
    tags: ["サンプルAPI"]
)]
class Notes extends AbstractRoute
{
    protected const ROUTE = 'notes/[id]';

    /**
     * @return array{id: int, method: string}
     */
    public function get(int $id): array
    {
        return ['id' => $id, 'method' => 'GET'];
    }

    /**
     * @return array{id: int, method: string, title: string}
     */
    public function post(int $id, string $title): array
    {
        return ['id' => $id, 'method' => 'POST', 'title' => $title];
    }
}
