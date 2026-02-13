<?php
define( 'ABSPATH', __DIR__ . '/../wordpress/' );

require_once __DIR__ . '/../wordpress/wp-includes/class-wp-http-response.php';
require_once __DIR__ . '/../wordpress/wp-includes/rest-api/class-wp-rest-response.php';

if (!function_exists('absint')) {
    function absint($val) {
        return abs((int) $val);
    }
}

// WordPress フック関数のモック
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $acceptedArgs = 1) {
        global $wp_filter;
        $wp_filter[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs
        ];
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1) {
        global $wp_actions;
        $wp_actions[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs
        ];
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }
}

if (!function_exists('has_filter')) {
    function has_filter($hook) {
        global $wp_filter;
        return isset($wp_filter[$hook]);
    }
}
