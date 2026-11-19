<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\Auth\Principal;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\Draft;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Example\Services\PostService;
use Wp\Resta\REST\Hooks\AuthorizationHook;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * callback の引数で Principal を受け取るルートのテストの手本。
 *
 * 引数の Principal はコンテナから解決されるので、TestPrincipal を bind する。
 * Container はプロセスに 1 つなので、bind したテストは tearDown() でリセットする
 */
class DraftTest extends TestCase
{
    protected function tearDown(): void
    {
        (new ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);
        Mockery::close();
        parent::tearDown();
    }

    public function testUnauthenticatedRequestIs401(): void
    {
        $response = $this->request(Mockery::mock(PostService::class), new TestPrincipal());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testUserWhoCannotEditTheDraftIs403(): void
    {
        $service = Mockery::mock(PostService::class);
        $service->shouldNotReceive('findDraft');

        $response = $this->request($service, new TestPrincipal(authenticated: true));

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testUserWhoCanEditTheDraftGetsIt(): void
    {
        $draft = self::post(5, 'draft');
        $service = Mockery::mock(PostService::class);
        $service->shouldReceive('findDraft')->once()->with(5)->andReturn($draft);

        $response = $this->request($service, self::editor());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($draft, $response->getData());
    }

    public function testMissingDraftIs404(): void
    {
        $service = Mockery::mock(PostService::class);
        $service->shouldReceive('findDraft')->andReturn(null);

        $response = $this->request($service, self::editor());

        $this->assertSame(404, $response->getStatusCode());
    }

    private function request(PostService $service, TestPrincipal $principal): RestaResponseInterface
    {
        Container::getInstance()->bind(Principal::class, $principal);

        $route = new Draft($service);
        $route->setNamespace('example');

        $dispatcher = new Dispatcher();
        $dispatcher->addSubscriber(new AuthorizationHook($principal));

        return (new RequestHandler($dispatcher))->handle($route, new TestRestaRequest('/example/draft/5', $route));
    }

    private static function editor(): TestPrincipal
    {
        return new TestPrincipal(authenticated: true, capabilities: ['edit_post']);
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
