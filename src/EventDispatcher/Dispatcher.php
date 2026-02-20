<?php
namespace Wp\Resta\EventDispatcher;

class Dispatcher implements DispatcherInterface
{
    /** @var array<string, EventHandler> */
    private array $listeners = [];

    public function addListener(string $eventName, callable|EventListenerInterface $callback, array $callbackOptions = []): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = new EventHandler($eventName);
        }
        if ($callback instanceof EventListenerInterface) {
            $listener = $callback;
        } else {
            $listener = new EventListener($callback);
        }
        $listener->setOptions($callbackOptions);
        $this->listeners[$eventName]->add($listener);
    }

    public function dispatch(Event $event): void
    {
        if (!isset($this->listeners[$event->eventName])) {
            throw new \InvalidArgumentException("No listeners registered for event: {$event->eventName}");
        }
        $this->listeners[$event->eventName]->handle($event);
    }
}
