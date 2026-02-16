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

        // スキーマクラスは OpenAPI 定義のみに使用
        // データは Post オブジェクトの配列として返す
        return array_map(fn($post) => new Post($post), $posts);
    }
}
