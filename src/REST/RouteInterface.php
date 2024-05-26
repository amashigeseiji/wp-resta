<?php
namespace Wp\Resta\REST;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface RouteInterface
{
    public function getNamespace(): string;
    public function getRouteRegex(): string;
    public function getMethods(): string;
    public function invoke(RequestInterface $request): ResponseInterface;
    public function permissionCallback();
    public function getArgs() : array;
    /** JsonSchema */
    public function getSchema() : array|null;
    public function getReadableRoute() : string;
}
