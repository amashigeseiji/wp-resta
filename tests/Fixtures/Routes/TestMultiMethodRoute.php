<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * getMethods() がカンマ区切りで複数メソッドを返すルート
 */
class TestMultiMethodRoute extends AbstractRoute
{
    protected const ROUTE = 'multi-method';

    public function getMethods(): string
    {
        return 'GET,POST';
    }

    public function callback(): string
    {
        return 'multi';
    }
}
