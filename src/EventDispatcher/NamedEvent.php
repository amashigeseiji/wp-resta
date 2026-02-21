<?php
namespace Wp\Resta\EventDispatcher;

/**
 * クラス名をイベント識別子とするイベント基底クラス。
 *
 * このクラスを継承したイベントは、クラス名（FQCN）がイベント名となる。
 * Dispatcher::addSubscriber() でメソッドのパラメータ型からイベントを自動推論できる。
 *
 * ## ルール
 *
 * 継承クラスがコンストラクタを定義する場合、必ず parent::__construct() を呼ぶこと:
 *
 * <code>
 * class RouteInvocationEvent extends NamedEvent
 * {
 *     public function __construct(
 *         public readonly RestaRequestInterface $request,
 *     ) {
 *         parent::__construct(); // 必須
 *     }
 * }
 * </code>
 *
 * コンストラクタが不要な場合は省略してよい（自動的に parent が呼ばれる）。
 *
 * ## 文字列名イベントとの使い分け
 *
 * - NamedEvent: 型安全・addSubscriber() で自動推論
 * - Event('wp.init'): 同一クラスで複数の名前が必要なとき（TransitionEvent など）
 */
abstract class NamedEvent extends Event
{
    public function __construct()
    {
        parent::__construct(static::class);
    }
}
