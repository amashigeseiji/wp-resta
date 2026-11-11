<?php
namespace Test\Resta\Unit\REST;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * $status / $body / $headers プロパティの非推奨化（#59 手順 2）。
 * 0.12 では E_USER_DEPRECATED を出しつつ動作は変えない。0.13 でプロパティごと削除する
 */
class DeprecatedPropertiesTest extends TestCase
{
    private function handle(AbstractRoute $route, string $path = '/x'): RestaResponseInterface
    {
        return (new RequestHandler(new TestPrincipal(), new Dispatcher()))
            ->handle($route, new TestRestaRequest($path, $route));
    }

    /**
     * ハンドラを通して E_USER_DEPRECATED を集める。出ないことも確かめられる
     *
     * @return list<string>
     */
    private function collectDeprecations(callable $run): array
    {
        $messages = [];
        set_error_handler(function (int $errno, string $message) use (&$messages): bool {
            if ($errno === E_USER_DEPRECATED) {
                $messages[] = $message;
                return true;
            }
            return false;
        });
        try {
            $run();
        } finally {
            restore_error_handler();
        }
        return $messages;
    }

    public function testHeadersConstantIsTheDefaultHeaders(): void
    {
        $route = new class extends AbstractRoute {
            protected const HEADERS = ['X-Declared' => 'yes'];
            public function callback(): string
            {
                return 'ok';
            }
        };

        $messages = $this->collectDeprecations(function () use ($route, &$response) {
            $response = $this->handle($route);
        });

        $this->assertSame([], $messages, 'HEADERS 定数と response() だけなら警告は出ない');
        $this->assertSame(['X-Declared' => 'yes'], $route->getHeaders());
        $this->assertSame(['X-Declared' => 'yes'], $response->getHeaders());
    }

    public function testHeadersPropertyStillWorksButIsDeprecated(): void
    {
        $route = new class extends AbstractRoute {
            protected const HEADERS = ['X-Declared' => 'const', 'X-Only-Const' => '1'];
            protected array $headers = ['X-Declared' => 'property'];
            public function callback(): string
            {
                return 'ok';
            }
        };

        $headers = null;
        $messages = $this->collectDeprecations(function () use ($route, &$headers) {
            $headers = $route->getHeaders();
        });

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('$headers property is deprecated', $messages[0]);
        $this->assertStringContainsString('HEADERS constant', $messages[0]);
        $this->assertSame(['X-Declared' => 'property', 'X-Only-Const' => '1'], $headers, 'プロパティが定数より優先される');
    }

    public function testStatusAssignmentIsDeprecatedButStillApplied(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(): ?array
            {
                $this->status = 404;
                return null;
            }
        };

        $messages = $this->collectDeprecations(function () use ($route, &$response) {
            $response = $this->handle($route);
        });

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('callback() assigned $status', $messages[0]);
        $this->assertStringContainsString('return $this->response($body, $status, $headers) instead', $messages[0]);
        $this->assertSame(404, $response->getStatusCode(), '0.12 では動作は変えない');
    }

    public function testBodyAndHeadersAssignmentsAreDeprecated(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(): string
            {
                $this->body = 'ignored';
                $this->headers['X-Assigned'] = '1';
                return 'returned';
            }
        };

        $messages = $this->collectDeprecations(function () use ($route, &$response) {
            $response = $this->handle($route);
        });

        $this->assertStringContainsString('assigned $body / $headers', $messages[0]);
        $this->assertSame('returned', $response->getData(), '戻り値が body。$body への代入は効かない');
        $this->assertSame(['X-Assigned' => '1'], $response->getHeaders(), '代入したヘッダは getHeaders() 経由で効く（こちらも警告）');
    }

    public function testResponseHelperDoesNotWarn(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(): RestaResponseInterface
            {
                return $this->response(null, 404, ['X-Resp' => '1']);
            }
        };

        $messages = $this->collectDeprecations(function () use ($route, &$response) {
            $response = $this->handle($route);
        });

        $this->assertSame([], $messages);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['X-Resp' => '1'], $response->getHeaders());
    }

    public function testSubclassInitialValuesAreNotAssignments(): void
    {
        // 宣言時の初期値を変えているだけなら代入ではない（0.11 までの書き方。$headers は getHeaders() 側で警告する）
        $route = new class extends AbstractRoute {
            protected int $status = 201;
            public function callback(): string
            {
                return 'created';
            }
        };

        $messages = $this->collectDeprecations(function () use ($route, &$response) {
            $response = $this->handle($route);
        });

        $this->assertSame([], $messages);
        $this->assertSame(201, $response->getStatusCode());
    }
}
