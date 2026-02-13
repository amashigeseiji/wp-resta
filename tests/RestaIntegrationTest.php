<?php
namespace Test\Resta;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Resta;
use Wp\Resta\DI\Container;
use Wp\Resta\Hooks\HookProvider;
use Wp\Resta\Hooks\Attributes\AddFilter;
use ReflectionClass;

class RestaIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Reflection で Container の $instance をリセット
        $reflection = new ReflectionClass(Container::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        parent::tearDown();
    }

    public function testRestaInitRegistersInternalHooks()
    {
        global $wp_actions;
        $wp_actions = [];

        $config = [
            'routeDirectory' => [
                [__DIR__ . '/Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
        ];

        $resta = new Resta();
        $resta->init($config);

        // InternalHooks が rest_api_init に登録されているか
        $this->assertTrue(isset($wp_actions['rest_api_init']));
        $this->assertCount(1, $wp_actions['rest_api_init']);
    }

    public function testRestaInitRegistersUserHooks()
    {
        global $wp_filter;
        $wp_filter = [];

        // テスト用 HookProvider
        $testHook = new class extends HookProvider {
            #[AddFilter('custom_hook', priority: 15, acceptedArgs: 2)]
            public function customFilter($value, $context) {
                return $value . '_filtered';
            }
        };

        $config = [
            'routeDirectory' => [
                [__DIR__ . '/fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
            'hooks' => [
                get_class($testHook),
            ],
        ];

        // Container にテスト用のフックを登録
        Container::getInstance()->bind(get_class($testHook), $testHook);

        $resta = new Resta();
        $resta->init($config);

        // ユーザー定義フックが登録されているか
        $this->assertTrue(isset($wp_filter['custom_hook']));
        $this->assertEquals(15, $wp_filter['custom_hook'][0]['priority']);
        $this->assertEquals(2, $wp_filter['custom_hook'][0]['accepted_args']);
    }

    public function testRestaInitMergesInternalAndUserHooks()
    {
        global $wp_actions, $wp_filter;
        $wp_actions = [];
        $wp_filter = [];

        $testHook = new class extends HookProvider {
            #[AddFilter('test_filter')]
            public function testMethod($value) {
                return $value;
            }
        };

        $config = [
            'routeDirectory' => [
                [__DIR__ . '/fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
            'hooks' => [
                get_class($testHook),
            ],
        ];

        Container::getInstance()->bind(get_class($testHook), $testHook);

        $resta = new Resta();
        $resta->init($config);

        // InternalHooks（必須）が登録されている
        $this->assertTrue(isset($wp_actions['rest_api_init']));

        // ユーザーフックも登録されている
        $this->assertTrue(isset($wp_filter['test_filter']));
    }

    public function testRestaInitThrowsExceptionForInvalidHookProvider()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement HookProviderInterface');

        // HookProviderInterface を実装していないクラス
        $config = [
            'routeDirectory' => [
                [__DIR__ . '/fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
            'hooks' => [
                \stdClass::class,  // 無効なクラス
            ],
        ];

        Container::getInstance()->bind(\stdClass::class, new \stdClass());

        $resta = new Resta();
        $resta->init($config);
    }
}
