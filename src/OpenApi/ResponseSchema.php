<?php
namespace Wp\Resta\OpenApi;

use Wp\Resta\REST\RegisterRestRoutes;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\RouteInterface;
use Wp\Resta\REST\Schemas\Schemas;
use ReflectionClass;

class ResponseSchema
{
    private readonly Schemas $schemas;
    private readonly RegisterRestRoutes $routes;

    public function __construct(Schemas $schemas, RegisterRestRoutes $routes)
    {
        $this->routes = $routes;
        $this->schemas = $schemas;
    }

    /**
     * スキーマをエンベロープ構造でラップ
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function wrapSchemaInEnvelope(array $schema): array
    {
        return [
            '$schema' => $schema['$schema'] ?? 'http://json-schema.org/draft-04/schema#',
            'type' => 'object',
            'properties' => [
                'data' => $this->extractDataSchema($schema),
                'meta' => [
                    'type' => 'object',
                    'description' => 'Response metadata',
                    'additionalProperties' => true
                ]
            ]
        ];
    }

    /**
     * スキーマからデータ部分を抽出
     *
     * $schema や title などのメタ情報を除外して、実際のデータ構造のみを抽出します。
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function extractDataSchema(array $schema): array
    {
        // $schema や title などのメタ情報は除外
        $dataSchema = $schema;
        unset($dataSchema['$schema']);

        return $dataSchema;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getPaths() : array
    {
        $paths = [];

        foreach ($this->routes->routes as $namespace => $routesInNamespace) {
            foreach ($routesInNamespace as $route) {
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

                // スキーマを取得
                $schema = $route->getSchema() ?? [];

                // #[Envelope] 属性があればエンベロープ構造でラップ
                $hasEnvelope = count($reflection->getAttributes(Envelope::class)) > 0;
                if ($hasEnvelope && !empty($schema)) {
                    $schema = $this->wrapSchemaInEnvelope($schema);
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
                                        'schema' => $schema
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

    public function init() : void
    {
        add_rewrite_tag('%rest_api_doc%', '([^&]+)');
        add_rewrite_rule('^rest-api/schema/?', 'index.php?rest_api_doc=schema', 'top');
        flush_rewrite_rules();
        add_action('wp', function () {
            if ( get_query_var('rest_api_doc') !== 'schema') {
                return;
            }
            wp_send_json($this->responseSchema());
        });
    }

    /**
     * swagger json生成
     * @return array<string, mixed>
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
