<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Exception\HttpException;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;

class AbstractRouteTest extends TestCase
{
    private string $errorLog;
    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        parent::setUp();
        // invoke() の error_log 出力をファイルに向けて、テスト出力を汚さずに内容を検証できるようにする
        $this->errorLog = tempnam(sys_get_temp_dir(), 'wp-resta-test-');
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
        return (string)file_get_contents($this->errorLog);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function requestWith(array $params): RestaRequestInterface
    {
        return new class($params) implements RestaRequestInterface {
            /** @param array<string, mixed> $params */
            public function __construct(private array $params) {}
            public function getUrlParam(string $name): mixed
            {
                return $this->params[$name] ?? null;
            }
        };
    }

    public function testInvokeWithSimpleCallback()
    {
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                return ['status' => 'ok', 'message' => 'test'];
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertInstanceOf(RestaResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // データを直接アサート - JSON decode 不要！
        $data = $response->getData();
        $this->assertIsArray($data);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('test', $data['message']);
    }

    public function testInvokeWithCustomHeaders()
    {
        $route = new class extends AbstractRoute {
            protected array $headers = [
                'X-Custom-Header' => 'custom-value',
            ];

            public function callback(): string
            {
                return 'response';
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertEquals('custom-value', $headers['X-Custom-Header']);
    }

    public function testInvokeReturnsResponseDirectly()
    {
        $route = new class extends AbstractRoute {
            public function callback(): RestaResponseInterface
            {
                return new \Wp\Resta\REST\Http\SimpleRestaResponse(
                    data: ['created' => true],
                    status: 201
                );
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(['created' => true], $response->getData());
    }

    public function testInvokeHandlesException()
    {
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                throw new \Exception('Test error');
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertEquals(500, $response->getStatusCode());

        // WP_Error と同じ形の JSON を返す。WP_DEBUG が無いときはメッセージを出さない
        $data = $response->getData();
        $this->assertSame('rest_internal_error', $data['code']);
        $this->assertSame('Internal Server Error', $data['message']);
        $this->assertSame(500, $data['data']['status']);
        $this->assertArrayNotHasKey('exception', $data['data']);

        // 握り潰さず error_log に出す
        $this->assertStringContainsString('Test error', $this->loggedErrors());
        $this->assertStringContainsString('Exception', $this->loggedErrors());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInvokeIncludesExceptionMessageInBodyWhenWpDebug()
    {
        define('WP_DEBUG', true);

        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                throw new \LogicException('debug me');
            }
        };

        $response = $route->invoke(new TestRestaRequest('/test', $route));

        $this->assertEquals(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertSame('rest_internal_error', $data['code']);
        $this->assertSame('debug me', $data['message']);
        $this->assertSame(\LogicException::class, $data['data']['exception']);
    }

    public function testInvokeConvertsErrorToInternalServerError()
    {
        // \Exception ではなく \Error（TypeError など）も致命的エラーにせず 500 にする
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                throw new \TypeError('type error');
            }
        };

        $response = $route->invoke(new TestRestaRequest('/test', $route));

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame('rest_internal_error', $response->getData()['code']);
        $this->assertStringContainsString('TypeError', $this->loggedErrors());
    }

    public function testInvokeDoesNotKeepStatusSetBeforeException()
    {
        // callback が $this->status を書き換えたあとに例外を投げても、その値は使わない
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                $this->status = 404;
                throw new \RuntimeException('after status');
            }
        };

        $response = $route->invoke(new TestRestaRequest('/test', $route));

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testInvokeKeepsHeadersOnErrorResponse()
    {
        $route = new class extends AbstractRoute {
            protected array $headers = ['X-Custom' => 'kept'];
            public function callback(): array
            {
                throw new \RuntimeException('boom');
            }
        };

        $response = $route->invoke(new TestRestaRequest('/test', $route));

        $this->assertSame('kept', $response->getHeaders()['X-Custom']);
    }

    public function testInvokeConvertsHttpExceptionThrownFromCallback()
    {
        $route = new class extends AbstractRoute {
            public function callback(): array
            {
                throw new HttpException('Post not found', 404, 'rest_post_invalid_id', ['id' => 99]);
            }
        };

        $response = $route->invoke(new TestRestaRequest('/test', $route));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertSame([
            'code' => 'rest_post_invalid_id',
            'message' => 'Post not found',
            'data' => ['status' => 404, 'id' => 99],
        ], $response->getData());
        // クライアント起因のエラーはログに出さない
        $this->assertSame('', $this->loggedErrors());
    }

    public function testInvokeLogsFullMessageWhenExceptionMessageContainsAnonymousClassName()
    {
        // URL_PARAMS の定義忘れで投げる LogicException はメッセージにルートクラス名を含む。
        // 匿名クラス名の NUL 文字でログが切れないこと
        $route = new class extends AbstractRoute {
            public function callback(int $id): array
            {
                return ['id' => $id];
            }
        };

        $response = $route->invoke(new TestRestaRequest('/test', $route));

        $this->assertEquals(500, $response->getStatusCode());
        $log = $this->loggedErrors();
        $this->assertStringNotContainsString("\0", $log);
        $this->assertStringContainsString('Please check URL_PARAMS has `id` parameter.', $log);
        $this->assertMatchesRegularExpression('/ in \S+AbstractRoute\.php:\d+/', $log);
    }

    public function testInvokeReturns500WhenRegexItselfIsInvalid()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'regex' => '[',
                ],
            ];

            public function callback(string $id): array
            {
                return ['id' => $id];
            }
        };

        // preg_match() の Warning はテスト出力に出さない（invoke() 側は握り潰さずログと 500 にする）
        set_error_handler(fn() => true, E_WARNING);
        try {
            $response = $route->invoke($this->requestWith(['id' => 'abc']));
        } finally {
            restore_error_handler();
        }

        // サーバー側の定義ミスなので、クライアント起因の 400 ではなく 500
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame('rest_internal_error', $response->getData()['code']);
        $this->assertStringContainsString('URL_PARAMS[id] has invalid regex "["', $this->loggedErrors());
    }

    public function testInvokeReturns400WhenRequiredParamIsMissing()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['id' => 'integer'];
            public function callback(int $id): array
            {
                return ['id' => $id];
            }
        };

        $response = $route->invoke($this->requestWith([]));

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertSame([
            'code' => 'rest_missing_callback_param',
            'message' => 'Missing parameter(s): id',
            'data' => ['status' => 400, 'params' => ['id']],
        ], $response->getData());
        $this->assertSame('', $this->loggedErrors());
    }

    public function testInvokeWithoutCallback()
    {
        $route = new class extends AbstractRoute {
            // No callback method
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertInstanceOf(RestaResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvokeWithCustomStatus()
    {
        $route = new class extends AbstractRoute {
            protected int $status = 201;

            public function callback(): array
            {
                return ['created' => true];
            }
        };

        $request = new TestRestaRequest('/test', $route);
        $response = $route->invoke($request);

        $this->assertEquals(201, $response->getStatusCode());

        $data = $response->getData();
        $this->assertTrue($data['created']);
    }

    public function testInvokeReturns400WhenRequiredParamValueDoesNotMatchRegex()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'item_ids' => [
                    'type' => 'string',
                    'required' => true,
                    'regex' => '^[\d,]+$',
                    'description' => 'item_ids',
                ],
            ];

            public function callback(string $item_ids): array
            {
                return ['item_ids' => $item_ids];
            }
        };

        // フルアンカーのregexに対して、スペースが混ざった不正な値を送る
        $response = $route->invoke($this->requestWith(['item_ids' => '1,2, 3']));

        // 入力不正はクライアント側の誤りなので 400。WP コアの rest_invalid_param と同じ形
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertSame([
            'code' => 'rest_invalid_param',
            'message' => 'Invalid parameter(s): item_ids',
            'data' => ['status' => 400, 'params' => ['item_ids' => 'item_ids is invalid.']],
        ], $response->getData());
        $this->assertSame('', $this->loggedErrors());
    }

    public function testInvokeDoesNotSilentlyFallBackToDefaultWhenOptionalParamValueDoesNotMatchRegex()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'item_ids' => [
                    'type' => 'string',
                    'required' => false,
                    'regex' => '^[\d,]+$',
                    'description' => 'item_ids',
                ],
            ];

            public function callback(?string $item_ids = null): array
            {
                return ['item_ids' => $item_ids];
            }
        };

        $response = $route->invoke($this->requestWith(['item_ids' => '1,2, 3']));

        // 不正な値を黙ってデフォルト値にフォールバックさせず、400 にする
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertSame('rest_invalid_param', $response->getData()['code']);
    }
}
