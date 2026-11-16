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

    public function testFromMethodWrapsProtectedCallback(): void
    {
        $object = new class {
            protected function callback(int $id): int
            {
                return $id;
            }
        };

        $callback = Callback::fromMethod($object, 'callback');

        $this->assertSame('id', $callback->getParameters()[0]->name);
    }

    public function testFromPropertyReadsTypeAndDocCommentFromTheProperty(): void
    {
        $object = new class {
            /** @var array<string, bool> */
            public readonly array $body;

            public function __construct()
            {
                $this->body = ['static' => true];
            }
        };

        $callback = Callback::fromProperty($object, 'body');

        $this->assertSame([], $callback->getParameters());
        $this->assertSame('array', (string) $callback->getReturnType());
        $this->assertStringContainsString('@var array<string, bool>', (string) $callback->getDocComment());
        $this->assertSame($object::class, $callback->getDeclaringClass()?->getName());
        $this->assertSame(['static' => true], $callback->invoke([]));
    }
}
