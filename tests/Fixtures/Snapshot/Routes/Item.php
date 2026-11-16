<?php
namespace Test\Resta\Fixtures\Snapshot\Routes;

use Test\Resta\Fixtures\Parameter\Sort;
use Test\Resta\Fixtures\Snapshot\Schemas\Item as ItemSchema;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Param;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Http\RestaResponseInterface;

/**
 * パスパラメータ、enum、default、min / max、配列の宣言を 1 つずつ持つルート。
 * GET と POST で操作を分ける
 */
#[RouteMeta(description: 'Snapshot item route', tags: ['snapshot'])]
class Item extends AbstractRoute
{
    protected const ROUTE = 'items/[id]';

    /**
     * @param string[] $tags
     */
    public function get(
        int $id,
        Sort $sort = Sort::Asc,
        #[Param(min: 1, max: 100, description: 'Page size')] int $limit = 20,
        array $tags = [],
        bool $verbose = false,
    ): ItemSchema {
        return new ItemSchema();
    }

    /** RestaResponseInterface との union でもスキーマは ItemSchema から推論される */
    public function post(int $id, string $name, ?string $note = null): ItemSchema|RestaResponseInterface
    {
        if ($id === 0) {
            return $this->response(null, 404);
        }
        return new ItemSchema();
    }
}
