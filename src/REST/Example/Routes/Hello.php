<?php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

/**
 * README の最初の例。'string' のパスパラメータは次の / までの任意の文字列を受け付ける。
 */
#[RouteMeta(
    description: "名前を受け取って挨拶を返すサンプル",
    tags: ["サンプルAPI"]
)]
class Hello extends AbstractRoute
{
    protected const ROUTE = 'hello/[name]';
    protected const URL_PARAMS = [
        'name' => 'string',
    ];

    /**
     * @return array{message: string}
     */
    public function callback(string $name): array
    {
        return ['message' => "Hello, {$name}!"];
    }
}
