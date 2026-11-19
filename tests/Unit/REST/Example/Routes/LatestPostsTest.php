<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\LatestPosts;
use Wp\Resta\REST\Example\Services\LatestPostsQuery;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

class LatestPostsTest extends TestCase
{
    public function testTooManyExcludedIdsIs400(): void
    {
        $route = new LatestPosts(new LatestPostsQuery());
        $route->setNamespace('example');

        // 上限は 10。WordPress を呼ぶ前に止まるので、WordPress 無しで確かめられる
        $url = '/example/posts/latest?exclude=' . implode(',', range(1, 11));
        $response = (new RequestHandler(new Dispatcher()))->handle($route, new TestRestaRequest($url, $route));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('rest_invalid_param', $response->getData()['code']);
    }
}
