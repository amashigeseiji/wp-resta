<?php
namespace Test\Resta\Fixtures\OperationRoutes;

use Wp\Resta\REST\AbstractRoute;

/**
 * SCHEMA 定数と戻り値型の両方を持つルート。OpenAPI では SCHEMA が優先される
 */
class TestSchemaConstantRoute extends AbstractRoute
{
    protected const ROUTE = 'schema-constant';

    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'fromConstant' => ['type' => 'string'],
        ],
    ];

    public function callback(): string
    {
        return 'ignored by OpenAPI';
    }
}
