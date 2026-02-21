<?php
namespace Wp\Resta\EventDispatcher;

class Event
{
    private bool $isStopped = false;

    /** @var array<string, mixed> */
    protected array $properties = [];

    public function __construct(readonly string $eventName) {
    }

    public function stopPropagation(): void
    {
        $this->isStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->isStopped;
    }

    public function __get(string $key): mixed
    {
        return $this->properties[$key] ?? null;
    }

    public function __set(string $key, mixed $value)
    {
        $this->properties[$key] = $value;
    }
}
