<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * Test Route for bool return type inference
 */
class TestBoolReturnRoute extends AbstractRoute
{
    protected const ROUTE = 'test-bool-return';

    public function callback(): bool
    {
        return true;
    }
}
