<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Cache;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\Param;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Schemas\Post;
use Wp\Resta\REST\Example\Services\PostService;
use Wp\Resta\REST\Example\Sort;

/**
 * 公開されている記事の一覧。
 *
 * クエリパラメータは callback の引数で宣言する。引数の型が検証と型変換になり、デフォルト値があれば省略できる。
 * 範囲のような制約は #[Param] で足す。記事を取り出す処理は PostService に置き、コンストラクタで受け取る
 */
#[RouteMeta(
    description: "公開されている記事の一覧を返します",
    tags: ["記事"]
)]
#[Envelope]
#[Cache(maxAge: 60)]
class Posts extends AbstractRoute
{
    protected const ROUTE = 'posts';

    public function __construct(private readonly PostService $posts)
    {
    }

    /**
     * @param string[] $tags タグのスラッグで絞り込む
     * @return Post[]
     */
    public function callback(
        int $page = 1,
        #[Param(min: 1, max: 100)] int $per_page = 10,
        Sort $sort = Sort::Desc,
        array $tags = [],
    ): array {
        return $this->posts->published($page, $per_page, $sort, $tags);
    }
}
