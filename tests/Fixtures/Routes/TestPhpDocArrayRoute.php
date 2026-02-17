<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;
use Test\Resta\Fixtures\Schemas\TestUser;

/**
 * PHPDoc の @return アノテーションでスキーマを定義するRoute
 */
class TestPhpDocArrayRoute extends AbstractRoute
{
    protected const ROUTE = 'test-phpdoc-array';

    /**
     * @return TestUser[]
     */
    public function callback(): array
    {
        return [];
    }
}
