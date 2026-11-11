<?php
namespace Wp\Resta\REST\Attributes;

use Attribute;

/**
 * 読み取り向けのレスポンスにキャッシュヘッダを付ける宣言
 *
 * ルートクラスに付けると、未認証の GET の 200 レスポンスに Cache-Control と ETag が付き、
 * If-None-Match が一致すれば空ボディの 304 で応える（CacheHook）。
 *
 * <code>
 * #[Cache(maxAge: 300)]
 * class Posts extends AbstractRoute {}
 * </code>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Cache
{
    /**
     * @param int $maxAge max-age（秒）
     * @param bool $public true なら public、false なら private
     * @param int|null $sMaxAge 共有キャッシュ向けの s-maxage（秒）
     * @param int|null $staleWhileRevalidate stale-while-revalidate（秒）
     */
    public function __construct(
        public readonly int $maxAge,
        public readonly bool $public = true,
        public readonly ?int $sMaxAge = null,
        public readonly ?int $staleWhileRevalidate = null,
    ) {
    }

    /**
     * Cache-Control ヘッダの値
     */
    public function toCacheControl(): string
    {
        $directives = [$this->public ? 'public' : 'private', 'max-age=' . $this->maxAge];
        if ($this->sMaxAge !== null) {
            $directives[] = 's-maxage=' . $this->sMaxAge;
        }
        if ($this->staleWhileRevalidate !== null) {
            $directives[] = 'stale-while-revalidate=' . $this->staleWhileRevalidate;
        }
        return implode(', ', $directives);
    }
}
