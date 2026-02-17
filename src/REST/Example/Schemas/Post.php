<?php
namespace Wp\Resta\REST\Example\Schemas;

use Wp\Resta\REST\Schemas\ObjectType;

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
