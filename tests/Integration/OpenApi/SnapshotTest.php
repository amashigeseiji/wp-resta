<?php
namespace Test\Resta\Integration\OpenApi;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\CodeGen\Generators\ApiEndpointsGenerator;
use Wp\Resta\CodeGen\Generators\InterfaceGenerator;
use Wp\Resta\CodeGen\OpenApiParser;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\OpenApi\ResponseSchema;

/**
 * tests/Fixtures/Snapshot のルートとスキーマから生成した OpenAPI / TypeScript を
 * tests/Fixtures/Snapshot/expected と突き合わせる。
 * 出力を意図して変えたときは UPDATE_SNAPSHOTS=1 composer test で期待値を書き換え、差分をレビューする
 */
class SnapshotTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/Snapshot';

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('rest_url')->justReturn('http://localhost/wp-json/');

        self::resetContainer();
        $this->container = Container::getInstance();
        $this->container->bind(Config::class, new Config([
            'routeDirectory' => [
                [self::FIXTURES . '/Routes', 'Test\\Resta\\Fixtures\\Snapshot\\Routes\\', 'snapshot'],
            ],
            'schemaDirectory' => [
                [self::FIXTURES . '/Schemas', 'Test\\Resta\\Fixtures\\Snapshot\\Schemas\\'],
            ],
        ]));
        $this->container->bind(DispatcherInterface::class, new Dispatcher());
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        self::resetContainer();
        parent::tearDown();
    }

    private static function resetContainer(): void
    {
        $instance = (new ReflectionClass(Container::class))->getProperty('instance');
        $instance->setValue(null, null);
    }

    public function testOpenApiMatchesSnapshot(): void
    {
        $spec = $this->container->get(ResponseSchema::class)->responseSchema();
        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

        $this->assertSnapshot('openapi.json', $json);
    }

    public function testTypeScriptEndpointsMatchSnapshot(): void
    {
        $parser = new OpenApiParser($this->container->get(ResponseSchema::class)->responseSchema());

        $this->assertSnapshot('endpoints.ts', (new ApiEndpointsGenerator())->generate($parser));
    }

    public function testTypeScriptInterfacesMatchSnapshot(): void
    {
        $parser = new OpenApiParser($this->container->get(ResponseSchema::class)->responseSchema());

        $this->assertSnapshot('schema.ts', (new InterfaceGenerator())->generate($parser));
    }

    private function assertSnapshot(string $name, string $actual): void
    {
        $path = self::FIXTURES . '/expected/' . $name;
        if (getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($path, $actual);
        }
        $this->assertFileExists($path, "snapshot が無い。UPDATE_SNAPSHOTS=1 で生成する: {$path}");
        $this->assertSame(file_get_contents($path), $actual, "{$name} が snapshot と違う。意図した変更なら UPDATE_SNAPSHOTS=1 で更新する");
    }
}
