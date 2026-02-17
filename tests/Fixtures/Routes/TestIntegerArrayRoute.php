<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * Test Route for integer array type inference
 */
class TestIntegerArrayRoute extends AbstractRoute
{
    protected const ROUTE = 'test-integer-array';

    /**
     * @return int[]
     */
    public function callback(): array
    {
        return [1, 2, 3];
    }
}
