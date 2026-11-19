<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Cache;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\Param;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Order;
use Wp\Resta\REST\Example\Schemas\Post;
use WP_Query;

/**
 * 公開されている記事の一覧。
 *
 * クエリパラメータは callback の引数で宣言する。引数の型が検証と型変換になり、デフォルト値があれば省略できる。
 * 範囲のような制約は #[Param] で足す。ここで宣言していないパラメータは受け取らないので、
 * このルートが発行するクエリは、下の配列に書いてある形だけになる
 */
#[RouteMeta(
    description: "公開されている記事の一覧を返します",
    tags: ["記事"]
)]
#[Envelope]
#[Cache(maxAge: 60)]
class Posts extends AbstractRoute
{
    protected const ROUTE = 'posts';

    /**
     * @return Post[]
     */
    public function callback(
        int $page = 1,
        #[Param(min: 1, max: 100)] int $per_page = 10,
        Order $order = Order::Desc,
    ): array {
        // データの取りかたに、決まった書きかたは無い。ここでは callback に直接書いている
        return Post::fromWpPosts((new WP_Query())->query([
            'post_type' => 'post',
            'post_status' => 'publish',
            // パスワードで保護された記事は、本文を返してしまうので除く
            'has_password' => false,
            'orderby' => 'date',
            'order' => $order === Order::Asc ? 'ASC' : 'DESC',
            'paged' => $page,
            'posts_per_page' => $per_page,
        ]));
    }
}
