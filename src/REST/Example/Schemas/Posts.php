<?php
namespace Wp\Restafari\REST\Example\Schemas;

use Wp\Restafari\REST\Attributes\Schema\Property;
use Wp\Restafari\REST\Schemas\ArrayType;
use WP_Post;

class Posts extends ArrayType
{
    public const ID = '#/components/schemas/Posts';

    #[Property(['$ref' => Post::class])]
    public readonly array $items;

    /**
     * @param WP_Post[]
     */
    public function __construct(array $posts)
    {
        $this->items = array_map(function (WP_Post $post) {
            return (array) new Post($post);
        }, $posts);
    }
}
