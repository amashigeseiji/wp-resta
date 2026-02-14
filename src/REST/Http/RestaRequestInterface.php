<?php
namespace Wp\Resta\REST\Http;

/**
 * wp-resta の内部リクエスト表現
 *
 * PSR-7 RequestInterface とは異なり、HTTP プロトコルの詳細ではなく、
 * wp-resta が必要とする情報のみを抽象化する。
 *
 * このインターフェースは WordPress 非依存であり、テスト環境でも
 * 実装可能な最小限のメソッドのみを定義する。
 */
interface RestaRequestInterface
{
    /**
     * URL パラメータを取得
     *
     * ルート定義の [id] や [slug] などのパラメータを取得する。
     *
     * @param string $name パラメータ名
     * @return mixed パラメータ値（存在しない場合は null）
     */
    public function getUrlParam(string $name): mixed;
}
