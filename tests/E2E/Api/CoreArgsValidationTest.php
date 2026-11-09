<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * URL_PARAMS の regex が pattern として WP コアに渡り、callback の引数に無い
 * クエリパラメータでもコア側で検証されることを確認する E2E テスト。
 *
 * /example/search の callback は WP_REST_Request を受け取るだけで、
 * keyword / order / path を引数に持たない。wp-resta 側の invokeCallback() の
 * 検証は通らないので、400 になるならコアの rest_validate_request_arg によるもの。
 */
class CoreArgsValidationTest extends AbstractE2ETestCase
{
    public function testValidQueryParametersPassThrough(): void
    {
        $response = $this->get('/wp-json/example/search', [
            'keyword' => 'hello',
            'order' => 'desc',
            'path' => 'foo/bar',
        ]);

        $this->assertResponseCode(200, $response);
        $this->assertSame(
            ['keyword' => 'hello', 'order' => 'desc', 'path' => 'foo/bar'],
            $this->getJsonResponse($response),
        );
    }

    public function testOmittedOptionalParametersAreNull(): void
    {
        $response = $this->get('/wp-json/example/search');

        $this->assertResponseCode(200, $response);
        $this->assertSame(
            ['keyword' => null, 'order' => 'asc', 'path' => null],
            $this->getJsonResponse($response),
        );
    }

    public function testQueryParameterNotInCallbackSignatureIsValidatedByCore(): void
    {
        // アンカー無しだと (asc|desc) に部分一致して通ってしまう値
        $response = $this->get('/wp-json/example/search', ['order' => 'zzz-asc-zzz']);

        $this->assertResponseCode(400, $response);
        $data = $this->getJsonResponse($response);
        $this->assertSame('rest_invalid_param', $data['code']);
        $this->assertArrayHasKey('order', $data['data']['params']);
    }

    public function testEmptyStringDoesNotMatchDefaultStringPattern(): void
    {
        // ?keyword= は省略ではなく空文字。\w+ に一致しないので 400
        $response = $this->get('/wp-json/example/search', ['keyword' => '']);

        $this->assertResponseCode(400, $response);
        $this->assertSame('rest_invalid_param', $this->getJsonResponse($response)['code']);
    }

    public function testSlashInRegexWorksAsCorePattern(): void
    {
        $response = $this->get('/wp-json/example/search', ['path' => 'Foo/Bar']);

        // 大文字は [a-z/]+ に一致しない。/ を含む regex がコア側で壊れていれば常に 400 になるので、
        // 上の testValidQueryParametersPassThrough と合わせて正しく動いていることが分かる
        $this->assertResponseCode(400, $response);
        $this->assertArrayHasKey('path', $this->getJsonResponse($response)['data']['params']);
    }
}
