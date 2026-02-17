<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * Test Route for primitive array type inference
 */
class TestPrimitiveArrayRoute extends AbstractRoute
{
    protected const ROUTE = 'test-primitive-array';

    /**
     * @return string[]
     */
    public function callback(): array
    {
        return ['foo', 'bar', 'baz'];
    }
}
