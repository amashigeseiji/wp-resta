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
    public function __construct(
        private readonly Container $container
    ) {}

    #[AddAction('init')]
    public function registerSwagger(): void
    {
        $this->container->get(Doc::class)->init();
        $this->container->get(ResponseSchema::class)->init();
    }
}
