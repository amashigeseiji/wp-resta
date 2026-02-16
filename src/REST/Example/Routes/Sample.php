<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\RouteMeta;
use wpdb;

#[RouteMeta(
    description: "サンプルです",
    tags: ["サンプルAPI", "ほげほげ"]
)]
#[Envelope]
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

    /**
     * @return array<string, mixed>
     */
    public function callback(int $id, string|null $name = null, string $a_or_b = 'a'): array
    {
        /** @var wpdb $wpdb */
        global $wpdb;
        $res = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->posts} WHERE ID = %d",
            $id
        ));
        return [
            'id' => $id,
            'name' => $name,
            'a_or_b' => $a_or_b,
            'route' => $this->getRouteRegex(),
            'post' => get_post($id),
            'result' => $res,
            'queries' => $wpdb->last_query,
        ];
    }
}
