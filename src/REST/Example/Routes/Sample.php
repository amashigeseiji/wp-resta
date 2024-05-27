<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

#[RouteMeta(
    description: "サンプルです",
    tags: ["サンプルAPI", "ほげほげ"]
)]
class Sample extends AbstractRoute
{
    protected const ROUTE = 'sample/[id]';
    protected const URL_PARAMS = [
        'id' => 'integer',
        'name' => '?string',
        'a_or_b' => [
            'type' => 'string',
            'required' => false,
            'regex' => '(a|b)',
            'description' => 'a または b です'
        ],
    ];

    public const SCHEMA = [
        '$schema' => 'http://json-schema.org/draft-04/schema#',
        'title' => 'sample',
        'type' => 'object',
        'properties' => [
            'id' => [
                'description' => 'wp post id',
                'type' => 'integer',
                'example' => 1,
            ],
            'name' => [
                'description' => 'requested value',
                'type' => 'string',
                'example' => 'hoge'
            ],
            'a_ore_b' => [
                'type' => 'string',
                'description' => '"a" or "b"',
                'example' => 'a',
            ],
            'route' => [
                'type' => 'string',
                'description' => 'route regex',
            ],
            'post' => [
                'type' => 'object',
                'description' => 'wp post',
            ],
            'result' => [
                'type' => 'object',
                'description' => 'wpdb last result'
            ],
            'queries' => [
                'type' => 'string',
                'description' => 'last requested query'
            ],
        ],
    ];

    public function callback(int $id, string $name = null, string $a_or_b = 'a'): array
    {
        global $wpdb;
        $res = $wpdb->query($wpdb->prepare(
            'SELECT * FROM wp_posts WHERE ID = %s',
            $id
        ));
        return [
            'id' => $id,
            'name' => $name,
            'a_or_b' => $a_or_b,
            'route' => $this->getRouteRegex(),
            'post' => get_post($id),
            'result' => $wpdb->last_result,
            'queries' => $wpdb->last_query,
        ];
    }
}
