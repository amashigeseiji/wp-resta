<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * #[Cache] の付いた fixture/posts に対する Cache-Control / ETag / 304（#78）
 */
class CacheTest extends AbstractE2ETestCase
{
    public function testPostsHasCacheHeaders(): void
    {
        $response = $this->get('/wp-json/fixture/posts');

        $this->assertResponseCode(200, $response);
        $this->assertSame('public, max-age=60', $response->getHeaderLine('Cache-Control'));
        $this->assertMatchesRegularExpression('/^"[0-9a-f]+"$/', $response->getHeaderLine('ETag'));
    }

    public function testIfNoneMatchReturns304WithoutBody(): void
    {
        $etag = $this->get('/wp-json/fixture/posts')->getHeaderLine('ETag');

        $response = $this->get('/wp-json/fixture/posts', [], ['If-None-Match' => $etag]);

        $this->assertResponseCode(304, $response);
        $this->assertSame('', (string)$response->getBody());
        $this->assertSame($etag, $response->getHeaderLine('ETag'));
    }

    public function testRouteWithoutCacheHasNoCacheHeaders(): void
    {
        $response = $this->get('/wp-json/fixture/post/1');

        $this->assertResponseCode(200, $response);
        $this->assertSame('', $response->getHeaderLine('ETag'));
    }
}
