<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * TestSamePathPostRoute と同じパスを GET で定義するルート
 */
class TestSamePathGetRoute extends AbstractRoute
{
    protected const ROUTE = 'same-path';

    public function callback(): string
    {
        return 'get';
    }
}
