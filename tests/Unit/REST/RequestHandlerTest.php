<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\Auth\Principal;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Authenticated;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Exception\ForbiddenException;
use Wp\Resta\REST\Exception\InvalidParameterException;
use Wp\Resta\REST\Hooks\AuthorizationHook;
use Wp\Resta\REST\Hooks\EnvelopeHook;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;
use Wp\Resta\REST\RouteInvocationEvent;

/**
 * RequestHandler は WP 非依存のパイプライン: validate → BeforeCallbackEvent → callback → RouteInvocationEvent
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

    private function handler(): RequestHandler
    {
        return new RequestHandler($this->dispatcher);
    }

    public function testCallbackDecidesWithValidatedValueAndBoundPrincipal(): void
    {
        // 値に依る認可は callback の中に書く。Principal は引数で宣言して受け取り、テストではコンテナに bind する
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'item/[id]';
            public function callback(int $id, Principal $principal): array
            {
                if (!$principal->can('edit_post', $id)) {
                    throw new ForbiddenException();
                }
                return ['id' => $id];
            }
        };
        $route->setNamespace('t');

        Container::getInstance()->bind(Principal::class, new TestPrincipal());
        $denied = $this->handler()->handle($route, new TestRestaRequest('/t/item/42', $route));
        $this->assertSame(403, $denied->getStatusCode());
        $this->assertSame('rest_forbidden', $denied->getData()['code']);

        Container::getInstance()->bind(Principal::class, new TestPrincipal(authenticated: true, capabilities: ['edit_post']));
        $allowed = $this->handler()->handle($route, new TestRestaRequest('/t/item/42', $route));
        $this->assertSame(['id' => 42], $allowed->getData());
    }

    public function testCallbackCanRejectCrossParameterInputWith400(): void
    {
        // 複数のパラメータにまたがる検証は callback の中に書く
        $route = new class extends AbstractRoute {
            public function callback(int $from = 0, int $to = 0): array
            {
                if ($from > $to) {
                    throw new InvalidParameterException('from', 'from must be <= to');
                }
                return ['from' => $from, 'to' => $to];
            }
        };

        $response = $this->handler()->handle($route, new TestRestaRequest('/x?from=5&to=1', $route));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('from must be <= to', $response->getData()['data']['params']['from']);
    }

    public function testDeniedRequestDoesNotResolveCallbackDependencies(): void
    {
        // DI の解決は、認可のアトリビュートの判定の後。未認証のリクエストでサービスを組み立てない
        $this->dispatcher->addSubscriber(new AuthorizationHook(new TestPrincipal()));
        $route = new class extends AbstractRoute {
            #[Authenticated]
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

        $this->assertSame(401, $response->getStatusCode());
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
            public function callback(): array
            {
                throw new ForbiddenException();
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
            protected const HEADERS = ['Cache-Control' => 'no-store', 'X-Route' => 'r'];
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

    public function testRequestHandlerCanBeExtended(): void
    {
        // DI で組み立てるクラスは、差し替えられる状態にしておく（docs/design.md）
        $handler = new class(new Dispatcher()) extends RequestHandler {
        };
        $route = new class extends AbstractRoute {
            public function callback(): string
            {
                return 'ok';
            }
        };

        $this->assertSame('ok', $handler->handle($route, new TestRestaRequest('/x', $route))->getData());
    }
}
