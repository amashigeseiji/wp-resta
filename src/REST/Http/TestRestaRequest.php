<?php
namespace Wp\Resta\REST\Http;

use Wp\Resta\REST\RouteInterface;

/**
 * テスト用の RestaRequestInterface 実装
 *
 * WordPress 環境なしでテスト可能にする。
 * URL パターンからパラメータを抽出する。
 */
class TestRestaRequest implements RestaRequestInterface
{
    /** @var array<string, mixed> */
    private array $urlParams = [];

    /**
     * @param string $path リクエストパス。クエリ文字列を含めてよい（例: '/example/post/123?name=foo'）
     * @param RouteInterface $route ルートオブジェクト（パターンと namespace を取得するため）
     * @param string $method HTTP メソッド。省略すると GET
     */
    public function __construct(
        private string $path,
        RouteInterface $route,
        private string $method = 'GET',
    ) {
        $this->method = strtoupper($method);
        $this->parseUrlParams($route->getRouteRegex(), $route->getNamespace());
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUrlParam(string $name): mixed
    {
        return $this->urlParams[$name] ?? null;
    }

    /**
     * URL パスからパラメータを抽出
     *
     * ルートのパターン（例: '(?P<id>\d+)'）を使って、
     * 実際の URL パス（例: '/example/post/123'）から
     * パラメータ（例: ['id' => '123']）を抽出する。
     *
     * Route オブジェクトから namespace を取得することで、
     * スラッシュを含む namespace (例: 'my/route') にも対応。
     *
     * @param string $routePattern ルートの正規表現パターン
     * @param string $namespace API namespace
     */
    private function parseUrlParams(string $routePattern, string $namespace): void
    {
        // クエリ文字列を分離する。同名ならパスパラメータが優先なので先に入れておき、後でパスの値で上書きする
        $path = $this->path;
        $queryPosition = strpos($path, '?');
        if ($queryPosition !== false) {
            parse_str(substr($path, $queryPosition + 1), $query);
            $this->urlParams = $query;
            $path = substr($path, 0, $queryPosition);
        }

        // wp-json プレフィックスを削除（WordPress 本番環境の場合）
        $path = preg_replace('#^/?wp-json/?#', '/', $path);

        // namespace を削除
        // preg_quote で namespace 内の特殊文字をエスケープ
        if ($namespace && $namespace !== 'default') {
            $namespacePattern = '#^/?' . preg_quote($namespace, '#') . '/?#';
            $path = preg_replace($namespacePattern, '/', $path);
        }

        // ルートパターンとマッチング。WP コアと同じくエンコードされたままのパスに照合し、
        // 取り出した値は WpRestaRequest と同じくデコードして callback に渡す
        $pattern = '#^/?' . ltrim($routePattern, '/') . '$#';
        if (preg_match($pattern, $path, $matches)) {
            foreach ($matches as $key => $value) {
                if (!is_numeric($key)) {
                    $this->urlParams[$key] = rawurldecode($value);
                }
            }
        }
    }
}
