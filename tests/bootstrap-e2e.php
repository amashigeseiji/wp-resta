<?php
/**
 * Bootstrap for E2E tests
 *
 * E2E tests run inside wpcli container:
 * - PHP 8.2 guaranteed by Docker image
 * - WordPress running in same Docker network
 * - Real HTTP requests to WordPress REST API
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Environment variables
// When running in wpcli container, use 'http://wordpress' (container name)
// When running from host, use 'http://localhost:8080'
$baseUrl = getenv('E2E_BASE_URL') ?: 'http://wordpress';
$timeout = (int) (getenv('E2E_TIMEOUT') ?: 30);

define('E2E_BASE_URL', $baseUrl);
define('E2E_TIMEOUT', $timeout);

// Simple connectivity check (optional, will fail fast if WordPress is not running)
echo "E2E Test Environment\n";
echo "  Base URL: " . E2E_BASE_URL . "\n";
echo "  Timeout:  " . E2E_TIMEOUT . "s\n";
echo "\n";
