<?php
namespace Test\Resta\Integration;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Resta;
use Wp\Resta\DI\Container;
use Wp\Resta\Hooks\HookProvider;
use Wp\Resta\Hooks\Attributes\AddFilter;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use ReflectionClass;
use Wp\Resta\Kernel\WpKernelAdapter;

class RestaIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();

        // Container をリセット
        $reflection = new ReflectionClass(Container::class);
        $instance = $reflection->getProperty('instance');
        $instance->setValue(null, null);

        parent::tearDown();
    }

    private function baseConfig(): array
    {
        return [
            'routeDirectory' => [
                [__DIR__ . '/../Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
            'adapters' => [WpKernelAdapter::class]
        ];
    }

    public function testRestaInitRegistersWpKernelAdapterHooks()
    {
        // WpKernelAdapter::install() は rest_api_init と init の add_action、
        // rest_request_parameter_order の add_filter を登録する
        Functions\expect('add_action')
            ->once()
            ->with('rest_api_init', Mockery::type('Closure'));

        Functions\expect('add_action')
            ->once()
            ->with('init', Mockery::type('Closure'));

        Functions\expect('add_filter')
            ->once()
            ->with('rest_request_parameter_order', Mockery::type('array'), 10, 2);

        $resta = new Resta();
        $resta->init($this->baseConfig());
    }

    public function testRestaInitRegistersUserHooks()
    {
        // WpKernelAdapter の add_action は素通り
        Functions\when('add_action')->justReturn();

        // WpKernelAdapter の add_filter と user の add_filter を個別に期待する
        Functions\expect('add_filter')
            ->once()
            ->with('rest_request_parameter_order', Mockery::type('array'), 10, 2);

        Functions\expect('add_filter')
            ->once()
            ->with('custom_hook', Mockery::type('array'), 15, 2);

        $testHook = new class extends HookProvider {
            #[AddFilter('custom_hook', priority: 15, acceptedArgs: 2)]
            public function customFilter($value, $context) {
                return $value . '_filtered';
            }
        };

        $config = array_merge($this->baseConfig(), [
            'hooks' => [get_class($testHook)],
        ]);

        Container::getInstance()->bind(get_class($testHook), $testHook);

        $resta = new Resta();
        $resta->init($config);
    }

    public function testRestaInitThrowsExceptionForInvalidHookProvider()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement HookProviderInterface');

        // WpKernelAdapter の add_action と add_filter を素通り
        Functions\when('add_action')->justReturn();
        Functions\expect('add_filter')
            ->once()
            ->with('rest_request_parameter_order', Mockery::type('array'), 10, 2);

        Container::getInstance()->bind(\stdClass::class, new \stdClass());

        $config = array_merge($this->baseConfig(), [
            'hooks' => [\stdClass::class],
        ]);

        $resta = new Resta();
        $resta->init($config);
    }

    public function testRestaInitRegistersSwaggerHookViaHooksConfig()
    {
        // SwaggerHook を hooks 配列で明示的に登録した場合、init アクションが登録される
        Functions\when('add_action')->justReturn();
        Functions\when('add_filter')->justReturn();

        $config = array_merge($this->baseConfig(), [
            'hooks' => [\Wp\Resta\Hooks\SwaggerHook::class],
        ]);

        // 例外が出ないことを確認（SwaggerHook は HookProviderInterface を実装している）
        $resta = new Resta();
        $resta->init($config);

        $this->assertTrue(true);
    }

    public function testRestaInitRegistersListenerViaListenersConfig(): void
    {
        Functions\when('add_action')->justReturn();
        Functions\when('add_filter')->justReturn();

        $called = false;
        $listener = new class($called) {
            public function __construct(private bool &$flag) {}
            public function onRoute(\Wp\Resta\REST\RouteInvocationEvent $event): void
            {
                $this->flag = true;
            }
        };

        Container::getInstance()->bind(get_class($listener), $listener);

        $config = array_merge($this->baseConfig(), [
            'listeners' => [get_class($listener)],
        ]);

        $resta = new Resta();
        $resta->init($config);

        // Dispatcher にリスナーが登録されたことを確認するため RouteInvocationEvent を dispatch する
        $dispatcher = Container::getInstance()->get(\Wp\Resta\EventDispatcher\DispatcherInterface::class);

        $route   = new class extends \Wp\Resta\REST\AbstractRoute {
            public function callback(): array { return []; }
        };
        $request  = new \Wp\Resta\REST\Http\TestRestaRequest('/test', $route);
        $response = new \Wp\Resta\REST\Http\SimpleRestaResponse([], 200);

        $dispatcher->dispatch(new \Wp\Resta\REST\RouteInvocationEvent($request, $route, $response));

        $this->assertTrue($called, 'listeners config で登録したリスナーが RouteInvocationEvent を受け取れること');
    }

    public function testWpKernelAdapterIsInstalledWhenAdaptersIsOmitted(): void
    {
        // adapters を省略しても WpKernelAdapter が組み込まれ、WP のフックが登録される
        Functions\expect('add_action')->once()->with('rest_api_init', Mockery::type('Closure'));
        Functions\expect('add_action')->once()->with('init', Mockery::type('Closure'));
        Functions\expect('add_filter')->once()->with('rest_request_parameter_order', Mockery::type('array'), 10, 2);

        $config = $this->baseConfig();
        unset($config['adapters']);

        $resta = new Resta();
        $resta->init($config);
    }

    public function testRoutesAreRegisteredEveryTimeRestApiInitFires(): void
    {
        // #60: WP は rest_api_init の発火回数を保証しない（テストフレームワークは毎テスト $wp_rest_server を作り直す）。
        // 何回発火しても例外にならず、そのたびにルートを登録し直す
        $captured = [];
        Functions\when('add_action')->alias(function (string $hook, callable $callback) use (&$captured): void {
            $captured[$hook][] = $callback;
        });
        Functions\when('add_filter')->justReturn();
        $registered = 0;
        Functions\when('register_rest_route')->alias(function () use (&$registered): bool {
            $registered++;
            return true;
        });

        $fired = 0;
        $listener = new class($fired) {
            public function __construct(private int &$count) {}
            #[\Wp\Resta\EventDispatcher\Attributes\Listen('wp.rest_api_init')]
            public function onRestApiInit(\Wp\Resta\EventDispatcher\Event $event): void
            {
                // 利用者のリスナーが呼ばれる時点でルートは登録済み
                $this->count++;
            }
        };
        Container::getInstance()->bind(get_class($listener), $listener);

        $resta = new Resta();
        $resta->init(array_merge($this->baseConfig(), ['listeners' => [get_class($listener)]]));

        $this->assertCount(1, $captured['rest_api_init']);
        $restApiInit = $captured['rest_api_init'][0];

        $restApiInit();
        $routeCount = $registered;
        $this->assertGreaterThan(0, $routeCount);
        $this->assertSame(1, $fired);

        $restApiInit();
        $this->assertSame($routeCount * 2, $registered, '2 回目の発火でも全ルートを登録し直す');
        $this->assertSame(2, $fired, 'wp.rest_api_init は発火のたびに dispatch される');
    }

    public function testUserListenerOnRestApiInitRunsAfterRoutesAreRegistered(): void
    {
        $captured = [];
        Functions\when('add_action')->alias(function (string $hook, callable $callback) use (&$captured): void {
            $captured[$hook][] = $callback;
        });
        Functions\when('add_filter')->justReturn();
        $registered = 0;
        Functions\when('register_rest_route')->alias(function () use (&$registered): bool {
            $registered++;
            return true;
        });

        $registeredWhenListenerRan = null;
        $listener = new class($registeredWhenListenerRan, $registered) {
            public function __construct(private mixed &$seen, private int &$registered) {}
            #[\Wp\Resta\EventDispatcher\Attributes\Listen('wp.rest_api_init')]
            public function onRestApiInit(\Wp\Resta\EventDispatcher\Event $event): void
            {
                $this->seen = $this->registered;
            }
        };
        Container::getInstance()->bind(get_class($listener), $listener);

        (new Resta())->init(array_merge($this->baseConfig(), ['listeners' => [get_class($listener)]]));
        $captured['rest_api_init'][0]();

        $this->assertGreaterThan(0, $registeredWhenListenerRan, '利用者のリスナーはルート登録後に呼ばれる');
    }

    public function testRestaInitThrowsForInvalidAdapter(): void
    {
        Functions\when('add_action')->justReturn();
        Functions\when('add_filter')->justReturn();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must extend WpKernelAdapter');

        Container::getInstance()->bind(\stdClass::class, new \stdClass());

        $config = array_merge($this->baseConfig(), [
            'adapters' => [\stdClass::class],
        ]);

        $resta = new Resta();
        $resta->init($config);
    }

    public function testRestaInitBindsInterfaceDependency(): void
    {
        Functions\when('add_action')->justReturn();
        Functions\when('add_filter')->justReturn();

        $config = array_merge($this->baseConfig(), [
            'dependencies' => [
                TestGreeterInterface::class => TestGreeter::class,
            ],
        ]);

        $resta = new Resta();
        $resta->init($config);

        $resolved = Container::getInstance()->get(TestGreeterInterface::class);
        $this->assertInstanceOf(TestGreeter::class, $resolved, 'dependencies config で interface に実装クラスをバインドできること');
    }
}

interface TestGreeterInterface
{
}

class TestGreeter implements TestGreeterInterface
{
}
