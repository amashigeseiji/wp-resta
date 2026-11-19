<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use Mockery;
use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\Posts;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Example\Services\PostService;
use Wp\Resta\REST\Example\Sort;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * Route クラスのテストの手本。WordPress 無しで、本番と同じパイプライン（検証、型変換、callback）を通す。
 *
 * PostService はモックに差し替えて、コンストラクタに直接渡す
 */
class PostsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testDefaultsApplyWhenParametersAreOmitted(): void
    {
        $post = self::post(1, 'Hello');
        $service = Mockery::mock(PostService::class);
        $service->shouldReceive('published')->once()->with(1, 10, Sort::Desc, [])->andReturn([$post]);

        $response = $this->request($service, '/example/posts');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([$post], $response->getData());
    }

    public function testQueryParametersAreConvertedToDeclaredTypes(): void
    {
        $service = Mockery::mock(PostService::class);
        // クエリ文字列の値は文字列で届くが、callback には宣言した型で渡る
        $service->shouldReceive('published')->once()->with(2, 50, Sort::Asc, ['news', 'php'])->andReturn([]);

        $response = $this->request($service, '/example/posts?page=2&per_page=50&sort=asc&tags=news,php');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testValueOutOfRangeIs400(): void
    {
        $service = Mockery::mock(PostService::class);
        // 検証で止まるので、callback は呼ばれない
        $service->shouldNotReceive('published');

        $response = $this->request($service, '/example/posts?per_page=101');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('rest_invalid_param', $response->getData()['code']);
    }

    private function request(PostService $service, string $url): RestaResponseInterface
    {
        $route = new Posts($service);
        $route->setNamespace('example');

        return (new RequestHandler(new Dispatcher()))->handle($route, new TestRestaRequest($url, $route));
    }

    private static function post(int $id, string $title): Post
    {
        return new Post(
            ID: $id,
            post_author: '1',
            post_date: '2026-01-01 00:00:00',
            post_date_gmt: '2026-01-01 00:00:00',
            post_content: '',
            post_title: $title,
            post_status: 'publish',
            post_name: 'hello',
        );
    }
}
