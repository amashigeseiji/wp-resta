<?php
namespace Test\Resta\Unit\REST\Hooks;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\Principal;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Cache;
use Wp\Resta\REST\Attributes\Envelope;
use Wp\Resta\REST\Exception\HttpException;
use Wp\Resta\REST\Hooks\CacheHook;
use Wp\Resta\REST\Hooks\EnvelopeHook;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;
use Wp\Resta\REST\RouteInvocationEvent;

/**
 * #[Cache] の Cache-Control / ETag / 304（#78）。RequestHandler を通し、本番と同じ購読順で確かめる
 */
class CacheHookTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    private function handle(
        AbstractRoute $route,
        array $headers = [],
        ?Principal $principal = null,
        string $method = 'GET',
        ?callable $userListener = null,
    ): RestaResponseInterface {
        $principal ??= new TestPrincipal();
        $dispatcher = new Dispatcher();
        $dispatcher->addSubscriber(new EnvelopeHook());
        $dispatcher->addSubscriber(new CacheHook($principal));
        if ($userListener !== null) {
            $dispatcher->addListener(RouteInvocationEvent::class, $userListener);
        }
        return (new RequestHandler($principal, $dispatcher))
            ->handle($route, new TestRestaRequest('/x', $route, $method, $headers));
    }

    private function cachedRoute(): AbstractRoute
    {
        return new #[Cache(maxAge: 300)] class extends AbstractRoute {
            public function callback(): array
            {
                return ['id' => 1];
            }
        };
    }

    public function testAddsCacheControlAndEtag(): void
    {
        $response = $this->handle($this->cachedRoute());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('public, max-age=300', $response->getHeaders()['Cache-Control']);
        $this->assertMatchesRegularExpression('/^"[0-9a-f]+"$/', $response->getHeaders()['ETag']);
    }

    public function testCacheControlDirectives(): void
    {
        $route = new #[Cache(maxAge: 60, public: false, sMaxAge: 600, staleWhileRevalidate: 30)] class extends AbstractRoute {
            public function callback(): string
            {
                return 'x';
            }
        };

        $response = $this->handle($route);

        $this->assertSame('private, max-age=60, s-maxage=600, stale-while-revalidate=30', $response->getHeaders()['Cache-Control']);
    }

    public function testEtagDependsOnBody(): void
    {
        $route = new #[Cache(maxAge: 1)] class extends AbstractRoute {
            public static string $current = 'a';
            public function callback(): string
            {
                return self::$current;
            }
        };

        $route::$current = 'a';
        $first = $this->handle($route)->getHeaders()['ETag'];
        $again = $this->handle($route)->getHeaders()['ETag'];
        $route::$current = 'b';
        $changed = $this->handle($route)->getHeaders()['ETag'];

        $this->assertSame($first, $again, '同じボディなら同じ ETag');
        $this->assertNotSame($first, $changed, 'ボディが変わると ETag も変わる');
    }

    public function testReturns304WhenIfNoneMatchMatches(): void
    {
        $route = $this->cachedRoute();
        $etag = $this->handle($route)->getHeaders()['ETag'];

        $response = $this->handle($route, ['If-None-Match' => $etag]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertNull($response->getData());
        $this->assertSame($etag, $response->getHeaders()['ETag'], '304 にも ETag が付く');
        $this->assertSame('public, max-age=300', $response->getHeaders()['Cache-Control']);
    }

    public function testIfNoneMatchAcceptsListsWeakTagsAndStar(): void
    {
        $route = $this->cachedRoute();
        $etag = $this->handle($route)->getHeaders()['ETag'];

        $this->assertSame(304, $this->handle($route, ['If-None-Match' => '"other", ' . $etag])->getStatusCode());
        $this->assertSame(304, $this->handle($route, ['if-none-match' => 'W/' . $etag])->getStatusCode(), 'ヘッダ名は大文字小文字を区別しない');
        $this->assertSame(304, $this->handle($route, ['If-None-Match' => '*'])->getStatusCode());
        $this->assertSame(200, $this->handle($route, ['If-None-Match' => '"other"'])->getStatusCode());
        // * はヘッダ全体がそれだけのとき。引用符の中のカンマや * は ETag の一部
        $this->assertSame(200, $this->handle($route, ['If-None-Match' => '"unrelated,*,tag"'])->getStatusCode());
        $this->assertSame(200, $this->handle($route, ['If-None-Match' => '"a", *'])->getStatusCode());
        $this->assertSame(200, $this->handle($route, ['If-None-Match' => trim($etag, '"')])->getStatusCode(), '引用符の無い値は ETag ではない');
    }

    public function testExplicitCacheControlIsRespected(): void
    {
        // callback が no-store を明示したレスポンスは public に緩めない。ETag も 304 も付けない
        $route = new #[Cache(maxAge: 300)] class extends AbstractRoute {
            public function callback(): RestaResponseInterface
            {
                return $this->response(['id' => 1], 200, ['cache-control' => 'no-store']);
            }
        };

        $response = $this->handle($route, ['If-None-Match' => '*']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['cache-control' => 'no-store'], $response->getHeaders());
    }

    public function testExplicitCacheControlFromDefaultPriorityListenerIsRespected(): void
    {
        $listener = function (RouteInvocationEvent $event): void {
            $event->response = $event->response->withHeaders(['Cache-Control' => 'private, max-age=0']);
        };

        $response = $this->handle($this->cachedRoute(), ['If-None-Match' => '*'], null, 'GET', $listener);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('private, max-age=0', $response->getHeaders()['Cache-Control']);
        $this->assertArrayNotHasKey('ETag', $response->getHeaders());
    }

    public function testRouteWithoutCacheAttributeIsUntouched(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                return ['id' => 1];
            }
        };

        $response = $this->handle($route, ['If-None-Match' => '*']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('Cache-Control', $response->getHeaders());
        $this->assertArrayNotHasKey('ETag', $response->getHeaders());
    }

    public function testAuthenticatedRequestIsNotCached(): void
    {
        $response = $this->handle($this->cachedRoute(), ['If-None-Match' => '*'], new TestPrincipal(authenticated: true));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('Cache-Control', $response->getHeaders());
        $this->assertArrayNotHasKey('ETag', $response->getHeaders());
    }

    public function testOnlyGetAndHeadWithStatus200AreCached(): void
    {
        $route = new #[Cache(maxAge: 300)] class extends AbstractRoute {
            public function getMethods(): string
            {
                return 'GET,POST';
            }
            public function callback(bool $fail = false): array
            {
                if ($fail) {
                    throw new HttpException('not found', 404, 'rest_not_found');
                }
                return ['id' => 1];
            }
        };

        $post = $this->handle($route, [], null, 'POST');
        $this->assertArrayNotHasKey('ETag', $post->getHeaders(), 'POST には付かない');

        $head = $this->handle($route, [], null, 'HEAD');
        $this->assertArrayHasKey('ETag', $head->getHeaders(), 'HEAD は GET と同じ');

        $principal = new TestPrincipal();
        $dispatcher = new Dispatcher();
        $dispatcher->addSubscriber(new CacheHook($principal));
        $error = (new RequestHandler($principal, $dispatcher))->handle($route, new TestRestaRequest('/x?fail=1', $route));
        $this->assertSame(404, $error->getStatusCode());
        $this->assertArrayNotHasKey('ETag', $error->getHeaders(), 'エラーには付かない');
    }

    public function testEtagIsComputedFromTheEnvelopedBody(): void
    {
        $route = new #[Envelope] #[Cache(maxAge: 300)] class extends AbstractRoute {
            public function callback(): array
            {
                return ['id' => 1];
            }
        };

        $response = $this->handle($route);

        $this->assertArrayHasKey('data', $response->getData(), 'Envelope に包まれている');
        $expected = '"' . hash('xxh128', json_encode($response->getData())) . '"';
        $this->assertSame($expected, $response->getHeaders()['ETag']);
    }

    public function testEtagReflectsChangesByListenersAtDefaultPriority(): void
    {
        $route = $this->cachedRoute();
        $listener = function (RouteInvocationEvent $event): void {
            $event->response = $event->response->withHeaders([]);
            $event->response = new \Wp\Resta\REST\Http\SimpleRestaResponse(['id' => 1, 'extra' => true], 200, $event->response->getHeaders());
        };

        $response = $this->handle($route, [], null, 'GET', $listener);

        $expected = '"' . hash('xxh128', json_encode(['id' => 1, 'extra' => true])) . '"';
        $this->assertSame($expected, $response->getHeaders()['ETag'], '既定の priority の購読者が差し替えた後のボディから計算する');
    }

    public function testRouteDefaultHeadersAreKeptOn304(): void
    {
        $route = new #[Cache(maxAge: 300)] class extends AbstractRoute {
            protected const HEADERS = ['X-Route' => 'yes'];
            public function callback(): array
            {
                return ['id' => 1];
            }
        };
        $etag = $this->handle($route)->getHeaders()['ETag'];

        $response = $this->handle($route, ['If-None-Match' => $etag]);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('yes', $response->getHeaders()['X-Route']);
    }
}
