<?php
namespace Test\Resta;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\AbstractRoute;
use WPRestApi\PSR7\WP_REST_PSR7_Request;

class RegexInvalidParamTest extends TestCase
{
    public function testInvokeReturns500WhenRequiredParamValueDoesNotMatchRegex()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'item_ids' => [
                    'type' => 'string',
                    'required' => true,
                    'regex' => '^[\d,]+$',
                    'description' => 'item_ids',
                ],
            ];

            public function callback(string $item_ids): array
            {
                return ['item_ids' => $item_ids];
            }
        };

        $request = new WP_REST_PSR7_Request('GET', '/example/');
        // フルアンカーのregexに対して、スペースが混ざった不正な値を送る
        $request->set_url_params(['item_ids' => '1,2, 3']);

        $response = $route->invoke($request);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeDoesNotSilentlyFallBackToDefaultWhenOptionalParamValueDoesNotMatchRegex()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'item_ids' => [
                    'type' => 'string',
                    'required' => false,
                    'regex' => '^[\d,]+$',
                    'description' => 'item_ids',
                ],
            ];

            public function callback(?string $item_ids = null): array
            {
                return ['item_ids' => $item_ids];
            }
        };

        $request = new WP_REST_PSR7_Request('GET', '/example/');
        $request->set_url_params(['item_ids' => '1,2, 3']);

        $response = $route->invoke($request);

        // 不正な値を黙ってデフォルト値にフォールバックさせず、エラーにする
        $this->assertEquals(500, $response->getStatusCode());
    }
}
