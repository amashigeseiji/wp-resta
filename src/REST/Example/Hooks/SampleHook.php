<?php
namespace Wp\Resta\REST\Example\Hooks;

use Wp\Resta\Hooks\HookProvider;
use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\Attributes\AddAction;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;
use WP_HTTP_Response;
use WP_Error;

class SampleHook extends HookProvider
{
    /**
     * REST API リクエストの前処理
     * カスタムヘッダーを追加
     */
    #[AddFilter('rest_pre_dispatch', priority: 10, acceptedArgs: 3)]
    public function addCustomHeaders(
        null|WP_HTTP_Response|WP_Error $result,
        WP_REST_Server $server,
        WP_REST_Request $request
    ): null|WP_HTTP_Response|WP_Error {
        header('X-WP-Resta-Version: 0.8.4');
        header('X-WP-Resta-Sample: active');
        return $result;
    }

    /**
     * REST API レスポンスの後処理
     * すべてのレスポンスにメタ情報を追加
     *
     * @param array<string, mixed> $handler
     */
    #[AddFilter('rest_request_after_callbacks', priority: 10, acceptedArgs: 3)]
    public function addMetaToResponse(
        WP_HTTP_Response|WP_Error $response,
        array $handler,
        WP_REST_Request $request
    ): WP_HTTP_Response|WP_Error {
        if ($response instanceof WP_REST_Response) {
            $data = $response->get_data();

            // メタ情報を追加
            $data['_resta_meta'] = [
                'processed_at' => current_time('mysql'),
                'plugin_version' => '0.8.4',
                'request_route' => $request->get_route(),
            ];

            $response->set_data($data);
        }

        return $response;
    }

    /**
     * REST API 初期化時の処理
     */
    #[AddAction('rest_api_init')]
    public function onRestApiInit(): void
    {
        // REST API 初期化時の処理サンプル
        // 例: カスタムフィールドの登録など
    }

    /**
     * 管理画面フッターにテキストを追加（動作確認用）
     */
    #[AddFilter('admin_footer_text')]
    public function adminFooterText(string $text): string
    {
        return $text . ' | Powered by <a href="https://github.com/amashigeseiji/wp-resta">wp-resta</a>';
    }

    /**
     * register() をオーバーライドして動的な登録も可能
     */
    public function register(): void
    {
        parent::register(); // Attribute ベースの登録

        // 条件付きで追加のフックを登録する例
        if (defined('WP_DEBUG') && WP_DEBUG) {
            \add_filter('rest_pre_echo_response', [$this, 'debugResponse'], 10, 3);
        }
    }

    /**
     * デバッグモード時のレスポンス出力前処理
     *
     * @param array<string, mixed>|null $result
     * @return array<string, mixed>|null
     */
    public function debugResponse(
        array|null $result,
        WP_REST_Server $server,
        WP_REST_Request $request
    ): array|null {
        error_log('REST API Request: ' . $request->get_route());
        return $result;
    }
}
