<?php
namespace Test\Resta\Unit\REST\Http;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\Http\Headers;
use Wp\Resta\REST\Http\SimpleRestaResponse;

class ResponseHeadersTest extends TestCase
{
    public function testMergeOverridesCaseInsensitively(): void
    {
        $merged = Headers::merge(['Cache-Control' => 'no-store', 'X-A' => '1'], ['cache-control' => 'max-age=60']);

        $this->assertSame(['X-A' => '1', 'cache-control' => 'max-age=60'], $merged);
    }

    public function testWithHeadersReturnsCopyAndKeepsType(): void
    {
        $original = new EnvelopeResponse(['x' => 1], ['m' => 1], 201, ['X-A' => '1']);

        $copy = $original->withHeaders(['x-a' => '2', 'X-B' => '3']);

        $this->assertInstanceOf(EnvelopeResponse::class, $copy, 'Envelope の型を保つ（EnvelopeHook の二重包み判定に効く）');
        $this->assertSame(['x-a' => '2', 'X-B' => '3'], $copy->getHeaders());
        $this->assertSame(['X-A' => '1'], $original->getHeaders(), '元のレスポンスは変わらない');
        $this->assertSame(201, $copy->getStatusCode());
        $this->assertSame(['data' => ['x' => 1], 'meta' => ['m' => 1]], $copy->getData());

        $simple = (new SimpleRestaResponse('d'))->withHeaders(['A' => 'b']);
        $this->assertSame(['A' => 'b'], $simple->getHeaders());
    }
}
