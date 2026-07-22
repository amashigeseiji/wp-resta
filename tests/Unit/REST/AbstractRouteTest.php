<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;

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

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertInstanceOf(RestaResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // データを直接アサート - JSON decode 不要！
        $data = $response->getData();
        $this->assertIsArray($data);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('test', $data['message']);
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

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertEquals('custom-value', $headers['X-Custom-Header']);
    }

    public function testInvokeReturnsResponseDirectly()
    {
        $route = new class extends AbstractRoute {
            public function callback(): RestaResponseInterface
            {
                return new \Wp\Resta\REST\Http\SimpleRestaResponse(
                    data: ['created' => true],
                    status: 201
                );
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(['created' => true], $response->getData());
    }

    public function testInvokeHandlesException()
    {
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                throw new \Exception('Test error');
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeWithoutCallback()
    {
        $route = new class extends AbstractRoute {
            // No callback method
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertInstanceOf(RestaResponseInterface::class, $response);
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

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertEquals(201, $response->getStatusCode());

        $data = $response->getData();
        $this->assertTrue($data['created']);
    }

    public function testInvokeReturns500WhenRequiredParamValueDoesNotMatchRegex()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'item_ids' => [
                    'type' => 'string',
                    'required' => true,
                    'regex' => '^[\d,]+$',
                    'description' => 'item_ids',
                ],
            ];

            public function callback(string $item_ids): array
            {
                return ['item_ids' => $item_ids];
            }
        };

        // フルアンカーのregexに対して、スペースが混ざった不正な値を送る
        $request = new class implements RestaRequestInterface {
            public function getUrlParam(string $name): mixed
            {
                return $name === 'item_ids' ? '1,2, 3' : null;
            }
        };

        $response = $route->invoke($request);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeDoesNotSilentlyFallBackToDefaultWhenOptionalParamValueDoesNotMatchRegex()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'item_ids' => [
                    'type' => 'string',
                    'required' => false,
                    'regex' => '^[\d,]+$',
                    'description' => 'item_ids',
                ],
            ];

            public function callback(?string $item_ids = null): array
            {
                return ['item_ids' => $item_ids];
            }
        };

        $request = new class implements RestaRequestInterface {
            public function getUrlParam(string $name): mixed
            {
                return $name === 'item_ids' ? '1,2, 3' : null;
            }
        };

        $response = $route->invoke($request);

        // 不正な値を黙ってデフォルト値にフォールバックさせず、エラーにする
        $this->assertEquals(500, $response->getStatusCode());
    }
}
