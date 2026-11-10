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
     * 宣言された名前に対してクライアントが送った値を返す。無ければ null。
     *
     * パスとクエリの値は文字列で、パスパラメータはパーセントデコード済み。配列で送られた値（?tags[]=a）は配列。
     * 型変換と検証はここでは行わず、RouteParameters::validate() の仕事。
     * 同名のパスパラメータとクエリパラメータがあればパスパラメータ。
     *
     * @param string $name パラメータ名
     */
    public function getUrlParam(string $name): mixed;
}
