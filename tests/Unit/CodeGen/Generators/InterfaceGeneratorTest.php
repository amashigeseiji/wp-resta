<?php
namespace Test\Resta\Unit\CodeGen\Generators;

use PHPUnit\Framework\TestCase;
use Wp\Resta\CodeGen\Generators\InterfaceGenerator;
use Wp\Resta\CodeGen\OpenApiParser;

class InterfaceGeneratorTest extends TestCase
{
    public function testGenerateSimpleInterface(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Post' => [
                        'type' => 'object',
                        'description' => 'A blog post',
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => 'Post ID'],
                            'title' => ['type' => 'string', 'description' => 'Post title'],
                        ]
                    ]
                ]
            ]
        ];

        $parser = new OpenApiParser($spec);
        $generator = new InterfaceGenerator();
        $output = $generator->generate($parser);

        $this->assertStringContainsString('export interface Post', $output);
        $this->assertStringContainsString('id: number;', $output);
        $this->assertStringContainsString('title: string;', $output);
        $this->assertStringContainsString('A blog post', $output);
    }

    public function testGenerateInterfaceWithOptionalProperty(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'required' => true],
                            'email' => ['type' => 'string', 'required' => false],
                        ]
                    ]
                ]
            ]
        ];

        $parser = new OpenApiParser($spec);
        $generator = new InterfaceGenerator();
        $output = $generator->generate($parser);

        $this->assertStringContainsString('name: string;', $output);
        $this->assertStringContainsString('email?: string;', $output);
    }

    public function testGenerateArrayType(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Post' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                        ]
                    ],
                    'Posts' => [
                        'type' => 'array',
                        'description' => 'Array of posts',
                        'items' => ['$ref' => '#/components/schemas/Post']
                    ]
                ]
            ]
        ];

        $parser = new OpenApiParser($spec);
        $generator = new InterfaceGenerator();
        $output = $generator->generate($parser);

        $this->assertStringContainsString('export type Posts = Post[];', $output);
        $this->assertStringContainsString('Array of posts', $output);
    }

    public function testGenerateInterfaceWithNestedObject(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Author' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ]
                    ],
                    'Post' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'author' => ['$ref' => '#/components/schemas/Author'],
                        ]
                    ]
                ]
            ]
        ];

        $parser = new OpenApiParser($spec);
        $generator = new InterfaceGenerator();
        $output = $generator->generate($parser);

        $this->assertStringContainsString('export interface Author', $output);
        $this->assertStringContainsString('export interface Post', $output);
        $this->assertStringContainsString('author: Author;', $output);
    }

    public function testGenerateInterfaceWithArrayProperty(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Post' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'tags' => [
                                'type' => 'array',
                                'items' => ['type' => 'string']
                            ],
                        ]
                    ]
                ]
            ]
        ];

        $parser = new OpenApiParser($spec);
        $generator = new InterfaceGenerator();
        $output = $generator->generate($parser);

        $this->assertStringContainsString('tags: string[];', $output);
    }

    public function testGenerateInterfaceWithEnumProperty(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Post' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => [
                                'type' => 'string',
                                'enum' => ['draft', 'published', 'archived']
                            ],
                        ]
                    ]
                ]
            ]
        ];

        $parser = new OpenApiParser($spec);
        $generator = new InterfaceGenerator();
        $output = $generator->generate($parser);

        $this->assertStringContainsString("status: 'draft' | 'published' | 'archived';", $output);
    }

    public function testGenerateInterfaceWithRecordType(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Config' => [
                        'type' => 'object',
                        'properties' => [
                            'metadata' => [
                                'type' => 'object',
                                'additionalProperties' => ['type' => 'string']
                            ],
                        ]
                    ]
                ]
            ]
        ];

        $parser = new OpenApiParser($spec);
        $generator = new InterfaceGenerator();
        $output = $generator->generate($parser);

        $this->assertStringContainsString('metadata: Record<string, string>;', $output);
    }
}
