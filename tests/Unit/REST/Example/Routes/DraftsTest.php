<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\Drafts;
use Wp\Resta\REST\Hooks\AuthorizationHook;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * 認可のアトリビュートのテストの手本。誰かが #[Permission] を消したら、ここが落ちる。
 *
 * #[Permission] を解釈するのは AuthorizationHook なので、Dispatcher に足してから RequestHandler に渡す。
 * 誰からのリクエストとして扱うかは、TestPrincipal で決める。
 * 認可は callback の前に行われるので、拒否される場合は WordPress 無しで確かめられる
 */
class DraftsTest extends TestCase
{
    public function testUnauthenticatedRequestIs401(): void
    {
        $this->assertSame(401, $this->request(new TestPrincipal(), 'GET', '/example/drafts')->getStatusCode());
        $this->assertSame(401, $this->request(new TestPrincipal(), 'POST', '/example/drafts?title=Hello')->getStatusCode());
    }

    public function testUserWithoutCapabilityIs403(): void
    {
        $subscriber = new TestPrincipal(authenticated: true);

        $this->assertSame(403, $this->request($subscriber, 'GET', '/example/drafts')->getStatusCode());
        $this->assertSame(403, $this->request($subscriber, 'POST', '/example/drafts?title=Hello')->getStatusCode());
    }

    public function testPostWithoutTitleIs400(): void
    {
        $editor = new TestPrincipal(authenticated: true, capabilities: ['edit_posts']);

        // HTTP メソッドは TestRestaRequest の第 3 引数。post() の操作が選ばれ、その引数の宣言で検証される
        $this->assertSame(400, $this->request($editor, 'POST', '/example/drafts')->getStatusCode());
    }

    private function request(TestPrincipal $principal, string $method, string $url): RestaResponseInterface
    {
        $route = new Drafts();
        $route->setNamespace('example');

        $dispatcher = new Dispatcher();
        $dispatcher->addSubscriber(new AuthorizationHook($principal));

        return (new RequestHandler($dispatcher))->handle($route, new TestRestaRequest($url, $route, $method));
    }
}
