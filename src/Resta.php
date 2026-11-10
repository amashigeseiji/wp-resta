<?php
namespace Wp\Resta;

use Wp\Resta\Auth\Principal;
use Wp\Resta\Auth\WpPrincipal;
use Wp\Resta\DI\Container;
use Wp\Resta\Config;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\Kernel\WpKernelAdapter;
use Wp\Resta\REST\RegisterRestRoutes;
use Wp\Resta\REST\Hooks\EnvelopeHook;

class Resta
{
    /**
     * @template T
     * @param array{
     *    autoloader?: string,
     *    routeDirectory?: array<string[]>,
     *    schemaDirectory?: array<string[]>,
     *    dependencies?: array<class-string<T>, T|class-string<T>>,
     *    hooks?: array<class-string<\Wp\Resta\Hooks\HookProviderInterface>>,
     *    listeners?: array<class-string>,
     *    adapters?: array<class-string>,
     * } $restaConfig
     */
    public function init(array $restaConfig) : void
    {
        $config = new Config($restaConfig);
        $container = Container::getInstance();
        $container->bind(Config::class, $config);

        // 認可の主体。WpPrincipal は状態を持たず呼ばれた時点の現在ユーザーに委譲するので、ここで 1 回 bind すれば足りる。
        // 利用者の dependencies が後から bind するので、WordPress のユーザーに対応づかない認証を使うときは差し替えられる
        $container->bind(Principal::class, WpPrincipal::class);

        foreach ($config->dependencies as $interface => $dependency) {
            if (is_string($interface)) {
                assert(class_exists($interface) || interface_exists($interface));
                $container->bind($interface, $dependency);
            } else {
                $container->bind($dependency);
            }
        }

        $dispatcher = new Dispatcher();
        $container->bind(DispatcherInterface::class, $dispatcher);

        // WP の rest_api_init のたびにルートを登録する。register_rest_route() は何度呼んでも壊れず、
        // $wp_rest_server が作り直される場面（WP のテストフレームワークなど）では登録し直す必要がある（#60）。
        // RegisterRestRoutes の解決をここまで遅らせるのは、REST 以外のリクエストでルートディレクトリを
        // スキャンしないため。priority を最大にしておくことで、利用者がどの priority で wp.rest_api_init を
        // 購読しても、呼ばれる時点でルートは登録済みになる（spec「イベント」）
        $dispatcher->addListener(
            WpKernelAdapter::REST_API_INIT,
            function () use ($container): void {
                $container->get(RegisterRestRoutes::class)->register();
            },
            ['priority' => PHP_INT_MAX]
        );

        // フレームワーク内部リスナーを登録
        $dispatcher->addSubscriber(new EnvelopeHook());

        // ユーザー定義リスナーを登録（DI コンテナ経由でインスタンス化）
        foreach ($config->listeners as $listenerClass) {
            $dispatcher->addSubscriber($container->get($listenerClass));
        }

        // WP ライフサイクルをフレームワークに橋渡しする。省略時は WpKernelAdapter
        foreach ($config->adapters as $adapter) {
            $adapterInstance = $container->get($adapter);
            if (!($adapterInstance instanceof WpKernelAdapter)) {
                throw new \InvalidArgumentException(
                    sprintf('%s must extend WpKernelAdapter', $adapter)
                );
            }
            $adapterInstance->install();
        }
    }
}
