<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Callback;

class CallbackTest extends TestCase
{
    public function testFromMethodExposesSignatureAndDocComment(): void
    {
        $object = new class {
            /**
             * @param string[] $tags
             */
            public function callback(int $id, array $tags = []): array
            {
                return [$id, $tags];
            }
        };

        $callback = Callback::fromMethod($object, 'callback');

        $names = array_map(fn($p) => $p->name, $callback->getParameters());
        $this->assertSame(['id', 'tags'], $names);
        $this->assertSame('array', (string)$callback->getReturnType());
        $this->assertStringContainsString('@param string[] $tags', (string)$callback->getDocComment());
    }

    public function testReturningHasNoParameters(): void
    {
        $callback = Callback::returning(['static' => true]);

        $this->assertSame([], $callback->getParameters());
        $this->assertNull($callback->getDocComment());
    }
}
