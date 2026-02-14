<?php
namespace Wp\Resta\REST\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use WP_REST_Request;
use WPRestApi\PSR7\WP_REST_PSR7_Request;

/**
 * WordPress implementation of RestaRequestInterface
 *
 * This implementation wraps WP_REST_PSR7_Request and delegates
 * all operations to it.
 */
class WpRestaRequest implements RestaRequestInterface
{
    private WP_REST_PSR7_Request $inner;

    private function __construct(WP_REST_PSR7_Request $inner)
    {
        $this->inner = $inner;
    }

    public static function fromWpRequest(WP_REST_Request $request): self
    {
        return new self(WP_REST_PSR7_Request::fromRequest($request));
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        // WP_REST_Request (parent of WP_REST_PSR7_Request) implements ArrayAccess
        return $this->inner[$name] ?? $default;
    }

    // Delegate all other methods to inner WP_REST_PSR7_Request

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
