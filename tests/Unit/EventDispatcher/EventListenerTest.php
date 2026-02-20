<?php
namespace Test\Resta\Unit\EventDispatcher;

use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Event;
use Wp\Resta\EventDispatcher\EventListener;

class EventListenerTest extends TestCase
{
    public function testHandleCallsTheCallbackWithEvent(): void
    {
        $received = null;
        $listener = new EventListener(function (Event $event) use (&$received) {
            $received = $event;
        });
        $event = new Event('test');

        $listener->handle($event);

        $this->assertSame($event, $received);
    }

    public function testHandleCanModifyEventProperties(): void
    {
        $listener = new EventListener(function (Event $event) {
            $event->result = 'handled';
        });
        $event = new Event('test');

        $listener->handle($event);

        $this->assertSame('handled', $event->result);
    }

    public function testSetOptionsStoresOptions(): void
    {
        $listener = new EventListener(function (Event $e) {});

        $listener->setOptions(['priority' => 5, 'once' => true]);

        $this->assertSame(['priority' => 5, 'once' => true], $listener->getOptions());
    }

    public function testGetOptionsReturnsEmptyArrayByDefault(): void
    {
        $listener = new EventListener(function (Event $e) {});

        $this->assertSame([], $listener->getOptions());
    }

    public function testGetOptionReturnsValueForExistingKey(): void
    {
        $listener = new EventListener(function (Event $e) {});
        $listener->setOptions(['priority' => 20]);

        $this->assertSame(20, $listener->getOption('priority'));
    }

    public function testGetOptionReturnsNullForMissingKey(): void
    {
        $listener = new EventListener(function (Event $e) {});

        $this->assertNull($listener->getOption('nonexistent'));
    }

    public function testSetOptionsOverwritesPreviousOptions(): void
    {
        $listener = new EventListener(function (Event $e) {});
        $listener->setOptions(['priority' => 5]);
        $listener->setOptions(['priority' => 99]);

        $this->assertSame(99, $listener->getOption('priority'));
    }
}
