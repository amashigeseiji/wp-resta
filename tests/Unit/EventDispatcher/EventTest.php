<?php
namespace Test\Resta\Unit\EventDispatcher;

use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Event;

class EventTest extends TestCase
{
    public function testEventNameIsStoredOnConstruction(): void
    {
        $event = new Event('my.event');

        $this->assertSame('my.event', $event->eventName);
    }

    public function testIsPropagationStoppedReturnsFalseByDefault(): void
    {
        $event = new Event('test');

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagationMakesIsPropagationStoppedReturnTrue(): void
    {
        $event = new Event('test');

        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testStopPropagationIsIdempotent(): void
    {
        $event = new Event('test');

        $event->stopPropagation();
        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testDynamicPropertyCanBeSetAndRetrieved(): void
    {
        $event = new Event('test');

        $event->foo = 'bar';

        $this->assertSame('bar', $event->foo);
    }

    public function testDynamicPropertyReturnsNullWhenNotSet(): void
    {
        $event = new Event('test');

        $this->assertNull($event->undefinedKey);
    }

    public function testMultipleDynamicPropertiesAreStoredIndependently(): void
    {
        $event = new Event('test');

        $event->key1 = 'value1';
        $event->key2 = 'value2';

        $this->assertSame('value1', $event->key1);
        $this->assertSame('value2', $event->key2);
    }

    public function testDynamicPropertyOverwrite(): void
    {
        $event = new Event('test');

        $event->key = 'original';
        $event->key = 'overwritten';

        $this->assertSame('overwritten', $event->key);
    }

    public function testDynamicPropertyAcceptsNonStringValues(): void
    {
        $event = new Event('test');

        $event->count = 42;
        $event->flag  = true;
        $event->items = [1, 2, 3];

        $this->assertSame(42, $event->count);
        $this->assertTrue($event->flag);
        $this->assertSame([1, 2, 3], $event->items);
    }
}
