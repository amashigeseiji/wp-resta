<?php
namespace Wp\Resta\REST\Hooks;

use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\Attributes\Authenticated;
use Wp\Resta\REST\Attributes\Permission;
use Wp\Resta\REST\BeforeCallbackEvent;
use Wp\Resta\REST\Exception\ForbiddenException;
use Wp\Resta\REST\Exception\UnauthorizedException;

/**
 * 操作のメソッドに付いた #[Authenticated] / #[Permission] を解釈する
 *
 * どちらかが付いた操作は、未認証なら 401。#[Permission] は、認証済みでもその capability が無ければ 403。
 * アトリビュートの無い操作には何もしない。
 *
 * Resta::init() で Dispatcher に登録される。
 */
class AuthorizationHook
{
    public function __construct(private readonly Principal $principal)
    {
    }

    public function handle(BeforeCallbackEvent $event): void
    {
        $callback = $event->operation->getCallback();
        $permissions = $callback->getAttributes(Permission::class);
        if ($permissions === [] && $callback->getAttributes(Authenticated::class) === []) {
            return;
        }

        if (!$this->principal->isAuthenticated()) {
            throw new UnauthorizedException();
        }
        foreach ($permissions as $permission) {
            if (!$this->principal->can($permission->capability)) {
                throw new ForbiddenException();
            }
        }
    }
}
