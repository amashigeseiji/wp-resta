<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;
use Test\Resta\Fixtures\Schemas\TestUser;

/**
 * SCHEMA 定数を持たず、callback の戻り値から自動推論されるRoute
 */
class TestInferredSchemaRoute extends AbstractRoute
{
    protected const ROUTE = 'test-inferred';

    public function callback(): TestUser
    {
        $user = new TestUser();
        $user->id = 1;
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        return $user;
    }
}
