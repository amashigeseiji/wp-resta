<?php
namespace Test\Resta\Unit\Auth;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\AnonymousPrincipal;
use Wp\Resta\Auth\TestPrincipal;

class StaticPrincipalsTest extends TestCase
{
    public function testAnonymousPrincipalIsUnauthenticatedAndHasNoCapability(): void
    {
        $principal = new AnonymousPrincipal();

        $this->assertFalse($principal->isAuthenticated());
        $this->assertFalse($principal->can('read'));
    }

    public function testTestPrincipalDefaultsToAnonymous(): void
    {
        $principal = new TestPrincipal();

        $this->assertFalse($principal->isAuthenticated());
        $this->assertFalse($principal->can('read'));
    }

    public function testTestPrincipalAnswersOnlyGivenCapabilities(): void
    {
        $principal = new TestPrincipal(authenticated: true, capabilities: ['edit_posts']);

        $this->assertTrue($principal->isAuthenticated());
        $this->assertTrue($principal->can('edit_posts', 1));
        $this->assertFalse($principal->can('manage_options'));
    }
}
