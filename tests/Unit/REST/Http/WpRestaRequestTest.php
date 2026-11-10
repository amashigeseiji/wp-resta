<?php
namespace Test\Resta\Unit\REST\Http;

use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use Wp\Resta\REST\Http\WpRestaRequest;

class WpRestaRequestTest extends TestCase
{
    public function testDecodesPathParametersOnly(): void
    {
        // パーマリンク有効時、WP はパスパラメータをパーセントエンコードのまま渡す。クエリパラメータはデコード済み
        $wp = new WP_REST_Request();
        $wp->set_url_params(['name' => '%E6%97%A5%E6%9C%AC%E8%AA%9E', 'id' => '123']);
        $wp['name'] = '%E6%97%A5%E6%9C%AC%E8%AA%9E';
        $wp['id'] = 123; // コアの type: integer による変換後の値
        $wp['q'] = 'a%20b'; // クエリパラメータの値はそのまま

        $request = WpRestaRequest::fromWpRequest($wp);

        $this->assertSame('日本語', $request->getUrlParam('name'));
        $this->assertSame(123, $request->getUrlParam('id'));
        $this->assertSame('a%20b', $request->getUrlParam('q'));
        $this->assertNull($request->getUrlParam('missing'));
    }

    public function testAlreadyDecodedPathParameterIsUnchanged(): void
    {
        // ?rest_route= 形式では WP がデコード済みで渡してくる。その場合も同じ値になる
        $wp = new WP_REST_Request();
        $wp->set_url_params(['name' => '日本語']);
        $wp['name'] = '日本語';

        $this->assertSame('日本語', WpRestaRequest::fromWpRequest($wp)->getUrlParam('name'));
    }
}
