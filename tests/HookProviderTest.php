<?php
namespace Test\Resta;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Hooks\HookProvider;
use Wp\Resta\DI\Container;
use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\Attributes\AddAction;

class HookProviderTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = Container::getInstance();
    }

    public function testHookProviderRegister()
    {
        global $wp_filter;
        $wp_filter = [];

        $provider = new class extends HookProvider {
            #[AddFilter('test_hook', priority: 20, acceptedArgs: 2)]
            public function testMethod($arg1, $arg2) {
                return $arg1 . $arg2;
            }
        };

        $provider->register();

        $this->assertTrue(has_filter('test_hook'));
        $this->assertCount(1, $wp_filter['test_hook']);
        $this->assertEquals(20, $wp_filter['test_hook'][0]['priority']);
        $this->assertEquals(2, $wp_filter['test_hook'][0]['accepted_args']);
    }

    public function testMultipleHookProviders()
    {
        global $wp_filter;
        $wp_filter = [];

        $provider1 = new class extends HookProvider {
            #[AddFilter('hook1')]
            public function method1($value) { return $value; }
        };

        $provider2 = new class extends HookProvider {
            #[AddAction('action1')]
            public function method2() {}
        };

        $this->container->bind(get_class($provider1), $provider1);
        $this->container->bind(get_class($provider2), $provider2);

        // Resta::init() と同じロジックでテスト
        $providers = [get_class($provider1), get_class($provider2)];
        foreach ($providers as $providerClass) {
            $provider = $this->container->get($providerClass);
            $provider->register();
        }

        $this->assertTrue(has_filter('hook1'));
        $this->assertTrue(has_filter('action1'));
    }

    public function testIsRepeatableAttribute()
    {
        global $wp_filter;
        $wp_filter = [];

        $provider = new class extends HookProvider {
            #[AddFilter('multi_hook', priority: 10)]
            #[AddFilter('multi_hook', priority: 20)]
            public function multiMethod($value) { return $value; }
        };

        $provider->register();

        $this->assertCount(2, $wp_filter['multi_hook']);
    }
}
