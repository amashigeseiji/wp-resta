<?php
namespace Wp\Resta\REST\Example\Schemas;

use Wp\Resta\REST\Schemas\ObjectType;
use WP_Post;

class Post extends ObjectType
{
    public const ID = '#/components/schemas/Post';

    public function __construct(
        public int $ID,
        public string $post_author,
        public string $post_date,
        public string $post_date_gmt,
        public string $post_content,
        public string $post_title,
        public string $post_status,
        public string $post_name,
    ) {
    }

    /**
     * WordPress の投稿から作る。レスポンスに載せる項目は、ここで決まる。
     * 詰め替えをどこに書くかは一例で、決まった書きかたは無い
     */
    public static function fromWpPost(WP_Post $post): self
    {
        return new self(
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

    /**
     * WP_Query::query() の結果から作る
     *
     * @param array<mixed> $posts
     * @return self[]
     */
    public static function fromWpPosts(array $posts): array
    {
        $result = [];
        foreach ($posts as $post) {
            if ($post instanceof WP_Post) {
                $result[] = self::fromWpPost($post);
            }
        }

        return $result;
    }

    public static function metadata(): array
    {
        return [
            'ID' => ['description' => 'wp post id', 'example' => 1],
            'post_author' => ['description' => 'wp post author', 'example' => '4'],
            'post_date' => ['description' => 'wp post datetime', 'example' => '2024-05-19 21:58:47'],
            'post_date_gmt' => ['description' => 'wp post datetime(gmt)', 'example' => '2024-05-19 12:58:47'],
            'post_content' => ['description' => 'wp post content', 'example' => '<h1>Hello</h1><p>This is content</p>'],
            'post_title' => ['description' => 'wp post title', 'example' => 'Hello'],
            'post_status' => ['description' => 'wp post status', 'example' => 'publish'],
            'post_name' => ['description' => 'wp post slug', 'example' => '1'],
        ];
    }
}
