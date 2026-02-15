<?php
namespace Test\Resta\E2E\Api;

use Test\Resta\Support\E2E\AbstractE2ETestCase;

/**
 * E2E tests for example API endpoints
 *
 * These tests make real HTTP requests to WordPress running in Docker
 */
class ExampleApiTest extends AbstractE2ETestCase
{
    public function testSampleApiReturnsCorrectStructure(): void
    {
        $response = $this->get('/wp-json/example/sample/1', [
            'name' => 'test',
            'a_or_b' => 'a',
        ]);

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        // Assert basic structure
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('a_or_b', $data);
        $this->assertArrayHasKey('route', $data);
        $this->assertArrayHasKey('_resta_meta', $data);

        // Assert values
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('test', $data['name']);
        $this->assertEquals('a', $data['a_or_b']);

        // Assert _resta_meta structure
        $this->assertArrayHasKey('processed_at', $data['_resta_meta']);
        $this->assertArrayHasKey('plugin_version', $data['_resta_meta']);
        $this->assertArrayHasKey('request_route', $data['_resta_meta']);
    }

    public function testSampleApiWithDifferentParameters(): void
    {
        $response = $this->get('/wp-json/example/sample/999', [
            'name' => 'custom',
            'a_or_b' => 'b',
        ]);

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        $this->assertEquals(999, $data['id']);
        $this->assertEquals('custom', $data['name']);
        $this->assertEquals('b', $data['a_or_b']);
    }

    public function testSampleApiWithoutOptionalParameters(): void
    {
        $response = $this->get('/wp-json/example/sample/1');

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        $this->assertEquals(1, $data['id']);
        $this->assertNull($data['name']);
        $this->assertEquals('a', $data['a_or_b']); // Default value
    }

    public function testPostsApiReturnsPostList(): void
    {
        $response = $this->get('/wp-json/example/posts');

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertIsArray($data['items']);
        $this->assertGreaterThan(0, count($data['items']));

        // Check first post structure
        $firstPost = $data['items'][0];
        $this->assertArrayHasKey('ID', $firstPost);
        $this->assertArrayHasKey('post_title', $firstPost);
        $this->assertArrayHasKey('post_status', $firstPost);
    }

    public function testPostApiReturnsPostDetails(): void
    {
        $response = $this->get('/wp-json/example/post/1');

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        $this->assertArrayHasKey('post', $data);
        $this->assertArrayHasKey('ID', $data['post']);
        $this->assertEquals(1, $data['post']['ID']);
    }

    public function testPostApiWithNonExistentId(): void
    {
        $response = $this->get('/wp-json/example/post/99999');

        // Non-existent post returns 404
        $this->assertResponseCode(404, $response);

        $data = $this->getJsonResponse($response);

        // Post route returns null for non-existent post, which becomes empty string
        // SampleHook only adds _resta_meta to array responses
        $this->assertIsString($data);
        $this->assertEmpty($data);
    }

    public function testSampleStaticApiReturnsStaticValue(): void
    {
        $response = $this->get('/wp-json/example/samplestatic');

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertEquals('static_parameter', $data['name']);
    }

    public function testApiNamespaceRoute(): void
    {
        $response = $this->get('/wp-json/example');

        $this->assertResponseCode(200, $response);

        $data = $this->getJsonResponse($response);

        $this->assertArrayHasKey('namespace', $data);
        $this->assertArrayHasKey('routes', $data);
        $this->assertEquals('example', $data['namespace']);

        // Verify expected routes exist
        $routes = array_keys($data['routes']);
        $this->assertContains('/example/sample/(?P<id>\\d+)', $routes);
        $this->assertContains('/example/posts', $routes);
        $this->assertContains('/example/post/(?P<id>\\d+)', $routes);
    }

    public function testInvalidRouteReturns404(): void
    {
        $response = $this->get('/wp-json/example/nonexistent');

        $this->assertResponseCode(404, $response);

        $data = $this->getJsonResponse($response);

        $this->assertArrayHasKey('code', $data);
        $this->assertEquals('rest_no_route', $data['code']);
    }
}
