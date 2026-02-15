<?php
namespace Wp\Resta\REST;

use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;

interface RouteInterface
{
    public function getNamespace(): string;
    public function setNamespace(string $namespace): void;
    public function getRouteRegex(): string;
    public function getMethods(): string;
    public function invoke(RestaRequestInterface $request): RestaResponseInterface;
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
