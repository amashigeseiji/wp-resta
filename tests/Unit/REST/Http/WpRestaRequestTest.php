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

    public function testDoesNotDecodeTwiceWhenPathParametersArriveDecoded(): void
    {
        // ?rest_route= 形式では WP がデコード済みで渡してくる。%2541 → %41 で届いた値を A にしてはいけない
        $wp = new WP_REST_Request();
        $wp->set_url_params(['name' => '%41']);
        $wp['name'] = '%41';

        $this->assertSame('%41', WpRestaRequest::fromWpRequest($wp, pathParamsAreEncoded: false)->getUrlParam('name'));
        // パーマリンク形式なら URL に書かれた %2541 が %2541 のまま届くので、1 回のデコードで同じ %41 になる
        $wp['name'] = '%2541';
        $wp->set_url_params(['name' => '%2541']);
        $this->assertSame('%41', WpRestaRequest::fromWpRequest($wp, pathParamsAreEncoded: true)->getUrlParam('name'));
    }

    public function testDetectsQueryFormByRestRouteInGet(): void
    {
        $wp = new WP_REST_Request();
        $wp->set_url_params(['name' => '%41']);
        $wp['name'] = '%41';

        $_GET['rest_route'] = '/example/hello/%41';
        try {
            $this->assertSame('%41', WpRestaRequest::fromWpRequest($wp)->getUrlParam('name'));
        } finally {
            unset($_GET['rest_route']);
        }
        $this->assertSame('A', WpRestaRequest::fromWpRequest($wp)->getUrlParam('name'));
    }
}
