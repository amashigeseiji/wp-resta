<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\Auth\Principal;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\Draft;
use Wp\Resta\REST\Hooks\AuthorizationHook;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * callback の中の認可を確かめる。
 *
 * callback の引数の Principal はコンテナから解決されるので、TestPrincipal を bind する。
 * Container はプロセスに 1 つなので、bind したテストは tearDown() でリセットする
 */
class DraftTest extends TestCase
{
    protected function tearDown(): void
    {
        (new ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);
        parent::tearDown();
    }

    public function testUnauthenticatedRequestIs401(): void
    {
        $this->assertSame(401, $this->request(new TestPrincipal())->getStatusCode());
    }

    public function testUserWhoCannotEditTheDraftIs403(): void
    {
        // callback は、下書きを取りに行く前に権限を確かめる。
        // 先に get_post() を呼ぶように書き換えると、WordPress が無いのでこのテストは落ちる
        $this->assertSame(403, $this->request(new TestPrincipal(authenticated: true))->getStatusCode());
    }

    private function request(TestPrincipal $principal): RestaResponseInterface
    {
        Container::getInstance()->bind(Principal::class, $principal);

        $route = new Draft();
        $route->setNamespace('example');

        $dispatcher = new Dispatcher();
        $dispatcher->addSubscriber(new AuthorizationHook($principal));

        return (new RequestHandler($dispatcher))->handle($route, new TestRestaRequest('/example/draft/5', $route));
    }
}
