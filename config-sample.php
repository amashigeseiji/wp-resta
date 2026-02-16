<?php
/**
 * wp-resta プラグイン設定ファイル（サンプル）
 * プラグインとして利用する際に、ファイルをコピーして利用してください。
 *
 * どのファイルが利用されるかは環境変数 RESTA_CONFIG_FILE で決定します。
 * resta-config-prod.php と resta-config-dev.php を用意し, 環境によって
 * 利用する設定ファイルを分岐することができます。
 */

return [
    // Composer オートローダーのパス（オプション）
    'autoloader' => ABSPATH . 'vendor/autoload.php',

    // REST API ルート定義のディレクトリ（必須）
    // 配列の各要素: [ディレクトリパス, PHP名前空間, APIネームスペース（オプション）]
    // 例: ['path/to/routes', 'MyApp\\Routes\\', 'api/v1']
    'routeDirectory' => [
        [ABSPATH . 'wp-content/plugins/wp-resta/src/REST/Example/Routes', 'Wp\\Resta\\REST\\Example\\Routes\\', 'example'],
        [ABSPATH . 'wp-content/plugins/wp-resta/src/Routes', 'Wp\\Resta\\Routes\\'],
    ],

    // OpenAPI スキーマ定義のディレクトリ（オプション）
    // 配列の各要素: [ディレクトリパス, PHP名前空間]
    'schemaDirectory' => [
        [ABSPATH . 'wp-content/plugins/wp-resta/src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
    ],

    // WordPress フックプロバイダー（オプション）
    // HookProviderInterface を実装したクラスを指定
    // SwaggerHooks: Swagger UI を有効化（開発環境推奨、本番では削除）
    // EnvelopeHook: エンベロープパターン（#[Envelope] Attribute）を有効化
    'hooks' => [
        \Wp\Resta\Hooks\SwaggerHooks::class,
        \Wp\Resta\REST\Hooks\EnvelopeHook::class,
        \Wp\Resta\REST\Example\Hooks\SampleHook::class,
    ],

    // DI コンテナのバインド設定（オプション）
    // インターフェースに対する実装クラスのバインドを定義
    // 例: [LoggerInterface::class => MonologLogger::class]
    'dependencies' => [],

    // 非推奨: 代わりに hooks 配列で SwaggerHooks を指定してください
    // 後方互換性のため残されています
    'use-swagger' => false,
];
