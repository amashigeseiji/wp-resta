<?php
namespace Wp\Resta\StateMachine;

class StateMachine
{
    public function __construct(
        private TransitionRegistry $registry,
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

        $subject->applyState($transition->to);
    }
}
