<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

/**
 * リクエストによって変わらない値を返す、静的なルート。
 *
 * callback を書かず、応答を public readonly の body で宣言する。
 * コンストラクタは、このルートが呼ばれなくても登録時に実行されるので、軽い値だけを置く
 */
#[RouteMeta(
    description: "この API のバージョンを返します",
    tags: ["記事"]
)]
class Version extends AbstractRoute
{
    protected const ROUTE = 'version';

    /**
     * @var array{version: string}
     */
    public readonly array $body;

    public function __construct()
    {
        $this->body = ['version' => '1.0'];
    }
}
