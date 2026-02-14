<?php
namespace Wp\Resta\REST;

use Psr\Http\Message\ResponseInterface;
use Wp\Resta\REST\Http\RestaRequestInterface;

interface RouteInterface
{
    public function getNamespace(): string;
    public function setNamespace(string $namespace): void;
    public function getRouteRegex(): string;
    public function getMethods(): string;
    public function invoke(RestaRequestInterface $request): ResponseInterface;
    public function permissionCallback(): string;

    /**
     * @return array<string, array<string, string>>
     */
    public function getArgs() : array;

    /**
     * JsonSchema
     * @return array<string, mixed>|null
     */
    public function getSchema() : array|null;
    public function getReadableRoute() : string;
}
