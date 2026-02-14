<?php
namespace Wp\Resta\REST\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Wp\Resta\REST\AbstractRoute;

/**
 * Test implementation of RestaRequestInterface (WordPress independent)
 *
 * This implementation parses URL parameters from the request path
 * using the route pattern from AbstractRoute.
 */
class TestRestaRequest implements RestaRequestInterface
{
    /** @var array<string, mixed> */
    private array $attributes = [];
    private string $routePattern;

    public function __construct(
        private RequestInterface $inner,
        AbstractRoute $route
    ) {
        // Get route regex pattern and store it
        $this->routePattern = $route->getRouteRegex();
        $this->parseUrlParams();
    }

    /**
     * Parse URL parameters from request path using route pattern
     */
    private function parseUrlParams(): void
    {
        $path = $this->inner->getUri()->getPath();

        // Remove namespace prefix from path if present
        // e.g., /wp-json/myroute/hello/amashige -> /hello/amashige
        $pathParts = explode('/', trim($path, '/'));
        if (count($pathParts) >= 2) {
            // Remove first two segments (wp-json, namespace)
            array_shift($pathParts);
            array_shift($pathParts);
            $path = '/' . implode('/', $pathParts);
        }

        $pattern = '#^/?' . ltrim($this->routePattern, '/') . '$#';

        if (preg_match($pattern, $path, $matches)) {
            foreach ($matches as $key => $value) {
                if (!is_numeric($key)) {  // Only named captures
                    $this->attributes[$key] = $value;
                }
            }
        }
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    // Delegate all other methods to inner RequestInterface

    public function getProtocolVersion(): string
    {
        return $this->inner->getProtocolVersion();
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withProtocolVersion($version);
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->inner->getHeaders();
    }

    public function hasHeader(string $name): bool
    {
        return $this->inner->hasHeader($name);
    }

    public function getHeader(string $name): array
    {
        return $this->inner->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->inner->getHeaderLine($name);
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withHeader($name, $value);
        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withAddedHeader($name, $value);
        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withoutHeader($name);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->inner->getBody();
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withBody($body);
        return $clone;
    }

    public function getRequestTarget(): string
    {
        return $this->inner->getRequestTarget();
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withRequestTarget($requestTarget);
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->inner->getMethod();
    }

    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withMethod($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->inner->getUri();
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withUri($uri, $preserveHost);
        return $clone;
    }
}
