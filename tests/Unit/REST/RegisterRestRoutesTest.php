<?php
namespace Test\Resta\Unit\REST;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Test\Resta\Fixtures\StatefulRoutes\TestDefaultStatusRoute;
use Test\Resta\Fixtures\StatefulRoutes\TestStatefulRoute;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\REST\RegisterRestRoutes;
use Wp\Resta\REST\RouteInterface;
use Wp\Resta\REST\RouteInvocationEvent;

/**
 * RegisterRestRoutes::register() が register_rest_route() に渡す callback のテスト
 *
 * ルートはプロセス内でシングルトンなので、callback 内で $this->status などに代入した値が
 * 次のリクエスト（/batch/v1、rest_preload_api_request、rest_do_request() の内部呼び出し）に
 * 持ち越されないことを確認する。
 */
class RegisterRestRoutesTest extends TestCase
{
    private Dispatcher $dispatcher;

    /** @var array<string, array<string, mixed>> ルート正規表現 => register_rest_route() に渡された引数 */
    private array $registered = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->resetContainer();

        $this->dispatcher = new Dispatcher();
        Container::getInstance()->bind(DispatcherInterface::class, $this->dispatcher);

        $this->registered = [];
        Functions\when('register_rest_route')->alias(function (string $namespace, string $route, array $args): bool {
            $this->registered[$route] = $args;
            return true;
        });

        $config = new Config([
            'routeDirectory' => [
                [__DIR__ . '/../../Fixtures/StatefulRoutes', 'Test\\Resta\\Fixtures\\StatefulRoutes\\', 'test'],
            ],
        ]);
        (new RegisterRestRoutes($config))->register();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $this->resetContainer();
        parent::tearDown();
    }

    private function resetContainer(): void
    {
        $reflection = new ReflectionClass(Container::class);
        $prop = $reflection->getProperty('instance');
        $prop->setValue(null, null);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function dispatch(string $routeRegex, array $params = []): \WP_REST_Response
    {
        $request = new \WP_REST_Request();
        foreach ($params as $name => $value) {
            $request[$name] = $value;
        }
        return $this->registered[$routeRegex]['callback']($request);
    }

    public function testStatusSetInPreviousRequestDoesNotCarryOverToNextRequest(): void
    {
        $notFound = $this->dispatch('stateful/(?P<id>\d+)', ['id' => 1]);
        $this->assertSame(404, $notFound->get_status());

        $found = $this->dispatch('stateful/(?P<id>\d+)', ['id' => 2]);
        $this->assertSame(200, $found->get_status());
        $this->assertSame(['id' => 2], $found->get_data());
    }

    public function testDefaultStatusDeclaredInSubclassIsPreservedAcrossRequests(): void
    {
        $first = $this->dispatch('default-status');
        $this->assertSame(404, $first->get_status());

        $second = $this->dispatch('default-status');
        $this->assertSame(404, $second->get_status());
    }

    public function testEachRequestIsHandledByFreshInstanceWithSameNamespace(): void
    {
        /** @var RouteInterface[] $seen */
        $seen = [];
        $this->dispatcher->addSubscriber(new class($seen) {
            /** @param RouteInterface[] $seen */
            public function __construct(private array &$seen) {}
            public function onInvoke(RouteInvocationEvent $event): void
            {
                $this->seen[] = $event->route;
            }
        });

        $this->dispatch('stateful/(?P<id>\d+)', ['id' => 2]);
        $this->dispatch('stateful/(?P<id>\d+)', ['id' => 3]);

        $this->assertCount(2, $seen);
        $this->assertInstanceOf(TestStatefulRoute::class, $seen[0]);
        $this->assertNotSame($seen[0], $seen[1]);
        $this->assertSame('test', $seen[0]->getNamespace());
        $this->assertSame('test', $seen[1]->getNamespace());
    }

    public function testBoundInstanceInContainerIsNotMutatedByRequest(): void
    {
        $this->dispatch('stateful/(?P<id>\d+)', ['id' => 1]);

        // OpenAPI スキーマ生成や CodeGen が参照する元のインスタンスは変わらない
        $original = Container::getInstance()->get(TestStatefulRoute::class);
        $reflection = new ReflectionClass($original);
        $this->assertSame(200, $reflection->getProperty('status')->getValue($original));
    }
}
