<?php
namespace Wp\Resta\StateMachine;

/**
 * 現在の状態がとりうる次のアクションを開示できる状態オブジェクト。
 *
 * HasState が汎用的な状態機械の実装であるのに対し、
 * AffordanceAware は REST の HATEOAS に触発された拡張で、
 * 「リソース自身が次の行為可能性を返す」という考え方を体現する。
 *
 * 実装例:
 *   $affordances = $subject->affordances();
 *   // => [Affordance('convert', RequestState::Prepared), ...]
 *
 *   $next = $subject->doAction($sm, 'convert');
 *   // 遷移を実行し、次のアフォーダンス一覧を返す
 */
interface AffordanceAware extends HasState
{
    /** @return Affordance[] */
    public function affordances(): array;

    /** @return Affordance[] */
    public function doAction(TransitionApplier $sm, string $action): array;
}
