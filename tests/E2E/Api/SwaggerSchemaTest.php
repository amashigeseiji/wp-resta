<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * SwaggerHook が OpenAPI のスキーマを /wp-json/ 配下で返すことを確認する（#158）
 */
class SwaggerSchemaTest extends AbstractE2ETestCase
{
    public function testSchemaIsServedUnderWpJson(): void
    {
        $response = $this->get('/wp-json/resta/v1/schema');

        $this->assertResponseCode(200, $response);
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey('openapi', $data);
        $this->assertArrayHasKey('paths', $data);
    }
}
