<?php
namespace Wp\Resta\OpenApi;

use Wp\Resta\REST\RegisterRestRoutes;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Schemas\Schemas;
use Wp\Resta\REST\Schemas\SchemaInference;
use ReflectionClass;

class ResponseSchema
{
    public function __construct(
        private readonly Schemas $schemas,
        private readonly RegisterRestRoutes $routes,
        private readonly SchemaInference $inference
    ) {
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
            '$schema' => 'http://json-schema.org/draft-04/schema#',
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
     * トップレベルの $schema や title などのメタ情報を除外して、実際のデータ構造のみを抽出します。
     * プロパティ定義配下の description や default などはそのまま保持されます。
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function extractDataSchema(array $schema): array
    {
        // トップレベルの JSON Schema メタ情報は除外する
        $dataSchema = $schema;

        $metaKeys = [
            '$schema',
            '$id',
            '$comment',
            'title',
            'description',
            'default',
            'examples',
        ];

        foreach ($metaKeys as $metaKey) {
            if (array_key_exists($metaKey, $dataSchema)) {
                unset($dataSchema[$metaKey]);
            }
        }

        return $dataSchema;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getPaths() : array
    {
        $paths = [];

        foreach ($this->routes->routes as $route) {
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

            // スキーマを取得（明示的定義がない場合は自動推論）
            $schema = $route->getSchema();
            if ($schema === null) {
                $schema = $this->inference->inferSchema($route);
            }
            $schema = $schema ?? [];

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

        return $paths;
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
