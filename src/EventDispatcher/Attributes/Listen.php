<?php
namespace Wp\Resta\EventDispatcher\Attributes;

use Attribute;

/**
 * Dispatcher にリスナーを登録するアトリビュート。
 *
 * Dispatcher::addSubscriber() と組み合わせて使う。
 * NamedEvent のサブクラスを受け取るメソッドには属性不要（型から自動推論）。
 * TransitionEvent や Event('wp.init') など文字列名のイベントにはこの属性が必要。
 *
 * <code>
 * class MyListener
 * {
 *     // NamedEvent サブクラス → 属性不要、型から自動推論
 *     public function onRoute(RouteInvocationEvent $event): void {}
 *
 *     // 文字列名イベント → 属性必須
 *     #[Listen('wp.init')]
 *     public function onInit(Event $event): void {}
 *
 *     // TransitionEvent → 動的な名前なので属性必須
 *     #[Listen('KernelState::Bootstrapped::boot')]
 *     public function onBoot(TransitionEvent $event): void {}
 * }
 * </code>
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Listen
{
    public function __construct(
        public readonly string $eventName,
        public readonly int $priority = 10,
    ) {}
}
