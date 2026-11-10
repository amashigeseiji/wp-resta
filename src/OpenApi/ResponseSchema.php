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

            // attribute から meta 情報を取得（ルート単位。操作をまたいで共通）
            $reflection = new ReflectionClass($route::class);
            $meta = new RouteMeta(); // デフォルト
            foreach ($reflection->getAttributes(RouteMeta::class) as $attr) {
                $meta = $attr->newInstance();
            }
            $hasEnvelope = count($reflection->getAttributes(Envelope::class)) > 0;
            $explicitSchema = $route->getSchema();

            // OpenAPI の Path Item Object は path の下にメソッドごとの Operation Object を持つ。
            // wp-resta の操作をそのまま写す。同一パスに複数ルート（GET と POST を別クラスで定義など）が
            // ある場合も上書きせずメソッド単位でマージする
            foreach ($route->getOperations() as $operation) {
                $parameters = [];
                foreach ($operation->getParameters() as $definition) {
                    $parameters[] = $definition->toOpenApiParameter();
                }

                // スキーマは明示的定義（SCHEMA / getSchema()）が優先。無ければ操作の callback から推論
                $schema = $explicitSchema ?? $this->inference->inferSchema($operation->getCallback()) ?? [];

                // #[Envelope] 属性があればエンベロープ構造でラップ
                if ($hasEnvelope && !empty($schema)) {
                    $schema = $this->wrapSchemaInEnvelope($schema);
                }

                $operationObject = [
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
                ];

                // 'GET,POST' のように複数のメソッドを持つ操作は、メソッドごとに展開する
                foreach ($operation->getMethodList() as $method) {
                    $paths[$path][strtolower($method)] = $operationObject;
                }
            }
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
