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
            'schemaDirectory' => [
                [__DIR__ . '/../../Fixtures/Schemas', 'Test\\Resta\\Fixtures\\Schemas\\'],
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

        // $ref で参照されているか確認
        $this->assertArrayHasKey('$ref', $schema);
        $this->assertStringContainsString('TestUser', $schema['$ref']);

        // components/schemas に完全なスキーマが登録されているか確認
        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('schemas', $result['components']);
        $this->assertArrayHasKey('TestUser', $result['components']['schemas']);

        $componentSchema = $result['components']['schemas']['TestUser'];
        $this->assertEquals('object', $componentSchema['type']);
        $this->assertArrayHasKey('properties', $componentSchema);
        $this->assertArrayHasKey('id', $componentSchema['properties']);
        $this->assertArrayHasKey('name', $componentSchema['properties']);
        $this->assertArrayHasKey('email', $componentSchema['properties']);

        // metadata() からの情報も含まれているか確認
        $this->assertEquals('integer', $componentSchema['properties']['id']['type']);
        $this->assertEquals('User ID', $componentSchema['properties']['id']['description']);

        $this->assertEquals('string', $componentSchema['properties']['name']['type']);
        $this->assertEquals('User name', $componentSchema['properties']['name']['description']);

        $this->assertEquals('string', $componentSchema['properties']['email']['type']);
        $this->assertEquals('User email', $componentSchema['properties']['email']['description']);
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
