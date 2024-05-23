<?php
/**
 * Plugin Name: Wp Restafari
 * Plugin URI:
 * Description: REST ルート定義
 * Version: 1.0
 * Author:
 * License: GPL2
 *
 * @package Wp\Restafari
 */
if (!defined('ABSPATH')) {
    die();
}

// Autoloader
if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
use Wp\Restafari\DI\Container;
use Wp\Restafari\REST\Route;

add_action('rest_api_init', function () {
    /** @var Route */
    $routes = Container::getInstance()->get(Route::class);
    $routes->register();
});

add_action('init', function() {
    $openApiDoc = Container::getInstance()->get(Wp\Restafari\OpenApi\Doc::class);
    $openApiDoc->init();
});
