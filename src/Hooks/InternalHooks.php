<?php
namespace Wp\Resta\Hooks;

use Wp\Resta\DI\Container;
use Wp\Resta\Hooks\Attributes\AddAction;
use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\Enum\RestApiHook;
use Wp\Resta\REST\RegisterRestRoutes;

/**
 * システム内部で必須のフック
 * ユーザーの設定ファイルから削除できない
 */
class InternalHooks extends HookProvider
{
    #[AddAction(RestApiHook::API_INIT)]
    public function registerRoutes(): void
    {
        // Route の解決を rest_api_init 実行時まで遅延
        // 非 REST リクエストでは Route が構築されないため、不要なディレクトリスキャンを回避
        $route = Container::getInstance()->get(RegisterRestRoutes::class);
        $route->register();
    }

    /**
     * URL パラメータをクエリパラメータより優先する
     *
     * /path/to/123?id=456 のようなリクエストで、埋め込みパラメータ(123)を
     * クエリパラメータ(id=456)より優先させる
     *
     * 理由は、正規のURL /path/to/123 にクエリを付け加えることを防ぐことはできないのに、正規URLにたいして任意のidを入れることができてしまうため。
     *
     * @param array<int, string> $order
     * @return array<int, string>
     */
    #[AddFilter(RestApiHook::REQUEST_PARAMETER_ORDER, priority: 10, acceptedArgs: 1)]
    public function prioritizeUrlParameters(array $order): array
    {
        if ($order[0] === 'GET' && $order[1] === 'URL') {
            $order[0] = 'URL';
            $order[1] = 'GET';
        }
        return $order;
    }
}
