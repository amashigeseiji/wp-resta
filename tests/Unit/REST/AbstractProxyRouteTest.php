<?php
namespace Test\Resta\Unit\REST;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\DI\Container;
use Wp\Resta\REST\AbstractProxyRoute;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;

/**
 * AbstractProxyRoute のユニットテスト
 *
 * WordPress 環境なしでプロキシ動作を検証する。
 * - rest_do_request() は Brain\Monkey でモック
 * - RestaRequestInterface は DI コンテナに手動バインド（RequestHandler を通さない分）
 */
class AbstractProxyRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $reflection = new ReflectionClass(Container::class);
        $prop = $reflection->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        parent::tearDown();
    }

    private function bindRequest(TestRestaRequest $request): void
    {
        Container::getInstance()->bind(RestaRequestInterface::class, $request);
    }

    private function makeWpResponse(mixed $data, int $status = 200, array $headers = []): \WP_REST_Response
    {
        return new \WP_REST_Response($data, $status, $headers);
    }

    // --- 基本動作 ---

    public function testDataIsPassedThrough(): void
    {
        $route = new class extends AbstractProxyRoute {
            protected const PROXY_PATH = '/wp/v2/posts';
        };

        $request = new TestRestaRequest('/posts', $route);
        $this->bindRequest($request);

        Functions\when('rest_do_request')->justReturn(
            $this->makeWpResponse(['id' => 1, 'title' => 'Test'])
        );

        $response = $route->invoke($request);

        $this->assertInstanceOf(RestaResponseInterface::class, $response);
        $this->assertEquals(['id' => 1, 'title' => 'Test'], $response->getData());
    }

    public function testStatusCodeIsForwarded(): void
    {
        $route = new class extends AbstractProxyRoute {
            protected const PROXY_PATH = '/wp/v2/posts';
        };

        $request = new TestRestaRequest('/posts', $route);
        $this->bindRequest($request);

        Functions\when('rest_do_request')->justReturn(
            $this->makeWpResponse([], 404)
        );

        $response = $route->invoke($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testHeadersAreForwarded(): void
    {
        $route = new class extends AbstractProxyRoute {
            protected const PROXY_PATH = '/wp/v2/posts';
        };

        $request = new TestRestaRequest('/posts', $route);
        $this->bindRequest($request);

        Functions\when('rest_do_request')->justReturn(
            $this->makeWpResponse([], 200, ['X-WP-Total' => '42'])
        );

        $response = $route->invoke($request);

        $this->assertArrayHasKey('X-WP-Total', $response->getHeaders());
        $this->assertEquals('42', $response->getHeaders()['X-WP-Total']);
    }

    // --- transform() ---

    public function testTransformIsApplied(): void
    {
        $route = new class extends AbstractProxyRoute {
            protected const PROXY_PATH = '/wp/v2/posts';

            protected function transform(mixed $data): mixed
            {
                return array_map(fn($post) => ['id' => $post['id']], $data);
            }
        };

        $request = new TestRestaRequest('/posts', $route);
        $this->bindRequest($request);

        Functions\when('rest_do_request')->justReturn(
            $this->makeWpResponse([
                ['id' => 1, 'title' => 'Post 1'],
                ['id' => 2, 'title' => 'Post 2'],
            ])
        );

        $response = $route->invoke($request);

        $this->assertEquals([['id' => 1], ['id' => 2]], $response->getData());
    }

    // --- URL パラメータ展開 ---

    public function testUrlParamIsExpandedInProxyPath(): void
    {
        $route = new class extends AbstractProxyRoute {
            protected const ROUTE = 'post/[id]';
            protected const URL_PARAMS = ['id' => 'integer'];
            protected const PROXY_PATH = '/wp/v2/posts/[id]';
        };
        $route->setNamespace('myapi');

        $request = new TestRestaRequest('/myapi/post/42', $route);
        $this->bindRequest($request);

        Functions\expect('rest_do_request')
            ->once()
            ->andReturnUsing(function (\WP_REST_Request $wpReq) {
                $this->assertEquals('/wp/v2/posts/42', $wpReq->getRoute());
                return new \WP_REST_Response(['id' => 42]);
            });

        $response = $route->invoke($request);

        $this->assertEquals(['id' => 42], $response->getData());
    }

    // --- クエリパラメータ ---

    public function testQueryParamsAreForwarded(): void
    {
        $route = new class extends AbstractProxyRoute {
            protected const PROXY_PATH = '/wp/v2/posts';
        };

        $request = new TestRestaRequest('/posts', $route, ['per_page' => 5, 'page' => 2]);
        $this->bindRequest($request);

        Functions\expect('rest_do_request')
            ->once()
            ->andReturnUsing(function (\WP_REST_Request $wpReq) {
                $params = $wpReq->get_query_params();
                $this->assertEquals(5, $params['per_page']);
                $this->assertEquals(2, $params['page']);
                return new \WP_REST_Response([]);
            });

        $route->invoke($request);
    }

    // --- エラーケース ---

    public function testThrowsLogicExceptionWhenProxyPathIsEmpty(): void
    {
        $route = new class extends AbstractProxyRoute {
            // PROXY_PATH は空のまま
        };

        $request = new TestRestaRequest('/test', $route);
        $this->bindRequest($request);

        $this->expectException(\LogicException::class);

        $route->invoke($request);
    }
}
