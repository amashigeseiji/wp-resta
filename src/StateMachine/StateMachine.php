<?php
namespace Wp\Resta\StateMachine;

use Wp\Resta\EventDispatcher\DispatcherInterface;

class StateMachine implements TransitionApplier
{
    public function __construct(
        private TransitionRegistry $registry,
        private ?DispatcherInterface $dispatcher = null,
    ) {}

    public function apply(HasState $subject, string $action): void
    {
        $from = $subject->currentState();
        $transition = $this->registry->resolve($from, $action);

        if ($transition === null) {
            throw new \InvalidArgumentException(
                "No transition defined from state '{$from->name}' on action '{$action}'."
            );
        }

        $path = 'to';

        if ($this->dispatcher !== null) {
            $guardEvent = new TransitionEvent(
                eventName: TransitionEvent::guardEventName($from, $action),
                from: $from,
                to: $transition->resolve('to'),
                action: $action,
                subject: $subject,
            );
            $this->dispatcher->dispatch($guardEvent);
            $path = $guardEvent->path;
        }

        $actual = $transition->resolve($path);
        $subject->applyState($actual);

        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch(new TransitionEvent(
                eventName: TransitionEvent::afterEventName($from, $action),
                from: $from,
                to: $actual,
                action: $action,
                subject: $subject,
            ));
        }
    }
}
