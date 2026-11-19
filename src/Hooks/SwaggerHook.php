<?php
namespace Wp\Resta\Hooks;

use Wp\Resta\Auth\Principal;
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
 *
 * スキーマを出す場所と相手は {@see SwaggerOptions} で変えられる。
 */
final class SwaggerHook extends HookProvider
{
    const VERSION = '1.0';

    public function __construct(
        private readonly ResponseSchema $schema,
        private readonly SwaggerOptions $options,
        private readonly Principal $principal,
    ) {}

    /**
     * スキーマを REST ルートとして返す。/wp-json/ が届くサイトなら、Web サーバーの設定を足さずに届く
     */
    #[AddAction('rest_api_init')]
    public function onRestApiInit(): void
    {
        $capability = $this->options->capability;
        register_rest_route($this->options->namespace, $this->options->route, [
            'methods' => 'GET',
            'callback' => fn () => $this->schema->responseSchema(),
            // Principal に聞くので、Principal を差し替えたサイトでも、ほかのルートと基準が揃う
            'permission_callback' => $capability === null
                ? '__return_true'
                : fn (): bool => $this->principal->can($capability),
        ]);
    }

    #[AddAction('init')]
    public function onInit(): void
    {
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
                        // テンプレートが読む。パーマリンク設定が「基本」でも rest_url() が正しい形を返す
                        $schemaUrl = rest_url($this->options->namespace . $this->options->route);
                        // capability を指定していると、cookie 認証には nonce が要る
                        $nonce = wp_create_nonce('wp_rest');
                        require plugin_dir_path(__FILE__) . '../OpenApi/template/swagger.php';
                    },
                    'dashicons-book',
                    99
                );
            });
        }
    }
}
