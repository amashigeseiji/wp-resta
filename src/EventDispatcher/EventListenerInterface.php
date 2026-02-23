<?php
namespace Wp\Resta\EventDispatcher;

interface EventListenerInterface
{
    public function handle(Event $event): void;
    /** @param array<string, mixed> $options */
    public function setOptions(array $options): void;
    /** @return array<string, mixed> */
    public function getOptions(): array;
    public function getOption(string $option): mixed;
}
