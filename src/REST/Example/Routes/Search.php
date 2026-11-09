<?php
namespace Wp\Resta\REST\Example\Routes;

use WP_REST_Request;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

/**
 * callback の引数でパラメータを受け取らず、WP_REST_Request から直接読む例。
 *
 * URL_PARAMS の regex は pattern として WP コアの args にも渡されるので、
 * callback の引数に無いパラメータでも、コアが dispatch 前に検証して 400 を返す。
 */
#[RouteMeta(
    description: "クエリパラメータを WP_REST_Request から直接読むサンプル",
    tags: ["サンプルAPI"]
)]
class Search extends AbstractRoute
{
    protected const ROUTE = 'search';
    protected const URL_PARAMS = [
        'keyword' => '?string',
        'order' => [
            'type' => 'string',
            'required' => false,
            'regex' => '(asc|desc)',
            'description' => '並び順',
        ],
        'path' => [
            'type' => 'string',
            'required' => false,
            'regex' => '[a-z/]+',
            'description' => 'スラッシュ区切りのパス',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function callback(WP_REST_Request $request): array
    {
        return [
            'keyword' => $request->get_param('keyword'),
            'order' => $request->get_param('order') ?? 'asc',
            'path' => $request->get_param('path'),
        ];
    }
}
