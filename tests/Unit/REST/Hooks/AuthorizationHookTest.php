<?php
namespace Test\Resta\Unit\REST\Hooks;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Wp\Resta\Auth\Principal;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\DI\Container;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Authenticated;
use Wp\Resta\REST\Attributes\Permission;
use Wp\Resta\REST\Hooks\AuthorizationHook;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * #[Authenticated] / #[Permission] の解釈（#139）。RequestHandler を通し、本番と同じく購読者として確かめる
 */
class AuthorizationHookTest extends TestCase
{
    protected function tearDown(): void
    {
        (new ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);
        parent::tearDown();
    }

    private function handle(AbstractRoute $route, ?Principal $principal = null, string $method = 'GET', string $url = '/x'): RestaResponseInterface
    {
        $dispatcher = new Dispatcher();
        $dispatcher->addSubscriber(new AuthorizationHook($principal ?? new TestPrincipal()));
        // RequestHandler に渡す主体は authorize() 用。アトリビュートの認可は購読者が受け取った主体を見る
        return (new RequestHandler(new TestPrincipal(), $dispatcher))
            ->handle($route, new TestRestaRequest($url, $route, $method));
    }

    private function authenticatedRoute(): AbstractRoute
    {
        return new class extends AbstractRoute {
            #[Authenticated]
            public function callback(): string
            {
                return 'ok';
            }
        };
    }

    private function permissionRoute(): AbstractRoute
    {
        return new class extends AbstractRoute {
            #[Permission('edit_posts')]
            public function callback(): string
            {
                return 'ok';
            }
        };
    }

    public function testOperationWithoutAttributeRunsUnauthenticated(): void
    {
        $route = new class extends AbstractRoute {
            public function callback(): string
            {
                return 'ok';
            }
        };

        $this->assertSame(200, $this->handle($route)->getStatusCode());
    }

    public function testAuthenticatedRejectsUnauthenticatedWith401(): void
    {
        $response = $this->handle($this->authenticatedRoute());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('rest_not_logged_in', $response->getData()['code']);
    }

    public function testAuthenticatedAllowsAuthenticated(): void
    {
        $response = $this->handle($this->authenticatedRoute(), new TestPrincipal(authenticated: true));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getData());
    }

    public function testPermissionRejectsUnauthenticatedWith401(): void
    {
        $this->assertSame(401, $this->handle($this->permissionRoute())->getStatusCode());
    }

    public function testPermissionRejectsMissingCapabilityWith403(): void
    {
        $response = $this->handle($this->permissionRoute(), new TestPrincipal(authenticated: true, capabilities: ['read']));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('rest_forbidden', $response->getData()['code']);
    }

    public function testPermissionAllowsCapability(): void
    {
        $response = $this->handle($this->permissionRoute(), new TestPrincipal(authenticated: true, capabilities: ['edit_posts']));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCallbackIsNotInvokedWhenRejected(): void
    {
        $route = new class extends AbstractRoute {
            public static int $calls = 0;

            #[Authenticated]
            public function callback(): string
            {
                self::$calls++;
                return 'ok';
            }
        };

        $this->handle($route);

        $this->assertSame(0, $route::$calls);
    }

    public function testAttributesAreReadPerOperation(): void
    {
        $route = new class extends AbstractRoute {
            public function get(): string
            {
                return 'public';
            }

            #[Permission('edit_posts')]
            public function post(): string
            {
                return 'restricted';
            }
        };

        $this->assertSame(200, $this->handle($route)->getStatusCode(), 'GET は未認証でも通る');
        $this->assertSame(401, $this->handle($route, null, 'POST')->getStatusCode(), 'POST は未認証で 401');
        $this->assertSame(200, $this->handle($route, new TestPrincipal(true, ['edit_posts']), 'POST')->getStatusCode());
    }

    public function testHeadFollowsTheGetOperation(): void
    {
        $this->assertSame(401, $this->handle($this->authenticatedRoute(), null, 'HEAD')->getStatusCode());
    }

    public function testBoundPrincipalDecides(): void
    {
        // 独自の認証は Principal の bind を差し替える。購読者はコンテナから受け取った実装に聞く
        $container = Container::getInstance();
        $container->bind(Principal::class, new TestPrincipal(authenticated: true, capabilities: ['edit_posts']));
        $dispatcher = new Dispatcher();
        $dispatcher->addSubscriber($container->get(AuthorizationHook::class));
        $route = $this->permissionRoute();

        $response = (new RequestHandler(new TestPrincipal(), $dispatcher))->handle($route, new TestRestaRequest('/x', $route));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRouteThatOnlyReceivesPrincipalIsPublic(): void
    {
        // Principal を使うこと自体は、許可制の宣言ではない
        $route = new class(new TestPrincipal()) extends AbstractRoute {
            public function __construct(private readonly Principal $principal)
            {
            }

            public function callback(): bool
            {
                return $this->principal->isAuthenticated();
            }
        };

        $response = $this->handle($route);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->getData());
    }

    public function testStaticRouteHasNoAttributes(): void
    {
        $route = new class('fixed') extends AbstractRoute {
            public function __construct(public readonly string $body)
            {
            }
        };

        $this->assertSame(200, $this->handle($route)->getStatusCode());
    }
}
