<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Schemas\Post as SchemasPost;

#[RouteMeta(
    description: "サンプルです",
    tags: ["サンプルAPI"]
)]
#[Envelope]
class Post extends AbstractRoute
{
    protected const ROUTE = 'post/[id]';
    protected const URL_PARAMS = [
        'id' => 'integer',
    ];

    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'post' => ['$ref' => SchemasPost::ID]
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
