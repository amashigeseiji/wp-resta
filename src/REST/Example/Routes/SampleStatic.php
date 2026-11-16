<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\RouteMeta;

#[RouteMeta(tags: ['サンプルAPI'])]
#[Envelope]
class SampleStatic extends AbstractRoute
{
    /**
     * 静的なルート。callback を書かず、応答を public readonly の body で宣言する。
     * コンストラクタは、このルートが呼ばれなくても登録時に実行されるので、軽い値だけを置く
     *
     * @var array{name: string, body: string}
     */
    public readonly array $body;

    public function __construct()
    {
        $this->body = [
            'name' => 'static_parameter',
            'body' => 'this is sttatic parameter',
        ];
    }
}
