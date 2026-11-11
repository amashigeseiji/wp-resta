<?php
namespace Wp\Resta\REST\Hooks;

use JsonException;
use ReflectionClass;
use Wp\Resta\Auth\Principal;
use Wp\Resta\EventDispatcher\Attributes\Listen;
use Wp\Resta\REST\Attributes\Cache;
use Wp\Resta\REST\Http\Headers;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use Wp\Resta\REST\RouteInterface;
use Wp\Resta\REST\RouteInvocationEvent;

/**
 * #[Cache] の付いたルートのレスポンスに Cache-Control と ETag を付け、If-None-Match が一致すれば 304 にする
 *
 * ETag は最終的なボディから計算したいので、EnvelopeHook や既定の priority の購読者より後に実行する。
 * 認証済みのリクエストには何もしない。WordPress はログイン中の REST リクエストに no-cache ヘッダを送るので、
 * それをそのまま活かす。
 */
final class CacheHook
{
    /** 既定の priority（10）の購読者がレスポンスを変えた後に走るようにする */
    public const PRIORITY = -100;

    public function __construct(private readonly Principal $principal)
    {
    }

    #[Listen(RouteInvocationEvent::class, priority: self::PRIORITY)]
    public function handle(RouteInvocationEvent $event): void
    {
        $cache = self::cacheOf($event->route);
        if ($cache === null) {
            return;
        }
        if (!in_array($event->request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }
        if ($event->response->getStatusCode() !== 200) {
            return;
        }
        if ($this->principal->isAuthenticated()) {
            return;
        }

        $etag = self::etagOf($event->response->getData());
        if ($etag === null) {
            return;
        }

        $headers = ['Cache-Control' => $cache->toCacheControl(), 'ETag' => $etag];
        if (self::matches($event->request->getHeader('If-None-Match'), $etag)) {
            // 304 にもキャッシュ関連のヘッダは付ける（RFC 9110 15.4.5）。ボディは無し
            $event->response = new SimpleRestaResponse(null, 304, Headers::merge($event->response->getHeaders(), $headers));
            return;
        }
        $event->response = $event->response->withHeaders($headers);
    }

    private static function cacheOf(RouteInterface $route): ?Cache
    {
        $attributes = (new ReflectionClass($route))->getAttributes(Cache::class);
        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * ボディの JSON のハッシュ。JSON にできないボディは WordPress 側でもエラーになるので、ここでは付けない
     */
    private static function etagOf(mixed $data): ?string
    {
        try {
            return '"' . hash('xxh128', json_encode($data, JSON_THROW_ON_ERROR)) . '"';
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * If-None-Match の値（複数可、W/ 付き可、*）に ETag が含まれるか。弱い比較
     */
    private static function matches(?string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === null) {
            return false;
        }
        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*') {
                return true;
            }
            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }
            if ($candidate === $etag) {
                return true;
            }
        }
        return false;
    }
}
