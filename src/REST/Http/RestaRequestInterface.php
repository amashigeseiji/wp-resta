<?php
namespace Wp\Resta\REST\Http;

use Psr\Http\Message\RequestInterface;

/**
 * Extended Request interface with attribute support for URL parameters
 */
interface RestaRequestInterface extends RequestInterface
{
    /**
     * Retrieve a single derived request attribute.
     *
     * @param string $name The attribute name.
     * @param mixed $default Default value to return if the attribute does not exist.
     * @return mixed
     */
    public function getAttribute(string $name, mixed $default = null): mixed;
}
