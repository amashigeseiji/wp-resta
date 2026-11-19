<?php
/**
 * OpenAPI 定義を JSON ファイルに書き出す。WordPress を起動せずに動く
 *
 * Usage:
 *   php scripts/generate-openapi.php <config-file> [output-file] [server-url]
 *
 * Arguments:
 *   config-file  Resta::init() に渡すのと同じ配列を返す PHP ファイル
 *   output-file  出力先（既定: openapi.json）
 *   server-url   servers[0].url に書く REST API の URL（既定: /wp-json/）
 *
 * WordPress を読み込まないので、設定ファイルと Route クラスのコンストラクタが
 * WordPress の関数や定数（ABSPATH など）を使っていると失敗する。その場合は、
 * 動いている WordPress の /wp-json/resta/v1/schema（SwaggerHook）から取得する
 */

use Wp\Resta\Auth\Principal;
use Wp\Resta\Auth\WpPrincipal;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\OpenApi\ResponseSchema;

// このリポジトリで直接実行する場合と、依存として vendor/ に入っている場合の両方で動かす
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../autoload.php'] as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;
        break;
    }
}

$configFile = $argv[1] ?? null;
$outputFile = $argv[2] ?? 'openapi.json';
$serverUrl = $argv[3] ?? '/wp-json/';

if ($configFile === null) {
    fwrite(STDERR, "Usage: php scripts/generate-openapi.php <config-file> [output-file] [server-url]\n");
    exit(1);
}
if (!file_exists($configFile)) {
    fwrite(STDERR, "Config file not found: {$configFile}\n");
    exit(1);
}

// ResponseSchema が servers に書く URL。WordPress の外なので引数の値を返す
if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        global $serverUrl;
        return $serverUrl . ltrim($path, '/');
    }
}

try {
    $restaConfig = require $configFile;
    if (isset($restaConfig['autoloader'])) {
        require_once $restaConfig['autoloader'];
    }
    $config = new Config($restaConfig);

    // Route クラスをインスタンス化できるように、Resta::init() と同じ依存を bind する。
    // フックやアダプタは登録しない
    $container = Container::getInstance();
    $container->bind(Config::class, $config);
    $container->bind(Principal::class, WpPrincipal::class);
    foreach ($config->dependencies as $interface => $dependency) {
        if (is_string($interface)) {
            $container->bind($interface, $dependency);
        } else {
            $container->bind($dependency);
        }
    }
    $container->bind(DispatcherInterface::class, new Dispatcher());

    $spec = $container->get(ResponseSchema::class)->responseSchema();
    $json = json_encode(
        $spec,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . "\n";

    if (file_put_contents($outputFile, $json) === false) {
        throw new RuntimeException("Cannot write to: {$outputFile}");
    }

    $pathCount = count((array) $spec['paths']);
    echo "Wrote {$outputFile} ({$pathCount} paths)\n";
    if ($pathCount === 0) {
        fwrite(STDERR, "Warning: no routes found. Check routeDirectory in {$configFile}\n");
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e::class . ": {$e->getMessage()}\n");
    fwrite(STDERR, "  at {$e->getFile()}:{$e->getLine()}\n");
    exit(1);
}
