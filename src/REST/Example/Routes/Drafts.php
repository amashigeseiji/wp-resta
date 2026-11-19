<?php
namespace Wp\Resta\REST\Example\Routes;

use RuntimeException;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Permission;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Schemas\Post;
use WP_Post;
use WP_Query;

/**
 * 下書きの一覧と作成。
 *
 * 同じ URL で GET と POST を分けるときは、HTTP メソッド名のメソッドを書く。それぞれが 1 つの操作になり、
 * 引数の宣言も認可のアトリビュートも操作ごとに独立している。
 * 一覧は他人の下書きも返すので edit_others_posts を、作成は edit_posts を求める
 */
#[RouteMeta(
    description: "下書きの一覧と作成です。一覧には edit_others_posts、作成には edit_posts 権限が必要です",
    tags: ["下書き"]
)]
class Drafts extends AbstractRoute
{
    protected const ROUTE = 'drafts';

    /**
     * @return Post[]
     */
    #[Permission('edit_others_posts')]
    public function get(): array
    {
        return Post::fromWpPosts((new WP_Query())->query([
            'post_type' => 'post',
            'post_status' => 'draft',
            'orderby' => 'modified',
            'order' => 'DESC',
            'posts_per_page' => 10,
        ]));
    }

    #[Permission('edit_posts')]
    public function post(string $title): Post
    {
        $post = get_post(wp_insert_post(['post_title' => $title, 'post_status' => 'draft']));
        if (!$post instanceof WP_Post) {
            throw new RuntimeException('Failed to create a draft');
        }

        return Post::fromWpPost($post);
    }
}
