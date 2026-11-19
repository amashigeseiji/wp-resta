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
 * 認可のアトリビュートから導出する security と、設定の securitySchemes（#139）。
 * tests/Fixtures/Snapshot の Item は、POST にだけ #[Permission] が付いている
 */
class SecurityTest extends TestCase
{
    private const PATH = '/snapshot/items/{id}';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('rest_url')->justReturn('http://localhost/wp-json/');
        self::resetContainer();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        self::resetContainer();
        parent::tearDown();
    }

    private static function resetContainer(): void
    {
        (new ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function generate(array $override = []): array
    {
        $container = Container::getInstance();
        $container->bind(Config::class, new Config($override + require __DIR__ . '/../../Fixtures/Snapshot/config.php'));
        $container->bind(DispatcherInterface::class, new Dispatcher());
        return $container->get(ResponseSchema::class)->responseSchema();
    }

    public function testDefaultSchemeIsCookieNonce(): void
    {
        $spec = $this->generate();

        $this->assertSame(
            ['cookieNonce' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-WP-Nonce']],
            $spec['components']['securitySchemes'],
        );
        $this->assertSame([['cookieNonce' => []]], $spec['paths'][self::PATH]['post']['security']);
    }

    public function testOperationWithoutAttributeHasNoSecurity(): void
    {
        $spec = $this->generate();

        $this->assertArrayNotHasKey('security', $spec['paths'][self::PATH]['get']);
    }

    public function testConfiguredSchemesReplaceTheDefault(): void
    {
        $schemes = ['applicationPassword' => ['type' => 'http', 'scheme' => 'basic']];

        $spec = $this->generate(['securitySchemes' => $schemes]);

        $this->assertSame($schemes, $spec['components']['securitySchemes']);
        $this->assertSame([['applicationPassword' => []]], $spec['paths'][self::PATH]['post']['security']);
    }

    public function testMultipleSchemesAreAlternatives(): void
    {
        $spec = $this->generate(['securitySchemes' => [
            'cookieNonce' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-WP-Nonce'],
            'applicationPassword' => ['type' => 'http', 'scheme' => 'basic'],
        ]]);

        // 要素を分けて並べると「どれか 1 つで通る」になる
        $this->assertSame(
            [['cookieNonce' => []], ['applicationPassword' => []]],
            $spec['paths'][self::PATH]['post']['security'],
        );
    }

    public function testSchemesAreOmittedWhenNoOperationRequiresThem(): void
    {
        // tests/Fixtures/Routes には、認可のアトリビュートが付いた操作が無い
        $spec = $this->generate(['routeDirectory' => [
            [__DIR__ . '/../../Fixtures/Routes', 'Test\\Resta\\Fixtures\\Routes\\', 'test'],
        ]]);

        $this->assertNotSame([], $spec['paths']);
        $this->assertSame('{}', json_encode($spec['components']['securitySchemes']));
    }

    public function testEmptySchemesEmitNoSecurity(): void
    {
        $spec = $this->generate(['securitySchemes' => []]);

        $this->assertArrayNotHasKey('security', $spec['paths'][self::PATH]['post']);
        $this->assertSame('{}', json_encode($spec['components']['securitySchemes']));
    }
}
