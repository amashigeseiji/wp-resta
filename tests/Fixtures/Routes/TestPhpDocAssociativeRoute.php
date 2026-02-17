<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;
use Test\Resta\Fixtures\Schemas\TestUser;

/**
 * PHPDoc の array<string, Type> 形式をテスト
 */
class TestPhpDocAssociativeRoute extends AbstractRoute
{
    protected const ROUTE = 'test-phpdoc-associative';

    /**
     * @return array<string, TestUser>
     */
    public function callback(): array
    {
        return [];
    }
}
