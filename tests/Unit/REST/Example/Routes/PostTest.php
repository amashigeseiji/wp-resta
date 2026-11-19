<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use Mockery;
use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\Post as PostRoute;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Example\Services\PostService;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

class PostTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testPathParameterIsPassedAsInteger(): void
    {
        $post = self::post(123, 'publish');
        $service = Mockery::mock(PostService::class);
        $service->shouldReceive('findPublished')->once()->with(123)->andReturn($post);

        $response = $this->request($service, '/example/post/123');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($post, $response->getData());
    }

    public function testMissingPostIs404(): void
    {
        $service = Mockery::mock(PostService::class);
        $service->shouldReceive('findPublished')->andReturn(null);

        $response = $this->request($service, '/example/post/999');

        // HttpException は、エラーレスポンスの形（code / message / data.status）に変換される
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('rest_post_invalid_id', $response->getData()['code']);
    }

    private function request(PostService $service, string $url): RestaResponseInterface
    {
        $route = new PostRoute($service);
        $route->setNamespace('example');

        return (new RequestHandler(new Dispatcher()))->handle($route, new TestRestaRequest($url, $route));
    }

    private static function post(int $id, string $status): Post
    {
        return new Post(
            ID: $id,
            post_author: '1',
            post_date: '2026-01-01 00:00:00',
            post_date_gmt: '2026-01-01 00:00:00',
            post_content: '',
            post_title: 'Hello',
            post_status: $status,
            post_name: 'hello',
        );
    }
}
