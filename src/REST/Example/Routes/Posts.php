<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Example\Schemas\Post;
use WP_Query;

#[Envelope]
class Posts extends AbstractRoute
{
    protected const ROUTE = 'posts';

    public const SCHEMA = [
        'type' => 'array',
        'items' => [
            '$ref' => Post::ID
        ],
    ];

    /**
     * @return Post[]
     */
    public function callback(): array
    {
        $q = new WP_Query();
        $posts = $q->query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC',
            'posts_per_page' => 10
        ]);

        return array_map(fn($post) => new Post(
            ID: $post->ID,
            post_author: $post->post_author,
            post_date: $post->post_date,
            post_date_gmt: $post->post_date_gmt,
            post_content: $post->post_content,
            post_title: $post->post_title,
            post_status: $post->post_status,
            post_name: $post->post_name,
        ), $posts);
    }
}
