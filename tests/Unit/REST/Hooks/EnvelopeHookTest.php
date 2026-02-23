<?php
namespace Test\Resta\Unit\REST\Hooks;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Hooks\EnvelopeHook;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\RouteInvocationEvent;

class EnvelopeHookTest extends TestCase
{
    private function makeEvent(AbstractRoute $route, RestaResponseInterface $response): RouteInvocationEvent
    {
        $request = new TestRestaRequest('/test', $route);
        $event = new RouteInvocationEvent($request, $route, $response);
        $event->response = $response;
        return $event;
    }

    public function testWrapInEnvelopeWithEnvelopeAttribute(): void
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return ['status' => 'ok', 'message' => 'test'];
            }
        };

        $hook = new EnvelopeHook();
        $event = $this->makeEvent($route, new SimpleRestaResponse(
            ['status' => 'ok', 'message' => 'test'],
            200,
            ['X-Custom' => 'value']
        ));

        $hook->handle($event);

        $this->assertInstanceOf(EnvelopeResponse::class, $event->response);
        $this->assertEquals(200, $event->response->getStatusCode());

        $data = $event->response->getData();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertEquals('ok', $data['data']['status']);
        $this->assertEquals('test', $data['data']['message']);

        $headers = $event->response->getHeaders();
        $this->assertArrayHasKey('X-Custom', $headers);
        $this->assertEquals('value', $headers['X-Custom']);
    }

    public function testWrapInEnvelopeWithoutEnvelopeAttribute(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                return ['status' => 'ok'];
            }
        };

        $hook = new EnvelopeHook();
        $original = new SimpleRestaResponse(['status' => 'ok'], 200);
        $event = $this->makeEvent($route, $original);

        $hook->handle($event);

        $this->assertSame($original, $event->response);
        $this->assertNotInstanceOf(EnvelopeResponse::class, $event->response);
    }

    public function testWrapInEnvelopeDoesNotDoubleWrap(): void
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return ['status' => 'ok'];
            }
        };

        $hook = new EnvelopeHook();
        $original = new EnvelopeResponse(['status' => 'ok'], ['custom_meta' => 'value'], 200);
        $event = $this->makeEvent($route, $original);

        $hook->handle($event);

        $this->assertSame($original, $event->response);
        $data = $event->response->getData();
        $this->assertEquals('ok', $data['data']['status']);
        $this->assertEquals('value', $data['meta']['custom_meta']);
    }

    public function testWrapInEnvelopePreservesStatusCode(): void
    {
        $route = new #[Envelope] class extends AbstractRoute {
            protected int $status = 201;
            public function callback(): array
            {
                return ['created' => true];
            }
        };

        $hook = new EnvelopeHook();
        $event = $this->makeEvent($route, new SimpleRestaResponse(['created' => true], 201));

        $hook->handle($event);

        $this->assertEquals(201, $event->response->getStatusCode());
    }

    public function testWrapInEnvelopePreservesHeaders(): void
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return ['test' => true];
            }
        };

        $hook = new EnvelopeHook();
        $event = $this->makeEvent($route, new SimpleRestaResponse(
            ['test' => true],
            200,
            ['X-Custom-Header' => 'envelope-value', 'X-Another-Header' => 'another-value']
        ));

        $hook->handle($event);

        $headers = $event->response->getHeaders();
        $this->assertEquals('envelope-value', $headers['X-Custom-Header']);
        $this->assertEquals('another-value', $headers['X-Another-Header']);
    }

    public function testWrapInEnvelopeWithEmptyData(): void
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return [];
            }
        };

        $hook = new EnvelopeHook();
        $event = $this->makeEvent($route, new SimpleRestaResponse([], 200));

        $hook->handle($event);

        $this->assertInstanceOf(EnvelopeResponse::class, $event->response);
        $data = $event->response->getData();
        $this->assertIsArray($data['data']);
        $this->assertEmpty($data['data']);
    }

    public function testWrapInEnvelopeWithNullData(): void
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): ?array
            {
                return null;
            }
        };

        $hook = new EnvelopeHook();
        $event = $this->makeEvent($route, new SimpleRestaResponse(null, 200));

        $hook->handle($event);

        $this->assertInstanceOf(EnvelopeResponse::class, $event->response);
        $this->assertNull($event->response->getData()['data']);
    }

    public function testWrapInEnvelopeWithErrorResponse(): void
    {
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return ['error' => 'Something went wrong'];
            }
        };

        $hook = new EnvelopeHook();
        $event = $this->makeEvent($route, new SimpleRestaResponse(
            ['error' => 'Something went wrong'],
            500
        ));

        $hook->handle($event);

        $this->assertInstanceOf(EnvelopeResponse::class, $event->response);
        $this->assertEquals(500, $event->response->getStatusCode());
        $this->assertEquals('Something went wrong', $event->response->getData()['data']['error']);
    }
}
