<?php
namespace Wp\Resta;

use Wp\Resta\DI\Container;
use Wp\Resta\Config;
use Wp\Resta\Hooks\HookProviderInterface;
use Wp\Resta\Hooks\InternalHooks;

class Resta
{
    /**
     * @template T
     * @param array{
     *    autoloader?: string,
     *    routeDirectory: array<string[]>,
     *    schemaDirectory?: array<string[]>,
     *    dependencies?: array<class-string<T>, T|class-string<T>>,
     *    hooks?: array<class-string<HookProviderInterface>>
     * } $restaConfig
     */
    public function init(array $restaConfig) : void
    {
        $config = new Config($restaConfig);
        $container = Container::getInstance();
        $container->bind(Config::class, $config);
        $dependencies = $config->get('dependencies') ?: [];
        foreach ($dependencies as $interface => $dependency) {
            if (is_string($interface)) {
                assert(class_exists($interface));
                $container->bind($interface, $dependency);
            } else {
                $container->bind($dependency);
            }
        }

        // 内部フック（必須、設定ファイルから変更不可）
        $internalHooks = [
            InternalHooks::class,
        ];

        // ユーザーフック（設定ファイルから）
        /** @var array<class-string<\Wp\Resta\Hooks\HookProviderInterface>> */
        $userHooks = $config->get('hooks') ?: [];

        // マージして登録
        $allHooks = array_merge($internalHooks, $userHooks);

        foreach ($allHooks as $providerClass) {
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
