<?php
namespace Wp\Resta\REST\Attributes;

use Attribute;

/**
 * ログイン済みのリクエストだけを通す宣言。permission_callback の 'is_user_logged_in' に当たる
 *
 * 操作のメソッド（callback() / get() / post() など）に付ける。未認証のリクエストは 401 になり、
 * OpenAPI のその操作に security が出る（AuthorizationHook）。
 *
 * <code>
 * #[Authenticated]
 * public function callback(): array {}
 * </code>
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Authenticated
{
}
