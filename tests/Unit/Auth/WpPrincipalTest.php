<?php
namespace Test\Resta\Unit\Auth;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\WpPrincipal;

class WpPrincipalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testIsAuthenticatedDelegatesToIsUserLoggedIn(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);

        $this->assertTrue((new WpPrincipal())->isAuthenticated());
    }

    public function testCanDelegatesToCurrentUserCanWithArgs(): void
    {
        // 投稿 ID などの対象をそのまま current_user_can() に渡す
        Functions\expect('current_user_can')->once()->with('edit_post', 42)->andReturn(true);

        $this->assertTrue((new WpPrincipal())->can('edit_post', 42));
    }

    public function testCanReturnsFalseWhenWordPressDenies(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $this->assertFalse((new WpPrincipal())->can('manage_options'));
    }
}
