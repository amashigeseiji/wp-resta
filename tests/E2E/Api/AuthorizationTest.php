<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * authorize() の拒否が WP コアを経由してもそのままのステータスとエラー形式で返ることを確認する。
 * /example/secret は未認証なら 401、認証済みでも edit_posts が無ければ 403 を返す
 */
class AuthorizationTest extends AbstractE2ETestCase
{
    public function testUnauthenticatedRequestIsRejectedWith401(): void
    {
        $response = $this->get('/wp-json/example/secret');

        $this->assertResponseCode(401, $response);
        $data = $this->getJsonResponse($response);
        $this->assertSame('rest_not_logged_in', $data['code']);
        $this->assertSame(401, $data['data']['status']);
    }

    public function testPublicRouteStillAllowsUnauthenticatedRequest(): void
    {
        // authorize() を持たないルートは既定で許可。permission_callback を常に許可にしても公開ルートが変わらないこと
        $response = $this->get('/wp-json/example/hello/world');

        $this->assertResponseCode(200, $response);
    }
}
