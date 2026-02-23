<?php
namespace Test\Resta\Unit\Lifecycle;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\Lifecycle\RequestHandler;
use Wp\Resta\Lifecycle\RequestState;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use Wp\Resta\REST\RouteInvocationEvent;
use Wp\Resta\StateMachine\StateMachine;
use Wp\Resta\StateMachine\TransitionApplier;
use Wp\Resta\StateMachine\TransitionRegistry;

/**
 * RequestHandler のユニットテスト
 *
 * RequestHandler はリクエストライフサイクル全体（Received → Prepared → Invoked → Responded）
 * を StateMachine + Dispatcher で駆動する。
 * WP クラスのスタブは tests/bootstrap.php で定義されている。
 */
class RequestHandlerTest extends TestCase
{
    private Dispatcher $dispatcher;
    private StateMachine $sm;
    private TransitionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new TransitionRegistry();
        $this->registry->registerFromEnum(RequestState::class);

        $this->dispatcher = new Dispatcher();
        $this->sm         = new StateMachine($this->registry, $this->dispatcher);

        // Container に必要なバインドを登録
        $container = Container::getInstance();
        $container->bind(TransitionRegistry::class, $this->registry);
        $container->bind(TransitionApplier::class, $this->sm);
        $container->bind(StateMachine::class, $this->sm);
    }

    protected function tearDown(): void
    {
        // Container をリセット
        $reflection = new ReflectionClass(Container::class);
        $prop = $reflection->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    private function makeRoute(array $data = ['result' => 'ok'], int $status = 200): AbstractRoute
    {
        return new class($data, $status) extends AbstractRoute {
            public function __construct(private array $data, private int $s) {}

            public function callback(): array
            {
                return $this->data;
            }
        };
    }

    private function makeHandler(): RequestHandler
    {
        return new RequestHandler($this->sm, $this->dispatcher);
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

    // --- 状態遷移の正当性 ---

    public function testHandleProgressesThroughAllRequestStates(): void
    {
        $states = [];
        $dispatcher = $this->dispatcher;

        // 各遷移後イベントで状態を記録
        foreach (['convert', 'invoke', 'respond'] as $action) {
            $dispatcher->addListener(
                \Wp\Resta\StateMachine\TransitionEvent::afterEventName(
                    match ($action) {
                        'convert' => RequestState::Received,
                        'invoke'  => RequestState::Prepared,
                        'respond' => RequestState::Invoked,
                    },
                    $action
                ),
                function (\Wp\Resta\StateMachine\TransitionEvent $e) use ($action, &$states): void {
                    $states[] = $action;
                }
            );
        }

        $handler = $this->makeHandler();
        $handler->handle(new \WP_REST_Request(), $this->makeRoute());

        $this->assertEquals(['convert', 'invoke', 'respond'], $states);
    }

    // --- エラーケース ---

    public function testHandleThrowsIfStateDoesNotAdvance(): void
    {
        // StateMachine を「常に遷移しない」ものに差し替える
        $noOpSm = new class implements TransitionApplier {
            public function apply(\Wp\Resta\StateMachine\HasState $subject, string $action): void
            {
                // 何もしない = 状態が変わらない
            }
        };

        $handler = new RequestHandler($noOpSm, $this->dispatcher);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to transition.');

        $handler->handle(new \WP_REST_Request(), $this->makeRoute());
    }
}
