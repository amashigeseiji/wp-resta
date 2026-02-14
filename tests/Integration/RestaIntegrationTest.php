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
        Mockery::close(); // Mockery の検証を明示的に実行

        // Container をリセット
        $reflection = new ReflectionClass(Container::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        parent::tearDown();
    }

    public function testRestaInitRegistersInternalHooks()
    {
        // InternalHooks が rest_api_init アクションを登録する
        Functions\expect('add_action')
            ->once()
            ->with('rest_api_init', Mockery::type('array'), 10, 1);

        $config = [
            'routeDirectory' => [
                [__DIR__ . '/../Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
        ];

        $resta = new Resta();
        $resta->init($config);
    }

    public function testRestaInitRegistersUserHooks()
    {
        // ユーザー定義の custom_hook フィルターが登録される
        Functions\expect('add_action')
            ->once()
            ->with('rest_api_init', Mockery::type('array'), 10, 1);

        Functions\expect('add_filter')
            ->once()
            ->with('custom_hook', Mockery::type('array'), 15, 2);

        $testHook = new class extends HookProvider {
            #[AddFilter('custom_hook', priority: 15, acceptedArgs: 2)]
            public function customFilter($value, $context) {
                return $value . '_filtered';
            }
        };

        $config = [
            'routeDirectory' => [
                [__DIR__ . '/../Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
            'hooks' => [
                get_class($testHook),
            ],
        ];

        Container::getInstance()->bind(get_class($testHook), $testHook);

        $resta = new Resta();
        $resta->init($config);
    }

    public function testRestaInitMergesInternalAndUserHooks()
    {
        // InternalHooks (rest_api_init) とユーザーフック (test_filter) の両方
        Functions\expect('add_action')
            ->once()
            ->with('rest_api_init', Mockery::type('array'), 10, 1);

        Functions\expect('add_filter')
            ->once()
            ->with('test_filter', Mockery::type('array'), 10, 1);

        $testHook = new class extends HookProvider {
            #[AddFilter('test_filter')]
            public function testMethod($value) {
                return $value;
            }
        };

        $config = [
            'routeDirectory' => [
                [__DIR__ . '/../Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
            'hooks' => [
                get_class($testHook),
            ],
        ];

        Container::getInstance()->bind(get_class($testHook), $testHook);

        $resta = new Resta();
        $resta->init($config);
    }

    public function testRestaInitThrowsExceptionForInvalidHookProvider()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement HookProviderInterface');

        Container::getInstance()->bind(\stdClass::class, new \stdClass());

        $config = [
            'routeDirectory' => [
                [__DIR__ . '/../Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
            'hooks' => [
                \stdClass::class,
            ],
        ];

        $resta = new Resta();
        $resta->init($config);
    }

    public function testRestaInitHandlesUseSwaggerBackwardsCompatibility()
    {
        // InternalHooks (rest_api_init) と SwaggerHooks (init) の両方
        Functions\expect('add_action')
            ->twice()
            ->with(Mockery::anyOf('rest_api_init', 'init'), Mockery::type('array'), 10, 1);

        $config = [
            'routeDirectory' => [
                [__DIR__ . '/../Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
            'use-swagger' => true,
        ];

        $resta = new Resta();
        $resta->init($config);
    }

    public function testRestaInitDoesNotDuplicateSwaggerHooks()
    {
        // SwaggerHooks が hooks 配列にもあり、use-swagger も true の場合
        // 重複せず1回だけ init が呼ばれる
        Functions\expect('add_action')
            ->once()
            ->with('rest_api_init', Mockery::type('array'), 10, 1);

        Functions\expect('add_action')
            ->once() // 重複しない
            ->with('init', Mockery::type('array'), 10, 1);

        $config = [
            'routeDirectory' => [
                [__DIR__ . '/../Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
            'hooks' => [
                \Wp\Resta\Hooks\SwaggerHooks::class,
            ],
            'use-swagger' => true, // これがあっても重複しない
        ];

        $resta = new Resta();
        $resta->init($config);
    }
}
