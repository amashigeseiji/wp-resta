<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Example\Services\LatestPostsQuery;
use Wp\Resta\REST\Exception\InvalidParameterException;
use WP_Query;

/**
 * 最新の記事を 5 件。記事ページの下に並べる用途で、いま表示している記事は exclude で除く。
 *
 * クエリの組み立てに判断があるので、LatestPostsQuery に分けて、テストで固定している。
 * 判断の無いルート（Posts、Post）は、callback に WordPress の呼び出しを直接書いている
 */
#[RouteMeta(
    description: "最新の記事を 5 件返します。exclude に指定した記事は除きます",
    tags: ["記事"]
)]
#[Envelope]
class LatestPosts extends AbstractRoute
{
    protected const ROUTE = 'posts/latest';

    public function __construct(private readonly LatestPostsQuery $query)
    {
    }

    /**
     * @param int[] $exclude 結果から除く記事の ID
     * @return Post[]
     */
    public function callback(array $exclude = []): array
    {
        // 配列の要素数は宣言では書けないので、ここで確かめる
        if (count($exclude) > LatestPostsQuery::MAX_EXCLUDE) {
            throw new InvalidParameterException('exclude', 'exclude accepts up to ' . LatestPostsQuery::MAX_EXCLUDE . ' ids.');
        }

        $posts = Post::fromWpPosts((new WP_Query())->query($this->query->args()));

        return $this->query->pick($posts, $exclude);
    }
}
