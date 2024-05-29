<?php
namespace Wp\Resta;

use Wp\Resta\DI\Container;
use Wp\Resta\OpenApi\ResponseSchema;
use Wp\Resta\OpenApi\Doc;
use Wp\Resta\REST\Route;
use Wp\Resta\Config;

class Resta
{
    /**
     * @template T
     * @param array{
     *    autoloader?: string,
     *    routeDirectory: array<string[]>,
     *    schemaDirectory?: array<string[]>,
     *    dependencies?: array<class-string<T>, T|class-string<T>>,
     *    use-swagger?: bool
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

        add_action('rest_api_init', function () use ($container) {
            $routes = $container->get(Route::class);
            $routes->register();
        });

        // use-swagger を false にしたら SwaggerUI や /rest-api/schema の出力をオフにする
        $useSwagger = $config->hasKey('use-swagger') ? $config->get('use-swagger') : true;
        if ($useSwagger) {
            add_action('init', function() use ($container) {
                $container->get(Doc::class)->init();
                $container->get(ResponseSchema::class)->init();
            });
        }
    }
}
