<?php
namespace Wp\Resta\CodeGen;

use Wp\Resta\OpenApi\ResponseSchema;
use Wp\Resta\CodeGen\Generators\InterfaceGenerator;
use Wp\Resta\CodeGen\Generators\ApiEndpointsGenerator;
use Wp\Resta\CodeGen\Generators\FetchClientGenerator;
use Wp\Resta\CodeGen\Writers\TypeScriptWriter;

/**
 * TypeScript型定義生成のメインオーケストレーター
 */
class TypeScriptGenerator
{
    public function __construct(
        private ResponseSchema $responseSchema,
        private InterfaceGenerator $interfaceGen,
        private ApiEndpointsGenerator $endpointsGen,
        private FetchClientGenerator $clientGen,
        private TypeScriptWriter $writer
    ) {
    }

    /**
     * TypeScript型定義を生成
     *
     * @param string $outputDir 出力ディレクトリ (例: frontend/src/lib/api)
     * @param array{interfaces?: bool, endpoints?: bool, client?: bool, zod?: bool} $options 生成オプション
     * @throws \RuntimeException OpenAPIスキーマの取得に失敗した場合
     */
    public function generate(string $outputDir, array $options = []): void
    {
        // デフォルトオプション
        $options = array_merge([
            'interfaces' => true,
            'endpoints' => true,
            'client' => true,
            'zod' => false, // 将来の拡張用
        ], $options);

        // OpenAPI JSONを取得
        try {
            $openApiJson = $this->responseSchema->responseSchema();
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Failed to get OpenAPI schema. Make sure routes and schemas are properly configured.",
                0,
                $e
            );
        }

        $parser = new OpenApiParser($openApiJson);

        // ファイル生成
        $files = [];

        if ($options['interfaces']) {
            $interfaces = $this->interfaceGen->generate($parser);
            $files[$outputDir . '/schema.ts'] = $interfaces;
        }

        if ($options['endpoints']) {
            $endpoints = $this->endpointsGen->generate($parser);
            $files[$outputDir . '/endpoints.ts'] = $endpoints;
        }

        if ($options['client']) {
            $client = $this->clientGen->generate();
            $files[$outputDir . '/client.ts'] = $client;
        }

        // ファイル書き込み
        try {
            $this->writer->writeMultiple($files);
        } catch (\RuntimeException $e) {
            // エラーが発生した場合、バックアップから復元
            foreach (array_keys($files) as $path) {
                $this->writer->restoreBackup($path);
            }
            throw $e;
        }

        // 成功したらバックアップを削除
        foreach (array_keys($files) as $path) {
            $this->writer->removeBackup($path);
        }
    }

    /**
     * 生成されるファイルのリストを取得（実際には生成しない）
     *
     * @param string $outputDir
     * @param array{interfaces?: bool, endpoints?: bool, client?: bool, zod?: bool} $options
     * @return string[] ファイルパスの配列
     */
    public function getOutputFiles(string $outputDir, array $options = []): array
    {
        $options = array_merge([
            'interfaces' => true,
            'endpoints' => true,
            'client' => true,
            'zod' => false,
        ], $options);

        $files = [];

        if ($options['interfaces']) {
            $files[] = $outputDir . '/schema.ts';
        }

        if ($options['endpoints']) {
            $files[] = $outputDir . '/endpoints.ts';
        }

        if ($options['client']) {
            $files[] = $outputDir . '/client.ts';
        }

        if ($options['zod']) {
            $files[] = $outputDir . '/schema.zod.ts';
        }

        return $files;
    }
}
