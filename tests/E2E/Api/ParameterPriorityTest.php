<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * rest_request_parameter_order フィルターの動作を検証するE2Eテスト
 *
 * このテストは、Route.php の register() メソッド内で
 * add_filter('rest_request_parameter_order', ...) が正しく動作しているかを検証します。
 *
 * テストシナリオ:
 * - ルート定義: /example/sample/[id]
 * - リクエスト: /example/sample/1?id=30
 * - 期待される挙動:
 *   - フィルターあり（現在の実装）: URLパラメータ(1)が優先される
 *   - フィルターなし（WordPressデフォルト）: クエリパラメータ(30)が優先される
 */
class ParameterPriorityTest extends AbstractE2ETestCase
{
    /**
     * URLパラメータがクエリパラメータより優先されることを確認
     *
     * /example/sample/1?id=30 のようなリクエストで、
     * 埋め込みパラメータ (1) がクエリパラメータ (id=30) より優先される
     */
    public function testUrlParameterTakesPriorityOverQueryParameter(): void
    {
        // /example/sample/1?id=30 にアクセス
        // URLパラメータ: id=1
        // クエリパラメータ: id=30
        $response = $this->get('/wp-json/example/sample/1', [
            'id' => '30',  // クエリパラメータとして id=30 を送信
            'name' => 'test',
            'a_or_b' => 'a',
        ]);

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        // URLパラメータ (1) が優先されることを確認
        // フィルターが動作していない場合、このアサーションは失敗し、
        // id=30 (クエリパラメータの値) が返される
        $this->assertEquals(
            1,
            $data['id'],
            'URL parameter (1) should take priority over query parameter (30). ' .
            'If this fails, the rest_request_parameter_order filter may not be working.'
        );
    }

    /**
     * 複数の異なるURLパラメータでも同じ挙動を確認
     */
    public function testUrlParameterPriorityWithDifferentIds(): void
    {
        // /example/sample/999?id=123 にアクセス
        $response = $this->get('/wp-json/example/sample/999', [
            'id' => '123',
        ]);

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        // URLパラメータ (999) が優先されることを確認
        $this->assertEquals(
            999,
            $data['id'],
            'URL parameter (999) should take priority over query parameter (123)'
        );
    }

    /**
     * クエリパラメータのみの場合は正常に動作することを確認
     */
    public function testQueryParameterWorksWhenNoConflict(): void
    {
        // /example/sample/5?name=test のように、
        // 競合しないクエリパラメータは正常に動作する
        $response = $this->get('/wp-json/example/sample/5', [
            'name' => 'test_name',
            'a_or_b' => 'b',
        ]);

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        // URLパラメータ
        $this->assertEquals(5, $data['id']);

        // クエリパラメータは正常に機能する
        $this->assertEquals('test_name', $data['name']);
        $this->assertEquals('b', $data['a_or_b']);
    }

    /**
     * 極端なケース: クエリパラメータで大きな数値を指定した場合
     */
    public function testUrlParameterPriorityWithLargeQueryValue(): void
    {
        // /example/sample/1?id=999999 にアクセス
        $response = $this->get('/wp-json/example/sample/1', [
            'id' => '999999',
            'name' => 'edge_case',
        ]);

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        // URLパラメータ (1) が優先される
        $this->assertEquals(
            1,
            $data['id'],
            'URL parameter (1) should take priority even with large query parameter (999999)'
        );

        // 他のクエリパラメータは正常に機能
        $this->assertEquals('edge_case', $data['name']);
    }

    /**
     * Post ルートでも同じ挙動を確認
     */
    public function testParameterPriorityInPostRoute(): void
    {
        // /example/post/1?id=50 にアクセス
        $response = $this->get('/wp-json/example/post/1', [
            'id' => '50',
        ]);

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        // URLパラメータ (1) が優先される
        $this->assertArrayHasKey('post', $data);
        $this->assertEquals(1, $data['post']['ID']);
    }
}
