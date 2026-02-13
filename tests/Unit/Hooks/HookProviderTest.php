<?php
namespace Test\Resta\Unit\Hooks;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Hooks\HookProvider;
use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\Attributes\AddAction;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class HookProviderTest extends TestCase
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
        parent::tearDown();
    }

    public function testRegisterCallsAddFilterForFilterAttribute()
    {
        Functions\expect('add_filter')
            ->once()
            ->with('test_filter', Mockery::type('array'), 10, 1);

        $provider = new class extends HookProvider {
            #[AddFilter('test_filter')]
            public function testMethod($value) {
                return $value;
            }
        };

        $provider->register();
    }

    public function testRegisterCallsAddActionForActionAttribute()
    {
        Functions\expect('add_action')
            ->once()
            ->with('test_action', Mockery::type('array'), 10, 1);

        $provider = new class extends HookProvider {
            #[AddAction('test_action')]
            public function testMethod(): void {}
        };

        $provider->register();
    }

    public function testRegisterHandlesMultipleAttributes()
    {
        Functions\expect('add_filter')
            ->once()
            ->with('filter1', Mockery::type('array'), 10, 1);

        Functions\expect('add_filter')
            ->once()
            ->with('filter2', Mockery::type('array'), 20, 2);

        $provider = new class extends HookProvider {
            #[AddFilter('filter1')]
            #[AddFilter('filter2', priority: 20, acceptedArgs: 2)]
            public function testMethod($value) {
                return $value;
            }
        };

        $provider->register();
    }

    public function testRegisterRespectsCustomPriorityAndAcceptedArgs()
    {
        Functions\expect('add_filter')
            ->once()
            ->with('custom_filter', Mockery::type('array'), 25, 3);

        $provider = new class extends HookProvider {
            #[AddFilter('custom_filter', priority: 25, acceptedArgs: 3)]
            public function customMethod($a, $b, $c) {
                return $a + $b + $c;
            }
        };

        $provider->register();
    }

    public function testRegisterIgnoresMethodsWithoutAttributes()
    {
        Functions\expect('add_filter')->never();
        Functions\expect('add_action')->never();

        $provider = new class extends HookProvider {
            public function methodWithoutAttribute() {
                return 'test';
            }
        };

        $provider->register();
    }

    public function testRegisterHandlesBothFiltersAndActions()
    {
        Functions\expect('add_filter')
            ->once()
            ->with('test_filter', Mockery::type('array'), 10, 1);

        Functions\expect('add_action')
            ->once()
            ->with('test_action', Mockery::type('array'), 10, 1);

        $provider = new class extends HookProvider {
            #[AddFilter('test_filter')]
            public function filterMethod($value) {
                return $value;
            }

            #[AddAction('test_action')]
            public function actionMethod(): void {}
        };

        $provider->register();
    }

    public function testRegisterSetsCorrectCallback()
    {
        Functions\expect('add_filter')
            ->once()
            ->with(
                'test_filter',
                Mockery::on(function($callback) {
                    return is_array($callback)
                        && $callback[0] instanceof HookProvider
                        && $callback[1] === 'testMethod';
                }),
                10,
                1
            );

        $provider = new class extends HookProvider {
            #[AddFilter('test_filter')]
            public function testMethod($value) {
                return $value;
            }
        };

        $provider->register();
    }
}
