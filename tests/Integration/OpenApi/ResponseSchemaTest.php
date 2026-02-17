<?php
namespace Test\Resta\Integration\OpenApi;

use PHPUnit\Framework\TestCase;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\OpenApi\ResponseSchema;
use ReflectionClass;
use Brain\Monkey;
use Brain\Monkey\Functions;

class ResponseSchemaTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // WordPressの関数をモック
        Functions\when('rest_url')->justReturn('http://localhost/wp-json/');

        // Containerをリセット
        $this->container = Container::getInstance();
        $reflection = new ReflectionClass(Container::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $this->container = Container::getInstance();

        // Configをセットアップ
        $config = new Config([
            'routeDirectory' => [
                [__DIR__ . '/../../Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
            ],
        ]);

        $this->container->bind(Config::class, $config);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();

        // Containerをリセット
        $reflection = new ReflectionClass(Container::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        parent::tearDown();
    }

    public function testSchemaInferenceIntegration()
    {
        // DIコンテナから全て取得
        $responseSchema = $this->container->get(ResponseSchema::class);

        $result = $responseSchema->responseSchema();

        $this->assertArrayHasKey('openapi', $result);
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('components', $result);

        // TestInferredSchemaRoute のスキーマが自動推論されているか確認
        $testPath = '/test/test-inferred';
        $this->assertArrayHasKey($testPath, $result['paths']);

        $schema = $result['paths'][$testPath]['get']['responses']['200']['content']['application/json']['schema'];

        // スキーマの構造を確認
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);

        // metadata() からの情報も含まれているか確認
        $this->assertEquals('integer', $schema['properties']['id']['type']);
        $this->assertEquals('User ID', $schema['properties']['id']['description']);

        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('User name', $schema['properties']['name']['description']);

        $this->assertEquals('string', $schema['properties']['email']['type']);
        $this->assertEquals('User email', $schema['properties']['email']['description']);
    }

    public function testRouteWithBuiltinReturnTypeInfersSchema()
    {
        $responseSchema = $this->container->get(ResponseSchema::class);
        $result = $responseSchema->responseSchema();

        // TestRoute は SCHEMA 定数がないが、callback(): string から自動推論される
        $testPath = '/test/test';
        $this->assertArrayHasKey($testPath, $result['paths']);

        $schema = $result['paths'][$testPath]['get']['responses']['200']['content']['application/json']['schema'];
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('string', $schema['type']);
    }
}
