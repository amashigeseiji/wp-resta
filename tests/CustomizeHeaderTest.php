<?php
namespace Test\Resta;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use PsrMock\Psr7\Request;
use Wp\Resta\REST\AbstractRoute;

class CustomizeHeaderTest extends TestCase
{
    public function testCustomizeHeader()
    {
        $class = new class extends AbstractRoute {
            protected array $headers = [
                'Cache-Control' => 'max-age=36400',
                'Access-Control-Allow-Origins' => 'http://example.com',
            ];
        };
        $req = new Request('GET', 'http://example.com/wp-json/example/');
        $response = $class->invoke($req);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertArrayHasKey('Cache-Control', $response->getHeaders());
        $this->assertArrayHasKey('Access-Control-Allow-Origins', $response->getHeaders());
    }
}
