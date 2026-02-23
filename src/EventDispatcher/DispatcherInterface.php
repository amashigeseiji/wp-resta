<?php
namespace Wp\Resta\EventDispatcher;

interface DispatcherInterface
{
    /** @param array<string, mixed> $callbackOptions */
    public function addListener(string $eventName, callable|EventListenerInterface $callback, array $callbackOptions = []): void;
    public function dispatch(Event $event): void;
}
