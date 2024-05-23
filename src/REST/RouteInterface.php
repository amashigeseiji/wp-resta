<?php
namespace Wp\Resta\REST;

use WP_REST_Request;
use WP_REST_Response;

interface RouteInterface
{
    public function getNamespace(): string;
    public function getRouteRegex(): string;
    public function getMethods(): string;
    public function invoke(WP_REST_Request $request): WP_REST_Response;
    public function permissionCallback();
    public function getArgs() : array;
    /** JsonSchema */
    public function getSchema() : array|null;
    public function getReadableRoute() : string;
}
