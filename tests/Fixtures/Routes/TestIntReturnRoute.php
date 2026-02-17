<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * Test Route for int return type inference
 */
class TestIntReturnRoute extends AbstractRoute
{
    protected const ROUTE = 'test-int-return';

    public function callback(): int
    {
        return 42;
    }
}
