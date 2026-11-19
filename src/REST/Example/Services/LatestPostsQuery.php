<?php
namespace Wp\Resta\REST\Example\Services;

use Wp\Resta\REST\Example\Schemas\Post;

/**
 * 「最新の記事を 5 件。ただし、指定された記事は除く」のクエリの組み立てかた。
 *
 * 除く記事の ID を post__not_in に渡すと、ID の組み合わせごとに別のクエリになり、クエリのキャッシュが効かなくなる。
 * この API では、ID に依らない同じクエリで少し多めに取り、PHP の側で除くと決めている。
 * 参考: https://docs.wpvip.com/databases/optimize-queries/using-post__not_in/
 *
 * こう決められるのは、このエンドポイントの用途が 1 つで、除く ID の数に上限があるからである。
 * WordPress を呼ばないので、どのクエリが出て、何が返るかを、WordPress 無しでテストできる
 */
class LatestPostsQuery
{
    /** 返す件数 */
    public const LIMIT = 5;

    /** 除ける ID の数の上限。これを超える指定は、Route クラスが 400 にする */
    public const MAX_EXCLUDE = 10;

    /**
     * WP_Query の引数。除く ID を受け取らないので、リクエストによって変わらない
     *
     * @return array<string, mixed>
     */
    public function args(): array
    {
        return [
            'post_type' => 'post',
            'post_status' => 'publish',
            // パスワードで保護された記事は、本文を返してしまうので除く
            'has_password' => false,
            'orderby' => 'date',
            'order' => 'DESC',
            // 上限まで除かれても LIMIT 件が残るように取る
            'posts_per_page' => self::LIMIT + self::MAX_EXCLUDE,
            // ページ送りを持たないので、総件数を数えない
            'no_found_rows' => true,
        ];
    }

    /**
     * 取ってきた記事から、指定された ID を除いて、先頭の LIMIT 件を返す
     *
     * @param Post[] $posts
     * @param int[] $exclude
     * @return Post[]
     */
    public function pick(array $posts, array $exclude): array
    {
        $rest = array_filter($posts, fn(Post $post) => !in_array($post->ID, $exclude, true));

        return array_slice(array_values($rest), 0, self::LIMIT);
    }
}
