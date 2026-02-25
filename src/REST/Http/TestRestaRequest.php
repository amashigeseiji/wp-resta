<?php
namespace Wp\Resta\REST\Http;

use Wp\Resta\REST\AbstractRoute;

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
     * @param string $path リクエストパス（例: '/example/post/123'）
     * @param AbstractRoute $route ルートオブジェクト（パターンと namespace を取得するため）
     * @param array<string, mixed> $queryParams クエリパラメータ（テスト用）
     */
    public function __construct(
        private string $path,
        AbstractRoute $route,
        private array $queryParams = []
    ) {
        $this->parseUrlParams($route->getRouteRegex(), $route->getNamespace());
    }

    public function getUrlParam(string $name): mixed
    {
        return $this->urlParams[$name] ?? null;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
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
        $path = $this->path;

        // wp-json プレフィックスを削除（WordPress 本番環境の場合）
        $path = preg_replace('#^/?wp-json/?#', '/', $path);

        // namespace を削除
        // preg_quote で namespace 内の特殊文字をエスケープ
        if ($namespace && $namespace !== 'default') {
            $namespacePattern = '#^/?' . preg_quote($namespace, '#') . '/?#';
            $path = preg_replace($namespacePattern, '/', $path);
        }

        // ルートパターンとマッチング
        $pattern = '#^/?' . ltrim($routePattern, '/') . '$#';
        if (preg_match($pattern, $path, $matches)) {
            foreach ($matches as $key => $value) {
                if (!is_numeric($key)) {
                    $this->urlParams[$key] = $value;
                }
            }
        }
    }
}
