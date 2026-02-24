<?php
/**
 * Plugin Name: Wp\Resta
 * Plugin URI:
 * Description: REST ルート定義
 * Version: 0.10.0
 * Author: amashigeseiji
 * License: AGPL
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

register_activation_hook(__FILE__, function() {
    add_rewrite_tag('%rest_api_doc%', '([^&]+)');
    add_rewrite_rule('^rest-api/schema/?', 'index.php?rest_api_doc=schema', 'top');
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, fn() => flush_rewrite_rules());
