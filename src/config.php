<?php
/**
 * DI の bind 定義。interface で抽象化したいものをここに置いておけば初期化できます
 * __routeDirectory は特殊で、一つは設定しておく必要がある。
 *
 * return [
 *     '__routeDirectory' => [
 *         ['path/to/dir', 'Route\\Namespace\\'],
 *         ['path/to/dir_two', 'RouteTwo\\Namespace\\'],
 *     ],
 *     LoggerInterface::class => LoggerImpl::class,
 * ];
 */

return [
    '__routeDirectory' => [
        ['wp-content/plugins/wp-restafari/src/REST/Example/Routes', 'Wp\\Restafari\\REST\\Example\\Routes\\'],
        ['wp-content/plugins/wp-restafari/src/Routes', 'Wp\\Restafari\\Routes\\'],
    ],
    '__schemaDirectory' => [
        ['wp-content/plugins/wp-restafari/src/REST/Example/Schemas', 'Wp\\Restafari\\REST\\Example\\Schemas\\'],
    ],
];
