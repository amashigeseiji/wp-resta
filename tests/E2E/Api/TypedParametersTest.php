<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * callback のシグニチャから宣言したパラメータが、WordPress コアを経由しても wp-resta の規則で
 * 型変換されて届くことを確認する（/example/items は URL_PARAMS を持たない）
 */
class TypedParametersTest extends AbstractE2ETestCase
{
    public function testDefaultsApplyWhenOmitted(): void
    {
        $response = $this->get('/wp-json/example/items');

        $this->assertResponseCode(200, $response);
        $this->assertSame(
            ['page' => 1, 'per_page' => 10, 'published' => true, 'sort' => 'asc', 'tags' => [], 'slug' => ''],
            $this->getJsonResponse($response),
        );
    }

    public function testValuesAreConvertedToDeclaredTypes(): void
    {
        $response = $this->client->get('/wp-json/example/items?page=2&per_page=50&published=false&sort=desc&tags=a,b&slug=hello-world');

        $this->assertResponseCode(200, $response);
        $this->assertSame(
            ['page' => 2, 'per_page' => 50, 'published' => false, 'sort' => 'desc', 'tags' => ['a', 'b'], 'slug' => 'hello-world'],
            $this->getJsonResponse($response),
        );
    }

    public function testArrayAcceptsRepeatedQueryKey(): void
    {
        $response = $this->client->get('/wp-json/example/items?tags[]=x&tags[]=y');

        $this->assertResponseCode(200, $response);
        $this->assertSame(['x', 'y'], $this->getJsonResponse($response)['tags']);
    }

    /** @return array<string, array{string}> */
    public static function invalidQueries(): array
    {
        return [
            'integer に文字列' => ['page=abc'],
            'boolean に yes' => ['published=yes'],
            'enum 外' => ['sort=sideways'],
            'maximum 超過' => ['per_page=101'],
            'pattern 不一致' => ['slug=Hello'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidQueries')]
    public function testInvalidValueIs400(string $query): void
    {
        $response = $this->client->get('/wp-json/example/items?' . $query);

        $this->assertResponseCode(400, $response);
        $this->assertSame(400, $this->getJsonResponse($response)['data']['status']);
    }
}
