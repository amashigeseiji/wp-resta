<?php
namespace Wp\Resta\REST\Example\Schemas;

use Wp\Resta\REST\Attributes\Schema\Property;
use Wp\Resta\REST\Schemas\ArrayType;
use WP_Post;

class Posts extends ArrayType
{
    public const ID = '#/components/schemas/Posts';

    /**
     * @var Post[]
     */
    #[Property(['$ref' => Post::ID])]
    public readonly array $items;

    /**
     * @param WP_Post[] $posts
     */
    public function __construct(array $posts)
    {
        $this->items = array_map(function (WP_Post $post) {
            return new Post($post);
        }, $posts);
    }
}
