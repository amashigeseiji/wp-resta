<?php
namespace Wp\Resta\REST\Hooks;

use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\RouteInterface;
use Wp\Resta\REST\RouteInvocationEvent;

/**
 * エンベロープパターンを適用するリスナー
 *
 * #[Envelope] Attribute が付いたルートの成功（2xx）レスポンスを
 * { data: ..., meta: ... } 構造でラップする。
 *
 * エラーは包まない。WordPress コアが dispatch 前に返すエラー（認証、rest_no_route、required 欠落）は
 * wp-resta に届かず WP_Error の形のまま返るので、パイプラインのエラーも同じ形にしておくことで、
 * クライアントはどの層で止まっても 1 通りの判定で済む（#85、spec「エラーレスポンス」）。
 *
 * Resta::init() で Dispatcher に登録される。
 */
class EnvelopeHook
{
    public function handle(RouteInvocationEvent $event): void
    {
        if (!$this->shouldUseEnvelope($event->route)) {
            return;
        }

        if ($event->response instanceof EnvelopeResponse) {
            return;
        }

        $status = $event->response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return;
        }

        $event->response = new EnvelopeResponse(
            $event->response->getData(),
            [],
            $event->response->getStatusCode(),
            $event->response->getHeaders(),
        );
    }

    private function shouldUseEnvelope(RouteInterface $route): bool
    {
        $reflection = new \ReflectionClass($route);
        return count($reflection->getAttributes(Envelope::class)) > 0;
    }
}
