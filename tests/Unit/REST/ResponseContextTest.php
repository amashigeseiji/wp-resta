<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Exception\HttpException;
use Wp\Resta\REST\Http\ResponseContext;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\Http\WpRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * callback の定義（ADR 0002）: 戻り値は body、ステータスとヘッダは ResponseContext、
 * 引数はパラメータ・Request・ResponseContext の 3 種類だけ
 */
class ResponseContextTest extends TestCase
{
    private string $errorLog;
    private string|false $previousErrorLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorLog = (string) tempnam(sys_get_temp_dir(), 'wp-resta-test-');
        $this->previousErrorLog = ini_set('error_log', $this->errorLog);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog === false ? '' : $this->previousErrorLog);
        @unlink($this->errorLog);
        parent::tearDown();
    }

    private function loggedErrors(): string
    {
        return (string) file_get_contents($this->errorLog);
    }

    private function handle(AbstractRoute $route, string $path = '/x', string $method = 'GET'): RestaResponseInterface
    {
        return (new RequestHandler(new TestPrincipal(), new Dispatcher()))->handle($route, new TestRestaRequest($path, $route, $method));
    }

    public function testStatusAndHeaderWrittenToContextBecomeTheResponse(): void
    {
        $route = new class extends AbstractRoute {
            /** @return array{id: int} */
            public function post(ResponseContext $response): array
            {
                $response->status(201);
                $response->header('Location', '/notes/1');
                return ['id' => 1];
            }
        };

        $response = $this->handle($route, '/x', 'POST');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['Location' => '/notes/1'], $response->getHeaders());
        $this->assertSame(['id' => 1], $response->getData(), '戻り値が body');
    }

    public function testStatusDefaultsTo200WhenContextIsNotTouched(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(ResponseContext $response): string
            {
                return 'ok';
            }
        };

        $response = $this->handle($route);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->getHeaders());
    }

    public function testContextHeaderWinsOverDeclaredHeaderCaseInsensitively(): void
    {
        $route = new class extends AbstractRoute {
            protected const HEADERS = ['Cache-Control' => 'public, max-age=3600', 'X-Route' => 'declared'];

            public function callback(ResponseContext $response): ?string
            {
                $response->status(204);
                $response->header('cache-control', 'no-store');
                $response->header('X-Extra', '1');
                return null;
            }
        };

        $response = $this->handle($route);

        $this->assertSame(204, $response->getStatusCode());
        // ルートの既定ヘッダは残り、同名（大文字小文字を区別しない）は callback が書いたほうだけになる
        $this->assertSame(['X-Route' => 'declared', 'cache-control' => 'no-store', 'X-Extra' => '1'], $response->getHeaders());
    }

    public function testHeaderWrittenTwiceKeepsTheLastValue(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(ResponseContext $response): string
            {
                $response->header('X-Value', 'first');
                $response->header('x-value', 'second');
                return 'ok';
            }
        };

        $this->assertSame(['x-value' => 'second'], $this->handle($route)->getHeaders());
    }

    public function testStatusAndHeadersWrittenBeforeHttpExceptionAreDiscarded(): void
    {
        $route = new class extends AbstractRoute {
            protected const HEADERS = ['X-Route' => 'declared'];

            public function callback(ResponseContext $response): string
            {
                $response->status(201);
                $response->header('Location', '/notes/1');
                throw new HttpException('gone', 410, 'rest_gone');
            }
        };

        $response = $this->handle($route);

        $this->assertSame(410, $response->getStatusCode());
        $this->assertSame(['X-Route' => 'declared'], $response->getHeaders(), 'エラーレスポンスに付くのは HEADERS 定数だけ');
    }

    public function testStatusAndHeadersWrittenBeforeUnexpectedExceptionAreDiscarded(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(ResponseContext $response): string
            {
                $response->status(201);
                $response->header('Location', '/notes/1');
                throw new \RuntimeException('boom');
            }
        };

        $response = $this->handle($route);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame([], $response->getHeaders());
    }

    public function testEachRequestGetsAFreshContext(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(ResponseContext $response, bool $created = false): string
            {
                if ($created) {
                    $response->status(201);
                    $response->header('X-Created', '1');
                }
                return 'ok';
            }
        };

        $first = $this->handle($route, '/x?created=1');
        $second = $this->handle($route, '/x');

        $this->assertSame(201, $first->getStatusCode());
        $this->assertSame(200, $second->getStatusCode(), '同じインスタンスで続けて呼んでも、前のリクエストの値は残らない');
        $this->assertSame([], $second->getHeaders());
    }

    public function testClassTypedArgumentsAreResolvedByTypeNotByName(): void
    {
        // 変数名は任意。パラメータと同じ名前でも、クラス型の引数は型で決まる
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['response' => '?string', 'request' => '?string'];

            /** @return array{method: string, raw: mixed} */
            public function callback(ResponseContext $request, RestaRequestInterface $response): array
            {
                $request->status(202);
                return ['method' => $response->getMethod(), 'raw' => $response->getUrlParam('response')];
            }
        };

        $response = $this->handle($route, '/x?response=from-url&request=from-url');

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(['method' => 'GET', 'raw' => 'from-url'], $response->getData());
    }

    public function testRequestCanBeDeclaredAsItsImplementationClass(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(TestRestaRequest $request): string
            {
                return $request->getMethod();
            }
        };

        $this->assertSame('GET', $this->handle($route)->getData());
    }

    public function testDeclaringAnotherRequestImplementationFailsWithBothClassNames(): void
    {
        // WpRestaRequest を宣言したルートは WordPress 前提。TestRestaRequest では実行できない
        $route = new class extends AbstractRoute {
            public function callback(WpRestaRequest $request): string
            {
                return 'never';
            }
        };

        $response = $this->handle($route);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('WpRestaRequest, but the current request is', $this->loggedErrors());
        $this->assertStringContainsString('TestRestaRequest', $this->loggedErrors());
    }

    public function testReturningTheContextIsAnErrorThatSaysWhatToReturn(): void
    {
        // $response に書き込んで return したくなる。黙って body として出さず、直しかたを言う
        $route = new class extends AbstractRoute {
            public function callback(ResponseContext $response): mixed
            {
                $response->status(201);
                return $response;
            }
        };

        $response = $this->handle($route);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('Return the response body; write the status and headers to ResponseContext', $this->loggedErrors());
    }

    public function testRequestHandlerIsOpenForExtension(): void
    {
        // DI で組み立てるクラスは差し替えられる状態にしておく（独自の Request を使う場合など）
        $this->assertFalse((new ReflectionClass(RequestHandler::class))->isFinal());
    }
}
