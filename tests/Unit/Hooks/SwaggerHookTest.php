<?php
namespace Test\Resta\Unit\Hooks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\Hooks\SwaggerHook;
use Wp\Resta\Hooks\SwaggerOptions;
use Wp\Resta\OpenApi\ResponseSchema;

/**
 * SwaggerHook がスキーマを REST ルートとして登録することを確認する（#158）
 */
class SwaggerHookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function testRegisterHooksRestApiInit(): void
    {
        $hooked = [];
        Functions\when('add_action')->alias(function (string $hook, callable $callback) use (&$hooked): void {
            $hooked[] = $hook;
        });

        $this->hook(Mockery::mock(ResponseSchema::class))->register();

        $this->assertContains('rest_api_init', $hooked);
    }

    public function testDoesNotAddRewriteRules(): void
    {
        Functions\when('is_admin')->justReturn(false);
        Functions\expect('add_rewrite_tag')->never();
        Functions\expect('add_rewrite_rule')->never();
        Functions\expect('add_action')->never();

        $this->hook(Mockery::mock(ResponseSchema::class))->onInit();

        $this->assertTrue(true);
    }

    public function testRestRouteReturnsSchemaToAnyone(): void
    {
        $schema = Mockery::mock(ResponseSchema::class);
        $schema->shouldReceive('responseSchema')->once()->andReturn(['openapi' => '3.0.0']);

        [$namespace, $route, $args] = $this->registeredBy($this->hook($schema));
        $this->assertSame('resta/v1', $namespace);
        $this->assertSame('/schema', $route);
        $this->assertSame('GET', $args['methods']);
        // 誰でも取得できる
        $this->assertSame('__return_true', $args['permission_callback']);
        $this->assertSame(['openapi' => '3.0.0'], ($args['callback'])());
    }

    public function testOptionsDecideWhereSchemaIsServed(): void
    {
        $hook = $this->hook(options: new SwaggerOptions(namespace: 'myapp/v1', route: '/openapi'));

        [$namespace, $route] = $this->registeredBy($hook);

        $this->assertSame('myapp/v1', $namespace);
        $this->assertSame('/openapi', $route);
    }

    public function testCapabilityIsAskedToPrincipal(): void
    {
        $options = new SwaggerOptions(capability: 'edit_pages');

        [, , $allowed] = $this->registeredBy($this->hook(
            options: $options,
            principal: new TestPrincipal(authenticated: true, capabilities: ['edit_pages']),
        ));
        [, , $denied] = $this->registeredBy($this->hook(
            options: $options,
            principal: new TestPrincipal(authenticated: true, capabilities: ['read']),
        ));

        $this->assertTrue(($allowed['permission_callback'])());
        $this->assertFalse(($denied['permission_callback'])());
    }

    private function hook(
        ?ResponseSchema $schema = null,
        ?SwaggerOptions $options = null,
        ?TestPrincipal $principal = null,
    ): SwaggerHook {
        return new SwaggerHook(
            $schema ?? Mockery::mock(ResponseSchema::class),
            $options ?? new SwaggerOptions(),
            $principal ?? new TestPrincipal(),
        );
    }

    /**
     * onRestApiInit() が register_rest_route() に渡した引数
     *
     * @return array{string, string, array<string, mixed>}
     */
    private function registeredBy(SwaggerHook $hook): array
    {
        $registered = [];
        Functions\when('register_rest_route')->alias(
            function (string $namespace, string $route, array $args) use (&$registered): bool {
                $registered = [$namespace, $route, $args];
                return true;
            }
        );

        $hook->onRestApiInit();

        return $registered;
    }
}
