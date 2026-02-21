<?php
namespace Wp\Resta\REST;

use Wp\Resta\EventDispatcher\NamedEvent;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;

/**
 * ルート実行イベント
 *
 * route->invoke() の結果を response に持ち、
 * リスナーが response を書き換えることでレスポンスを変換できる。
 *
 * NamedEvent を継承しているため、Dispatcher::addSubscriber() で
 * パラメータ型から自動推論される（#[Listen] アトリビュート不要）。
 */
class RouteInvocationEvent extends NamedEvent
{
    public function __construct(
        public readonly RestaRequestInterface $request,
        public readonly RouteInterface $route,
        public RestaResponseInterface $response,
    ) {
        parent::__construct();
    }
}
