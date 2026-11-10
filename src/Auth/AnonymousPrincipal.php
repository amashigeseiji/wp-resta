<?php
namespace Wp\Resta\Auth;

/**
 * 未認証の主体。Principal が bind されていない環境（WordPress 無しの invoke()）で使われる。
 */
final class AnonymousPrincipal implements Principal
{
    public function isAuthenticated(): bool
    {
        return false;
    }

    public function can(string $capability, mixed ...$args): bool
    {
        return false;
    }
}
