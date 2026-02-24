<?php
namespace Wp\Resta\Hooks;

use Wp\Resta\Hooks\Attributes\AddAction;
use Wp\Resta\OpenApi\ResponseSchema;

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
    const VERSION = '1.0';

    public function __construct(private readonly ResponseSchema $schema) {}

    #[AddAction('init')]
    public function onInit(): void
    {
        add_rewrite_tag('%rest_api_doc%', '([^&]+)');
        add_rewrite_rule('^rest-api/schema/?', 'index.php?rest_api_doc=schema', 'top');
        add_action('wp', function () {
            if ( get_query_var('rest_api_doc') !== 'schema') {
                return;
            }
            wp_send_json($this->schema->responseSchema());
        });
        if (is_admin() && is_user_logged_in()) {
            // メニュー追加
            add_action('admin_menu', function () {
                add_menu_page(
                    'REST API doc',
                    'REST API doc',
                    'edit_pages',
                    'wp-resta',
                    function () {
                        wp_enqueue_style('rest-api-doc', 'https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css', [], self::VERSION);
                        wp_enqueue_script('rest-api-doc-swagger', 'https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js', [], self::VERSION);
                        wp_enqueue_script('rest-api-doc-swagger-preset', 'https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-standalone-preset.js', [], self::VERSION);
                        require plugin_dir_path(__FILE__) . '../OpenApi/template/swagger.php';
                    },
                    'dashicons-book',
                    99
                );
            });
        }
    }
}
