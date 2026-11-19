<?php
namespace Test\Resta\E2E\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\RouteMeta;
use Test\Resta\E2E\Fixtures\Schemas\FixturePost;
use Wp\Resta\REST\Exception\HttpException;

#[RouteMeta(
    description: "サンプルです",
    tags: ["サンプルAPI"]
)]
#[Envelope]
class Post extends AbstractRoute
{
    protected const ROUTE = 'post/[id]';
    protected const URL_PARAMS = [
        'id' => 'integer',
    ];

    /**
     * 戻り値が body で、戻り値型がレスポンスのスキーマになる。見つからないときは例外で 404 を返す
     */
    public function callback(int $id): FixturePost
    {
        $post = get_post($id);
        if ($post === null) {
            throw new HttpException('Post not found', 404, 'rest_post_invalid_id');
        }

        return new FixturePost(
            ID: $post->ID,
            post_author: $post->post_author,
            post_date: $post->post_date,
            post_date_gmt: $post->post_date_gmt,
            post_content: $post->post_content,
            post_title: $post->post_title,
            post_status: $post->post_status,
            post_name: $post->post_name,
        );
    }
}
