<?php
namespace Test\Resta\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use PsrMock\Psr7\Request;
use Wp\Resta\REST\AbstractRoute;

class CustomizeHeaderTest extends TestCase
{
    public function testCustomizeHeader()
    {
        $route = new class extends AbstractRoute {
            protected array $headers = [
                'Cache-Control' => 'max-age=36400',
                'Access-Control-Allow-Origins' => 'http://example.com',
            ];
        };

        // Pure PSR-7 Request (WordPress independent)
        $request = new Request('GET', 'http://example.com/wp-json/example/');
        $response = $route->invoke($request);

        $this->assertInstanceOf(ResponseInterface::class, $response);

        // Check custom headers are set
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertArrayHasKey('Access-Control-Allow-Origins', $headers);
        $this->assertEquals(['max-age=36400'], $headers['Cache-Control']);
        $this->assertEquals(['http://example.com'], $headers['Access-Control-Allow-Origins']);
    }
}
