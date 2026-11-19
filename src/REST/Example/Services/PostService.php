<?php
namespace Wp\Resta\REST\Example\Services;

use RuntimeException;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Example\Sort;
use WP_Post;
use WP_Query;

/**
 * 記事と下書きを WordPress から取り出す Service。
 *
 * WordPress の関数を呼ぶのはこのクラスだけにして、Route クラスの callback は薄く保つ。
 * Route クラスはコンストラクタでこのクラスを受け取るので、テストではモックに差し替えられる
 */
class PostService
{
    /**
     * 公開されている記事の一覧
     *
     * @param string[] $tags タグのスラッグ。空なら絞り込まない
     * @return Post[]
     */
    public function published(int $page, int $perPage, Sort $sort, array $tags = []): array
    {
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => $sort === Sort::Asc ? 'ASC' : 'DESC',
            'paged' => $page,
            'posts_per_page' => $perPage,
        ];
        if ($tags !== []) {
            $args['tag_slug__in'] = $tags;
        }

        return $this->query($args);
    }

    /**
     * 公開されている記事 1 件。無ければ null
     */
    public function findPublished(int $id): ?Post
    {
        return $this->find($id, 'publish');
    }

    /**
     * 下書きの一覧
     *
     * @return Post[]
     */
    public function drafts(): array
    {
        return $this->query([
            'post_type' => 'post',
            'post_status' => 'draft',
            'orderby' => 'modified',
            'order' => 'DESC',
            'posts_per_page' => 10,
        ]);
    }

    /**
     * 下書き 1 件。無ければ null
     */
    public function findDraft(int $id): ?Post
    {
        return $this->find($id, 'draft');
    }

    /**
     * タイトルだけの下書きを作る
     */
    public function createDraft(string $title): Post
    {
        $id = wp_insert_post(['post_title' => $title, 'post_status' => 'draft']);
        $post = get_post($id);
        if (!$post instanceof WP_Post) {
            throw new RuntimeException('Failed to create a draft');
        }

        return $this->toSchema($post);
    }

    private function find(int $id, string $status): ?Post
    {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'post' || $post->post_status !== $status) {
            return null;
        }

        return $this->toSchema($post);
    }

    /**
     * @param array<string, mixed> $args
     * @return Post[]
     */
    private function query(array $args): array
    {
        $posts = [];
        foreach ((new WP_Query())->query($args) as $post) {
            if ($post instanceof WP_Post) {
                $posts[] = $this->toSchema($post);
            }
        }

        return $posts;
    }

    private function toSchema(WP_Post $post): Post
    {
        return new Post(
            ID: $post->ID,
            post_author: $post->post_author,
            post_date: $post->post_date,
            post_date_gmt: $post->post_date_gmt,
            post_content: $post->post_content,
            post_title: $post->post_title,
            post_status: $post->post_status,
            post_name: $post->post_name,
        );
    }
}
