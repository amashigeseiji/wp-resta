<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * Test Route for generic primitive array type inference
 */
class TestGenericPrimitiveRoute extends AbstractRoute
{
    protected const ROUTE = 'test-generic-primitive';

    /**
     * @return array<string>
     */
    public function callback(): array
    {
        return ['foo', 'bar', 'baz'];
    }
}
