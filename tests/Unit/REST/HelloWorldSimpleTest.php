<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use PsrMock\Psr7\Request;
use Wp\Resta\REST\AbstractRoute;

class HelloWorldSimpleTest extends TestCase
{
    public function testHelloWorldWithBodyProperty()
    {
        // README のサンプルと同じ構造
        $route = new class extends AbstractRoute {
            protected $body = 'Hello, world!';
        };

        $request = new Request('GET', 'http://example.com/wp-json/myroute/helloworld');
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello, world!', (string)$response->getBody());
    }
}
