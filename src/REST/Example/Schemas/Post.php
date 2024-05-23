<?php
namespace Wp\Resta\REST\Example\Schemas;

use Wp\Resta\REST\Attributes\Schema\Property;
use Wp\Resta\REST\Schemas\ObjectType;
use WP_Post;

class Post extends ObjectType
{
    public const ID = '#/components/schemas/Post';

    #[Property(['type' => 'integer', 'description'=>'wp post id', 'example' => 1])]
    public readonly int $ID;

    #[Property(['type' => 'string', 'description'=>'wp post author', 'example' => '4'])]
    public readonly string $post_author;

    #[Property(['type' => 'string', 'description'=>'wp post datetime', 'example' => '2024-05-19 21:58:47'])]
    public readonly string $post_date;

    #[Property(['type' => 'string', 'description'=>'wp post datetime(gmt)', 'example' => '2024-05-19 12:58:47'])]
    public readonly string $post_date_gmt;

    #[Property(['type' => 'string', 'description'=>'wp post content', 'example' => '<h1>Hello</h1><p>This is content</p>'])]
    public readonly string $post_content;

    #[Property(['type' => 'string', 'description'=>'wp post title', 'example' => 'Hello'])]
    public readonly string $post_title;

    #[Property(['type' => 'string', 'description'=>'wp post status', 'example' => 'publish'])]
    public readonly string $post_status;

    #[Property(['type' => 'string', 'description'=>'wp post slug', 'example' => '1'])]
    public readonly string $post_name;

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
