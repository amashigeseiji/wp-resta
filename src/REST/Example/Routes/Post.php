<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Schemas\Post as SchemasPost;

#[RouteMeta(
    description: "サンプルです",
    tags: ["サンプルAPI"]
)]
class Post extends AbstractRoute
{
    protected const ROUTE = 'post/[id]';
    protected const URL_PARAMS = [
        'id' => 'integer',
    ];

    public const SCHEMA = [
        '$schema' => 'http://json-schema.org/draft-04/schema#',
        'type' => 'object',
        'properties' => [
            'post' => ['$ref' => '#/components/schemas/Post']
        ]
    ];

    /**
     * @return array<string, SchemasPost>|null
     */
    public function callback(int $id) : ?array
    {
        $post = get_post($id);
        if ($post === null) {
            $this->status = 404;
            return null;
        }

        return [
            'post' => new SchemasPost($post)
        ];
    }
}
