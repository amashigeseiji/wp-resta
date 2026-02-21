<?php
namespace Wp\Resta\Kernel;

use Wp\Resta\StateMachine\HasState;

class Kernel implements HasState
{
    private KernelState $state;

    public function __construct(KernelState $initial = KernelState::Booting)
    {
        $this->state = $initial;
    }

    public function currentState(): \UnitEnum
    {
        return $this->state;
    }

    public function applyState(\UnitEnum $state): void
    {
        assert($state instanceof KernelState);
        $this->state = $state;
    }
}
