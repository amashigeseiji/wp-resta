<?php
namespace Test\Resta\Unit\Kernel;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Kernel\WpRestaServer;
use Wp\Resta\REST\AbstractProxyRoute;

class WpRestaServerTest extends TestCase
{
    protected function tearDown(): void
    {
        WpRestaServer::clearProxyRoutes();
        parent::tearDown();
    }

    public function testAddProxyRouteAndGetProxyRoutes(): void
    {
        $route = new class extends AbstractProxyRoute {
            protected const PROXY_PATH = '/wp/v2/posts';
        };

        WpRestaServer::addProxyRoute($route);

        $routes = WpRestaServer::getProxyRoutes();
        $this->assertCount(1, $routes);
        $this->assertSame($route, $routes[0]);
    }

    public function testClearProxyRoutes(): void
    {
        $route = new class extends AbstractProxyRoute {
            protected const PROXY_PATH = '/wp/v2/posts';
        };

        WpRestaServer::addProxyRoute($route);
        WpRestaServer::clearProxyRoutes();

        $this->assertEmpty(WpRestaServer::getProxyRoutes());
    }

    public function testMultipleRoutesCanBeAdded(): void
    {
        $route1 = new class extends AbstractProxyRoute {
            protected const PROXY_PATH = '/wp/v2/posts';
        };
        $route2 = new class extends AbstractProxyRoute {
            protected const PROXY_PATH = '/wp/v2/users';
        };

        WpRestaServer::addProxyRoute($route1);
        WpRestaServer::addProxyRoute($route2);

        $routes = WpRestaServer::getProxyRoutes();
        $this->assertCount(2, $routes);
    }

    public function testGetProxyRoutesReturnsEmptyByDefault(): void
    {
        $this->assertEmpty(WpRestaServer::getProxyRoutes());
    }
}
