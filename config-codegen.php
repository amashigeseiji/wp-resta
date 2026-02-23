<?php
/**
 * TypeScript型定義生成用の設定ファイル
 */

return [
    // Composer オートローダーのパス
    'autoloader' => __DIR__ . '/vendor/autoload.php',

    // REST API ルート定義のディレクトリ
    'routeDirectory' => [
        [__DIR__ . '/src/REST/Example/Routes', 'Wp\\Resta\\REST\\Example\\Routes\\', 'example'],
    ],

    // OpenAPI スキーマ定義のディレクトリ
    'schemaDirectory' => [
        [__DIR__ . '/src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
    ],

    // WordPress フックプロバイダー（型生成では使用しない）
    'hooks' => [],

    // DI コンテナのバインド設定
    'dependencies' => [],
];
