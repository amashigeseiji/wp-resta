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
use Wp\Restafari\OpenApi\ResponseSchema;
use Wp\Restafari\OpenApi\Doc;
use Wp\Restafari\REST\Route;

$container = Container::getInstance();
add_action('rest_api_init', function () use ($container) {
    /** @var Route */
    $routes = $container->get(Route::class);
    $routes->register();
});

add_action('init', function() use ($container) {
    $container->get(Doc::class)->init();
    $container->get(ResponseSchema::class)->init();
});
