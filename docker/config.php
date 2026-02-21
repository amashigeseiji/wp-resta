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
    ],

    // OpenAPI スキーマ定義のディレクトリ
    'schemaDirectory' => [
        [__DIR__ . '/../src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
    ],

    // WordPress フックプロバイダー
    'hooks' => [
        \Wp\Resta\Hooks\SwaggerHooks::class,
        \Wp\Resta\REST\Example\Hooks\SampleHook::class,
    ],

    // DI コンテナのバインド設定
    'dependencies' => [],
];
