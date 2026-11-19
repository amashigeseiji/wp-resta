<?php
namespace Test\Resta\Unit\REST\Example\Services;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Example\Services\LatestPostsQuery;

/**
  * このエンドポイントがどのクエリを発行し、何を返すかを、WordPress もモックも無しに固定する。
 * 引数を足したり条件を変えたりして、発行されるクエリが変われば、ここが落ちる
 */
class LatestPostsQueryTest extends TestCase
{
    public function testQueryIsTheSameForEveryRequest(): void
    {
        $args = (new LatestPostsQuery())->args();

        // 除く ID はクエリに入らない。リクエストごとにクエリが変わると、クエリのキャッシュが効かなくなる
        $this->assertArrayNotHasKey('post__not_in', $args);
        $this->assertSame('publish', $args['post_status']);
        // 上限の 10 件が除かれても 5 件が残る
        $this->assertSame(15, $args['posts_per_page']);
    }

    public function testExcludedPostsAreRemoved(): void
    {
        $picked = (new LatestPostsQuery())->pick(self::posts(1, 2, 3), [2]);

        $this->assertSame([1, 3], self::ids($picked));
    }

    public function testResultIsLimitedToFive(): void
    {
        $picked = (new LatestPostsQuery())->pick(self::posts(1, 2, 3, 4, 5, 6, 7), []);

        $this->assertSame([1, 2, 3, 4, 5], self::ids($picked));
    }

    public function testFivePostsRemainEvenWhenTheMaximumNumberIsExcluded(): void
    {
        $query = new LatestPostsQuery();
        $posts = self::posts(...range(1, $query->args()['posts_per_page']));

        $picked = $query->pick($posts, range(1, LatestPostsQuery::MAX_EXCLUDE));

        $this->assertSame([11, 12, 13, 14, 15], self::ids($picked));
    }

    /**
     * @return Post[]
     */
    private static function posts(int ...$ids): array
    {
        return array_map(fn(int $id) => new Post(
            ID: $id,
            post_author: '1',
            post_date: '2026-01-01 00:00:00',
            post_date_gmt: '2026-01-01 00:00:00',
            post_content: '',
            post_title: "Post {$id}",
            post_status: 'publish',
            post_name: "post-{$id}",
        ), $ids);
    }

    /**
     * @param Post[] $posts
     * @return int[]
     */
    private static function ids(array $posts): array
    {
        return array_map(fn(Post $post) => $post->ID, $posts);
    }
}
