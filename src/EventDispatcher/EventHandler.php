<?php

namespace Wp\Resta\EventDispatcher;

class EventHandler
{
    const DEFAULT_PRIORITY = 10;

    /** @var array<int, EventListenerInterface[]> */
    private array $listeners = [];

    public function __construct(readonly string $eventName) {
    }

    public function add(EventListenerInterface $handler): void
    {
        $priority = (int) ($handler->getOption('priority') ?? self::DEFAULT_PRIORITY);
        $this->listeners[$priority][] = $handler;
    }

    public function handle(Event $event): void
    {
        if (empty($this->listeners)) {
            return;
        }
        krsort($this->listeners); // priority 降順: 値が大きいほど先に実行
        foreach ($this->listeners as $handlers) {
            foreach ($handlers as $handler) {
                if ($event->isPropagationStopped()) {
                    return;
                }
                $handler->handle($event);
            }
        }
    }
}
