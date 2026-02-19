<?php

namespace Test\Resta\Unit\CodeGen;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wp\Resta\CodeGen\TypeScriptGenerator;
use Wp\Resta\CodeGen\Generators\InterfaceGenerator;
use Wp\Resta\CodeGen\Generators\ApiEndpointsGenerator;
use Wp\Resta\CodeGen\Generators\FetchClientGenerator;
use Wp\Resta\CodeGen\Writers\TypeScriptWriter;
use Wp\Resta\OpenApi\ResponseSchema;

class TypeScriptGeneratorTest extends TestCase
{
    /** @var ResponseSchema&MockObject */
    private ResponseSchema $responseSchema;

    /** @var InterfaceGenerator&MockObject */
    private InterfaceGenerator $interfaceGen;

    /** @var ApiEndpointsGenerator&MockObject */
    private ApiEndpointsGenerator $endpointsGen;

    /** @var FetchClientGenerator&MockObject */
    private FetchClientGenerator $clientGen;

    /** @var TypeScriptWriter&MockObject */
    private TypeScriptWriter $writer;

    private TypeScriptGenerator $generator;

    /** 最小限の有効な OpenAPI スペック */
    private array $minimalSpec = [
        'openapi' => '3.0.0',
        'paths' => [],
        'components' => ['schemas' => []],
    ];

    protected function setUp(): void
    {
        $this->responseSchema = $this->createMock(ResponseSchema::class);
        $this->interfaceGen   = $this->createMock(InterfaceGenerator::class);
        $this->endpointsGen   = $this->createMock(ApiEndpointsGenerator::class);
        $this->clientGen      = $this->createMock(FetchClientGenerator::class);
        $this->writer         = $this->createMock(TypeScriptWriter::class);

        $this->generator = new TypeScriptGenerator(
            $this->responseSchema,
            $this->interfaceGen,
            $this->endpointsGen,
            $this->clientGen,
            $this->writer
        );
    }

    // -------------------------------------------------------------------------
    // getOutputFiles()
    // -------------------------------------------------------------------------

    public function testGetOutputFilesDefaultOptions(): void
    {
        $files = $this->generator->getOutputFiles('/out');

        $this->assertContains('/out/schema.ts', $files);
        $this->assertContains('/out/endpoints.ts', $files);
        $this->assertContains('/out/client.ts', $files);
        $this->assertCount(3, $files);
    }

    public function testGetOutputFilesWithInterfacesFalse(): void
    {
        $files = $this->generator->getOutputFiles('/out', ['interfaces' => false]);

        $this->assertNotContains('/out/schema.ts', $files);
        $this->assertContains('/out/endpoints.ts', $files);
        $this->assertContains('/out/client.ts', $files);
    }

    public function testGetOutputFilesWithEndpointsFalse(): void
    {
        $files = $this->generator->getOutputFiles('/out', ['endpoints' => false]);

        $this->assertContains('/out/schema.ts', $files);
        $this->assertNotContains('/out/endpoints.ts', $files);
        $this->assertContains('/out/client.ts', $files);
    }

    public function testGetOutputFilesWithClientFalse(): void
    {
        $files = $this->generator->getOutputFiles('/out', ['client' => false]);

        $this->assertContains('/out/schema.ts', $files);
        $this->assertContains('/out/endpoints.ts', $files);
        $this->assertNotContains('/out/client.ts', $files);
    }

    public function testGetOutputFilesWithZodTrue(): void
    {
        $files = $this->generator->getOutputFiles('/out', ['zod' => true]);

        $this->assertContains('/out/schema.zod.ts', $files);
    }

    public function testGetOutputFilesWithZodFalseByDefault(): void
    {
        $files = $this->generator->getOutputFiles('/out');

        $this->assertNotContains('/out/schema.zod.ts', $files);
    }

    public function testGetOutputFilesAllFalseReturnsEmptyArray(): void
    {
        $files = $this->generator->getOutputFiles('/out', [
            'interfaces' => false,
            'endpoints'  => false,
            'client'     => false,
            'zod'        => false,
        ]);

        $this->assertEmpty($files);
    }

    public function testGetOutputFilesRespectsOutputDir(): void
    {
        $files = $this->generator->getOutputFiles('/custom/dir');

        foreach ($files as $file) {
            $this->assertStringStartsWith('/custom/dir/', $file);
        }
    }

    // -------------------------------------------------------------------------
    // generate() — 各ジェネレーターの呼び出し
    // -------------------------------------------------------------------------

    public function testGenerateCallsInterfaceGeneratorWhenEnabled(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->expects($this->once())->method('generate');
        $this->endpointsGen->method('generate')->willReturn('');
        $this->clientGen->method('generate')->willReturn('');
        $this->writer->method('writeMultiple');
        $this->writer->method('removeBackup');

        $this->generator->generate('/out');
    }

    public function testGenerateSkipsInterfaceGeneratorWhenDisabled(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->expects($this->never())->method('generate');
        $this->endpointsGen->method('generate')->willReturn('');
        $this->clientGen->method('generate')->willReturn('');
        $this->writer->method('writeMultiple');
        $this->writer->method('removeBackup');

        $this->generator->generate('/out', ['interfaces' => false]);
    }

    public function testGenerateCallsEndpointsGeneratorWhenEnabled(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->method('generate')->willReturn('');
        $this->endpointsGen->expects($this->once())->method('generate');
        $this->clientGen->method('generate')->willReturn('');
        $this->writer->method('writeMultiple');
        $this->writer->method('removeBackup');

        $this->generator->generate('/out');
    }

    public function testGenerateSkipsEndpointsGeneratorWhenDisabled(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->method('generate')->willReturn('');
        $this->endpointsGen->expects($this->never())->method('generate');
        $this->clientGen->method('generate')->willReturn('');
        $this->writer->method('writeMultiple');
        $this->writer->method('removeBackup');

        $this->generator->generate('/out', ['endpoints' => false]);
    }

    public function testGenerateCallsFetchClientGeneratorWhenEnabled(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->method('generate')->willReturn('');
        $this->endpointsGen->method('generate')->willReturn('');
        $this->clientGen->expects($this->once())->method('generate');
        $this->writer->method('writeMultiple');
        $this->writer->method('removeBackup');

        $this->generator->generate('/out');
    }

    public function testGenerateSkipsFetchClientGeneratorWhenDisabled(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->method('generate')->willReturn('');
        $this->endpointsGen->method('generate')->willReturn('');
        $this->clientGen->expects($this->never())->method('generate');
        $this->writer->method('writeMultiple');
        $this->writer->method('removeBackup');

        $this->generator->generate('/out', ['client' => false]);
    }

    // -------------------------------------------------------------------------
    // generate() — ファイル書き込み
    // -------------------------------------------------------------------------

    public function testGenerateWritesFilesToCorrectPaths(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->method('generate')->willReturn('interface-content');
        $this->endpointsGen->method('generate')->willReturn('endpoints-content');
        $this->clientGen->method('generate')->willReturn('client-content');
        $this->writer->method('removeBackup');

        $this->writer->expects($this->once())
            ->method('writeMultiple')
            ->with($this->callback(function (array $files): bool {
                return isset($files['/out/schema.ts'])
                    && isset($files['/out/endpoints.ts'])
                    && isset($files['/out/client.ts'])
                    && $files['/out/schema.ts'] === 'interface-content'
                    && $files['/out/endpoints.ts'] === 'endpoints-content'
                    && $files['/out/client.ts'] === 'client-content';
            }));

        $this->generator->generate('/out');
    }

    public function testGenerateRemovesBackupsOnSuccess(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->method('generate')->willReturn('');
        $this->endpointsGen->method('generate')->willReturn('');
        $this->clientGen->method('generate')->willReturn('');
        $this->writer->method('writeMultiple');

        // デフォルトオプション (3ファイル) なので removeBackup が3回呼ばれる
        $this->writer->expects($this->exactly(3))->method('removeBackup');

        $this->generator->generate('/out');
    }

    public function testGenerateRemovesBackupsOnlyForEnabledFiles(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->method('generate')->willReturn('');
        $this->endpointsGen->method('generate')->willReturn('');
        $this->writer->method('writeMultiple');

        // client: false なので2ファイル分のみ
        $this->writer->expects($this->exactly(2))->method('removeBackup');

        $this->generator->generate('/out', ['client' => false]);
    }

    // -------------------------------------------------------------------------
    // generate() — エラーハンドリング
    // -------------------------------------------------------------------------

    public function testGenerateThrowsRuntimeExceptionWhenResponseSchemaFails(): void
    {
        $this->responseSchema->method('responseSchema')
            ->willThrowException(new \Exception('Schema error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get OpenAPI schema');

        $this->generator->generate('/out');
    }

    public function testGenerateRestoresBackupsWhenWriteFails(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->method('generate')->willReturn('');
        $this->endpointsGen->method('generate')->willReturn('');
        $this->clientGen->method('generate')->willReturn('');

        $this->writer->method('writeMultiple')
            ->willThrowException(new \RuntimeException('Disk full'));

        // 書き込み失敗時は restoreBackup が3ファイル分呼ばれる
        $this->writer->expects($this->exactly(3))->method('restoreBackup');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disk full');

        $this->generator->generate('/out');
    }

    public function testGenerateDoesNotRemoveBackupsWhenWriteFails(): void
    {
        $this->responseSchema->method('responseSchema')->willReturn($this->minimalSpec);
        $this->interfaceGen->method('generate')->willReturn('');
        $this->endpointsGen->method('generate')->willReturn('');
        $this->clientGen->method('generate')->willReturn('');

        $this->writer->method('writeMultiple')
            ->willThrowException(new \RuntimeException('Disk full'));
        $this->writer->method('restoreBackup');

        // 書き込み失敗時は removeBackup が呼ばれない
        $this->writer->expects($this->never())->method('removeBackup');

        try {
            $this->generator->generate('/out');
        } catch (\RuntimeException) {
            // 例外は期待通り
        }
    }
}
