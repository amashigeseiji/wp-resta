<?php
namespace Test\Resta\Unit\REST\Example\Routes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Example\Routes\Posts;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;

/**
 * 宣言のテストの手本。このエンドポイントが受け取らない値を、クライアントから見える契約として固定する。
 *
 * 検証は callback の前に行われるので、callback が WordPress を呼ぶルートでも、WordPress 無しで確かめられる。
 * 受け取った値で何が返るかは、WordPress が要るので E2E で確かめる
 */
class PostsTest extends TestCase
{
    #[DataProvider('invalidQueries')]
    public function testInvalidValueIs400(string $query): void
    {
        $route = new Posts();
        $route->setNamespace('example');

        $response = (new RequestHandler(new Dispatcher()))->handle($route, new TestRestaRequest('/example/posts?' . $query, $route));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('rest_invalid_param', $response->getData()['code']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'per_page が上限を超える' => ['per_page=101'],
            'per_page が 0' => ['per_page=0'],
            'page が整数でない' => ['page=first'],
            'order が asc でも desc でもない' => ['order=random'],
        ];
    }
}
