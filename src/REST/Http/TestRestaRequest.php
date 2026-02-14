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
     * @param AbstractRoute $route ルートオブジェクト（パターンを取得するため）
     */
    public function __construct(
        private string $path,
        AbstractRoute $route
    ) {
        $this->parseUrlParams($route->getRouteRegex());
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
     */
    private function parseUrlParams(string $routePattern): void
    {
        // namespace prefix を削除 (例: /wp-json/example/ を削除)
        $pathParts = explode('/', trim($this->path, '/'));
        if (count($pathParts) >= 2) {
            array_shift($pathParts); // 'wp-json' など
            array_shift($pathParts); // 'example' など
            $path = '/' . implode('/', $pathParts);
        } else {
            $path = $this->path;
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
