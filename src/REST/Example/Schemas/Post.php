<?php
namespace Wp\Resta\REST\Example\Schemas;

use Wp\Resta\REST\Schemas\ObjectType;
use WP_Post;

class Post extends ObjectType
{
    public const ID = '#/components/schemas/Post';

    public readonly int $ID;
    public readonly string $post_author;
    public readonly string $post_date;
    public readonly string $post_date_gmt;
    public readonly string $post_content;
    public readonly string $post_title;
    public readonly string $post_status;
    public readonly string $post_name;

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

    public function __construct(WP_Post $post)
    {
        $this->ID = $post->ID;
        $this->post_author = $post->post_author;
        $this->post_date = $post->post_date;
        $this->post_date_gmt = $post->post_date_gmt;
        $this->post_content = $post->post_content;
        $this->post_title = $post->post_title;
        $this->post_status = $post->post_status;
        $this->post_name = $post->post_name;
    }
}
