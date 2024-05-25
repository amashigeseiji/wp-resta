<?php
namespace Wp\Resta\OpenApi;

class Doc
{
    const VERSION = '1.0';

    public function init()
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
                        require plugin_dir_path(__FILE__) . 'template/swagger.php';
                    },
                    'dashicons-book',
                    99
                );
            });
        }
    }
}
