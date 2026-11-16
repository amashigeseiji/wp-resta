<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\Http\WpRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * callback の引数（ADR 0002）: 書けるのはパラメータと Request の 2 種類だけで、クラス型は型で解決する
 */
class CallbackArgumentsTest extends TestCase
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

    public function testRequestIsResolvedByTypeNotByName(): void
    {
        // 変数名は任意。パラメータと同じ名前でも、クラス型の引数は型で決まる
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['id' => '?string'];

            /** @return array{method: string, raw: mixed} */
            public function callback(RestaRequestInterface $id): array
            {
                return ['method' => $id->getMethod(), 'raw' => $id->getUrlParam('id')];
            }
        };

        $response = $this->handle($route, '/x?id=from-url');

        $this->assertSame(200, $response->getStatusCode());
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

    public function testRequestHandlerIsOpenForExtension(): void
    {
        // DI で組み立てるクラスは差し替えられる状態にしておく（独自の Request を使う場合など）
        $this->assertFalse((new ReflectionClass(RequestHandler::class))->isFinal());
    }
}
