<?php
namespace Wp\Resta\REST\Attributes;

use Attribute;

/**
 * エンベロープパターンを適用する Attribute
 *
 * このAttributeをルートクラスに付けると、レスポンスが自動的に
 * { data: ..., meta: ... } 構造でラップされます。
 *
 * @example
 * ```php
 * #[Envelope]
 * class Posts extends AbstractRoute
 * {
 *     public function callback(): array
 *     {
 *         return $posts;
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Envelope
{
}
