<?php
namespace Wp\Resta\Hooks;

use Wp\Resta\DI\Container;
use Wp\Resta\REST\Route;
use Wp\Resta\Hooks\Attributes\AddAction;

/**
 * システム内部で必須のフック
 * ユーザーの設定ファイルから削除できない
 */
class InternalHooks extends HookProvider
{
    public function __construct(
        private readonly Container $container
    ) {}

    #[AddAction('rest_api_init')]
    public function registerRoutes(): void
    {
        $routes = $this->container->get(Route::class);
        $routes->register();
    }
}
