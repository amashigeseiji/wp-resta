<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * 同じ URL で GET と POST を分けたルート（/example/notes/[id]）が、WordPress コアを経由して
 * メソッドごとのエンドポイントとして動くことを確認する（#92）
 */
class OperationsTest extends AbstractE2ETestCase
{
    public function testGetIsHandledByGetOperation(): void
    {
        $response = $this->get('/wp-json/example/notes/1');

        $this->assertResponseCode(200, $response);
        $this->assertSame(['id' => 1, 'method' => 'GET'], $this->getJsonResponse($response));
    }

    public function testPostIsHandledByPostOperationWithItsOwnParameters(): void
    {
        $response = $this->post('/wp-json/example/notes/1', ['title' => 'hello']);

        // ステータスとヘッダは callback が ResponseContext に書いたもの。WordPress を経由しても届く
        $this->assertResponseCode(201, $response);
        // Location は GET できる URL を指す
        $location = $response->getHeaderLine('Location');
        $this->assertStringEndsWith('/wp-json/example/notes/1', $location);
        $this->assertSame(['id' => 1, 'method' => 'GET'], $this->getJsonResponse($this->get((string) parse_url($location, PHP_URL_PATH))));
        $this->assertSame(['id' => 1, 'method' => 'POST', 'title' => 'hello'], $this->getJsonResponse($response));
    }

    public function testPostWithoutRequiredParameterIs400(): void
    {
        $response = $this->post('/wp-json/example/notes/1');

        $this->assertResponseCode(400, $response);
    }

    public function testUndeclaredMethodIs404(): void
    {
        $response = $this->put('/wp-json/example/notes/1', ['title' => 'x']);

        $this->assertResponseCode(404, $response);
        $this->assertSame('rest_no_route', $this->getJsonResponse($response)['code']);
    }
}
