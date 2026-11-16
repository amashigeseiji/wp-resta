<?php
namespace Test\Resta\Fixtures\StatefulRoutes;

use Wp\Resta\REST\AbstractRoute;

/**
 * callback の中で、自分のプロパティに書き込むルート
 */
class TestStatefulRoute extends AbstractRoute
{
    protected const ROUTE = 'stateful/[id]';
    protected const URL_PARAMS = [
        'id' => 'integer',
    ];

    public int $calls = 0;

    /**
     * @return array{id: int, calls: int}
     */
    public function callback(int $id): array
    {
        $this->calls++;
        return ['id' => $id, 'calls' => $this->calls];
    }
}
