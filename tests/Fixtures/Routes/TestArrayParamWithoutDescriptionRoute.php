<?php
namespace Test\Resta\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;

/**
 * 配列形式の URL_PARAMS で description を省略したルート（README の例と同じ書き方）
 */
class TestArrayParamWithoutDescriptionRoute extends AbstractRoute
{
    protected const ROUTE = 'array-param/[id]';
    protected const URL_PARAMS = [
        'id' => [
            'type' => 'integer',
            'required' => true,
            'regex' => '\d+',
        ],
        'first_name' => [
            'type' => 'string',
            'regex' => '[a-z]+',
        ],
    ];

    public function callback(int $id, string $first_name = ''): string
    {
        return "{$id}:{$first_name}";
    }
}
