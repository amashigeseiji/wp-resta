<?php
namespace Test\Resta\E2E\Fixtures\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Permission;
use Wp\Resta\REST\Attributes\RouteMeta;

/**
 * 操作ごとに認可を宣言するサンプル。GET は公開で、POST は edit_posts を持つユーザーだけが使える
 */
#[RouteMeta(
    description: "認可のアトリビュートのサンプルです。POST には edit_posts 権限が必要です",
    tags: ["サンプルAPI"]
)]
class Drafts extends AbstractRoute
{
    protected const ROUTE = 'drafts';

    /**
     * @return array{count: int}
     */
    public function get(): array
    {
        return ['count' => 0];
    }

    /**
     * @return array{title: string}
     */
    #[Permission('edit_posts')]
    public function post(string $title): array
    {
        return ['title' => $title];
    }
}
