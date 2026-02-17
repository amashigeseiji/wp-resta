<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * Test Route for associative primitive array type inference
 */
class TestAssociativePrimitiveRoute extends AbstractRoute
{
    protected const ROUTE = 'test-associative-primitive';

    /**
     * @return array<int, string>
     */
    public function callback(): array
    {
        return ['foo', 'bar', 'baz'];
    }
}
