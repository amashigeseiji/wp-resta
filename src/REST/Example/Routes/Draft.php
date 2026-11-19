<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Authenticated;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Exception\ForbiddenException;
use Wp\Resta\REST\Exception\HttpException;
use WP_Post;

/**
 * 下書き 1 件。ログイン済みで、その下書きを編集できるユーザーだけが読める。
 *
 * ログイン済みかどうかはアトリビュートで宣言する。「この下書きを編集できるか」のようにリクエストの値に依る判定は、
 * アトリビュートでは書けないので、Principal を引数で受け取って callback の中に書く
 */
#[RouteMeta(
    description: "下書きを 1 件返します。ログインと、その下書きの編集権限が必要です",
    tags: ["下書き"]
)]
class Draft extends AbstractRoute
{
    protected const ROUTE = 'draft/[id]';

    #[Authenticated]
    public function callback(int $id, Principal $principal): Post
    {
        // 編集できない相手には、下書きがあるかどうかも教えない。先に権限を確かめる
        if (!$principal->can('edit_post', $id)) {
            throw new ForbiddenException();
        }

        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'post' || $post->post_status !== 'draft') {
            throw new HttpException('Draft not found', 404, 'rest_post_invalid_id');
        }

        return Post::fromWpPost($post);
    }
}
