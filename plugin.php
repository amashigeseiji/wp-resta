<?php
/**
 * Plugin Name: Wp\Resta
 * Plugin URI:
 * Description: REST ルート定義
 * Version: 0.8.4
 * Author: amashigeseiji
 * License: GPL2
 *
 * @package Wp\Resta
 */

if (!defined('ABSPATH')) {
    die();
}

$restaConfigFile = getenv('RESTA_CONFIG_FILE') or __DIR__ . '/config-sample.php';
if (!$restaConfigFile || !file_exists($restaConfigFile)) {
    throw new RuntimeException('file does not exist: ' . $restaConfigFile);
}
$restaConfig = require($restaConfigFile);
if (isset($restaConfig['autoloader'])) {
    $loader = require $restaConfig['autoloader'];
    if (!($loader instanceof Composer\Autoload\ClassLoader)) {
        throw new RuntimeException("autoloader config \"{$restaConfig['autoloader']}\" is not composer autoloader.");
    }
} elseif(!class_exists(Composer\Autoload\ClassLoader::class)) {
    throw new RuntimeException('Composer\Autoload\ClassLoader does not defined. Make sure that autoloader is loaded.');
}

(new Wp\Resta\Resta)->init($restaConfig);
