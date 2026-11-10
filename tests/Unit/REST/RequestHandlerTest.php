<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\Auth\Principal;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Exception\ForbiddenException;
use Wp\Resta\REST\Exception\InvalidParameterException;
use Wp\Resta\REST\Hooks\EnvelopeHook;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\Parameter\BoundParameters;
use Wp\Resta\REST\RequestHandler;
use Wp\Resta\REST\RouteInvocationEvent;

/**
 * RequestHandler は WP 非依存のパイプライン: validate → validate フック → authorize → callback → RouteInvocationEvent
 */
class RequestHandlerTest extends TestCase
{
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new Dispatcher();
    }

    protected function tearDown(): void
    {
        (new ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);
        parent::tearDown();
    }

    private function handler(?Principal $principal = null): RequestHandler
    {
        return new RequestHandler($principal ?? new TestPrincipal(), $this->dispatcher);
    }

    public function testPipelineOrderIsValidateThenAuthorizeThenCallback(): void
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'item/[id]';
            /** @var string[] */
            public array $trace = [];
            public function validate(BoundParameters $params): void
            {
                $this->trace[] = 'validate:' . var_export($params->get('id'), true);
            }
            public function authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal): void
            {
                $this->trace[] = 'authorize:' . var_export($params->get('id'), true);
            }
            public function callback(int $id): array
            {
                $this->trace[] = 'callback';
                return ['id' => $id];
            }
        };
        $route->setNamespace('t');

        $response = $this->handler()->handle($route, new TestRestaRequest('/t/item/42', $route));

        $this->assertSame(['id' => 42], $response->getData());
        $this->assertSame(['validate:42', 'authorize:42', 'callback'], $route->trace, 'authorize() が見る値は型変換済み');
    }

    public function testValidateHookCanRejectWith400(): void
    {
        $route = new class extends AbstractRoute {
            public bool $called = false;
            public function validate(BoundParameters $params): void
            {
                if ($params->get('from') > $params->get('to')) {
                    throw new InvalidParameterException('from', 'from must be <= to');
                }
            }
            public function callback(int $from = 0, int $to = 0): array
            {
                $this->called = true;
                return [];
            }
        };

        $response = $this->handler()->handle($route, new TestRestaRequest('/x?from=5&to=1', $route));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('from must be <= to', $response->getData()['data']['params']['from']);
        $this->assertFalse($route->called);
    }

    public function testDeniedRequestDoesNotResolveCallbackDependencies(): void
    {
        // DI の解決は認可の後。未認証のリクエストでサービスを組み立てない
        $route = new class extends AbstractRoute {
            public function authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal): void
            {
                throw new ForbiddenException();
            }
            public function callback(\stdClass $neverResolved): array
            {
                return [];
            }
        };
        $resolved = false;
        Container::getInstance()->bind(\stdClass::class, function () use (&$resolved): \stdClass {
            $resolved = true;
            return new \stdClass();
        });

        $response = $this->handler()->handle($route, new TestRestaRequest('/x', $route));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($resolved);
    }

    public function testEnvelopeIsAppliedWithoutWordPress(): void
    {
        $this->dispatcher->addSubscriber(new EnvelopeHook());
        $route = new #[Envelope] class extends AbstractRoute {
            public function callback(): array
            {
                return ['x' => 1];
            }
        };

        $response = $this->handler()->handle($route, new TestRestaRequest('/x', $route));

        $this->assertSame(['x' => 1], $response->getData()['data']);
        $this->assertArrayHasKey('meta', $response->getData());
    }

    public function testListenerCanReplaceResponse(): void
    {
        $this->dispatcher->addSubscriber(new class {
            public function onInvoke(RouteInvocationEvent $event): void
            {
                $event->response = new SimpleRestaResponse(['modified' => true], 202);
            }
        });
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                return ['original' => true];
            }
        };

        $response = $this->handler()->handle($route, new TestRestaRequest('/x', $route));

        $this->assertSame(['modified' => true], $response->getData());
        $this->assertSame(202, $response->getStatusCode());
    }

    public function testEventCarriesRequestRouteAndErrorResponseToo(): void
    {
        $seen = null;
        $this->dispatcher->addSubscriber(new class($seen) {
            public function __construct(private mixed &$seen) {}
            public function onInvoke(RouteInvocationEvent $event): void
            {
                $this->seen = [$event->request, $event->route, $event->response->getStatusCode()];
            }
        });
        $route = new class extends AbstractRoute {
            public function authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal): void
            {
                throw new ForbiddenException();
            }
            public function callback(): array
            {
                return [];
            }
        };
        $request = new TestRestaRequest('/x', $route);

        $this->handler()->handle($route, $request);

        $this->assertSame($request, $seen[0]);
        $this->assertSame($route, $seen[1]);
        $this->assertSame(403, $seen[2], 'エラーレスポンスも RouteInvocationEvent を通る');
    }

    public function testDeclaredHeadersApplyToEveryResponseAndResponseWins(): void
    {
        // ルートの既定ヘッダは、callback が直接返したレスポンスにもエラーにも付く。同名はレスポンス側が勝つ
        $route = new class extends AbstractRoute {
            protected array $headers = ['Cache-Control' => 'no-store', 'X-Route' => 'r'];
            public function callback(string $mode = 'data'): mixed
            {
                return match ($mode) {
                    'direct' => new SimpleRestaResponse(['direct' => true], 200, ['cache-control' => 'max-age=60']),
                    'error' => throw new InvalidParameterException('mode'),
                    default => ['data' => true],
                };
            }
        };

        $data = $this->handler()->handle($route, new TestRestaRequest('/x', $route));
        $this->assertSame(['Cache-Control' => 'no-store', 'X-Route' => 'r'], $data->getHeaders());

        $direct = $this->handler()->handle($route, new TestRestaRequest('/x?mode=direct', $route));
        $this->assertSame(['X-Route' => 'r', 'cache-control' => 'max-age=60'], $direct->getHeaders(), '直接返したレスポンスの同名ヘッダが勝つ');

        $error = $this->handler()->handle($route, new TestRestaRequest('/x?mode=error', $route));
        $this->assertSame(400, $error->getStatusCode());
        $this->assertSame(['Cache-Control' => 'no-store', 'X-Route' => 'r'], $error->getHeaders(), 'エラーレスポンスにも付く');
    }

    public function testStatusAssignedInCallbackStillWorksThroughBridge(): void
    {
        // 0.12 の橋: callback 内の $this->status への代入は、AbstractRoute::getCallback() が戻り値の包みに変換する
        $route = new class extends AbstractRoute {
            public function callback(): ?array
            {
                $this->status = 404;
                return null;
            }
        };

        $response = $this->handler()->handle($route, new TestRestaRequest('/x', $route));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertNull($response->getData());
    }

    public function testCurrentRequestIsPassedToCallbackWithoutContainer(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(RestaRequestInterface $request): array
            {
                return ['id' => $request->getUrlParam('id')];
            }
        };

        $response = $this->handler()->handle($route, new TestRestaRequest('/x?id=7', $route));

        $this->assertSame(['id' => '7'], $response->getData(), 'RestaRequestInterface から読む値はクライアントが送った文字列のまま');
    }
}
