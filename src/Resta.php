<?php
namespace Wp\Resta;

use Wp\Resta\DI\Container;
use Wp\Resta\Config;
use Wp\Resta\Hooks\HookProviderInterface;
use Wp\Resta\Hooks\InternalHooks;
use Wp\Resta\Hooks\SwaggerHooks;

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

        // InternalHooks の重複を防ぐ
        $userHooks = array_filter(
            $config->hooks,
            static fn(string $hook): bool => $hook !== InternalHooks::class
        );

        /** @var array<class-string<\Wp\Resta\Hooks\HookProviderInterface>> */
        $allHooks = [InternalHooks::class, ...$userHooks];

        // use-swagger の後方互換性
        if ($config->useSwagger && !in_array(SwaggerHooks::class, $allHooks, true)) {
            $allHooks[] = SwaggerHooks::class;
        }

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
