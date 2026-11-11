<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Test\Resta\Fixtures\Schemas\TestUser;

/**
 * PHPDoc の @return が RestaResponseInterface との union でも、もう一方の配列型から推論できることをテスト
 */
class TestPhpDocUnionListRoute extends AbstractRoute
{
    protected const ROUTE = 'test-phpdoc-union-list';

    /**
     * @return TestUser[]|RestaResponseInterface
     */
    public function callback(): array|RestaResponseInterface
    {
        return [];
    }
}
