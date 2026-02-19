<?php
namespace Test\Resta\Unit\CodeGen;

use PHPUnit\Framework\TestCase;
use Wp\Resta\CodeGen\OpenApiParser;

class OpenApiParserTest extends TestCase
{
    private array $sampleSpec;

    protected function setUp(): void
    {
        $this->sampleSpec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/posts' => [
                    'get' => [
                        'description' => 'Get all posts',
                        'tags' => ['Posts'],
                        'parameters' => [
                            [
                                'name' => 'page',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'integer'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Posts']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/post/{id}' => [
                    'get' => [
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Post']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'Post' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'title' => ['type' => 'string']
                        ]
                    ],
                    'Posts' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Post']
                    ]
                ]
            ]
        ];
    }

    public function testConstructorValidatesOpenApiSpec(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing "openapi" field');

        new OpenApiParser([]);
    }

    public function testConstructorValidatesPathsField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing or invalid "paths"');

        new OpenApiParser(['openapi' => '3.0.0']);
    }

    public function testGetPaths(): void
    {
        $parser = new OpenApiParser($this->sampleSpec);
        $paths = $parser->getPaths();

        $this->assertArrayHasKey('/api/posts', $paths);
        $this->assertArrayHasKey('/api/post/{id}', $paths);
        $this->assertArrayHasKey('get', $paths['/api/posts']);
    }

    public function testGetSchemas(): void
    {
        $parser = new OpenApiParser($this->sampleSpec);
        $schemas = $parser->getSchemas();

        $this->assertArrayHasKey('Post', $schemas);
        $this->assertArrayHasKey('Posts', $schemas);
        $this->assertEquals('object', $schemas['Post']['type']);
    }

    public function testGetInfo(): void
    {
        $parser = new OpenApiParser($this->sampleSpec);
        $info = $parser->getInfo();

        $this->assertEquals('Test API', $info['title']);
        $this->assertEquals('1.0.0', $info['version']);
    }

    public function testExtractEndpoints(): void
    {
        $parser = new OpenApiParser($this->sampleSpec);
        $endpoints = $parser->extractEndpoints();

        $this->assertCount(2, $endpoints);

        // 最初のエンドポイント
        $this->assertEquals('/api/posts', $endpoints[0]['path']);
        $this->assertEquals('GET', $endpoints[0]['method']);
        $this->assertCount(0, $endpoints[0]['pathParams']);
        $this->assertCount(1, $endpoints[0]['queryParams']);
        $this->assertEquals('page', $endpoints[0]['queryParams'][0]['name']);

        // 2番目のエンドポイント
        $this->assertEquals('/api/post/{id}', $endpoints[1]['path']);
        $this->assertEquals('GET', $endpoints[1]['method']);
        $this->assertCount(1, $endpoints[1]['pathParams']);
        $this->assertEquals('id', $endpoints[1]['pathParams'][0]['name']);
        $this->assertEquals('integer', $endpoints[1]['pathParams'][0]['type']);
    }

    public function testResolveSchemaRef(): void
    {
        $parser = new OpenApiParser($this->sampleSpec);

        $schema = ['$ref' => '#/components/schemas/Post'];
        $resolved = $parser->resolveSchemaRef($schema);

        $this->assertIsArray($resolved);
        $this->assertEquals('object', $resolved['type']);
        $this->assertArrayHasKey('id', $resolved['properties']);
    }

    public function testResolveSchemaRefReturnsOriginalIfNotRef(): void
    {
        $parser = new OpenApiParser($this->sampleSpec);

        $schema = ['type' => 'string'];
        $resolved = $parser->resolveSchemaRef($schema);

        $this->assertEquals($schema, $resolved);
    }

    public function testExtractSchemaNameFromRef(): void
    {
        $parser = new OpenApiParser($this->sampleSpec);

        $name = $parser->extractSchemaNameFromRef('#/components/schemas/Post');
        $this->assertEquals('Post', $name);

        $name = $parser->extractSchemaNameFromRef('invalid-ref');
        $this->assertNull($name);
    }
}
