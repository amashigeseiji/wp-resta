<?php
namespace Wp\Restafari\OpenApi;

class Doc
{
    const VERSION = '1.0';
    const CAPABILITY = 'edit_pages';

    public readonly ResponseSchema $responseSchema;

    public function __construct(ResponseSchema $responseSchema)
    {
        $this->responseSchema = $responseSchema;
    }

    public function init()
    {
        if (is_admin() && is_user_logged_in()) {
            // メニュー追加
            add_action('admin_menu', function () {
                add_menu_page(
                    'REST API doc',
                    'REST API doc',
                    self::CAPABILITY,
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
        $this->add_pages();
        add_action('wp', [$this, 'response_schema']);
    }

    public function add_pages()
    {
        add_rewrite_tag('%rest_api_doc%', '([^&]+)');
        add_rewrite_rule('^rest-api/schema/?', 'index.php?rest_api_doc=schema', 'top');
    }

    /**
     * swagger json生成
     */
    public function response_schema()
    {
        if (
            get_query_var('rest_api_doc') !== 'schema'
            || !current_user_can(self::CAPABILITY)
        ) {
            return;
        }
        // $responseSchema = new OpenApiResponseSchema();
        wp_send_json($this->responseSchema->schema);
    }
}
