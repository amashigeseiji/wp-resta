<?php
namespace Test\Resta\E2E\Fixtures\Routes;

use WP_REST_Request;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

/**
 * callback の引数でパラメータを受け取らず、WP_REST_Request から直接読む例。
 *
 * URL_PARAMS で宣言したパラメータは、callback の引数に無くても wp-resta が検証し、合わなければ 400 を返す。
 * WP_REST_Request から読む値は、型変換の前の、クライアントが送ったままの値である。
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
