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
    'autoloader' => ABSPATH . 'vendor/autoload.php',
    'routeDirectory' => [
        [ABSPATH . 'wp-content/plugins/wp-resta/src/REST/Example/Routes', 'Wp\\Resta\\REST\\Example\\Routes\\', 'example'],
        [ABSPATH . 'wp-content/plugins/wp-resta/src/Routes', 'Wp\\Resta\\Routes\\'],
    ],
    'schemaDirectory' => [
        [ABSPATH . 'wp-content/plugins/wp-resta/src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
    ],
    'hooks' => [
        \Wp\Resta\Hooks\SwaggerHooks::class, // 本番で不要の場合は外してください。
        \Wp\Resta\REST\Example\Hooks\SampleHook::class,
    ],
    'dependencies' => [], // DI の定義です。interface に対して実装をバインドする際に設定してください。
];
