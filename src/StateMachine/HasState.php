<?php
namespace Wp\Resta\StateMachine;

interface HasState
{
    public function currentState(): \UnitEnum;
    public function applyState(\UnitEnum $state): void;
}
