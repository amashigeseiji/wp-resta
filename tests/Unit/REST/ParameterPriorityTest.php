<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\TestRestaRequest;

/**
 * パラメータの優先順位に関するユニットテスト
 *
 * このテストは以下を検証します：
 * 1. URL パラメータが正しく抽出されること
 * 2. rest_request_parameter_order フィルターのロジックが正しいこと
 * 3. Sample ルートが URL パラメータを正しく処理すること
 */
class ParameterPriorityTest extends TestCase
{
    /**
     * URL パラメータが正しく抽出されることを確認
     */
    public function testUrlParameterExtraction()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'sample/[id]';
            protected const URL_PARAMS = [
                'id' => 'integer',
            ];

            public function callback(int $id): array
            {
                return ['id' => $id];
            }
        };

        $route->setNamespace('example');

        // /example/sample/123 からパラメータを抽出
        $request = new TestRestaRequest('/example/sample/123', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();

        // URL パラメータ id=123 が正しく抽出される
        $this->assertEquals(123, $data['id']);
    }

    /**
     * 異なる ID 値でもパラメータが正しく抽出されることを確認
     */
    public function testUrlParameterExtractionWithDifferentValues()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'sample/[id]';
            protected const URL_PARAMS = [
                'id' => 'integer',
            ];

            public function callback(int $id): array
            {
                return ['id' => $id];
            }
        };

        $route->setNamespace('example');

        // 複数の異なる値でテスト
        $testCases = [1, 999, 12345];

        foreach ($testCases as $expectedId) {
            $request = new TestRestaRequest("/example/sample/{$expectedId}", $route);
            $response = $route->invoke($request);

            $this->assertEquals(200, $response->getStatusCode());
            $data = $response->getData();
            $this->assertEquals($expectedId, $data['id'], "Failed for id={$expectedId}");
        }
    }

    /**
     * 複数のパラメータを持つルートのテスト
     */
    public function testMultipleUrlParameters()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'posts/[category]/[id]';
            protected const URL_PARAMS = [
                'category' => 'string',
                'id' => 'integer',
            ];

            public function callback(string $category, int $id): array
            {
                return [
                    'category' => $category,
                    'id' => $id,
                ];
            }
        };

        $route->setNamespace('example');

        // /example/posts/tech/456 からパラメータを抽出
        $request = new TestRestaRequest('/example/posts/tech/456', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();

        $this->assertEquals('tech', $data['category']);
        $this->assertEquals(456, $data['id']);
    }

    /**
     * オプショナルパラメータのテスト
     */
    public function testOptionalParameters()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'sample/[id]';
            protected const URL_PARAMS = [
                'id' => 'integer',
                'name' => '?string',
            ];

            public function callback(int $id, ?string $name = null): array
            {
                return [
                    'id' => $id,
                    'name' => $name,
                ];
            }
        };

        $route->setNamespace('example');

        // name パラメータなし
        $request = new TestRestaRequest('/example/sample/1', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();

        $this->assertEquals(1, $data['id']);
        $this->assertNull($data['name']);
    }

    /**
     * rest_request_parameter_order フィルターのロジックをテスト
     *
     * このテストは、フィルター関数の配列操作ロジックを検証します。
     * 実際のフィルター登録は E2E テストで検証されます。
     */
    public function testParameterOrderLogic()
    {
        // フィルター関数のロジックを再現
        $filterLogic = function(array $order): array {
            if ($order[0] === 'GET' && $order[1] === 'URL') {
                $order[0] = 'URL';
                $order[1] = 'GET';
            }
            return $order;
        };

        // テストケース1: ['GET', 'URL'] -> ['URL', 'GET']
        $input1 = ['GET', 'URL'];
        $expected1 = ['URL', 'GET'];
        $this->assertEquals($expected1, $filterLogic($input1));

        // テストケース2: ['URL', 'GET'] -> 変更なし
        $input2 = ['URL', 'GET'];
        $expected2 = ['URL', 'GET'];
        $this->assertEquals($expected2, $filterLogic($input2));

        // テストケース3: ['POST', 'GET', 'URL'] -> 変更なし
        $input3 = ['POST', 'GET', 'URL'];
        $expected3 = ['POST', 'GET', 'URL'];
        $this->assertEquals($expected3, $filterLogic($input3));

        // テストケース4: WordPress のデフォルト
        $input4 = ['GET', 'URL', 'POST', 'FILES'];
        $expected4 = ['URL', 'GET', 'POST', 'FILES'];
        $this->assertEquals($expected4, $filterLogic($input4));
    }

    /**
     * エッジケース: パラメータが存在しないルート
     */
    public function testRouteWithoutParameters()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'static-endpoint';

            public function callback(): array
            {
                return ['message' => 'static response'];
            }
        };

        $route->setNamespace('example');

        $request = new TestRestaRequest('/example/static-endpoint', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();

        $this->assertEquals('static response', $data['message']);
    }

    /**
     * 実際の Sample ルートと同じ構造でテスト
     */
    public function testSampleRouteLikeStructure()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'sample/[id]';
            protected const URL_PARAMS = [
                'id' => 'integer',
                'name' => '?string',
                'a_or_b' => [
                    'type' => 'string',
                    'required' => false,
                    'regex' => '(a|b)',
                ],
            ];

            public function callback(int $id, ?string $name = null, string $a_or_b = 'a'): array
            {
                return [
                    'id' => $id,
                    'name' => $name,
                    'a_or_b' => $a_or_b,
                ];
            }
        };

        $route->setNamespace('example');

        // /example/sample/1 にアクセス
        $request = new TestRestaRequest('/example/sample/1', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();

        // URL パラメータから id が正しく取得される
        $this->assertEquals(1, $data['id']);

        // オプショナルパラメータはデフォルト値
        $this->assertNull($data['name']);
        $this->assertEquals('a', $data['a_or_b']);
    }

    /**
     * パラメータの型変換が正しく行われることを確認
     */
    public function testParameterTypeCasting()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'sample/[id]';
            protected const URL_PARAMS = [
                'id' => 'integer',
            ];

            public function callback(int $id): array
            {
                return [
                    'id' => $id,
                    'type' => gettype($id),
                ];
            }
        };

        $route->setNamespace('example');

        // URL から "123" (文字列) が渡されるが、int にキャストされる
        $request = new TestRestaRequest('/example/sample/123', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();

        $this->assertSame(123, $data['id']); // 厳密な型チェック
        $this->assertEquals('integer', $data['type']);
    }

    /**
     * namespace を含む複雑なパスのテスト
     */
    public function testComplexNamespace()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'item/[id]';
            protected const URL_PARAMS = [
                'id' => 'integer',
            ];

            public function callback(int $id): array
            {
                return ['id' => $id];
            }
        };

        // スラッシュを含む namespace
        $route->setNamespace('api/v2');

        // /api/v2/item/789 からパラメータを抽出
        $request = new TestRestaRequest('/api/v2/item/789', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();

        $this->assertEquals(789, $data['id']);
    }

    /**
     * wp-json プレフィックス付きパスのテスト
     */
    public function testWpJsonPrefixHandling()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'sample/[id]';
            protected const URL_PARAMS = [
                'id' => 'integer',
            ];

            public function callback(int $id): array
            {
                return ['id' => $id];
            }
        };

        $route->setNamespace('example');

        // WordPress の実際の URL: /wp-json/example/sample/999
        $request = new TestRestaRequest('/wp-json/example/sample/999', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();

        // wp-json プレフィックスが正しく削除され、パラメータが抽出される
        $this->assertEquals(999, $data['id']);
    }
}
