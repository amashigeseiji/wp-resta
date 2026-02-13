<?php
namespace Wp\Resta\Hooks;

use Wp\Resta\DI\Container;
use Wp\Resta\OpenApi\Doc;
use Wp\Resta\OpenApi\ResponseSchema;
use Wp\Resta\Hooks\Attributes\AddAction;

/**
 * Swagger UI 機能のフック
 * 開発環境用。本番環境では設定ファイルから除外可能
 */
class SwaggerHooks extends HookProvider
{
    #[AddAction('init')]
    public function registerSwagger(): void
    {
        // Doc/ResponseSchema の解決を init 実行時まで遅延
        // フック登録時ではなく、実際に使用する時点で依存を解決
        // schemaDirectory 未設定時の warning を回避
        $container = Container::getInstance();
        $doc = $container->get(Doc::class);
        $responseSchema = $container->get(ResponseSchema::class);

        $doc->init();
        $responseSchema->init();
    }
}
