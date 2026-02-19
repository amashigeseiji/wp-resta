<?php
/**
 * TypeScript型定義生成スクリプト
 *
 * Usage:
 *   php scripts/generate-types.php [config-file] [output-dir]
 *
 * Arguments:
 *   config-file  Config file path (default: config-sample.php)
 *   output-dir   Output directory (default: frontend/src/lib/api)
 */

require __DIR__ . '/../vendor/autoload.php';

use Wp\Resta\CodeGen\TypeScriptGenerator;
use Wp\Resta\DI\Container;
use Wp\Resta\Config;
use Wp\Resta\Resta;

// WordPress定数を定義（WordPress環境外で実行する場合）
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// WordPress関数のスタブ（WordPress環境外で実行する場合）
if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string {
        return 'http://localhost:8080/wp-json' . $path;
    }
}

// 引数解析
$configFile = $argv[1] ?? __DIR__ . '/../config-sample.php';
$outputDir = $argv[2] ?? __DIR__ . '/../frontend/src/lib/api';

try {
    // Configファイルの存在チェック
    if (!file_exists($configFile)) {
        throw new RuntimeException("Config file not found: {$configFile}");
    }

    echo "Loading configuration from: {$configFile}\n";

    // Config読み込み
    $restaConfig = require $configFile;
    $config = new Config($restaConfig);

    // DIコンテナ初期化（WordPress関数を使わずに）
    $container = Container::getInstance();
    $container->bind(Config::class, $config);

    // 必要なクラスを手動でバインド（Restaを経由せず）
    // RegisterRestRoutesとSchemasを初期化
    $container->bind(\Wp\Resta\REST\RegisterRestRoutes::class);
    $container->bind(\Wp\Resta\REST\Schemas\Schemas::class);
    $container->bind(\Wp\Resta\OpenApi\ResponseSchema::class);
    $container->bind(\Wp\Resta\CodeGen\TypeScriptGenerator::class);

    // Generatorを取得
    $generator = $container->get(TypeScriptGenerator::class);

    // 生成するファイルのリストを表示
    echo "Generating TypeScript types to: {$outputDir}\n";
    $files = $generator->getOutputFiles($outputDir);
    foreach ($files as $file) {
        echo "  - " . basename($file) . "\n";
    }

    // 生成実行
    $generator->generate($outputDir);

    echo "\n✓ TypeScript generation completed successfully!\n";
    echo "\nGenerated files:\n";
    foreach ($files as $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            echo "  ✓ {$file} (" . number_format($size) . " bytes)\n";
        }
    }

    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, "\n✗ Error: {$e->getMessage()}\n");

    if (getenv('DEBUG')) {
        fwrite(STDERR, "\nStack trace:\n");
        fwrite(STDERR, $e->getTraceAsString() . "\n");
    }

    exit(1);
}
