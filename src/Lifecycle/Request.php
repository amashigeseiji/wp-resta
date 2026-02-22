<?php
namespace Wp\Resta\Lifecycle;

use Wp\Resta\StateMachine\HasState;

class Request implements HasState
{
    private RequestState $state;
    public ?RequestContext $ctx = null;

    public function __construct()
    {
        $this->state = RequestState::Received;
    }

    public function currentState(): \UnitEnum
    {
        return $this->state;
    }

    public function applyState(\UnitEnum $state): void
    {
        assert($state instanceof RequestState);
        $this->state = $state;
    }
}
