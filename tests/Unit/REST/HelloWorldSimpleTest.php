<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

class HelloWorldSimpleTest extends TestCase
{
    public function testHelloWorldWithCallback()
    {
        // README のサンプルと同じ構造
        $route = new class extends AbstractRoute {
            public function callback(): string
            {
                return 'Hello, world!';
            }
        };

        $request = new TestRestaRequest('/myroute/helloworld', $route);
        $response = (new RequestHandler(new TestPrincipal(), new Dispatcher()))->handle($route, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello, world!', $response->getData());
    }

    public function testStaticRouteReturnsItsBody()
    {
        $route = new class extends AbstractRoute {
            public function __construct(public readonly string $body = 'Hello, world!') {}
        };

        $request = new TestRestaRequest('/myroute/helloworld', $route);
        $response = (new RequestHandler(new TestPrincipal(), new Dispatcher()))->handle($route, $request);

        $this->assertEquals('Hello, world!', $response->getData());
    }
}
