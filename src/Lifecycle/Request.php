<?php
namespace Wp\Resta\Lifecycle;

use Wp\Resta\DI\Container;
use Wp\Resta\StateMachine\Affordance;
use Wp\Resta\StateMachine\AffordanceAware;
use Wp\Resta\StateMachine\TransitionApplier;
use Wp\Resta\StateMachine\TransitionRegistry;

class Request implements AffordanceAware
{
    private RequestState $state;
    private TransitionRegistry $registry;
    public ?RequestContext $ctx = null;

    public function __construct()
    {
        $this->state = RequestState::Received;
        $this->registry = Container::getInstance()->get(TransitionRegistry::class);
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

    /**
     * @return Affordance[]
     */
    public function affordances(): array
    {
        return $this->registry->affordancesFrom($this->state);
    }

    /**
     * @return Affordance[]
     */
    public function doAction(TransitionApplier $sm, string $action): array
    {
        $sm->apply($this, $action);
        return $this->affordances();
    }
}
