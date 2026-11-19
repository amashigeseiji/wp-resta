<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Permission;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Example\Services\PostService;

/**
 * 下書きの一覧と作成。
 *
 * 同じ URL で GET と POST を分けるときは、HTTP メソッド名のメソッドを書く。それぞれが 1 つの操作になり、
 * 引数の宣言も認可のアトリビュートも操作ごとに独立している。どちらも edit_posts を持つユーザーだけが使える
 */
#[RouteMeta(
    description: "下書きの一覧と作成です。edit_posts 権限が必要です",
    tags: ["下書き"]
)]
class Drafts extends AbstractRoute
{
    protected const ROUTE = 'drafts';

    public function __construct(private readonly PostService $posts)
    {
    }

    /**
     * @return Post[]
     */
    #[Permission('edit_posts')]
    public function get(): array
    {
        return $this->posts->drafts();
    }

    #[Permission('edit_posts')]
    public function post(string $title): Post
    {
        return $this->posts->createDraft($title);
    }
}
