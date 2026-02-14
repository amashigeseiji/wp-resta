<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use PsrMock\Psr7\Request;
use Wp\Resta\REST\AbstractRoute;
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

        // TestRestaRequest が自動的にパスパラメータをパース
        $request = new TestRestaRequest(
            new Request('GET', 'http://example.com/wp-json/myroute/hello/amashige'),
            $route
        );
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello, amashige!', (string)$response->getBody());
    }
}
