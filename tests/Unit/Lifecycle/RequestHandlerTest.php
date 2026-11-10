<?php
namespace Test\Resta\Unit\Lifecycle;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\Lifecycle\RequestHandler;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use Wp\Resta\REST\RouteInvocationEvent;

/**
 * RequestHandler のユニットテスト
 *
 * RequestHandler は WP_REST_Request → invoke() → RouteInvocationEvent → WP_REST_Response の直線処理。
 * WP クラスのスタブは tests/bootstrap.php で定義されている。
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
        // Container をリセット
        $reflection = new ReflectionClass(Container::class);
        $prop = $reflection->getProperty('instance');
        $prop->setValue(null, null);

        parent::tearDown();
    }

    private function makeRoute(array $data = ['result' => 'ok']): AbstractRoute
    {
        return new class($data) extends AbstractRoute {
            public function __construct(private array $data) {}

            public function callback(): array
            {
                return $this->data;
            }
        };
    }

    private function makeHandler(): RequestHandler
    {
        return new RequestHandler($this->dispatcher);
    }

    // --- 基本的なライフサイクル ---

    public function testHandleReturnsWpRestResponse(): void
    {
        $handler = $this->makeHandler();
        $route   = $this->makeRoute(['hello' => 'world']);

        $wpRequest = new \WP_REST_Request();
        $result    = $handler->handle($wpRequest, $route);

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
    }

    public function testHandleReturnsRouteData(): void
    {
        $handler = $this->makeHandler();
        $route   = $this->makeRoute(['key' => 'value']);

        $wpRequest = new \WP_REST_Request();
        $result    = $handler->handle($wpRequest, $route);

        $this->assertEquals(['key' => 'value'], $result->get_data());
    }

    public function testHandleReturnsCorrectStatusCode(): void
    {
        $handler = $this->makeHandler();
        $route   = new class extends AbstractRoute {
            protected int $status = 201;
            public function callback(): array { return ['created' => true]; }
        };

        $wpRequest = new \WP_REST_Request();
        $result    = $handler->handle($wpRequest, $route);

        $this->assertEquals(201, $result->get_status());
    }

    public function testHandleCopiesResponseHeaders(): void
    {
        $handler = $this->makeHandler();
        $route   = new class extends AbstractRoute {
            protected array $headers = ['X-Custom' => 'test-value'];
            public function callback(): array { return []; }
        };

        $wpRequest = new \WP_REST_Request();
        $result    = $handler->handle($wpRequest, $route);

        $this->assertArrayHasKey('X-Custom', $result->get_headers());
        $this->assertEquals('test-value', $result->get_headers()['X-Custom']);
    }

    public function testHandleMakesCurrentRequestAvailableToCallback(): void
    {
        // callback 引数の WP_REST_Request と RestaRequestInterface は、いま処理中のリクエストになる
        $handler = $this->makeHandler();
        $route   = new class extends AbstractRoute {
            public function callback(\WP_REST_Request $wp, RestaRequestInterface $request): array
            {
                return ['wp' => spl_object_id($wp), 'request' => $request->getUrlParam('id')];
            }
        };

        $wpRequest = new \WP_REST_Request();
        $wpRequest['id'] = 42;
        $result = $handler->handle($wpRequest, $route);

        $this->assertSame(['wp' => spl_object_id($wpRequest), 'request' => 42], $result->get_data());
    }

    // --- RouteInvocationEvent リスナーとの連携 ---

    public function testListenerCanModifyResponseViaRouteInvocationEvent(): void
    {
        $modified = false;
        $this->dispatcher->addSubscriber(new class($modified) {
            public function __construct(private bool &$flag) {}
            public function onInvoke(RouteInvocationEvent $event): void
            {
                $event->response = new SimpleRestaResponse(
                    ['modified' => true],
                    $event->response->getStatusCode()
                );
                $this->flag = true;
            }
        });

        $handler = $this->makeHandler();
        $route   = $this->makeRoute(['original' => true]);

        $result = $handler->handle(new \WP_REST_Request(), $route);

        $this->assertTrue($modified);
        $this->assertEquals(['modified' => true], $result->get_data());
    }

    public function testMultipleListenersReceiveRouteInvocationEvent(): void
    {
        $callCount = 0;
        $counter   = new class($callCount) {
            public function __construct(private int &$count) {}
            public function onInvoke(RouteInvocationEvent $event): void { $this->count++; }
        };

        $this->dispatcher->addSubscriber($counter);
        $this->dispatcher->addSubscriber(clone $counter);

        $handler = $this->makeHandler();
        $handler->handle(new \WP_REST_Request(), $this->makeRoute());

        $this->assertEquals(2, $callCount);
    }

    public function testRouteInvocationEventCarriesRequestRouteAndResponse(): void
    {
        $seen = null;
        $this->dispatcher->addSubscriber(new class($seen) {
            public function __construct(private mixed &$seen) {}
            public function onInvoke(RouteInvocationEvent $event): void
            {
                $this->seen = [$event->request, $event->route, $event->response->getData()];
            }
        });

        $route = $this->makeRoute(['x' => 1]);
        $this->makeHandler()->handle(new \WP_REST_Request(), $route);

        $this->assertInstanceOf(RestaRequestInterface::class, $seen[0]);
        $this->assertSame($route, $seen[1]);
        $this->assertSame(['x' => 1], $seen[2]);
    }
}
