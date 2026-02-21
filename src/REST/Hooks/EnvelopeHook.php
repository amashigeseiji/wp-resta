<?php
namespace Wp\Resta\REST\Hooks;

use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\RouteInterface;
use Wp\Resta\REST\RouteInvocationEvent;

/**
 * エンベロープパターンを適用するリスナー
 *
 * #[Envelope] Attribute が付いたルートのレスポンスを
 * { data: ..., meta: ... } 構造でラップする。
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
