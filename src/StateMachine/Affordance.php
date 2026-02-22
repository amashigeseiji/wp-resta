<?php
namespace Wp\Resta\StateMachine;

/**
 * 現在の状態から実行可能なアクションを表す値オブジェクト。
 *
 * REST における HATEOAS のリンク（rel + href）に対応する概念で、
 * 「今何ができるか」を状態オブジェクト自身が開示するために使う。
 *
 * @see AffordanceAware
 */
final class Affordance
{
    public function __construct(
        public readonly string $action,
        public readonly \UnitEnum $to,
    ) {}
}
