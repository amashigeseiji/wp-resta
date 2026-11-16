<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Test\Resta\Fixtures\Schemas\TestUser;

/**
 * PHPDoc の @return が辞書型と RestaResponseInterface と null の union でも辞書として推論できることをテスト
 */
class TestPhpDocUnionDictionaryRoute extends AbstractRoute
{
    protected const ROUTE = 'test-phpdoc-union-dictionary';

    /**
     * @return array<string, TestUser>|RestaResponseInterface|null
     */
    public function callback(): array|RestaResponseInterface|null
    {
        return null;
    }
}
