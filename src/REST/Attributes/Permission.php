<?php
namespace Wp\Resta\REST\Attributes;

use Attribute;

/**
 * capability を持つリクエストだけを通す宣言。permission_callback の fn () => current_user_can('edit_posts') に当たる
 *
 * 操作のメソッド（callback() / get() / post() など）に付ける。未認証のリクエストは 401、
 * 認証済みで capability が無ければ 403 になり、OpenAPI のその操作に security が出る（AuthorizationHook）。
 * リクエストの値に依る判定（「この投稿を編集できるか」など）は書けない。callback の中で確かめる。
 *
 * <code>
 * #[Permission('edit_posts')]
 * public function post(string $title): array {}
 * </code>
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Permission
{
    public function __construct(public readonly string $capability)
    {
    }
}
