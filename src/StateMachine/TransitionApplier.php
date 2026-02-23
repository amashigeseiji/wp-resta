<?php
namespace Wp\Resta\StateMachine;

/**
 * 状態遷移を適用する責務を持つインターフェース。
 *
 * AffordanceAware::doAction() の引数型として使うことで、
 * 状態オブジェクトが StateMachine の具象クラスに依存しないようにする。
 */
interface TransitionApplier
{
    public function apply(HasState $subject, string $action): void;
}
