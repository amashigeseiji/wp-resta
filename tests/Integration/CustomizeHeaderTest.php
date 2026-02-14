<?php
namespace Test\Resta\Integration;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;

class CustomizeHeaderTest extends TestCase
{
    public function testCustomizeHeader()
    {
        $route = new class extends AbstractRoute {
            protected array $headers = [
                'Cache-Control' => 'max-age=36400',
                'Access-Control-Allow-Origins' => 'http://example.com',
            ];

            public function callback(): string
            {
                return 'test response';
            }
        };

        // WordPress 非依存のテストリクエスト
        $request = new TestRestaRequest('/example/test', $route);
        $response = $route->invoke($request);

        $this->assertInstanceOf(RestaResponseInterface::class, $response);

        // ヘッダーの確認
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertEquals('max-age=36400', $headers['Cache-Control']);
        $this->assertArrayHasKey('Access-Control-Allow-Origins', $headers);
        $this->assertEquals('http://example.com', $headers['Access-Control-Allow-Origins']);

        // データを直接取得できる
        $this->assertEquals('test response', $response->getData());
        $this->assertEquals(200, $response->getStatusCode());
    }
}
