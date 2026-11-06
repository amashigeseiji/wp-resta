<?php
namespace Test\Resta\Fixtures\StatefulRoutes;

use Wp\Resta\REST\AbstractRoute;

/**
 * callback 内で $this->status に代入するルート（Example/Routes/Post.php と同じパターン）
 */
class TestStatefulRoute extends AbstractRoute
{
    protected const ROUTE = 'stateful/[id]';
    protected const URL_PARAMS = [
        'id' => 'integer',
    ];

    public function callback(int $id): ?array
    {
        if ($id === 1) {
            $this->status = 404;
            return null;
        }
        return ['id' => $id];
    }
}
