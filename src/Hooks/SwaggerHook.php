<?php
namespace Wp\Resta\Hooks;

use Wp\Resta\Hooks\Attributes\AddAction;
use Wp\Resta\OpenApi\Doc;

/**
 * Swagger UI と OpenAPI スキーマエンドポイントを WordPress に登録する HookProvider。
 *
 * 管理画面 UI の登録は resta コアではなくアプリケーション関心事であるため、
 * HookProvider として実装し、ユーザーが明示的に hooks 設定に追加する形にしている。
 *
 * <code>
 * (new Resta)->init([
 *     'hooks' => [SwaggerHook::class],
 * ]);
 * </code>
 */
final class SwaggerHook extends HookProvider
{
    public function __construct(private readonly Doc $doc) {}

    #[AddAction('init')]
    public function onInit(): void
    {
        $this->doc->init();
    }
}
