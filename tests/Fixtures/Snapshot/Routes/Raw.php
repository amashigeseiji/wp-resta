<?php
namespace Test\Resta\Fixtures\Snapshot\Routes;

use Wp\Resta\REST\AbstractRoute;

/** 要素型のわからない配列を受け取り、要素型のわからない配列を返す。items は両方 {} になる */
class Raw extends AbstractRoute
{
    protected const ROUTE = 'raw';

    public function callback(array $raw = []): array
    {
        return $raw;
    }
}
