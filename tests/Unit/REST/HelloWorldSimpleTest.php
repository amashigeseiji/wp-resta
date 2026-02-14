<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\TestRestaRequest;

class HelloWorldSimpleTest extends TestCase
{
    public function testHelloWorldWithBodyProperty()
    {
        // README のサンプルと同じ構造
        $route = new class extends AbstractRoute {
            protected $body = 'Hello, world!';
        };

        $request = new TestRestaRequest('/myroute/helloworld', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello, world!', $response->getData());
    }
}
