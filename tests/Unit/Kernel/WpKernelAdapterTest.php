<?php
namespace Test\Resta\Unit\Kernel;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\Kernel\WpKernelAdapter;

/**
 * WpKernelAdapter のユニットテスト
 *
 * WordPress フック（add_action/add_filter）の登録は install() が担うため
 * WP 依存のテストは Integration に委ね、ここでは WP 非依存のロジックをテストする。
 */
class WpKernelAdapterTest extends TestCase
{
    private WpKernelAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new WpKernelAdapter(new Dispatcher(), new Config([]));
    }

    // --- prioritizeUrlParameters ---

    /**
     * URL スロットに値を持つリクエスト（dispatch 後の [id] 付きルート）
     */
    private function requestWithUrlParams(): \WP_REST_Request
    {
        $request = new \WP_REST_Request();
        $request->set_url_params(['id' => '123']);
        return $request;
    }

    /**
     * URL スロットが空のリクエスト（dispatch 前の内部リクエスト、名前付きキャプチャ無しのルート）
     */
    private function requestWithoutUrlParams(): \WP_REST_Request
    {
        return new \WP_REST_Request();
    }

    // GET

    public function testSwapsGetAndUrlWhenGetIsFirst(): void
    {
        $order  = ['GET', 'URL', 'POST', 'COOKIE'];
        $result = $this->adapter->prioritizeUrlParameters($order, $this->requestWithUrlParams());

        $this->assertSame(['URL', 'GET', 'POST', 'COOKIE'], $result);
    }

    public function testDoesNotChangeOrderWhenUrlIsAlreadyFirst(): void
    {
        $order  = ['URL', 'GET', 'POST', 'COOKIE'];
        $result = $this->adapter->prioritizeUrlParameters($order, $this->requestWithUrlParams());

        $this->assertSame(['URL', 'GET', 'POST', 'COOKIE'], $result);
    }

    public function testPreservesOtherElementsUnchanged(): void
    {
        $order  = ['GET', 'URL', 'COOKIE', 'POST', 'FILE'];
        $result = $this->adapter->prioritizeUrlParameters($order, $this->requestWithUrlParams());

        $this->assertSame(['URL', 'GET', 'COOKIE', 'POST', 'FILE'], $result);
    }

    // GET 以外（B）

    public function testMovesUrlToFrontForPostOrder(): void
    {
        // POST / PUT / PATCH / DELETE の順序
        $order  = ['POST', 'GET', 'URL', 'defaults'];
        $result = $this->adapter->prioritizeUrlParameters($order, $this->requestWithUrlParams());

        $this->assertSame(['URL', 'POST', 'GET', 'defaults'], $result);
    }

    public function testMovesUrlToFrontForJsonBodyOrder(): void
    {
        // JSON ボディ付きリクエストの順序
        $order  = ['JSON', 'POST', 'GET', 'URL', 'defaults'];
        $result = $this->adapter->prioritizeUrlParameters($order, $this->requestWithUrlParams());

        $this->assertSame(['URL', 'JSON', 'POST', 'GET', 'defaults'], $result);
    }

    public function testDoesNotChangeOrderWhenUrlSlotIsAbsent(): void
    {
        $order  = ['POST', 'GET', 'defaults'];
        $result = $this->adapter->prioritizeUrlParameters($order, $this->requestWithUrlParams());

        $this->assertSame(['POST', 'GET', 'defaults'], $result);
    }

    // 適用範囲のガード（A）

    public function testDoesNotChangeOrderWhenRequestHasNoUrlParams(): void
    {
        $order  = ['GET', 'URL', 'defaults'];
        $result = $this->adapter->prioritizeUrlParameters($order, $this->requestWithoutUrlParams());

        $this->assertSame(['GET', 'URL', 'defaults'], $result);
    }

    public function testDoesNotChangeOrderForPostRequestWithoutUrlParams(): void
    {
        $order  = ['POST', 'GET', 'URL', 'defaults'];
        $result = $this->adapter->prioritizeUrlParameters($order, $this->requestWithoutUrlParams());

        $this->assertSame(['POST', 'GET', 'URL', 'defaults'], $result);
    }

    public function testDoesNotChangeOrderWhenRequestIsNotGiven(): void
    {
        // accepted_args が 1 のまま呼ばれた場合など、request が渡らないときは触らない
        $order  = ['GET', 'URL', 'defaults'];
        $result = $this->adapter->prioritizeUrlParameters($order);

        $this->assertSame(['GET', 'URL', 'defaults'], $result);
    }
}
