<?php
namespace Wp\Resta\EventDispatcher;

class EventListener implements EventListenerInterface
{
    /** @var array<string, mixed> */
    protected array $options = [];
    /** @var callable */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function handle(Event $event): void
    {
        call_user_func($this->callback, $event);
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $option): mixed
    {
        return $this->options[$option] ?? null;
    }
}
