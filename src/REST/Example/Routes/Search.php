<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Http\RestaRequestInterface;

/**
 * callback の引数でパラメータを受け取らず、リクエストから直接読む例。
 * getUrlParam() が返すのはクライアントが送った値そのままで、型変換はされない。
 *
 * URL_PARAMS の regex は pattern として WP コアの args にも渡されるので、
 * callback の引数に無いパラメータでも、コアが dispatch 前に検証して 400 を返す。
 */
#[RouteMeta(
    description: "クエリパラメータをリクエストから直接読むサンプル",
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
    public function callback(RestaRequestInterface $request): array
    {
        return [
            'keyword' => $request->getUrlParam('keyword'),
            'order' => $request->getUrlParam('order') ?? 'asc',
            'path' => $request->getUrlParam('path'),
        ];
    }
}
