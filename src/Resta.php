<?php
namespace Wp\Resta;

use Wp\Resta\DI\Container;
use Wp\Resta\Config;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\Kernel\Kernel;
use Wp\Resta\Kernel\KernelState;
use Wp\Resta\Kernel\WpKernelAdapter;
use Wp\Resta\Lifecycle\RequestState;
use Wp\Resta\REST\RegisterRestRoutes;
use Wp\Resta\REST\Hooks\EnvelopeHook;
use Wp\Resta\StateMachine\StateMachine;
use Wp\Resta\StateMachine\TransitionApplier;
use Wp\Resta\StateMachine\TransitionEvent;
use Wp\Resta\StateMachine\TransitionRegistry;

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
     *    adapters? : array<class-string>,
     * } $restaConfig
     */
    public function init(array $restaConfig) : void
    {
        $config = new Config($restaConfig);
        $container = Container::getInstance();
        $container->bind(Config::class, $config);

        foreach ($config->dependencies as $interface => $dependency) {
            if (is_string($interface)) {
                assert(class_exists($interface));
                $container->bind($interface, $dependency);
            } else {
                $container->bind($dependency);
            }
        }

        // SM インフラストラクチャを構築
        $kernel = new Kernel();
        $registry = new TransitionRegistry();
        $registry->registerFromEnum(KernelState::class);
        $registry->registerFromEnum(RequestState::class);
        $container->bind(TransitionRegistry::class, $registry);
        $dispatcher = new Dispatcher();
        $sm = new StateMachine($registry, $dispatcher);

        // DI コンテナに登録
        $container->bind(Kernel::class, $kernel);
        $container->bind(TransitionApplier::class, $sm);
        $container->bind(StateMachine::class, $sm);
        $container->bind(DispatcherInterface::class, $dispatcher);

        // registerRoutes 遷移が完了したときにルートを実際に登録する
        $dispatcher->addListener(
            TransitionEvent::afterEventName(KernelState::Bootstrapped, 'registerRoutes'),
            function () use ($container): void {
                $container->get(RegisterRestRoutes::class)->register();
            }
        );

        // フレームワーク内部リスナーを登録
        $dispatcher->addSubscriber(new EnvelopeHook());

        // ユーザー定義リスナーを登録（DI コンテナ経由でインスタンス化）
        foreach ($config->listeners as $listenerClass) {
            $dispatcher->addSubscriber($container->get($listenerClass));
        }

        // DI 設定完了 → Bootstrapped に遷移
        $sm->apply($kernel, 'boot');

        // WP ライフサイクルをフレームワークに橋渡し
        // WpKernelAdapter はオートワイヤリングで解決される
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
