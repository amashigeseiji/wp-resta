<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Schemas\Post as PostSchema;
use Wp\Resta\REST\Exception\HttpException;
use WP_Post;

/**
 * 公開されている記事 1 件。
 *
 * ROUTE の [id] は callback の引数 int $id で宣言する。戻り値が body で、戻り値型がレスポンスのスキーマになる。
 * 見つからないときは例外で 404 を返す
 */
#[RouteMeta(
    description: "公開されている記事を 1 件返します",
    tags: ["記事"]
)]
#[Envelope]
class Post extends AbstractRoute
{
    protected const ROUTE = 'post/[id]';

    public function callback(int $id): PostSchema
    {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
            throw new HttpException('Post not found', 404, 'rest_post_invalid_id');
        }

        return PostSchema::fromWpPost($post);
    }
}
