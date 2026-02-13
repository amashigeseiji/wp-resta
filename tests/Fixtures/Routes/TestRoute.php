<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

class TestRoute extends AbstractRoute
{
    protected const ROUTE = 'test';

    public function callback(): string
    {
        return 'test response';
    }
}
