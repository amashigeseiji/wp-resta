<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\RequestHandler;
use Wp\Resta\REST\Http\TestRestaRequest;

class HelloWorldWithParamTest extends TestCase
{
    public function testHelloWorldWithUrlParam()
    {
        // README のサンプルと同じ構造
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'hello/[name]';
            protected const URL_PARAMS = [
                'name' => 'string',
            ];

            public function callback(string $name): string
            {
                return "Hello, {$name}!";
            }
        };

        // namespace を設定（パスと一致させる）
        $route->setNamespace('myroute');

        // TestRestaRequest が自動的にパスパラメータをパース
        $request = new TestRestaRequest('/myroute/hello/amashige', $route);
        $response = (new RequestHandler(new TestPrincipal(), new Dispatcher()))->handle($route, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello, amashige!', $response->getData());
    }
}
