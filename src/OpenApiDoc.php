<?php
namespace Wp\Restafari;

class OpenApiDoc
{
    const VERSION = '1.0';
    const PLUGIN_ID = 'restafari-api-doc';
    const MAIN_MENU_SLUG = self::PLUGIN_ID;
    const CAPABILITY = 'edit_pages';

    public static function init()
    {
        return new self();
    }

    private function __construct()
    {
        if (is_admin() && is_user_logged_in()) {
            // メニュー追加
            add_action('admin_menu', [$this, 'set_plugin_menu']);
            add_action('admin_head', [$this, 'change_plugin_menu_link']);
        }
        $this->add_pages();
        add_action('template_include', [$this, 'document_page_view'], 99, 1);
        add_action('wp_enqueue_scripts', [$this, 'document_page_script'], 99);
        add_action('wp', [$this, 'response_schema']);
    }

    public function add_pages()
    {
        add_rewrite_tag('%rest_api_doc%', '([^&]+)');
        add_rewrite_rule('^rest-api/doc/?', 'index.php?rest_api_doc=document-page', 'top');
        add_rewrite_rule('^rest-api/schema/?', 'index.php?rest_api_doc=schema', 'top');
    }

    public function document_page_view($template)
    {
        if (get_query_var('rest_api_doc') === 'document-page') {
            $template = plugin_dir_path(__FILE__) . '../template/swagger.php';
        }
        return $template;
    }

    public function document_page_script()
    {
        if (get_query_var('rest_api_doc') === 'document-page') {
            wp_enqueue_style('rest-api-doc', plugin_dir_url(__FILE__) . '../assets/swagger-ui.css', [], self::VERSION);
            wp_enqueue_script('rest-api-doc-swagger', plugin_dir_url(__FILE__) . '../assets/swagger-ui-bundle.js', [], self::VERSION);
            wp_enqueue_script('rest-api-doc-swagger-preset', plugin_dir_url(__FILE__) . '../assets/swagger-ui-standalone-preset.js', [], self::VERSION);
            wp_localize_script('rest-api-doc-swagger', 'swaggerSetting', [
                'schemaUrl' => home_url('/rest-api/schema'),
            ]);
        }
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
        $responseSchema = new OpenApiResponseSchema();
        wp_send_json($responseSchema->schema);
    }

    /**
     * 管理画面にメニューを追加
     */
    public function set_plugin_menu()
    {
        add_menu_page(
            'REST API doc',
            'REST API doc',
            self::CAPABILITY,
            self::MAIN_MENU_SLUG,
            function () { /**リンク置き換え */},
            'dashicons-book',
            99
        );
    }

    /**
     * 管理画面にメニューリンクをswaggerページへ置き換え
     */
    public function change_plugin_menu_link()
    {
        ?>
        <script>
        const restApiMenuPageSlug = '<?php echo self::MAIN_MENU_SLUG; ?>';
        const restApiDocPageLink = '<?php echo home_url('/rest-api/doc'); ?>';
        document.addEventListener("DOMContentLoaded",function() {
            const link = document.querySelector("a.toplevel_page_" + restApiMenuPageSlug);
            if (link) {
                link.href = restApiDocPageLink;
                link.target = "_blank";
            }
        }, false);
        </script>
    <?php }
}
