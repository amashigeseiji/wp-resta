<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * 認可のアトリビュートが、Resta::init() の組み立てと WP コアを経由しても効くことを確認する（#139）。
 * /fixture/drafts は GET が公開で、POST に #[Permission('edit_posts')] が付いている
 */
class AuthorizationAttributeTest extends AbstractE2ETestCase
{
    public function testOperationWithoutAttributeIsPublic(): void
    {
        $response = $this->get('/wp-json/fixture/drafts');

        $this->assertResponseCode(200, $response);
    }

    public function testOperationWithPermissionRejectsUnauthenticatedWith401(): void
    {
        $response = $this->post('/wp-json/fixture/drafts', ['title' => 'hello']);

        $this->assertResponseCode(401, $response);
        $data = $this->getJsonResponse($response);
        $this->assertSame('rest_not_logged_in', $data['code']);
        $this->assertSame(401, $data['data']['status']);
    }
}
