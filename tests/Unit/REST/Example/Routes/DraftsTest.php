<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use Mockery;
use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\Drafts;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Example\Services\PostService;
use Wp\Resta\REST\Hooks\AuthorizationHook;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * 認可のアトリビュートまで確かめるテストの手本。
 *
 * #[Permission] を解釈するのは AuthorizationHook なので、Dispatcher に足してから RequestHandler に渡す。
 * 誰からのリクエストとして扱うかは、TestPrincipal で決める
 */
class DraftsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testUnauthenticatedRequestIs401(): void
    {
        $service = Mockery::mock(PostService::class);
        $service->shouldNotReceive('drafts');

        $response = $this->request($service, new TestPrincipal(), '/example/drafts');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testUserWithoutCapabilityIs403(): void
    {
        $service = Mockery::mock(PostService::class);
        $service->shouldNotReceive('drafts');

        $response = $this->request($service, new TestPrincipal(authenticated: true), '/example/drafts');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testGetReturnsDrafts(): void
    {
        $draft = self::post(1, 'draft');
        $service = Mockery::mock(PostService::class);
        $service->shouldReceive('drafts')->once()->andReturn([$draft]);

        $response = $this->request($service, self::editor(), '/example/drafts');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([$draft], $response->getData());
    }

    public function testPostCreatesDraft(): void
    {
        $draft = self::post(2, 'draft');
        $service = Mockery::mock(PostService::class);
        $service->shouldReceive('createDraft')->once()->with('Hello')->andReturn($draft);

        // HTTP メソッドは第 3 引数。post() の操作が選ばれる
        $response = $this->request($service, self::editor(), '/example/drafts?title=Hello', 'POST');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($draft, $response->getData());
    }

    public function testPostWithoutTitleIs400(): void
    {
        $service = Mockery::mock(PostService::class);
        $service->shouldNotReceive('createDraft');

        $response = $this->request($service, self::editor(), '/example/drafts', 'POST');

        $this->assertSame(400, $response->getStatusCode());
    }

    private function request(PostService $service, TestPrincipal $principal, string $url, string $method = 'GET'): RestaResponseInterface
    {
        $route = new Drafts($service);
        $route->setNamespace('example');

        $dispatcher = new Dispatcher();
        $dispatcher->addSubscriber(new AuthorizationHook($principal));

        return (new RequestHandler($dispatcher))->handle($route, new TestRestaRequest($url, $route, $method));
    }

    private static function editor(): TestPrincipal
    {
        return new TestPrincipal(authenticated: true, capabilities: ['edit_posts']);
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
