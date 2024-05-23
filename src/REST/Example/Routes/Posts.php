<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Example\Schemas\Posts as SchemasPosts;
use WP_Query;

class Posts extends AbstractRoute
{
    protected const ROUTE = 'posts';

    public const SCHEMA = [
        '$schema' => 'http://json-schema.org/draft-04/schema#',
        'type' => 'array',
        'items' => [
            '$ref' => '#/components/schemas/Post'
        ],
    ];

    public function callback(WP_Query $q)
    {
        $posts = $q->query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC',
            'posts_per_page' => 10
        ]);
        return (array) new SchemasPosts($posts);
    }
}
