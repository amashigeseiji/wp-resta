<?php
/**
 * Plugin Name: Wp Resta
 * Plugin URI:
 * Description: REST ルート定義
 * Version: 1.0
 * Author:
 * License: GPL2
 *
 * @package Wp\Resta
 */

if (!defined('ABSPATH')) {
    die();
}

$restaConfigFile = getenv('RESTA_CONFIG_FILE');
if (!$restaConfigFile || !file_exists($restaConfigFile)) {
    $restaConfigFile = __DIR__ . '/config-sample.php';
}
if (!file_exists($restaConfigFile)) {
    throw new RuntimeException('file does not exist: ' . $restaConfigFile);
}
$restaConfig = require($restaConfigFile);
$loader = require $restaConfig['autoloader'];
if (!($loader instanceof Composer\Autoload\ClassLoader)) {
    die();
}

(new Wp\Resta\Resta)->init($restaConfig);
