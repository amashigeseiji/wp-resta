<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * Test Route for nullable string return type inference
 */
class TestNullableStringReturnRoute extends AbstractRoute
{
    protected const ROUTE = 'test-nullable-string-return';

    public function callback(): ?string
    {
        return null;
    }
}
