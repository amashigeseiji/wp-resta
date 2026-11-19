<?php
namespace Test\Resta\E2E\Fixtures\Hooks;

use Wp\Resta\Hooks\Attributes\AddFilter;
use Wp\Resta\Hooks\Enum\RestApiHook;
use Wp\Resta\Hooks\HookProvider;
use WP_REST_Request;
use WP_REST_Response;

/**
 * HookProvider の #[AddFilter] が WordPress のフックとして登録されることを確かめるためのフィクスチャ。
 * エンベロープの meta に値を足す
 */
class MetaHook extends HookProvider
{
    /**
     * rest_request_after_callbacks の第 1 引数は callback の生の戻り値で、
     * WP コアのルートは配列を返すこともある。WP_REST_Response 以外はそのまま返す。
     *
     * @param array<string, mixed> $handler
     */
    #[AddFilter(RestApiHook::REQUEST_AFTER_CALLBACKS, priority: 10, acceptedArgs: 3)]
    public function addMetaToResponse(
        mixed $response,
        array $handler,
        WP_REST_Request $request
    ): mixed {
        if ($response instanceof WP_REST_Response) {
            $data = $response->get_data();

            if (is_array($data) && isset($data['data'], $data['meta']) && is_array($data['meta'])) {
                $data['meta']['processed_at'] = current_time('mysql');
                $data['meta']['plugin_version'] = 'fixture';
                $data['meta']['request_route'] = $request->get_route();
                $response->set_data($data);
            }
        }

        return $response;
    }
}
