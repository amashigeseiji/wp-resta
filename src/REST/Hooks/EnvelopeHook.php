<?php
namespace Wp\Resta\REST\Hooks;

use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\HookProvider;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\RouteInterface;

/**
 * エンベロープパターンを適用する Hook
 *
 * #[Envelope] Attribute が付いたルートのレスポンスを
 * { data: ..., meta: ... } 構造でラップします。
 */
class EnvelopeHook extends HookProvider
{
    /**
     * レスポンスをエンベロープでラップ
     */
    #[AddFilter('resta_after_invoke', priority: 10, acceptedArgs: 3)]
    public function wrapInEnvelope(
        RestaResponseInterface $response,
        RouteInterface $route,
        RestaRequestInterface $request
    ): RestaResponseInterface {
        // Envelope Attribute をチェック
        if (!$this->shouldUseEnvelope($route)) {
            return $response;
        }

        // 既に EnvelopeResponse の場合はそのまま返す
        if ($response instanceof EnvelopeResponse) {
            return $response;
        }

        // エンベロープでラップ
        $data = $response->getData();
        return new EnvelopeResponse(
            $data,
            [],
            $response->getStatusCode(),
            $response->getHeaders()
        );
    }

    /**
     * エンベロープを使うべきか判定
     */
    private function shouldUseEnvelope(RouteInterface $route): bool
    {
        // Envelope Attribute をチェック
        $reflection = new \ReflectionClass($route);
        $attributes = $reflection->getAttributes(Envelope::class);

        if (count($attributes) > 0) {
            return true;
        }

        // グローバル設定でオーバーライド可能
        return apply_filters('resta_use_envelope_for_route', false, $route);
    }
}
