<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * TestSamePathGetRoute と同じパスを POST で定義するルート
 */
class TestSamePathPostRoute extends AbstractRoute
{
    protected const ROUTE = 'same-path';

    public function getMethods(): string
    {
        return 'POST';
    }

    public function callback(): string
    {
        return 'post';
    }
}
