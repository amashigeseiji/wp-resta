<?php
namespace Test\Resta\Fixtures\OperationRoutes;

use Wp\Resta\REST\AbstractRoute;

/**
 * 同じ URL で GET と POST を分けるルート
 */
class TestNotesRoute extends AbstractRoute
{
    protected const ROUTE = 'notes/[id]';

    /** @return array{id: int, method: string} */
    public function get(int $id): array
    {
        return ['id' => $id, 'method' => 'GET'];
    }

    public function post(int $id, string $title): string
    {
        return "created {$id}: {$title}";
    }
}
