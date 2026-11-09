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

    public function testInvokeAcceptsValueMatchingRegexThatContainsSlash()
    {
        $route = new class extends AbstractRoute {
            protected const ROUTE = 'slash/[path]';
            protected const URL_PARAMS = ['path' => '[a-z/]+'];

            public function callback(string $path): array
            {
                return ['path' => $path];
            }
        };

        // regex に / が含まれていても区切り文字と衝突せず、一致する値はそのまま通る
        foreach (['foo/bar', 'foo'] as $path) {
            $response = $route->invoke($this->requestWith(['path' => $path]));
            $this->assertEquals(200, $response->getStatusCode(), "path={$path}");
            $this->assertSame(['path' => $path], $response->getData());
        }
        $this->assertSame('', $this->loggedErrors());
    }

    public function testInvokeAcceptsValueMatchingRegexThatContainsHash()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['tag' => '[a-z#]+'];

            public function callback(string $tag): array
            {
                return ['tag' => $tag];
            }
        };

        // 区切り文字の第一候補 # が regex に含まれていても別の区切り文字が選ばれ、Warning も出ない
        $response = $route->invoke($this->requestWith(['tag' => 'a#b']));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(['tag' => 'a#b'], $response->getData());
        $this->assertSame('', $this->loggedErrors());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function regexWithEscapedHashProvider(): array
    {
        return [
            'backslash escaped' => ['a\\#b'],
            'quoted with \\Q...\\E' => ['\\Qa#b\\E'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('regexWithEscapedHashProvider')]
    public function testInvokeKeepsEscapedHashInRegexIntact(string $regex)
    {
        $route = new class($regex) extends AbstractRoute {
            public function __construct(private string $regex) {}

            public function getArgs(): array
            {
                return ['tag' => ['type' => 'string', 'required' => true, 'regex' => $this->regex, 'description' => 'tag']];
            }

            public function callback(string $tag): array
            {
                return ['tag' => $tag];
            }
        };

        // # をエスケープで処理すると '\#' が '\\#' に化けて regex が壊れる。区切り文字を変えるだけなので元のまま通る
        $response = $route->invoke($this->requestWith(['tag' => 'a#b']));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(['tag' => 'a#b'], $response->getData());
        $this->assertSame('', $this->loggedErrors());

        $this->assertEquals(400, $route->invoke($this->requestWith(['tag' => 'a-b']))->getStatusCode());
    }

    public function testInvokeKeepsLeadingPcreVerbsAtPatternStart()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['name' => '(*UTF)(*UCP)\\w+'];

            public function callback(string $name): array
            {
                return ['name' => $name];
            }
        };

        // (*UTF) (*UCP) はパターン先頭にしか置けない。^(?: の内側に入るとコンパイルエラーで 500 になる
        $response = $route->invoke($this->requestWith(['name' => '日本語']));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(['name' => '日本語'], $response->getData());
        $this->assertSame('', $this->loggedErrors());

        // 指示子を外に出してもアンカーは効いている
        $this->assertEquals(400, $route->invoke($this->requestWith(['name' => '日本 語']))->getStatusCode());
    }

    public function testInvokeReturns500WhenRegexContainsEveryDelimiterCandidate()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['tag' => '[#~%@!`;]+'];

            public function callback(string $tag): array
            {
                return ['tag' => $tag];
            }
        };

        // 区切り文字の候補をすべて含む regex は組み立てられない。定義側の問題なので 500 にしてログに残す
        $response = $route->invoke($this->requestWith(['tag' => '#']));
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame('rest_internal_error', $response->getData()['code']);
        $this->assertStringContainsString('contains every delimiter candidate', $this->loggedErrors());
    }

    public function testInvokeRejectsPartialMatchBecauseRegexIsAnchored()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['kind' => '(a|b)'];

            public function callback(string $kind): array
            {
                return ['kind' => $kind];
            }
        };

        $this->assertEquals(200, $route->invoke($this->requestWith(['kind' => 'a']))->getStatusCode());
        $this->assertEquals(200, $route->invoke($this->requestWith(['kind' => 'b']))->getStatusCode());

        // アンカー無しだと部分一致で通ってしまう値。alternation を (?:...) で囲んでいるので ^a|b$ にも化けない
        foreach (['zzz-a-zzz', 'ab', 'a-', '-b'] as $kind) {
            $response = $route->invoke($this->requestWith(['kind' => $kind]));
            $this->assertEquals(400, $response->getStatusCode(), "kind={$kind}");
            $this->assertSame('rest_invalid_param', $response->getData()['code']);
        }
    }

    public function testInvokeRejectsValueWithTrailingNewline()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['id' => 'integer'];

            public function callback(int $id): array
            {
                return ['id' => $id];
            }
        };

        // $ は既定だと末尾の改行の手前にもマッチするので、?id=123%0A が \d+ を通り抜けないことを確認する
        $response = $route->invoke($this->requestWith(['id' => "123\n"]));
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testInvokeReturns400WhenOptionalStringParamIsEmpty()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['name' => '?string'];

            public function callback(string $name = 'default'): array
            {
                return ['name' => $name];
            }
        };

        // ?name= のように空文字が渡された場合、省略（null）とは区別して \w+ に不一致の 400 にする。
        // 省略時はデフォルト値にフォールバックする
        $response = $route->invoke($this->requestWith(['name' => '']));
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertSame('rest_invalid_param', $response->getData()['code']);

        $response = $route->invoke($this->requestWith([]));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(['name' => 'default'], $response->getData());
    }

    public function testGetArgsExpandsRegexToAnchoredPattern()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'name' => 'string',
                'nick' => '?string',
                'kind' => '(a|b)',
                'id' => 'integer',
                'page' => '?integer',
                'first_name' => ['type' => 'string', 'required' => false, 'regex' => '[a-z]+', 'description' => 'first name'],
            ];
        };

        $args = $route->getArgs();

        // regex はコアの知らないキーなので、string 型は pattern にも展開する。コアは部分一致なのでアンカーを付ける
        $this->assertSame('^(?:\w+)$', $args['name']['pattern']);
        $this->assertSame('^(?:\w+)$', $args['nick']['pattern']);
        $this->assertSame('^(?:(a|b))$', $args['kind']['pattern']);
        $this->assertSame('^(?:[a-z]+)$', $args['first_name']['pattern']);
        // integer はコアが type で検証する。pattern は string 型にしか効かないので付けない
        $this->assertArrayNotHasKey('pattern', $args['id']);
        $this->assertArrayNotHasKey('pattern', $args['page']);

        // コアは type があれば sanitize_params() 経由で検証するので、validate_callback は付けない
        foreach ($args as $name => $arg) {
            $this->assertArrayNotHasKey('validate_callback', $arg, $name);
        }

        // 既存のキーは変えない
        $this->assertSame(['type' => 'string', 'required' => true, 'regex' => '\w+', 'description' => 'name'], array_intersect_key($args['name'], array_flip(['type', 'required', 'regex', 'description'])));
    }

    public function testGetArgsKeepsExplicitPattern()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'kind' => ['type' => 'string', 'required' => false, 'regex' => '(a|b)', 'pattern' => 'a|b', 'description' => 'kind'],
                'untyped' => ['required' => false, 'regex' => '\w+', 'description' => 'untyped'],
            ];
        };

        $args = $route->getArgs();

        // 利用側が明示した pattern はそのまま
        $this->assertSame('a|b', $args['kind']['pattern']);
        // type が無い定義はコアが検証しないので pattern を足しても意味がなく、触らない
        $this->assertArrayNotHasKey('pattern', $args['untyped']);
    }

    public function testGetArgsDoesNotEmitPatternForInvalidRegex()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'broken' => ['type' => 'string', 'required' => true, 'regex' => '[', 'description' => 'broken'],
                'all_delimiters' => ['type' => 'string', 'required' => true, 'regex' => '[#~%@!`;]+', 'description' => 'all'],
                'ok' => '[a-z]+',
            ];

            public function callback(string $broken): array
            {
                return [];
            }
        };

        $args = $route->getArgs();

        // コアに渡すと preg_match() の失敗が「不一致」として 400 になり、定義ミスが入力エラーに見えてしまう。
        // pattern を付けずに invokeCallback() の 500 + ログの経路に任せる
        $this->assertArrayNotHasKey('pattern', $args['broken']);
        $this->assertArrayNotHasKey('pattern', $args['all_delimiters']);
        $this->assertSame('^(?:[a-z]+)$', $args['ok']['pattern']);

        set_error_handler(fn () => true);
        try {
            $response = $route->invoke($this->requestWith(['broken' => 'x']));
        } finally {
            restore_error_handler();
        }
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('has invalid regex "["', $this->loggedErrors());
    }

    public function testGetArgsPatternKeepsLeadingPcreVerbsAtPatternStart()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = ['name' => '(*UTF)(*UCP)\w+'];
        };

        // コアは '#' . pattern . '#u' と組み立てるので、先頭指示子は pattern の先頭にある必要がある
        $this->assertSame('(*UTF)(*UCP)^(?:\w+)$', $route->getArgs()['name']['pattern']);
    }

    public function testGetArgsPatternWorksWithCorePatternValidation()
    {
        $route = new class extends AbstractRoute {
            protected const URL_PARAMS = [
                'path' => '[a-z/]+',
                'kind' => '(a|b)',
                'tag' => '[a-z#]+',
                'name' => '?string',
            ];
        };
        $args = $route->getArgs();

        // WP コアの rest_validate_json_schema_pattern() と同じ組み立てで照合する
        $core = fn (string $pattern, string $value): int => preg_match('#' . str_replace('#', '\\#', $pattern) . '#u', $value);

        $this->assertSame(1, $core($args['path']['pattern'], 'foo/bar'), '/ を含む regex が pattern として動く');
        $this->assertSame(0, $core($args['path']['pattern'], 'Foo'));
        $this->assertSame(1, $core($args['kind']['pattern'], 'a'));
        $this->assertSame(0, $core($args['kind']['pattern'], 'zzz-a-zzz'), 'コアは部分一致なので、アンカーが無いと通ってしまう');
        $this->assertSame(1, $core($args['tag']['pattern'], 'a#b'), 'コア側の # エスケープと衝突しない');
        $this->assertSame(0, $core($args['name']['pattern'], ''), '空文字は \w+ に一致しない');
    }
}
