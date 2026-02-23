<?php
namespace Test\Resta\Unit\Kernel;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\Kernel\Kernel;
use Wp\Resta\Kernel\WpKernelAdapter;
use Wp\Resta\StateMachine\StateMachine;
use Wp\Resta\StateMachine\TransitionRegistry;

/**
 * WpKernelAdapter のユニットテスト
 *
 * WordPress フック（add_action/add_filter）の登録は install() が担うため
 * WP 依存のテストは Integration に委ね、ここでは WP 非依存のロジックをテストする。
 */
class WpKernelAdapterTest extends TestCase
{
    private WpKernelAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new WpKernelAdapter(
            new Kernel(),
            new StateMachine(new TransitionRegistry()),
            new Dispatcher(),
            new Config([]),
        );
    }

    // --- prioritizeUrlParameters ---

    public function testSwapsGetAndUrlWhenGetIsFirst(): void
    {
        $order  = ['GET', 'URL', 'POST', 'COOKIE'];
        $result = $this->adapter->prioritizeUrlParameters($order);

        $this->assertSame(['URL', 'GET', 'POST', 'COOKIE'], $result);
    }

    public function testDoesNotChangeOrderWhenUrlIsAlreadyFirst(): void
    {
        $order  = ['URL', 'GET', 'POST', 'COOKIE'];
        $result = $this->adapter->prioritizeUrlParameters($order);

        $this->assertSame(['URL', 'GET', 'POST', 'COOKIE'], $result);
    }

    public function testDoesNotChangeOrderWhenPatternDoesNotMatch(): void
    {
        $order  = ['POST', 'GET', 'URL', 'COOKIE'];
        $result = $this->adapter->prioritizeUrlParameters($order);

        $this->assertSame(['POST', 'GET', 'URL', 'COOKIE'], $result);
    }

    public function testPreservesOtherElementsUnchanged(): void
    {
        $order  = ['GET', 'URL', 'COOKIE', 'POST', 'FILE'];
        $result = $this->adapter->prioritizeUrlParameters($order);

        $this->assertSame(['URL', 'GET', 'COOKIE', 'POST', 'FILE'], $result);
    }
}
