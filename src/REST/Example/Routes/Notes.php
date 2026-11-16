<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Http\ResponseContext;

/**
 * 同じ URL で GET と POST を分けるサンプル。HTTP メソッド名のメソッドがそれぞれ 1 つの操作になる。
 * POST は、ステータスとヘッダを ResponseContext に書く例でもある
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
     * 戻り値は body。ステータスとヘッダは、引数で受け取った ResponseContext に書く（返す必要は無い）
     *
     * @return array{id: int, method: string, title: string}
     */
    public function post(int $id, string $title, ResponseContext $response): array
    {
        $response->status(201);
        $response->header('Location', "/example/notes/{$id}");
        return ['id' => $id, 'method' => 'POST', 'title' => $title];
    }
}
