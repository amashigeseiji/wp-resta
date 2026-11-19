<?php
/**
 * wp-resta プラグイン設定ファイル（Docker 開発環境用）
 */

return [
    // Composer オートローダーのパス
    'autoloader' => __DIR__ . '/../vendor/autoload.php',

    // REST API ルート定義のディレクトリ
    'routeDirectory' => [
        [__DIR__ . '/../src/REST/Example/Routes', 'Wp\\Resta\\REST\\Example\\Routes\\', 'example'],
        // E2E のフィクスチャ。本体の挙動を確かめるためのルートで、手本ではない
        [__DIR__ . '/../tests/E2E/Fixtures/Routes', 'Test\\Resta\\E2E\\Fixtures\\Routes\\', 'fixture'],
    ],

    // OpenAPI スキーマ定義のディレクトリ
    'schemaDirectory' => [
        [__DIR__ . '/../src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
        [__DIR__ . '/../tests/E2E/Fixtures/Schemas', 'Test\\Resta\\E2E\\Fixtures\\Schemas\\'],
    ],

    // WordPress フックプロバイダー
    'hooks' => [
        \Wp\Resta\Hooks\SwaggerHook::class,
        \Wp\Resta\REST\Example\Hooks\SampleHook::class,
        \Test\Resta\E2E\Fixtures\Hooks\MetaHook::class,
    ],

    // DI コンテナのバインド設定
    'dependencies' => [],
    'adapters' => [
        \Wp\Resta\Kernel\WpKernelAdapter::class
    ],
];
