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

    public function testEmptyStringIsAValueForUnconstrainedString(): void
    {
        // ?keyword= は省略ではなく空文字。'string' は制約なしなので空文字のまま届く（#81）
        $response = $this->get('/wp-json/example/search', ['keyword' => '']);

        $this->assertResponseCode(200, $response);
        $this->assertSame('', $this->getJsonResponse($response)['keyword']);
    }

    public function testEmptyStringIsRejectedByExplicitRegex(): void
    {
        // 非空を要求するのは regex の役目。order の (asc|desc) に空文字は一致しない
        $response = $this->get('/wp-json/example/search', ['order' => '']);

        $this->assertResponseCode(400, $response);
        $this->assertSame('rest_invalid_param', $this->getJsonResponse($response)['code']);
    }

    public function testStringPathParameterAcceptsMultibyteAndSymbols(): void
    {
        // #81: 'string' のパスパラメータは [^/]+ で登録されるので、コアのルート照合（u なし）でも日本語が通る
        foreach (['日本語', 'foo-bar', 'a.b'] as $name) {
            $response = $this->get('/wp-json/example/hello/' . rawurlencode($name));
            $this->assertResponseCode(200, $response, $name);
            $this->assertSame("Hello, {$name}!", $this->getJsonResponse($response)['message']);
        }
    }

    public function testMultibyteStringIsAcceptedBothByCoreAndByWpResta(): void
    {
        // コアの pattern 検証（u 付き）と wp-resta の検証（u 付き）が揃っているので、
        // callback の引数で受けるかどうかに関係なく 'string' は日本語を受け付ける
        $response = $this->get('/wp-json/example/search', ['keyword' => '日本語']);
        $this->assertResponseCode(200, $response);
        $this->assertSame('日本語', $this->getJsonResponse($response)['keyword']);

        $response = $this->get('/wp-json/example/sample/1', ['name' => '日本語']);
        $this->assertResponseCode(200, $response);
        $this->assertSame('日本語', $this->getJsonResponse($response)['data']['name']);
    }

    public function testInvalidUtf8IsRejectedBothByCoreAndByWpResta(): void
    {
        // regex を持つパラメータは、不正な UTF-8 をコアの pattern 検証と wp-resta の検証の両方で 400 にする
        foreach (['/wp-json/example/search' => 'path', '/wp-json/example/sample/1' => 'a_or_b'] as $uri => $param) {
            $response = $this->get($uri, [$param => "\xff\xfe"]);
            $this->assertResponseCode(400, $response, $uri);
        }
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
