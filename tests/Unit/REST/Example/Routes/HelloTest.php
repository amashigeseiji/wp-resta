<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\Hello;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * いちばん小さいテストの手本。Route クラスを new して、URL を渡し、応答を確かめる
 */
class HelloTest extends TestCase
{
    public function testPathParameterIsPassedToCallback(): void
    {
        $route = new Hello();
        // setNamespace() を呼ばないと、URL の namespace 部分が一致せずパラメータが取れない
        $route->setNamespace('example');

        $response = (new RequestHandler(new Dispatcher()))->handle($route, new TestRestaRequest('/example/hello/world', $route));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Hello, world!'], $response->getData());
    }
}
