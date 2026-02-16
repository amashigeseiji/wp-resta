<?php
namespace Test\Resta\Unit\CodeGen\Writers;

use PHPUnit\Framework\TestCase;
use Wp\Resta\CodeGen\Writers\TypeScriptWriter;

class TypeScriptWriterTest extends TestCase
{
    private string $tempDir;
    private TypeScriptWriter $writer;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/wp-resta-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->writer = new TypeScriptWriter();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testWriteCreatesFile(): void
    {
        $path = $this->tempDir . '/test.ts';
        $content = 'export interface Test {}';

        $this->writer->write($path, $content);

        $this->assertFileExists($path);
        $this->assertEquals($content, file_get_contents($path));
    }

    public function testWriteCreatesDirectory(): void
    {
        $path = $this->tempDir . '/nested/dir/test.ts';
        $content = 'export interface Test {}';

        $this->writer->write($path, $content);

        $this->assertFileExists($path);
        $this->assertDirectoryExists(dirname($path));
    }

    public function testWriteCreatesBackup(): void
    {
        $path = $this->tempDir . '/test.ts';
        $originalContent = 'export interface Original {}';
        $newContent = 'export interface Updated {}';

        // 最初にファイルを作成
        file_put_contents($path, $originalContent);

        // 上書き
        $this->writer->write($path, $newContent);

        $this->assertFileExists($path . '.backup');
        $this->assertEquals($originalContent, file_get_contents($path . '.backup'));
        $this->assertEquals($newContent, file_get_contents($path));
    }

    public function testWriteThrowsExceptionForNonWritableDirectory(): void
    {
        $nonWritablePath = '/root/test.ts'; // 通常書き込めないパス

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create directory');

        $this->writer->write($nonWritablePath, 'test');
    }

    public function testWriteMultiple(): void
    {
        $files = [
            $this->tempDir . '/file1.ts' => 'export interface File1 {}',
            $this->tempDir . '/file2.ts' => 'export interface File2 {}',
            $this->tempDir . '/file3.ts' => 'export interface File3 {}',
        ];

        $this->writer->writeMultiple($files);

        foreach ($files as $path => $content) {
            $this->assertFileExists($path);
            $this->assertEquals($content, file_get_contents($path));
        }
    }

    public function testRemoveBackup(): void
    {
        $path = $this->tempDir . '/test.ts';
        $backupPath = $path . '.backup';

        // バックアップを作成
        file_put_contents($path, 'original');
        $this->writer->write($path, 'updated');

        $this->assertFileExists($backupPath);

        // バックアップを削除
        $this->writer->removeBackup($path);

        $this->assertFileDoesNotExist($backupPath);
    }

    public function testRestoreBackup(): void
    {
        $path = $this->tempDir . '/test.ts';
        $originalContent = 'export interface Original {}';
        $newContent = 'export interface Updated {}';

        // 最初にファイルを作成
        file_put_contents($path, $originalContent);

        // 上書き（バックアップが作成される）
        $this->writer->write($path, $newContent);

        // バックアップから復元
        $result = $this->writer->restoreBackup($path);

        $this->assertTrue($result);
        $this->assertEquals($originalContent, file_get_contents($path));
    }

    public function testRestoreBackupReturnsFalseWhenNoBackup(): void
    {
        $path = $this->tempDir . '/test.ts';

        $result = $this->writer->restoreBackup($path);

        $this->assertFalse($result);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
