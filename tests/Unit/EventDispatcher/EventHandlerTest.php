<?php
namespace Test\Resta\Unit\EventDispatcher;

use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Event;
use Wp\Resta\EventDispatcher\EventHandler;
use Wp\Resta\EventDispatcher\EventListener;

class EventHandlerTest extends TestCase
{
    public function testEventNameIsStoredOnConstruction(): void
    {
        $handler = new EventHandler('my.event');

        $this->assertSame('my.event', $handler->eventName);
    }

    public function testHandleDoesNothingWhenNoListenersRegistered(): void
    {
        $handler = new EventHandler('test');
        $event   = new Event('test');

        // 例外が発生しないこと・伝播が止まっていないことを確認
        $handler->handle($event);

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAddedListenerIsCalledOnHandle(): void
    {
        $handler = new EventHandler('test');
        $called  = false;

        $handler->add(new EventListener(function () use (&$called) {
            $called = true;
        }));

        $handler->handle(new Event('test'));

        $this->assertTrue($called);
    }

    public function testListenerReceivesTheCorrectEvent(): void
    {
        $handler  = new EventHandler('test');
        $received = null;

        $handler->add(new EventListener(function (Event $e) use (&$received) {
            $received = $e;
        }));

        $event = new Event('test');
        $handler->handle($event);

        $this->assertSame($event, $received);
    }

    public function testAllListenersAreCalledWhenPropagationIsNotStopped(): void
    {
        $handler = new EventHandler('test');
        $log     = [];

        $handler->add(new EventListener(function () use (&$log) { $log[] = 'A'; }));
        $handler->add(new EventListener(function () use (&$log) { $log[] = 'B'; }));
        $handler->add(new EventListener(function () use (&$log) { $log[] = 'C'; }));

        $handler->handle(new Event('test'));

        $this->assertSame(['A', 'B', 'C'], $log);
    }

    public function testHigherPriorityListenerRunsFirst(): void
    {
        $handler = new EventHandler('test');
        $log     = [];

        $low = new EventListener(function () use (&$log) { $log[] = 'low'; });
        $low->setOptions(['priority' => 1]);

        $high = new EventListener(function () use (&$log) { $log[] = 'high'; });
        $high->setOptions(['priority' => 100]);

        // 意図的に low → high の順で登録
        $handler->add($low);
        $handler->add($high);

        $handler->handle(new Event('test'));

        $this->assertSame(['high', 'low'], $log);
    }

    public function testDefaultPriorityIsUsedWhenNotSpecified(): void
    {
        $handler = new EventHandler('test');
        $log     = [];

        // priority 未指定 → DEFAULT_PRIORITY (10)
        $default = new EventListener(function () use (&$log) { $log[] = 'default'; });

        $higher = new EventListener(function () use (&$log) { $log[] = 'higher'; });
        $higher->setOptions(['priority' => 20]);

        $lower = new EventListener(function () use (&$log) { $log[] = 'lower'; });
        $lower->setOptions(['priority' => 5]);

        $handler->add($default);
        $handler->add($lower);
        $handler->add($higher);

        $handler->handle(new Event('test'));

        $this->assertSame(['higher', 'default', 'lower'], $log);
    }

    public function testSamePriorityListenersRunInRegistrationOrder(): void
    {
        $handler = new EventHandler('test');
        $log     = [];

        $first  = new EventListener(function () use (&$log) { $log[] = 'first'; });
        $second = new EventListener(function () use (&$log) { $log[] = 'second'; });
        $third  = new EventListener(function () use (&$log) { $log[] = 'third'; });

        // 全て同じ priority
        $first->setOptions(['priority' => 10]);
        $second->setOptions(['priority' => 10]);
        $third->setOptions(['priority' => 10]);

        $handler->add($first);
        $handler->add($second);
        $handler->add($third);

        $handler->handle(new Event('test'));

        $this->assertSame(['first', 'second', 'third'], $log);
    }

    public function testStopPropagationPreventsSubsequentListeners(): void
    {
        $handler = new EventHandler('test');
        $log     = [];

        $stopper = new EventListener(function (Event $event) use (&$log) {
            $log[] = 'stopper';
            $event->stopPropagation();
        });
        $stopper->setOptions(['priority' => 20]);

        $shouldNotRun = new EventListener(function () use (&$log) {
            $log[] = 'should_not_run';
        });
        $shouldNotRun->setOptions(['priority' => 10]);

        $handler->add($stopper);
        $handler->add($shouldNotRun);

        $handler->handle(new Event('test'));

        $this->assertSame(['stopper'], $log);
    }

    public function testStopPropagationAlsoBlocksListenersWithinSamePriorityBucket(): void
    {
        $handler = new EventHandler('test');
        $log     = [];

        $stopper = new EventListener(function (Event $event) use (&$log) {
            $log[] = 'stopper';
            $event->stopPropagation();
        });
        $shouldNotRun = new EventListener(function () use (&$log) {
            $log[] = 'should_not_run';
        });

        // 同じ priority に登録
        $stopper->setOptions(['priority' => 10]);
        $shouldNotRun->setOptions(['priority' => 10]);

        $handler->add($stopper);
        $handler->add($shouldNotRun);

        $handler->handle(new Event('test'));

        $this->assertSame(['stopper'], $log);
    }
}
