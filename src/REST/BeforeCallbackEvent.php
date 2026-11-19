<?php
namespace Wp\Resta\REST;

use Wp\Resta\EventDispatcher\NamedEvent;
use Wp\Resta\REST\Http\RestaRequestInterface;

/**
 * 選ばれた操作の callback を実行する直前に発火する
 *
 * 購読者が HttpException を投げると、callback は呼ばれず、その例外がレスポンスになる。
 * 組み込みの AuthorizationHook が、認可のアトリビュートを解釈するために購読する。
 *
 * @internal 利用者に約束した拡張点ではない（spec「イベント」に無い）。発火する位置も形も変わりうる
 */
class BeforeCallbackEvent extends NamedEvent
{
    public function __construct(
        public readonly RestaRequestInterface $request,
        public readonly RouteInterface $route,
        public readonly Operation $operation,
    ) {
        parent::__construct();
    }
}
