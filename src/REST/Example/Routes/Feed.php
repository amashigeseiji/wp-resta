<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Attributes\RouteMeta;
use Wp\Resta\REST\Example\Hoge;

#[RouteMeta(tags: ['サンプルAPI'])]
#[Envelope]
class Feed extends AbstractRoute
{
    protected const ROUTE = 'feed/[id]';
    protected const URL_PARAMS = [
        'id' => '\d+',
    ];

    /**
     * Service はコンストラクタで受ける。コンテナが解決して渡す
     */
    public function __construct(private readonly Hoge $hoge)
    {
    }

    /**
     * @return array<mixed>
     */
    public function callback(int $id): array
    {
        return [$id, $id * 2, $this->hoge->getHoge(), $this->hoge->fuga];
    }
}
