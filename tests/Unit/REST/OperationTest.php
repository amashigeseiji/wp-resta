<?php
namespace Test\Resta\Unit\REST;

use LogicException;
use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Param;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * ルート（URL）と操作（HTTP メソッド）の分離（#92）。
 * get() / post() で操作を分けたルートと、callback() 1 つのルートの両方を RequestHandler で通す
 */
class OperationTest extends TestCase
{
    private function handle(AbstractRoute $route, string $path, string $method = 'GET'): RestaResponseInterface
    {
        return (new RequestHandler(new TestPrincipal(), new Dispatcher()))
            ->handle($route, new TestRestaRequest($path, $route, $method));
    }

    private function notes(): AbstractRoute
    {
        return new class extends AbstractRoute {
            protected const ROUTE = 'notes/[id]';

            public function get(int $id): array
            {
                return ['method' => 'GET', 'id' => $id];
            }

            public function post(int $id, string $title): array
            {
                return ['method' => 'POST', 'id' => $id, 'title' => $title];
            }
        };
    }

    public function testRequestMethodSelectsTheOperation(): void
    {
        $route = $this->notes();

        $this->assertSame(['method' => 'GET', 'id' => 1], $this->handle($route, '/notes/1')->getData());
        $this->assertSame(['method' => 'POST', 'id' => 1, 'title' => 'x'], $this->handle($route, '/notes/1?title=x', 'POST')->getData());
    }

    public function testEachOperationIsValidatedByItsOwnSignature(): void
    {
        $route = $this->notes();

        // title は post() だけの宣言。GET では要求されず、POST では必須
        $this->assertSame(200, $this->handle($route, '/notes/1')->getStatusCode());
        $this->assertSame(400, $this->handle($route, '/notes/1', 'POST')->getStatusCode());
    }

    public function testUndeclaredMethodIs404(): void
    {
        $response = $this->handle($this->notes(), '/notes/1', 'PUT');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('rest_no_route', $response->getData()['code']);
    }

    public function testHeadFallsBackToGetLikeWordPressCore(): void
    {
        $route = $this->notes();

        $this->assertSame($route->getOperation('GET'), $route->getOperation('HEAD'));
        $this->assertSame(200, $this->handle($route, '/notes/1', 'HEAD')->getStatusCode());
    }

    public function testMethodLookupIsCaseInsensitive(): void
    {
        $route = $this->notes();

        $this->assertSame($route->getOperation('POST'), $route->getOperation('post'));
        $this->assertNull($route->getOperation('delete'));
    }

    public function testOperationsAreDerivedOnce(): void
    {
        $route = $this->notes();

        $this->assertCount(2, $route->getOperations());
        $this->assertSame($route->getOperations(), $route->getOperations());
        $this->assertSame(['GET', 'POST'], array_map(fn($op) => $op->getMethods(), $route->getOperations()));
    }

    public function testCallbackIsOneOperationForAllDeclaredMethods(): void
    {
        $route = new class extends AbstractRoute {
            public function getMethods(): string
            {
                return 'GET,POST';
            }

            public function callback(): string
            {
                return 'same';
            }
        };

        $this->assertCount(1, $route->getOperations());
        $this->assertSame($route->getOperation('GET'), $route->getOperation('POST'));
        $this->assertSame(['GET', 'POST'], $route->getOperation('GET')->getMethodList());
        $this->assertSame('same', $this->handle($route, '/x')->getData());
        $this->assertSame('same', $this->handle($route, '/x', 'POST')->getData());
        $this->assertSame(404, $this->handle($route, '/x', 'DELETE')->getStatusCode());
    }

    public function testCallbackAndHttpMethodNameTogetherIsLogicException(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(): string
            {
                return 'a';
            }

            public function get(): string
            {
                return 'b';
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('declares both callback() and get()');
        $route->getOperations();
    }

    public function testPathParameterDeclarationsMustAgreeAcrossOperations(): void
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'notes/[id]';

            public function get(int $id): array
            {
                return [];
            }

            public function delete(string $id): array
            {
                return [];
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('path parameter [id] of ROUTE "notes/[id]" must be declared the same way in every operation (GET vs DELETE)');
        $route->getRouteRegex();
    }

    public function testPathParameterTypeMustAgreeEvenWhenRegexIsTheSame(): void
    {
        // float と string はどちらも正規表現が [^/]+ だが、型が違えば束縛結果が変わる
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'notes/[id]';

            public function get(float $id): array
            {
                return [];
            }

            public function post(string $id): array
            {
                return [];
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('path parameter [id] of ROUTE "notes/[id]" must be declared the same way in every operation (GET vs POST)');
        $route->getOperations();
    }

    public function testPathParameterConstraintMustAgreeAcrossOperations(): void
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'notes/[id]';

            public function get(#[Param(min: 1)] int $id): array
            {
                return [];
            }

            public function post(int $id): array
            {
                return [];
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('path parameter [id] of ROUTE "notes/[id]" must be declared the same way in every operation (GET vs POST)');
        $route->getOperations();
    }

    public function testPathParameterDescriptionMayDifferAcrossOperations(): void
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'notes/[id]';

            public function get(#[Param(description: 'Note to read')] int $id): array
            {
                return [];
            }

            public function post(#[Param(description: 'Note to update')] int $id): array
            {
                return [];
            }
        };

        $this->assertCount(2, $route->getOperations());
        $this->assertSame('notes/(?P<id>\\d+)', $route->getRouteRegex());
    }

    public function testStatusAssignedInHttpMethodOperationGoesThroughBridge(): void
    {
        // 0.12 の橋は get() / post() にも効く（#59 で消すまで）
        $route = new class extends AbstractRoute {
            public function get(): ?array
            {
                $this->status = 404;
                return null;
            }
        };

        $this->assertSame(404, $this->handle($route, '/x')->getStatusCode());
    }

    public function testRouteWithoutAnyCallbackBecomesOneDeprecatedOperation(): void
    {
        $route = new class extends AbstractRoute {
            protected $body = 'static';
        };
        $this->expectUserDeprecationMessageMatches('/has no callback\(\)/');

        $this->assertCount(1, $route->getOperations());
        $this->assertSame('GET', $route->getOperations()[0]->getMethods());
        $this->assertSame('static', $this->handle($route, '/x')->getData());
    }
}
