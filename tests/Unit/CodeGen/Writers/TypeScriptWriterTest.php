<?php

namespace Test\Resta\Unit\CodeGen\Writers;

use PHPUnit\Framework\TestCase;
use Wp\Resta\CodeGen\Writers\TypeScriptWriter;

class TypeScriptWriterTest extends TestCase
{
    private TypeScriptWriter $writer;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->writer = new TypeScriptWriter();
        $this->tmpDir = sys_get_temp_dir() . '/typescript-writer-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // テスト用ディレクトリを再帰削除
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // write()
    // -------------------------------------------------------------------------

    public function testWriteCreatesFileWithContent(): void
    {
        $path = $this->tmpDir . '/schema.ts';
        $this->writer->write($path, 'export interface Foo {}');

        $this->assertFileExists($path);
        $this->assertSame('export interface Foo {}', file_get_contents($path));
    }

    public function testWriteCreatesDirectoryIfNotExists(): void
    {
        $path = $this->tmpDir . '/nested/dir/schema.ts';
        $this->writer->write($path, 'content');

        $this->assertDirectoryExists($this->tmpDir . '/nested/dir');
        $this->assertFileExists($path);
    }

    public function testWriteCreatesBackupIfFileAlreadyExists(): void
    {
        $path = $this->tmpDir . '/schema.ts';
        file_put_contents($path, 'old content');

        $this->writer->write($path, 'new content');

        $this->assertFileExists($path . '.backup');
        $this->assertSame('old content', file_get_contents($path . '.backup'));
        $this->assertSame('new content', file_get_contents($path));
    }

    public function testWriteDoesNotCreateBackupForNewFile(): void
    {
        $path = $this->tmpDir . '/schema.ts';
        $this->writer->write($path, 'content');

        $this->assertFileDoesNotExist($path . '.backup');
    }

    // -------------------------------------------------------------------------
    // writeMultiple()
    // -------------------------------------------------------------------------

    public function testWriteMultipleWritesAllFiles(): void
    {
        $files = [
            $this->tmpDir . '/schema.ts'    => 'interface content',
            $this->tmpDir . '/endpoints.ts' => 'endpoints content',
            $this->tmpDir . '/client.ts'    => 'client content',
        ];

        $this->writer->writeMultiple($files);

        foreach ($files as $path => $content) {
            $this->assertFileExists($path);
            $this->assertSame($content, file_get_contents($path));
        }
    }

    // -------------------------------------------------------------------------
    // removeBackup()
    // -------------------------------------------------------------------------

    public function testRemoveBackupDeletesBackupFile(): void
    {
        $path = $this->tmpDir . '/schema.ts';
        file_put_contents($path . '.backup', 'backup content');

        $this->writer->removeBackup($path);

        $this->assertFileDoesNotExist($path . '.backup');
    }

    public function testRemoveBackupDoesNothingIfNoBackupExists(): void
    {
        $path = $this->tmpDir . '/schema.ts';

        // 例外が出ないことを確認
        $this->writer->removeBackup($path);
        $this->assertFileDoesNotExist($path . '.backup');
    }

    // -------------------------------------------------------------------------
    // restoreBackup()
    // -------------------------------------------------------------------------

    public function testRestoreBackupRestoresFileFromBackup(): void
    {
        $path = $this->tmpDir . '/schema.ts';
        file_put_contents($path . '.backup', 'backup content');
        file_put_contents($path, 'broken content');

        $result = $this->writer->restoreBackup($path);

        $this->assertTrue($result);
        $this->assertSame('backup content', file_get_contents($path));
    }

    public function testRestoreBackupReturnsFalseIfNoBackupExists(): void
    {
        $path = $this->tmpDir . '/schema.ts';

        $result = $this->writer->restoreBackup($path);

        $this->assertFalse($result);
    }
}
