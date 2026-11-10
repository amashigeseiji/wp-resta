<?php
namespace Wp\Resta\Auth;

/**
 * リクエストの主体。認証状態と capability を答える。
 *
 * 認証そのもの（cookie + nonce、Application Passwords など）は WordPress が行い、
 * wp-resta はその結果だけをこのインターフェースを通して見る。
 * WordPress 上では WpPrincipal が現在のユーザーに対応づけ、テストでは TestPrincipal を使う。
 * WordPress のユーザーに対応づかない認証を使う場合は、設定の dependencies で実装を差し替える。
 */
interface Principal
{
    public function isAuthenticated(): bool;

    /**
     * @param mixed ...$args capability に付随する対象（投稿 ID など）
     */
    public function can(string $capability, mixed ...$args): bool;
}
