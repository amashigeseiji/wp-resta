<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\EnvelopeRoute;
use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\Http\TestRestaRequest;

class EnvelopeRouteTest extends TestCase
{
    public function testInvokeWrapsArrayInEnvelope()
    {
        $route = new class extends EnvelopeRoute {
            public function callback(): array
            {
                return ['status' => 'ok', 'message' => 'test'];
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        // EnvelopeResponse が返される
        $this->assertInstanceOf(EnvelopeResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // エンベロープ構造を確認
        $data = $response->getData();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);

        // データ部分を確認
        $this->assertEquals('ok', $data['data']['status']);
        $this->assertEquals('test', $data['data']['message']);

        // メタ部分は空配列
        $this->assertIsArray($data['meta']);
    }

    public function testInvokeReturnsEnvelopeResponseDirectly()
    {
        $route = new class extends EnvelopeRoute {
            public function callback(): EnvelopeResponse
            {
                return EnvelopeResponse::success(
                    ['message' => 'custom'],
                    ['custom_meta' => 'value']
                );
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        // 既に EnvelopeResponse なのでそのまま返される
        $this->assertInstanceOf(EnvelopeResponse::class, $response);

        $data = $response->getData();
        $this->assertEquals('custom', $data['data']['message']);
        $this->assertEquals('value', $data['meta']['custom_meta']);
    }

    public function testInvokeWithCustomStatus()
    {
        $route = new class extends EnvelopeRoute {
            protected int $status = 201;

            public function callback(): array
            {
                return ['created' => true];
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertEquals(201, $response->getStatusCode());

        $data = $response->getData();
        $this->assertTrue($data['data']['created']);
    }

    public function testInvokeWithCustomHeaders()
    {
        $route = new class extends EnvelopeRoute {
            protected array $headers = [
                'X-Custom-Header' => 'envelope-value',
            ];

            public function callback(): array
            {
                return ['test' => true];
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertEquals('envelope-value', $headers['X-Custom-Header']);
    }

    public function testInvokeHandlesException()
    {
        $route = new class extends EnvelopeRoute {
            public function callback(): array
            {
                throw new \Exception('Test error', 123);
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        // エラー時もエンベロープでラップされる
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData();
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertArrayHasKey('error', $data['data']);
        $this->assertEquals('Test error', $data['data']['error']);
    }

    public function testGetSchemaWrapsInEnvelope()
    {
        $route = new class extends EnvelopeRoute {
            public const SCHEMA = [
                'type' => 'array',
                'items' => [
                    'type' => 'string'
                ]
            ];
        };

        $schema = $route->getSchema();

        // エンベロープ構造に変換される
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('data', $schema['properties']);
        $this->assertArrayHasKey('meta', $schema['properties']);

        // data プロパティに元のスキーマが含まれる
        $this->assertEquals('array', $schema['properties']['data']['type']);
        $this->assertEquals('string', $schema['properties']['data']['items']['type']);

        // meta プロパティが追加される
        $this->assertEquals('object', $schema['properties']['meta']['type']);
    }

    public function testGetSchemaWithObjectType()
    {
        $route = new class extends EnvelopeRoute {
            public const SCHEMA = [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string']
                ]
            ];
        };

        $schema = $route->getSchema();

        // エンベロープ構造
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('data', $schema['properties']);

        // data に元のオブジェクト定義が含まれる
        $dataSchema = $schema['properties']['data'];
        $this->assertEquals('object', $dataSchema['type']);
        $this->assertArrayHasKey('id', $dataSchema['properties']);
        $this->assertArrayHasKey('name', $dataSchema['properties']);
    }

    public function testGetSchemaReturnsNullWhenSchemaIsNull()
    {
        $route = new class extends EnvelopeRoute {
            public const SCHEMA = null;
        };

        $schema = $route->getSchema();
        $this->assertNull($schema);
    }

    public function testInvokeWithoutCallback()
    {
        $route = new class extends EnvelopeRoute {
            // No callback method
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        // callback がない場合もエンベロープでラップされる
        $this->assertInstanceOf(EnvelopeResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function testInvokeWithNullData()
    {
        $route = new class extends EnvelopeRoute {
            public function callback(): ?array
            {
                return null;
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $data = $response->getData();
        $this->assertArrayHasKey('data', $data);
        $this->assertNull($data['data']);
    }
}
