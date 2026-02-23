<?php
namespace Test\Resta\Unit\EventDispatcher;

use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\Event;
use Wp\Resta\EventDispatcher\EventListener;

class DispatcherTest extends TestCase
{
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new Dispatcher();
    }

    // --- 基本動作 ---

    public function testAddListenerWithCallableAndDispatchCallsIt(): void
    {
        $called = false;
        $this->dispatcher->addListener('test.event', function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch(new Event('test.event'));

        $this->assertTrue($called);
    }

    public function testDispatchPassesCorrectEventToListener(): void
    {
        $received = null;
        $this->dispatcher->addListener('test.event', function (Event $e) use (&$received) {
            $received = $e;
        });

        $event = new Event('test.event');
        $this->dispatcher->dispatch($event);

        $this->assertSame($event, $received);
    }

    public function testAddListenerWithEventListenerInstance(): void
    {
        $called   = false;
        $listener = new EventListener(function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->addListener('test.event', $listener);
        $this->dispatcher->dispatch(new Event('test.event'));

        $this->assertTrue($called);
    }

    // --- 複数リスナー ---

    public function testMultipleListenersOnSameEventAreAllCalled(): void
    {
        $log = [];
        $this->dispatcher->addListener('test.event', function () use (&$log) { $log[] = 'first'; });
        $this->dispatcher->addListener('test.event', function () use (&$log) { $log[] = 'second'; });

        $this->dispatcher->dispatch(new Event('test.event'));

        $this->assertContains('first', $log);
        $this->assertContains('second', $log);
    }

    public function testDispatchOnlyCallsListenersMatchingTheEvent(): void
    {
        $calledA = false;
        $calledB = false;

        $this->dispatcher->addListener('event.a', function () use (&$calledA) { $calledA = true; });
        $this->dispatcher->addListener('event.b', function () use (&$calledB) { $calledB = true; });

        $this->dispatcher->dispatch(new Event('event.a'));

        $this->assertTrue($calledA);
        $this->assertFalse($calledB);
    }

    // --- callbackOptions ---

    public function testCallbackOptionsAreAppliedToListener(): void
    {
        $listener = new EventListener(function () {});

        $this->dispatcher->addListener('test.event', $listener, ['priority' => 42]);

        $this->assertSame(42, $listener->getOption('priority'));
    }

    // --- priority による実行順 ---

    public function testListenersAreCalledInDescendingPriorityOrder(): void
    {
        $log = [];

        $this->dispatcher->addListener(
            'test.event',
            function () use (&$log) { $log[] = 'low'; },
            ['priority' => 1]
        );
        $this->dispatcher->addListener(
            'test.event',
            function () use (&$log) { $log[] = 'high'; },
            ['priority' => 100]
        );

        $this->dispatcher->dispatch(new Event('test.event'));

        $this->assertSame(['high', 'low'], $log);
    }

    // --- stopPropagation ---

    public function testStopPropagationPreventsLaterListeners(): void
    {
        $log = [];

        $this->dispatcher->addListener(
            'test.event',
            function (Event $event) use (&$log) {
                $log[] = 'first';
                $event->stopPropagation();
            },
            ['priority' => 20]
        );
        $this->dispatcher->addListener(
            'test.event',
            function () use (&$log) { $log[] = 'second'; },
            ['priority' => 10]
        );

        $this->dispatcher->dispatch(new Event('test.event'));

        $this->assertSame(['first'], $log);
    }

    // --- 未登録イベントの dispatch ---

    public function testDispatchUnregisteredEventDoesNothing(): void
    {
        // リスナーが登録されていないイベントを dispatch しても何も起きない（例外なし）
        $this->dispatcher->dispatch(new Event('not.registered'));
        $this->addToAssertionCount(1);
    }
}
