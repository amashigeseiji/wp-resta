<?php
namespace Test\Resta\Unit\REST\Example\Hooks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Wp\Resta\REST\Example\Hooks\SampleHook;

class SampleHookTest extends TestCase
{
    private SampleHook $hook;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('current_time')->justReturn('2026-11-11 00:00:00');
        $this->hook = new SampleHook();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * rest_request_after_callbacks には callback の生の戻り値が渡る。
     * WP コアの /wp/v2/settings のように配列を返すルートでも落ちない（#94）
     */
    public function testArrayResponseFromCoreRouteIsReturnedAsIs(): void
    {
        $request = new WP_REST_Request();
        $request->set_route('/wp/v2/settings');
        $data = ['title' => 'My Site'];

        $result = $this->hook->addMetaToResponse($data, [], $request);

        $this->assertSame($data, $result);
    }

    public function testWpErrorIsReturnedAsIs(): void
    {
        $error = new WP_Error('rest_forbidden', 'forbidden');

        $result = $this->hook->addMetaToResponse($error, [], new WP_REST_Request());

        $this->assertSame($error, $result);
    }

    public function testEnvelopeResponseGetsMeta(): void
    {
        $request = new WP_REST_Request();
        $request->set_route('/myapi/items');
        $response = new WP_REST_Response(['data' => ['id' => 1], 'meta' => []]);

        $result = $this->hook->addMetaToResponse($response, [], $request);

        $this->assertSame($response, $result);
        $meta = $result->get_data()['meta'];
        $this->assertSame('2026-11-11 00:00:00', $meta['processed_at']);
        $this->assertSame('/myapi/items', $meta['request_route']);
    }

    public function testNonEnvelopeResponseIsUntouched(): void
    {
        $response = new WP_REST_Response(['id' => 1]);

        $result = $this->hook->addMetaToResponse($response, [], new WP_REST_Request());

        $this->assertSame(['id' => 1], $result->get_data());
    }
}
