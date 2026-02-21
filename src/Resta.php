<?php
namespace Wp\Resta;

use Wp\Resta\DI\Container;
use Wp\Resta\Config;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\Hooks\HookProviderInterface;
use Wp\Resta\Kernel\Kernel;
use Wp\Resta\Kernel\KernelState;
use Wp\Resta\Kernel\WpKernelAdapter;
use Wp\Resta\OpenApi\Doc;
use Wp\Resta\OpenApi\ResponseSchema;
use Wp\Resta\REST\RegisterRestRoutes;
use Wp\Resta\REST\Hooks\EnvelopeHook;
use Wp\Resta\StateMachine\StateMachine;
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
     *    hooks?: array<class-string<HookProviderInterface>>,
     *    'use-swagger'?: bool
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
        $dispatcher = new Dispatcher();
        $sm = new StateMachine($registry, $dispatcher);

        // DI コンテナに登録
        $container->bind(Kernel::class, $kernel);
        $container->bind(StateMachine::class, $sm);
        $container->bind(DispatcherInterface::class, $dispatcher);

        // registerRoutes 遷移が完了したときにルートを実際に登録する
        $dispatcher->addListener(
            TransitionEvent::afterEventName(KernelState::Bootstrapped, 'registerRoutes'),
            function () use ($container): void {
                $container->get(RegisterRestRoutes::class)->register();
            }
        );

        // Swagger のセットアップ（任意）
        // SwaggerHooks の代替: wp.init イベントで Doc と ResponseSchema を初期化する
        if ($config->useSwagger) {
            $dispatcher->addListener('wp.init', function () use ($container): void {
                $container->get(Doc::class)->init();
                $container->get(ResponseSchema::class)->init();
            });
        }

        // DI 設定完了 → Bootstrapped に遷移
        $sm->apply($kernel, 'boot');

        // WP ライフサイクルをフレームワークに橋渡し
        (new WpKernelAdapter($kernel, $sm, $dispatcher))->install();

        // ユーザー定義の HookProvider を登録
        foreach ($config->hooks as $providerClass) {
            $provider = $container->get($providerClass);

            if (!($provider instanceof HookProviderInterface)) {
                throw new \InvalidArgumentException(
                    sprintf('%s must implement HookProviderInterface', $providerClass)
                );
            }

            $provider->register();
        }
    }
}
