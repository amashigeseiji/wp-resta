<?php
namespace Wp\Resta\OpenApi;

use Wp\Resta\DI\Container;
use Wp\Resta\REST\Route;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\RouteInterface;
use Wp\Resta\REST\Schemas\Schemas;
use ReflectionClass;

class ResponseSchema
{
    private readonly Schemas $schemas;
    private readonly Route $routes;

    public function __construct(Schemas $schemas, Route $routes)
    {
        $this->routes = $routes;
        $this->schemas = $schemas;
    }

    private function getPaths() : array
    {
        $paths = [];

        $container = Container::getInstance();
        foreach ($this->routes->routes as $namespace => $routesInNamespace) {
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

    public function init()
    {
        add_rewrite_tag('%rest_api_doc%', '([^&]+)');
        add_rewrite_rule('^rest-api/schema/?', 'index.php?rest_api_doc=schema', 'top');
        flush_rewrite_rules();
        add_action('wp', function () {
            if (
                get_query_var('rest_api_doc') !== 'schema'
                || !current_user_can('edit_pages')
            ) {
                return;
            }
            wp_send_json($this->responseSchema());
        });
    }

    /**
     * swagger json生成
     */
    public function responseSchema(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'WordPress REST API',
                'description' => '',
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
                'schemas' => $this->schemas->schemas,
            ],
        ];
    }
}
