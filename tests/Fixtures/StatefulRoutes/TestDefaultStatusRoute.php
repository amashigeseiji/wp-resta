<?php
namespace Test\Resta\Fixtures\StatefulRoutes;

use Wp\Resta\REST\AbstractRoute;

/**
 * クラス宣言で $status の初期値を変えているルート
 */
class TestDefaultStatusRoute extends AbstractRoute
{
    protected const ROUTE = 'default-status';
    protected int $status = 404;

    public function callback(): array
    {
        return ['found' => false];
    }
}
