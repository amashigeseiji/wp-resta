<?php
namespace Test\Resta\Support\E2E;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for E2E tests
 *
 * Provides HTTP client and helper methods for testing REST API endpoints
 */
abstract class AbstractE2ETestCase extends TestCase
{
    protected Client $client;
    protected string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseUrl = E2E_BASE_URL;
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => E2E_TIMEOUT,
            'http_errors' => false, // Don't throw exceptions on 4xx/5xx
        ]);
    }

    /**
     * Send GET request
     *
     * @param string $uri URI path (e.g., '/wp-json/example/sample/1')
     * @param array<string, mixed> $query Query parameters
     * @return ResponseInterface
     */
    protected function get(string $uri, array $query = []): ResponseInterface
    {
        try {
            return $this->client->get($uri, [
                'query' => $query,
            ]);
        } catch (GuzzleException $e) {
            $this->fail("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Send POST request
     *
     * @param string $uri URI path
     * @param array<string, mixed> $data Request body data
     * @param array<string, string> $headers Additional headers
     * @return ResponseInterface
     */
    protected function post(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        try {
            return $this->client->post($uri, [
                'json' => $data,
                'headers' => $headers,
            ]);
        } catch (GuzzleException $e) {
            $this->fail("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Send PUT request
     *
     * @param string $uri URI path
     * @param array<string, mixed> $data Request body data
     * @param array<string, string> $headers Additional headers
     * @return ResponseInterface
     */
    protected function put(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        try {
            return $this->client->put($uri, [
                'json' => $data,
                'headers' => $headers,
            ]);
        } catch (GuzzleException $e) {
            $this->fail("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Send DELETE request
     *
     * @param string $uri URI path
     * @param array<string, string> $headers Additional headers
     * @return ResponseInterface
     */
    protected function delete(string $uri, array $headers = []): ResponseInterface
    {
        try {
            return $this->client->delete($uri, [
                'headers' => $headers,
            ]);
        } catch (GuzzleException $e) {
            $this->fail("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Decode JSON response body
     *
     * @param ResponseInterface $response
     * @return array<string, mixed>
     */
    protected function getJsonResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail("Invalid JSON response: " . json_last_error_msg() . "\nBody: {$body}");
        }

        return $decoded;
    }

    /**
     * Assert response status code
     *
     * @param int $expectedCode
     * @param ResponseInterface $response
     * @param string $message
     */
    protected function assertResponseCode(int $expectedCode, ResponseInterface $response, string $message = ''): void
    {
        $actualCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        $this->assertEquals(
            $expectedCode,
            $actualCode,
            $message ?: "Expected status code {$expectedCode}, got {$actualCode}. Response body: {$body}"
        );
    }

    /**
     * Assert response is JSON and contains specific data
     *
     * @param ResponseInterface $response
     * @param array<string, mixed> $expectedData
     */
    protected function assertJsonResponse(ResponseInterface $response, array $expectedData): void
    {
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $data = $this->getJsonResponse($response);

        foreach ($expectedData as $key => $value) {
            $this->assertArrayHasKey($key, $data, "Response missing key: {$key}");
            $this->assertEquals($value, $data[$key], "Value mismatch for key: {$key}");
        }
    }

    /**
     * Assert JSON response has specific structure
     *
     * @param ResponseInterface $response
     * @param array<int, string> $keys
     */
    protected function assertJsonStructure(ResponseInterface $response, array $keys): void
    {
        $data = $this->getJsonResponse($response);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $data, "Response missing key: {$key}");
        }
    }
}
