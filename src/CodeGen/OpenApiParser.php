<?php
namespace Wp\Resta\CodeGen;

/**
 * OpenAPI JSON構造を解析してPHP配列に変換
 */
class OpenApiParser
{
    private array $spec;

    public function __construct(array $openApiSpec)
    {
        $this->spec = $openApiSpec;
        $this->validate();
    }

    /**
     * OpenAPI仕様の基本的な検証
     */
    private function validate(): void
    {
        if (!isset($this->spec['openapi'])) {
            throw new \InvalidArgumentException('Invalid OpenAPI spec: missing "openapi" field');
        }

        if (!isset($this->spec['paths']) || !is_array($this->spec['paths'])) {
            throw new \InvalidArgumentException('Invalid OpenAPI spec: missing or invalid "paths"');
        }
    }

    /**
     * paths定義を取得
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPaths(): array
    {
        return $this->spec['paths'] ?? [];
    }

    /**
     * components.schemas定義を取得
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSchemas(): array
    {
        return $this->spec['components']['schemas'] ?? [];
    }

    /**
     * info定義を取得
     *
     * @return array<string, mixed>
     */
    public function getInfo(): array
    {
        return $this->spec['info'] ?? [];
    }

    /**
     * servers定義を取得
     *
     * @return array<array<string, mixed>>
     */
    public function getServers(): array
    {
        return $this->spec['servers'] ?? [];
    }

    /**
     * パスから型安全なEndpoint構造を抽出
     *
     * @return array{path: string, method: string, pathParams: array, queryParams: array, responseSchema: array|null, description: string, tags: array}[]
     */
    public function extractEndpoints(): array
    {
        $endpoints = [];

        foreach ($this->getPaths() as $path => $methods) {
            foreach ($methods as $method => $definition) {
                $pathParams = [];
                $queryParams = [];

                // パラメータを分類
                foreach ($definition['parameters'] ?? [] as $param) {
                    $paramInfo = [
                        'name' => $param['name'],
                        'type' => $param['schema']['type'] ?? 'string',
                        'required' => $param['required'] ?? false,
                        'description' => $param['description'] ?? '',
                    ];

                    if ($param['in'] === 'path') {
                        $pathParams[] = $paramInfo;
                    } elseif ($param['in'] === 'query') {
                        $queryParams[] = $paramInfo;
                    }
                }

                // レスポンススキーマを取得
                $responseSchema = $definition['responses']['200']['content']['application/json']['schema'] ?? null;

                $endpoints[] = [
                    'path' => $path,
                    'method' => strtoupper($method),
                    'pathParams' => $pathParams,
                    'queryParams' => $queryParams,
                    'responseSchema' => $responseSchema,
                    'description' => $definition['description'] ?? '',
                    'tags' => $definition['tags'] ?? [],
                ];
            }
        }

        return $endpoints;
    }

    /**
     * スキーマ参照（$ref）を解決
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>|null
     */
    public function resolveSchemaRef(array $schema): ?array
    {
        if (isset($schema['$ref'])) {
            // $ref形式: "#/components/schemas/Post"
            $refPath = $schema['$ref'];
            $parts = explode('/', $refPath);
            $schemaName = end($parts);

            $schemas = $this->getSchemas();
            return $schemas[$schemaName] ?? null;
        }

        return $schema;
    }

    /**
     * スキーマ名を$refから抽出
     *
     * @param string $ref
     * @return string|null
     */
    public function extractSchemaNameFromRef(string $ref): ?string
    {
        if (str_starts_with($ref, '#/components/schemas/')) {
            return basename($ref);
        }
        return null;
    }
}
