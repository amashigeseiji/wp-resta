<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * src/REST/Example の手本が、実際の WordPress の上で動くことを確かめる。
 *
 * 手本の Route クラスは tests/Unit/REST/Example/ が WordPress 無しで確かめている。
 * ここで確かめるのは、callback の中の WordPress の呼び出しと、ルートの登録である。
 * 本体の挙動は、tests/E2E/Fixtures のルートで確かめる
 */
class ExampleApiTest extends AbstractE2ETestCase
{
    public function testPostsReturnsPublishedPosts(): void
    {
        $response = $this->get('/wp-json/example/posts');

        $this->assertResponseCode(200, $response);
        $posts = $this->getJsonResponse($response)['data'];
        $this->assertNotEmpty($posts);
        foreach ($posts as $post) {
            $this->assertSame('publish', $post['post_status']);
        }
    }

    public function testPostsPerPageLimitsTheList(): void
    {
        $response = $this->get('/wp-json/example/posts', ['per_page' => 1, 'order' => 'asc']);

        $this->assertResponseCode(200, $response);
        $this->assertCount(1, $this->getJsonResponse($response)['data']);
    }

    public function testLatestPostsExcludesGivenIds(): void
    {
        $all = $this->getJsonResponse($this->get('/wp-json/example/posts/latest'))['data'];
        $this->assertNotEmpty($all);
        $this->assertLessThanOrEqual(5, count($all));

        $first = $all[0]['ID'];
        $rest = $this->getJsonResponse($this->get('/wp-json/example/posts/latest', ['exclude' => $first]))['data'];

        $this->assertNotContains($first, array_column($rest, 'ID'));
    }

    public function testLatestPostsRejectsTooManyExcludedIds(): void
    {
        $response = $this->get('/wp-json/example/posts/latest', ['exclude' => implode(',', range(1, 11))]);

        $this->assertResponseCode(400, $response);
    }

    public function testPostReturnsOnePost(): void
    {
        $response = $this->get('/wp-json/example/post/1');

        $this->assertResponseCode(200, $response);
        $this->assertSame(1, $this->getJsonResponse($response)['data']['ID']);
    }

    public function testMissingPostIs404(): void
    {
        $response = $this->get('/wp-json/example/post/99999');

        $this->assertResponseCode(404, $response);
        $this->assertSame('rest_post_invalid_id', $this->getJsonResponse($response)['code']);
    }

    public function testDraftsRequireLogin(): void
    {
        $this->assertResponseCode(401, $this->get('/wp-json/example/drafts'));
        $this->assertResponseCode(401, $this->post('/wp-json/example/drafts', ['title' => 'hello']));
        $this->assertResponseCode(401, $this->get('/wp-json/example/draft/1'));
    }

    public function testVersionReturnsStaticBody(): void
    {
        $response = $this->get('/wp-json/example/version');

        $this->assertResponseCode(200, $response);
        $this->assertSame(['version' => '1.0'], $this->getJsonResponse($response));
    }
}
