<?php
namespace Wp\Restafari\OpenApi;

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
                    'wp-restafari',
                    function () {
                        wp_enqueue_style('rest-api-doc', plugin_dir_url(__FILE__) . 'assets/swagger-ui.css', [], self::VERSION);
                        wp_enqueue_script('rest-api-doc-swagger', plugin_dir_url(__FILE__) . 'assets/swagger-ui-bundle.js', [], self::VERSION);
                        wp_enqueue_script('rest-api-doc-swagger-preset', plugin_dir_url(__FILE__) . 'assets/swagger-ui-standalone-preset.js', [], self::VERSION);
                        require plugin_dir_path(__FILE__) . 'template/swagger.php';
                    },
                    'dashicons-book',
                    99
                );
            });
        }
    }
}
