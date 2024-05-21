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

use Wp\Restafari\DI\Container;
use Wp\Restafari\REST\Route;

add_action('rest_api_init', function () {
    /** @var Route */
    $routes = Container::getInstance()->get(Route::class);
    $routes->register();
});
