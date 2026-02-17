<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * Test Route for string return type inference
 */
class TestStringReturnRoute extends AbstractRoute
{
    protected const ROUTE = 'test-string-return';

    public function callback(): string
    {
        return 'Hello, World!';
    }
}
