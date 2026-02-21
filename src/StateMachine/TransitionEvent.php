<?php
namespace Wp\Resta\StateMachine;

use Wp\Resta\EventDispatcher\Event;

class TransitionEvent extends Event
{
    public function __construct(
        string $eventName,
        public readonly \UnitEnum $from,
        public readonly \UnitEnum $to,
        public readonly string $action,
        public readonly HasState $subject,
    ) {
        parent::__construct($eventName);
    }

    /**
     * 遷移前のガードイベント名を返す。
     * stopPropagation() を呼ぶと遷移をキャンセルできる。
     * 例: Wp\Resta\Kernel\KernelState::boot.guard
     */
    public static function guardEventName(\UnitEnum $from, string $action): string
    {
        return $from::class . '::' . $action . '.guard';
    }

    /**
     * 遷移後のイベント名を返す。
     * 例: Wp\Resta\Kernel\KernelState::boot
     */
    public static function afterEventName(\UnitEnum $from, string $action): string
    {
        return $from::class . '::' . $action;
    }
}
