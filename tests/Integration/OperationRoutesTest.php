<?php
namespace Test\Resta\Integration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Test\Resta\Fixtures\OperationRoutes\TestNotesRoute;
use Wp\Resta\Auth\Principal;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\Config;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\EventDispatcher\DispatcherInterface;
use Wp\Resta\OpenApi\ResponseSchema;
use Wp\Resta\REST\RegisterRestRoutes;

/**
 * get() / post() で操作を分けたルートが、register_rest_route() と OpenAPI にメソッドごとに出ること（#92）
 */
class OperationRoutesTest extends TestCase
{
    /** @var array<string, array<int|string, mixed>> ルート正規表現 => register_rest_route() の第 3 引数 */
    private array $registered = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->resetContainer();

        Functions\when('rest_url')->justReturn('http://localhost/wp-json/');
        Functions\when('register_rest_route')->alias(function (string $namespace, string $route, array $args): bool {
            $this->registered[$route] = $args;
            return true;
        });

        $config = new Config([
            'routeDirectory' => [
                [__DIR__ . '/../Fixtures/OperationRoutes', 'Test\\Resta\\Fixtures\\OperationRoutes\\', 'test'],
            ],
            'schemaDirectory' => [
                [__DIR__ . '/../Fixtures/Schemas', 'Test\\Resta\\Fixtures\\Schemas\\'],
            ],
        ]);
        $container = Container::getInstance();
        $container->bind(Config::class, $config);
        $container->bind(DispatcherInterface::class, new Dispatcher());
        $container->bind(Principal::class, new TestPrincipal());
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $this->resetContainer();
        parent::tearDown();
    }

    private function resetContainer(): void
    {
        (new ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);
    }

    /** @return array<int, array<string, mixed>> 数値キーのエンドポイントだけ */
    private function endpoints(string $routeRegex): array
    {
        return array_values(array_filter($this->registered[$routeRegex], fn($v, $k) => is_int($k), ARRAY_FILTER_USE_BOTH));
    }

    public function testRegistersOneEndpointPerOperation(): void
    {
        Container::getInstance()->get(RegisterRestRoutes::class)->register();

        $regex = 'notes/(?P<id>\d+)';
        $this->assertArrayHasKey($regex, $this->registered);
        $this->assertIsCallable($this->registered[$regex]['schema'], 'schema はルートのオプション');

        $endpoints = $this->endpoints($regex);
        $this->assertSame(['GET', 'POST'], array_column($endpoints, 'methods'));
        foreach ($endpoints as $endpoint) {
            $this->assertSame('__return_true', $endpoint['permission_callback']);
        }
        $this->assertSame(['id'], array_keys($endpoints[0]['args']), 'GET の args は get() のシグニチャから');
        $this->assertSame(['id', 'title'], array_keys($endpoints[1]['args']), 'POST の args は post() のシグニチャから');
    }

    public function testEndpointCallbackDispatchesByWpRequestMethod(): void
    {
        Container::getInstance()->get(RegisterRestRoutes::class)->register();
        $endpoints = $this->endpoints('notes/(?P<id>\d+)');

        $get = new \WP_REST_Request();
        $get['id'] = '3';
        $this->assertSame(['id' => 3, 'method' => 'GET'], $endpoints[0]['callback']($get)->get_data());

        $post = new \WP_REST_Request();
        $post->set_method('POST');
        $post['id'] = '3';
        $post['title'] = 'hello';
        $this->assertSame('created 3: hello', $endpoints[1]['callback']($post)->get_data());
    }

    public function testOpenApiHasOneOperationObjectPerMethod(): void
    {
        $paths = Container::getInstance()->get(ResponseSchema::class)->responseSchema()['paths'];

        $path = '/test/notes/{id}';
        $this->assertSame(['get', 'post'], array_keys($paths[$path]));
        $this->assertSame(['id'], array_column($paths[$path]['get']['parameters'], 'name'));
        $this->assertSame(['id', 'title'], array_column($paths[$path]['post']['parameters'], 'name'));

        $getSchema = $paths[$path]['get']['responses']['200']['content']['application/json']['schema'];
        $postSchema = $paths[$path]['post']['responses']['200']['content']['application/json']['schema'];
        $this->assertSame('object', $getSchema['type'], 'レスポンスの形は操作ごとの戻り値から');
        $this->assertSame('string', $postSchema['type']);
    }

    public function testExplicitSchemaWinsOverInferredReturnType(): void
    {
        $paths = Container::getInstance()->get(ResponseSchema::class)->responseSchema()['paths'];

        $schema = $paths['/test/schema-constant']['get']['responses']['200']['content']['application/json']['schema'];
        $this->assertArrayHasKey('fromConstant', $schema['properties']);
    }

    public function testRouteInstanceIsSharedByItsOperations(): void
    {
        $routes = Container::getInstance()->get(RegisterRestRoutes::class);
        $notes = array_values(array_filter($routes->routes, fn($r) => $r instanceof TestNotesRoute))[0];

        $this->assertSame('test', $notes->getNamespace());
        $this->assertCount(2, $notes->getOperations());
    }
}
