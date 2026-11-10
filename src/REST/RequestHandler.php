<?php
namespace Wp\Resta\REST;

use Throwable;
use Wp\Resta\Auth\Principal;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\REST\Exception\HttpException;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;

/**
 * HTTP 実行のパイプライン（spec「HTTP 実行のパイプライン」）。WordPress に依存しない。
 *
 * ルートは宣言（パラメータ、callback、認可フック）を提供するだけで、実行の順序はここが持つ。
 * バリデーション → 認可 → callback → RouteInvocationEvent の順で、どの段階で止まっても
 * エラーレスポンスの形は同じ。WordPress からは RegisterRestRoutes が WP_REST_Request を変換して呼び、
 * テストからは TestRestaRequest を渡して直接呼ぶ。
 */
final class RequestHandler
{
    public function __construct(
        private readonly Principal $principal,
        private readonly DispatcherInterface $dispatcher,
    ) {}

    public function handle(RouteInterface $route, RestaRequestInterface $request): RestaResponseInterface
    {
        try {
            // 宣言されたパラメータすべてを検証する。callback の引数にあるかどうかは関係ない
            $params = $route->getParameters()->validate($request);
            $route->validate($params);
            // バリデーションの後に認可するので、authorize() が見る値は制約を通った後の値
            $route->authorize($request, $params, $this->principal);

            $callback = $route->getCallback();
            $args = $callback->resolve($params, $request, Container::getInstance());
            $response = $route->respond($callback->invoke($args));
        } catch (HttpException $e) {
            // 入力不正・認可の拒否などクライアント起因のエラー。WP_Error と同じ形で返す
            $response = $route->respond(
                self::errorBody($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getData()),
                $e->getStatusCode(),
            );
        } catch (Throwable $e) {
            // callback のバグや DI 解決失敗など。握り潰さずログに出して 500 を返す
            // 匿名クラスの名前は NUL 文字を含み error_log がそこで切れる。
            // クラス名はメッセージにも含まれうるので、整形後の文字列全体から取り除く
            error_log(str_replace("\0", '', sprintf(
                '[wp-resta] Uncaught %s in %s: %s in %s:%d',
                $e::class,
                $route::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            )));
            $data = [];
            $message = 'Internal Server Error';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $message = $e->getMessage();
                $data['exception'] = $e::class;
            }
            $response = $route->respond(self::errorBody('rest_internal_error', $message, 500, $data), 500);
        }

        $event = new RouteInvocationEvent($request, $route, $response);
        $this->dispatcher->dispatch($event);
        return $event->response;
    }

    /**
     * WP_Error::get_error_data() を含む WP_REST_Server のエラーレスポンスと同じ形のボディ
     *
     * @param array<string, mixed> $data
     * @return array{code: string, message: string, data: array<string, mixed>}
     */
    private static function errorBody(string $code, string $message, int $status, array $data): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'data' => ['status' => $status] + $data,
        ];
    }
}
