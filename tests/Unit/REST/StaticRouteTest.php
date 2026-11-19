<?php
namespace Test\Resta\Unit\REST;

use LogicException;
use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * 静的なルート: callback を持たず、応答を public readonly の body プロパティで宣言する（#105）
 */
class StaticRouteTest extends TestCase
{
    private function handle(AbstractRoute $route): RestaResponseInterface
    {
        return (new RequestHandler(new Dispatcher()))->handle($route, new TestRestaRequest('/x', $route));
    }

    public function testBodyPropertyIsReturnedWithStatus200AndDefaultHeaders(): void
    {
        $route = new class extends AbstractRoute {
            protected const HEADERS = ['Cache-Control' => 'max-age=60'];

            /** @param array<string, string> $body */
            public function __construct(public readonly array $body = ['version' => '1.0']) {}
        };

        $response = $this->handle($route);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['version' => '1.0'], $response->getData());
        $this->assertSame(['Cache-Control' => 'max-age=60'], $response->getHeaders());
    }

    public function testBodyCanBeAssignedInConstructor(): void
    {
        $route = new class('from service') extends AbstractRoute {
            public readonly string $body;

            public function __construct(string $loaded)
            {
                $this->body = strtoupper($loaded);
            }
        };

        $this->assertSame('FROM SERVICE', $this->handle($route)->getData());
    }

    public function testClonedRouteReturnsTheSameBody(): void
    {
        // RegisterRestRoutes はリクエストごとにルートを clone する
        $route = new class extends AbstractRoute {
            public function __construct(public readonly string $body = 'static') {}
        };

        $this->assertSame('static', $this->handle(clone $route)->getData());
    }

    public function testRouteWithNeitherBodyNorCallbackIsRejected(): void
    {
        $route = new class extends AbstractRoute {
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('declares no operation');
        $route->getOperations();
    }

    public function testBodyWithCallbackIsRejected(): void
    {
        // プロパティの型と callback の戻り値型が違うと、レスポンスの型が 1 つに決まらない
        $route = new class extends AbstractRoute {
            public function __construct(public readonly string $body = 'static') {}

            public function callback(): int
            {
                return 1;
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('declares both a $body property and callback()');
        $route->getOperations();
    }

    public function testBodyWithHttpMethodOperationIsRejected(): void
    {
        $route = new class extends AbstractRoute {
            public function __construct(public readonly string $body = 'static') {}

            public function post(): int
            {
                return 1;
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('declares both a $body property and post()');
        $route->getOperations();
    }

    public function testNonReadonlyBodyIsRejected(): void
    {
        // 書き換えられる body は、リクエストをまたいで値を持ち越せてしまう。黙って受け付けない
        $route = new class extends AbstractRoute {
            public string $body = 'static';
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$body must be declared as public readonly');
        $route->getOperations();
    }

    public function testNonPublicBodyIsRejected(): void
    {
        // 0.12 までの書きかた（protected $body）。無視して「操作が無い」と言うより、直しかたを言う
        $route = new class extends AbstractRoute {
            /** @var string */
            protected $body = 'static';
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$body must be declared as public readonly');
        $route->getOperations();
    }

    public function testAssigningBodyAtRuntimeIsAnErrorRaisedByPhp(): void
    {
        $route = new class extends AbstractRoute {
            public function __construct(public readonly string $body = 'static') {}
        };

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');
        /** @phpstan-ignore-next-line 実行時に PHP が止めることを確かめる */
        $route->body = 'changed';
    }
}
