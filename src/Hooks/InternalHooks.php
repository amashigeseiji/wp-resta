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
    #[AddAction('rest_api_init')]
    public function registerRoutes(): void
    {
        // Route の解決を rest_api_init 実行時まで遅延
        // 非 REST リクエストでは Route が構築されないため、不要なディレクトリスキャンを回避
        $route = Container::getInstance()->get(Route::class);
        $route->register();
    }
}
