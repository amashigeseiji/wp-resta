<?php
namespace Wp\Resta\Auth;

/**
 * テスト用の Principal。認証状態と持っている capability をコンストラクタで固定する。
 *
 * <code>
 * Container::getInstance()->bind(Principal::class, new TestPrincipal(authenticated: true, capabilities: ['edit_posts']));
 * </code>
 */
final class TestPrincipal implements Principal
{
    /**
     * @param string[] $capabilities can() が true を返す capability
     */
    public function __construct(
        private readonly bool $authenticated = false,
        private readonly array $capabilities = [],
    ) {}

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function can(string $capability, mixed ...$args): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
