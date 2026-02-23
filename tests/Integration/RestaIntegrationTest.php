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
        $instance->setAccessible(true);
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
            ->with('rest_request_parameter_order', Mockery::type('array'), 10, 1);

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
            ->with('rest_request_parameter_order', Mockery::type('array'), 10, 1);

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
            ->with('rest_request_parameter_order', Mockery::type('array'), 10, 1);

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
}
