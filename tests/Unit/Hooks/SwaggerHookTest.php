<?php
namespace Test\Resta\Unit\Hooks;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testMenuUsesEditPagesByDefaultAndPageHasNoNonce(): void
    {
        Functions\expect('wp_create_nonce')->never();

        [$capability, $html] = $this->menuPageOf($this->hook());

        $this->assertSame('edit_pages', $capability);
        $this->assertStringContainsString('const schemaUrl = "https://example.com/wp-json/resta/v1/schema";', $html);
        // 誰でも取得できるスキーマには nonce を付けない
        $this->assertStringContainsString('const nonce = null;', $html);
    }

    public function testMenuFollowsCapabilityAndPageHasNonce(): void
    {
        Functions\when('wp_create_nonce')->justReturn('abc123');

        [$capability, $html] = $this->menuPageOf(
            $this->hook(options: new SwaggerOptions(capability: 'manage_options'))
        );

        // スキーマを読める人にだけメニューを見せる
        $this->assertSame('manage_options', $capability);
        $this->assertStringContainsString('const nonce = "abc123";', $html);
    }

    #[DataProvider('slashVariants')]
    public function testSchemaUrlDoesNotDependOnSlashes(string $namespace, string $route): void
    {
        [, $html] = $this->menuPageOf(
            $this->hook(options: new SwaggerOptions(namespace: $namespace, route: $route))
        );

        $this->assertStringContainsString('const schemaUrl = "https://example.com/wp-json/myapp/v1/schema";', $html);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function slashVariants(): array
    {
        return [
            '既定と同じ形' => ['myapp/v1', '/schema'],
            'route の先頭に / が無い' => ['myapp/v1', 'schema'],
            'namespace の末尾に / がある' => ['myapp/v1/', '/schema'],
            'namespace の先頭に / がある' => ['/myapp/v1', 'schema'],
        ];
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

    /**
     * onInit() が登録したメニューの capability と、ページが出力する HTML
     *
     * @return array{string, string}
     */
    private function menuPageOf(SwaggerHook $hook): array
    {
        $capability = '';
        $render = null;
        Functions\when('is_admin')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('add_action')->alias(fn (string $name, callable $callback) => $callback());
        Functions\when('add_menu_page')->alias(
            function (string $pageTitle, string $menuTitle, string $cap, string $slug, callable $callback) use (&$capability, &$render): void {
                $capability = $cap;
                $render = $callback;
            }
        );
        Functions\when('wp_enqueue_style')->justReturn();
        Functions\when('wp_enqueue_script')->justReturn();
        Functions\when('plugin_dir_path')->alias(fn (string $file): string => dirname($file) . '/');
        Functions\when('rest_url')->alias(fn (string $path): string => 'https://example.com/wp-json/' . $path);
        Functions\when('wp_json_encode')->alias(
            fn (mixed $value): string => (string) json_encode($value, JSON_UNESCAPED_SLASHES)
        );

        $hook->onInit();
        $this->assertIsCallable($render);

        ob_start();
        try {
            $render();
        } finally {
            $html = (string) ob_get_clean();
        }

        return [$capability, $html];
    }
}
