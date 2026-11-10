<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Param;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Sort;

/**
 * callback のシグニチャだけで URL パラメータを宣言するサンプル。URL_PARAMS は書かない
 */
#[RouteMeta(
    description: "シグニチャからパラメータを宣言するサンプルです",
    tags: ["サンプルAPI"]
)]
class Items extends AbstractRoute
{
    protected const ROUTE = 'items';

    /**
     * @param string[] $tags タグで絞り込む
     * @return array<string, mixed>
     */
    public function callback(
        int $page = 1,
        #[Param(min: 1, max: 100)] int $per_page = 10,
        bool $published = true,
        Sort $sort = Sort::Asc,
        array $tags = [],
        #[Param(pattern: '[a-z-]+', description: 'スラッグ')] string $slug = '',
    ): array {
        return [
            'page' => $page,
            'per_page' => $per_page,
            'published' => $published,
            'sort' => $sort->value,
            'tags' => $tags,
            'slug' => $slug,
        ];
    }
}
