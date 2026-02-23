<?php
namespace Wp\Resta\StateMachine;

#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
final class Transition
{
    /**
     * @param \UnitEnum|array<string, \UnitEnum> $to
     *   単一の遷移先、または名前付き遷移先のマップ。
     *   マップの場合は 'to' キーがデフォルトの遷移先になる。
     *   例: ['to' => State::Authenticated, 'stop' => State::AuthenticationFailed]
     */
    public function __construct(
        public readonly \UnitEnum|array $to,
        public readonly string $on,
    ) {}

    /**
     * パスキーに対応する遷移先を返す。
     * $to が単一の場合は 'to' のみ有効。
     * 定義されていないキーは例外。
     */
    public function resolve(string $key = 'to'): \UnitEnum
    {
        if ($this->to instanceof \UnitEnum) {
            if ($key !== 'to') {
                throw new \InvalidArgumentException(
                    "Transition path '{$key}' is not defined for action '{$this->on}'."
                );
            }
            return $this->to;
        }

        return $this->to[$key]
            ?? throw new \InvalidArgumentException(
                "Transition path '{$key}' is not defined for action '{$this->on}'."
            );
    }
}
