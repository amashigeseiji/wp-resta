<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;
use Test\Resta\Fixtures\Schemas\TestUser;

/**
 * PHPDoc の array<Type> 形式をテスト
 */
class TestPhpDocGenericRoute extends AbstractRoute
{
    protected const ROUTE = 'test-phpdoc-generic';

    /**
     * @return array<TestUser>
     */
    public function callback(): array
    {
        return [];
    }
}
