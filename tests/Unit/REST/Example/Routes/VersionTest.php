<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\Version;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

class VersionTest extends TestCase
{
    public function testBodyPropertyIsTheResponse(): void
    {
        $route = new Version();
        $route->setNamespace('example');

        $response = (new RequestHandler(new Dispatcher()))->handle($route, new TestRestaRequest('/example/version', $route));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['version' => '1.0'], $response->getData());
    }
}
