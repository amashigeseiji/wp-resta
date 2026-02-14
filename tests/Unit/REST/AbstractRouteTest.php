<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use PsrMock\Psr7\Request;
use Wp\Resta\REST\AbstractRoute;

class AbstractRouteTest extends TestCase
{
    public function testInvokeWithSimpleCallback()
    {
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                return ['status' => 'ok', 'message' => 'test'];
            }
        };

        $request = new Request('GET', 'http://example.com/test');
        $response = $route->invoke($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('ok', $body['status']);
        $this->assertEquals('test', $body['message']);
    }

    public function testInvokeWithCustomHeaders()
    {
        $route = new class extends AbstractRoute {
            protected array $headers = [
                'X-Custom-Header' => 'custom-value',
            ];

            public function callback(): string
            {
                return 'response';
            }
        };

        $request = new Request('GET', 'http://example.com/test');
        $response = $route->invoke($request);

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertEquals(['custom-value'], $headers['X-Custom-Header']);
    }

    public function testInvokeReturnsResponseDirectly()
    {
        $route = new class extends AbstractRoute {
            public function callback(): ResponseInterface
            {
                return new \PsrMock\Psr7\Response(201);
            }
        };

        $request = new Request('POST', 'http://example.com/test');
        $response = $route->invoke($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testInvokeHandlesException()
    {
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                throw new \Exception('Test error');
            }
        };

        $request = new Request('GET', 'http://example.com/test');
        $response = $route->invoke($request);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithoutCallback()
    {
        $route = new class extends AbstractRoute {
            // No callback method
        };

        $request = new Request('GET', 'http://example.com/test');
        $response = $route->invoke($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvokeWithCustomStatus()
    {
        $route = new class extends AbstractRoute {
            protected int $status = 201;

            public function callback(): array
            {
                return ['created' => true];
            }
        };

        $request = new Request('POST', 'http://example.com/test');
        $response = $route->invoke($request);

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertTrue($body['created']);
    }
}
