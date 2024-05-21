<?php
namespace Wp\Restafari;

use Wp\Restafari\DI\Container;
use Wp\Restafari\REST\Route;
use Wp\Restafari\REST\Attributes\RouteMeta;
use Wp\Restafari\REST\RouteInterface;
use Wp\Restafari\REST\Schemas\Schemas;
use ReflectionClass;

class OpenApiResponseSchema
{
    public const VERSION = '1.0';

    public readonly array $schema;

    public function __construct()
    {
        $schemas = Container::getInstance()->get(Schemas::class);
        $this->schema = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'WordPress REST API',
                'description' => '',
                'version' => self::VERSION,
            ],
            'tags' => [],
            'servers' => [
                [
                    'url' => rest_url(),
                ],
            ],
            'paths' => $this->getPaths(),
            'components' => [
                'securitySchemes' => [],
                'schemas' => $schemas->schemas,
            ],
        ];
    }

    private function getPaths() : array
    {
        $paths = [];

        $container = Container::getInstance();
        $routes = $container->get(Route::class);
        assert($routes instanceof Route);
        foreach ($routes->routes as $namespace => $routesInNamespace) {
            foreach ($routesInNamespace as $r) {
                $route = $container->get($r);
                assert($route instanceof RouteInterface);
                $path = $route->getReadableRoute();

                // パラメータを集めておく
                $parameters = [];
                foreach ($route->getArgs() as $name => $def) {
                    $parameters[] = [
                        'name' => $name,
                        'required' => $def['required'],
                        'description' => $def['description'],
                        'in' => str_contains($path, "{{$name}}") ? 'path' : 'query', // 他にもヘッダー埋め込みなどの種類があるが、あまり使わなそうなので決め打ちにしておきます
                        'schema' => [
                            'type' => $def['type'],
                        ]
                    ];
                }

                // attribute から meta 情報を取得
                $reflection = new ReflectionClass($route::class);
                $meta = new RouteMeta(); // デフォルト
                foreach ($reflection->getAttributes(RouteMeta::class) as $attr) {
                    $meta = $attr->newInstance();
                }
                $paths[$path] = [
                    strtolower($route->getMethods()) => [
                        'description' => $meta->description,
                        'tags' => $meta->tags,
                        'parameters' => $parameters,
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => $route->getSchema() ?? []
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
            }
        }

        return $paths;
    }
}
