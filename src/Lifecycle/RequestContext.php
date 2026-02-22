<?php
namespace Wp\Resta\Lifecycle;

use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\RouteInterface;
use WP_REST_Request;
use WP_REST_Response;

class RequestContext
{
    public function __construct(
        public WP_REST_Request $wpRequest,
        public RouteInterface $route,
        public ?RestaRequestInterface $request = null,
        public ?RestaResponseInterface $response = null,
        public ?WP_REST_Response $wpResponse = null,
    ) {}
}
