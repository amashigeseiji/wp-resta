<?php
namespace Test\Resta\Integration\OpenApi;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\OpenApi\ResponseSchema;

/**
 * tests/Fixtures/Snapshot のルートとスキーマから生成した OpenAPI を
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
        $this->container->bind(Config::class, new Config(require self::FIXTURES . '/config.php'));
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

    /**
     * scripts/generate-openapi.php は WordPress 無しの別プロセスで動く。同じ宣言から同じ JSON が出る
     */
    public function testGenerateOpenApiScriptWritesTheSameJson(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'openapi');
        $this->assertIsString($output);

        try {
            $command = sprintf(
                '%s %s %s %s %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg(__DIR__ . '/../../../scripts/generate-openapi.php'),
                escapeshellarg(self::FIXTURES . '/config.php'),
                escapeshellarg($output),
                escapeshellarg('http://localhost/wp-json/'),
            );
            exec($command, $lines, $exitCode);

            $this->assertSame(0, $exitCode, implode("\n", $lines));
            $this->assertStringEqualsFile(self::FIXTURES . '/expected/openapi.json', (string) file_get_contents($output));
        } finally {
            unlink($output);
        }
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
