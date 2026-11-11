<?php
namespace Test\Resta\Unit\REST\Http;

use PHPUnit\Framework\Attributes\DataProvider;
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

        $request = WpRestaRequest::fromWpRequest($wp, pathParamsAreEncoded: true);

        $this->assertSame('日本語', $request->getUrlParam('name'));
        $this->assertSame(123, $request->getUrlParam('id'));
        $this->assertSame('a%20b', $request->getUrlParam('q'));
        $this->assertNull($request->getUrlParam('missing'));
    }

    public function testGetHeaderDelegatesToWpRequest(): void
    {
        $wp = new WP_REST_Request();
        $wp->set_header('If-None-Match', '"abc"');

        $request = WpRestaRequest::fromWpRequest($wp, pathParamsAreEncoded: false);

        $this->assertSame('"abc"', $request->getHeader('if-none-match'));
        $this->assertNull($request->getHeader('X-Missing'));
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

    /**
     * @return array<string, array{string|null, string, string}>
     */
    public static function requestOrigins(): array
    {
        // [REQUEST_URI, ルート文字列, 期待値]。URL に %2541 と書いたときにパスパラメータとして届く値は %2541（パーマリンク形式）か %41（それ以外）
        return [
            'パーマリンク形式' => ['/wp-json/example/hello/%2541', '/example/hello/%2541', '%41'],
            'パーマリンク形式・末尾スラッシュ' => ['/wp-json/example/hello/%2541/', '/example/hello/%2541', '%41'],
            'サブディレクトリ設置' => ['/blog/wp-json/example/hello/%2541', '/example/hello/%2541', '%41'],
            'index.php を含む形式' => ['/index.php/wp-json/example/hello/%2541', '/example/hello/%2541', '%41'],
            '?rest_route= 形式（PHP がデコード済み）' => ['/?rest_route=/example/hello/%2541', '/example/hello/%41', '%41'],
            '別ルートの処理中に rest_do_request() で組み立てた内部リクエスト' => ['/wp-json/example/other', '/example/hello/%41', '%41'],
            'HTTP リクエストの外（CLI など）' => [null, '/example/hello/%41', '%41'],
        ];
    }

    #[DataProvider('requestOrigins')]
    public function testDecodesOnlyWhenRouteIsTakenFromRequestPath(?string $requestUri, string $route, string $expected): void
    {
        $previous = $_SERVER['REQUEST_URI'] ?? null;
        if ($requestUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $requestUri;
        }
        try {
            $wp = new WP_REST_Request();
            $wp->set_route($route);
            $value = substr($route, strlen('/example/hello/'));
            $wp->set_url_params(['name' => $value]);
            $wp['name'] = $value;

            $this->assertSame($expected, WpRestaRequest::fromWpRequest($wp)->getUrlParam('name'));
        } finally {
            if ($previous === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previous;
            }
        }
    }
}
