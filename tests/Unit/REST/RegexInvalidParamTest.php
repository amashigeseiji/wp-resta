<?php
namespace Test\Resta\Unit\REST;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\AbstractRoute;
use WPRestApi\PSR7\WP_REST_PSR7_Request;

class RegexInvalidParamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        require_once __DIR__ . '/../../../wordpress/wp-includes/class-wp-http-response.php';
        require_once __DIR__ . '/../../../wordpress/wp-includes/rest-api/class-wp-rest-response.php';
        require_once __DIR__ . '/../../../wordpress/wp-includes/rest-api/class-wp-rest-request.php';
        Functions\when('apply_filters')->returnArg(2);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

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
