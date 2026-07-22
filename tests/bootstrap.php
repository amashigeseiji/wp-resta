<?php
define( 'ABSPATH', __DIR__ . '/../wordpress/' );

require_once __DIR__ . '/../wordpress/wp-includes/class-wp-http-response.php';
require_once __DIR__ . '/../wordpress/wp-includes/rest-api/class-wp-rest-response.php';
require_once __DIR__ . '/../wordpress/wp-includes/rest-api/class-wp-rest-request.php';

if (!function_exists('absint')) {
    function absint($val) {
        return abs((int) $val);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}
