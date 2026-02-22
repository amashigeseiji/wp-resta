<?php
namespace Wp\Resta\StateMachine;

use Wp\Resta\EventDispatcher\Event;

class TransitionEvent extends Event
{
    /**
     * リスナーが遷移先パスを変更するためのキー。
     * デフォルト 'to' は Transition::resolve() のデフォルトに対応する。
     * 変更する場合は Transition 属性で定義されたキーのみ有効。
     * 例: $event->path = 'stop'
     */
    public string $path = 'to';

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
     * $event->path を変更すると遷移先を切り替えられる。
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
