<?php
namespace Wp\Resta\Auth;

/**
 * WordPress の現在のユーザーを Principal として見せる。WP 境界。
 *
 * 状態を持たず、呼ばれた時点の wp_get_current_user() に委譲するので、
 * リクエストごとではなく Resta::init() で 1 回 bind すれば足りる。
 */
final class WpPrincipal implements Principal
{
    public function isAuthenticated(): bool
    {
        return is_user_logged_in();
    }

    public function can(string $capability, mixed ...$args): bool
    {
        return current_user_can($capability, ...$args);
    }
}
