<?php
namespace Wp\Resta\REST\Http;

/**
 * wp-resta の内部レスポンス表現
 *
 * PSR-7 ResponseInterface とは異なり、純粋な PHP データ（配列、オブジェクト、
 * スカラー値など）を保持し、HTTP プロトコルの詳細（Stream など）は扱わない。
 *
 * このインターフェースにより、AbstractRoute は WordPress 非依存のまま
 * レスポンスを返すことができる。JSON エンコードは WordPress 層（Route.php）で
 * 必要に応じて行われる。
 */
interface RestaResponseInterface
{
    /**
     * HTTP ステータスコードを取得
     *
     * @return int HTTP ステータスコード（200, 404, 500 など）
     */
    public function getStatusCode(): int;

    /**
     * レスポンスデータを取得
     *
     * 純粋な PHP データ（配列、文字列、オブジェクトなど）を返す。
     * JSON エンコード前の生データ。
     *
     * @return mixed レスポンスデータ
     */
    public function getData(): mixed;

    /**
     * HTTP ヘッダーを取得
     *
     * @return array<string, string> ヘッダー名 => 値 の配列
     */
    public function getHeaders(): array;
}
