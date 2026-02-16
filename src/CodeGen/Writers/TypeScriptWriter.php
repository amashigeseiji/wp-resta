<?php
namespace Wp\Resta\CodeGen\Writers;

/**
 * TypeScriptファイル書き込み
 * ディレクトリ作成、バックアップ、エラーハンドリング
 */
class TypeScriptWriter
{
    /**
     * TypeScriptファイルを書き込む
     *
     * @param string $path 書き込み先のファイルパス
     * @param string $content ファイル内容
     * @throws \RuntimeException ディレクトリ作成や書き込みに失敗した場合
     */
    public function write(string $path, string $content): void
    {
        // ディレクトリが存在しない場合は作成
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$dir}");
            }
        }

        // ディレクトリが書き込み可能かチェック
        if (!is_writable($dir)) {
            throw new \RuntimeException("Output directory is not writable: {$dir}");
        }

        // 既存ファイルがある場合はバックアップ
        if (file_exists($path)) {
            $backup = $path . '.backup';
            if (!copy($path, $backup)) {
                throw new \RuntimeException("Failed to create backup file: {$backup}");
            }
        }

        // ファイルに書き込み
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }
    }

    /**
     * 複数のファイルを一括で書き込む
     *
     * @param array<string, string> $files キーがファイルパス、値が内容の配列
     * @throws \RuntimeException いずれかのファイル書き込みに失敗した場合
     */
    public function writeMultiple(array $files): void
    {
        foreach ($files as $path => $content) {
            $this->write($path, $content);
        }
    }

    /**
     * バックアップファイルを削除
     *
     * @param string $path 元のファイルパス
     */
    public function removeBackup(string $path): void
    {
        $backup = $path . '.backup';
        if (file_exists($backup)) {
            unlink($backup);
        }
    }

    /**
     * バックアップファイルを復元
     *
     * @param string $path 元のファイルパス
     * @return bool 復元に成功した場合true
     */
    public function restoreBackup(string $path): bool
    {
        $backup = $path . '.backup';
        if (file_exists($backup)) {
            return copy($backup, $path);
        }
        return false;
    }
}
