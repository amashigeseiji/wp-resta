<?php
namespace Test\Resta\Unit\REST\Hooks;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Hooks\EnvelopeHook;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\AbstractRoute;
use Brain\Monkey;
use Brain\Monkey\Functions;

class EnvelopeHookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testWrapInEnvelopeWithEnvelopeAttribute()
    {
        // #[Envelope] 属性がついているルート
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return ['status' => 'ok', 'message' => 'test'];
            }
        };

        // グローバルフィルターをモック
        Functions\when('apply_filters')->returnArg(2);

        $hook = new EnvelopeHook();
        $request = new TestRestaRequest('/test', $route);

        // 元のレスポンス
        $originalResponse = new SimpleRestaResponse(
            ['status' => 'ok', 'message' => 'test'],
            200,
            ['X-Custom' => 'value']
        );

        // フックを実行
        $response = $hook->wrapInEnvelope($originalResponse, $route, $request);

        // EnvelopeResponse でラップされる
        $this->assertInstanceOf(EnvelopeResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // エンベロープ構造を確認
        $data = $response->getData();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertEquals('ok', $data['data']['status']);
        $this->assertEquals('test', $data['data']['message']);
        $this->assertIsArray($data['meta']);

        // ヘッダーが保持される
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Custom', $headers);
        $this->assertEquals('value', $headers['X-Custom']);
    }

    public function testWrapInEnvelopeWithoutEnvelopeAttribute()
    {
        // #[Envelope] 属性がないルート
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                return ['status' => 'ok'];
            }
        };

        // グローバルフィルターをモック（false を返す）
        Functions\when('apply_filters')
            ->justReturn(false);

        $hook = new EnvelopeHook();
        $request = new TestRestaRequest('/test', $route);

        $originalResponse = new SimpleRestaResponse(['status' => 'ok'], 200);

        // フックを実行
        $response = $hook->wrapInEnvelope($originalResponse, $route, $request);

        // 元のレスポンスがそのまま返される
        $this->assertSame($originalResponse, $response);
        $this->assertNotInstanceOf(EnvelopeResponse::class, $response);
    }

    public function testWrapInEnvelopeDoesNotDoubleWrap()
    {
        // #[Envelope] 属性がついているルート
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return ['status' => 'ok'];
            }
        };

        Functions\when('apply_filters')->returnArg(2);

        $hook = new EnvelopeHook();
        $request = new TestRestaRequest('/test', $route);

        // 既に EnvelopeResponse
        $originalEnvelopeResponse = new EnvelopeResponse(
            ['status' => 'ok'],
            ['custom_meta' => 'value'],
            200
        );

        // フックを実行
        $response = $hook->wrapInEnvelope($originalEnvelopeResponse, $route, $request);

        // そのまま返される（二重ラップしない）
        $this->assertSame($originalEnvelopeResponse, $response);

        $data = $response->getData();
        $this->assertEquals('ok', $data['data']['status']);
        $this->assertEquals('value', $data['meta']['custom_meta']);
    }

    public function testWrapInEnvelopePreservesStatusCode()
    {
        $route = new #[Envelope] class extends AbstractRoute {
            protected int $status = 201;

            public function callback(): array
            {
                return ['created' => true];
            }
        };

        Functions\when('apply_filters')->returnArg(2);

        $hook = new EnvelopeHook();
        $request = new TestRestaRequest('/test', $route);

        $originalResponse = new SimpleRestaResponse(['created' => true], 201);
        $response = $hook->wrapInEnvelope($originalResponse, $route, $request);

        // ステータスコードが保持される
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testWrapInEnvelopePreservesHeaders()
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return ['test' => true];
            }
        };

        Functions\when('apply_filters')->returnArg(2);

        $hook = new EnvelopeHook();
        $request = new TestRestaRequest('/test', $route);

        $originalResponse = new SimpleRestaResponse(
            ['test' => true],
            200,
            [
                'X-Custom-Header' => 'envelope-value',
                'X-Another-Header' => 'another-value',
            ]
        );

        $response = $hook->wrapInEnvelope($originalResponse, $route, $request);

        // ヘッダーが保持される
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertEquals('envelope-value', $headers['X-Custom-Header']);
        $this->assertArrayHasKey('X-Another-Header', $headers);
        $this->assertEquals('another-value', $headers['X-Another-Header']);
    }

    public function testShouldUseEnvelopeWithGlobalFilter()
    {
        // #[Envelope] 属性がないルート
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                return ['status' => 'ok'];
            }
        };

        // グローバルフィルターで true を返す
        Functions\expect('apply_filters')
            ->once()
            ->with('resta_use_envelope_for_route', false, $route)
            ->andReturn(true);

        $hook = new EnvelopeHook();
        $request = new TestRestaRequest('/test', $route);

        $originalResponse = new SimpleRestaResponse(['status' => 'ok'], 200);

        // フックを実行
        $response = $hook->wrapInEnvelope($originalResponse, $route, $request);

        // グローバルフィルターで true が返されるので、エンベロープでラップされる
        $this->assertInstanceOf(EnvelopeResponse::class, $response);
    }

    public function testWrapInEnvelopeWithEmptyData()
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return [];
            }
        };

        Functions\when('apply_filters')->returnArg(2);

        $hook = new EnvelopeHook();
        $request = new TestRestaRequest('/test', $route);

        $originalResponse = new SimpleRestaResponse([], 200);
        $response = $hook->wrapInEnvelope($originalResponse, $route, $request);

        // 空配列でもエンベロープでラップされる
        $this->assertInstanceOf(EnvelopeResponse::class, $response);

        $data = $response->getData();
        $this->assertIsArray($data['data']);
        $this->assertEmpty($data['data']);
        $this->assertIsArray($data['meta']);
    }

    public function testWrapInEnvelopeWithNullData()
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): ?array
            {
                return null;
            }
        };

        Functions\when('apply_filters')->returnArg(2);

        $hook = new EnvelopeHook();
        $request = new TestRestaRequest('/test', $route);

        $originalResponse = new SimpleRestaResponse(null, 200);
        $response = $hook->wrapInEnvelope($originalResponse, $route, $request);

        // null でもエンベロープでラップされる
        $this->assertInstanceOf(EnvelopeResponse::class, $response);

        $data = $response->getData();
        $this->assertNull($data['data']);
        $this->assertIsArray($data['meta']);
    }

    public function testWrapInEnvelopeWithErrorResponse()
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return ['error' => 'Something went wrong'];
            }
        };

        Functions\when('apply_filters')->returnArg(2);

        $hook = new EnvelopeHook();
        $request = new TestRestaRequest('/test', $route);

        $originalResponse = new SimpleRestaResponse(
            ['error' => 'Something went wrong'],
            500
        );

        $response = $hook->wrapInEnvelope($originalResponse, $route, $request);

        // エラーレスポンスもエンベロープでラップされる
        $this->assertInstanceOf(EnvelopeResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData();
        $this->assertEquals('Something went wrong', $data['data']['error']);
    }
}
