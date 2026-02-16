<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

/**
 * AbstractRoute を使用したシンプルなAPI
 *
 * EnvelopeRoute を使わない場合の例。
 * エンベロープパターンを使わず、直接データを返します。
 */
#[RouteMeta(
    description: "Simple API without envelope pattern",
    tags: ["Sample API", "AbstractRoute"]
)]
class SimpleApi extends AbstractRoute
{
    protected const ROUTE = 'simpleapi';

    public const SCHEMA = [
        '$schema' => 'http://json-schema.org/draft-04/schema#',
        'type' => 'object',
        'properties' => [
            'message' => [
                'type' => 'string',
                'description' => 'A simple message',
            ],
            'status' => [
                'type' => 'string',
                'description' => 'Status of the response',
            ],
        ],
    ];

    /**
     * @return array<string, string>
     */
    public function callback(): array
    {
        return [
            'message' => 'This is a simple API response',
            'status' => 'ok',
        ];
    }
}
