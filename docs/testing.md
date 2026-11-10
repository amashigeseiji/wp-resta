# テストガイド

wp-resta 本体と、wp-resta を使うプラグインのテストの書きかた。保証される挙動は [spec.md](spec.md) にある。

## 3 層

| 層 | 場所 | WP | 用途 |
|---|---|---|---|
| Unit | `tests/Unit/` | 不要。WP 関数を呼ぶ箇所だけ Brain Monkey でモック | ルート、DI、Dispatcher、スキーマ推論 |
| Integration | `tests/Integration/` | 不要。`add_action` / `register_rest_route` を Brain Monkey で受ける | `Resta::init()` から登録までの流れ |
| E2E | `tests/E2E/` | Docker 上の WordPress に HTTP で叩く | 実際の REST API 応答 |

```bash
composer test            # Unit + Integration
composer test:e2e        # E2E（docker/e2e-test.sh。初回は docker/setup.sh）
composer test:coverage   # カバレッジ
composer lint            # PHPStan
```

`tests/bootstrap.php` が `WP_HTTP_Response` / `WP_REST_Response` の最小スタブを定義している。Unit / Integration で WP のクラスが新たに必要になったらここに足す。

## ルートのユニットテスト

ルートの実行（検証、認可、callback、`RouteInvocationEvent`）は `RequestHandler` が行い、WordPress に依存しない。`TestRestaRequest` が URL 文字列をルート定義に照らしてパラメータに分解するので、WordPress 無しで本番と同じパイプラインを通せる。

```php
use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;
use MyApp\Routes\MyRoute;

class MyRouteTest extends TestCase
{
    public function testWithParameters(): void
    {
        $route = new MyRoute();
        $route->setNamespace('myapi');
        $handler = new RequestHandler(new TestPrincipal(), new Dispatcher());

        $request = new TestRestaRequest('/myapi/myroute/123', $route);
        $response = $handler->handle($route, $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(123, $response->getData()['id']);
    }
}
```

- `setNamespace()` を呼ばないと URL の namespace 部分が一致せずパラメータが取れない
- クエリパラメータは `'/myapi/myroute/123?name=test'` のように URL に付ける。同名ならパスパラメータが優先される
- 400 / 500 の検証は `getStatusCode()` と `getData()['code']` を見る（形は spec.md「エラーレスポンス」）
- `TestRestaRequest` の値はクライアントが送った文字列のまま。宣言した型への変換は `RequestHandler` の中で行われるので、本番と同じ値が callback に届く
- `authorize()` を持つルートは `new TestPrincipal(authenticated: true, capabilities: ['edit_posts'])` を `RequestHandler` に渡して主体を固定する。引数なしの `TestPrincipal` は未認証で、401 / 403 の検証に使える
- `#[Envelope]` の包みや自作のリスナーまで含めて確認したいときは、`Dispatcher` に `addSubscriber(new EnvelopeHook())` などを足してから `RequestHandler` に渡す

## DI のモック

コンストラクタインジェクションしている依存は Mockery で差し替えて直接渡す。

```php
$service = Mockery::mock(MyService::class);
$service->shouldReceive('getData')->with(123)->andReturn(['result' => 'mocked']);

$route = new MyRoute($service);
```

callback 引数で受け取る依存は `Container::getInstance()->bind(MyService::class, $service)` で bind する。

## Container のリセット

`Container` はプロセス内シングルトンなので、bind したものはテスト間で持ち越される。bind したテストは `tearDown()` でリセットする。

```php
protected function tearDown(): void
{
    $instance = (new ReflectionClass(Container::class))->getProperty('instance');
    $instance->setValue(null, null);
    Mockery::close();
    parent::tearDown();
}
```

## WP 関数を呼ぶコードのテスト

Brain Monkey を使う。`setUp()` で `Monkey\setUp()`、`tearDown()` で `Monkey\tearDown()`。

```php
Functions\when('get_post')->justReturn((object)['ID' => 1, 'post_title' => 't']);
Functions\expect('register_rest_route')->once()->with('myapi', Mockery::type('string'), Mockery::type('array'));
```

`add_action` に渡されたクロージャを捕まえて後から呼ぶときは `Functions\when('add_action')->alias(function ($hook, $cb) use (&$captured) { $captured[$hook][] = $cb; })` のように受ける。

## E2E

`docker-compose.yml` の WordPress にプラグインとして wp-resta を有効化し、`tests/E2E/Api/` から Guzzle で叩く。WP コアの検証・型変換・認証を含めて確認したいケースはここに書く。Unit で再現できるものは Unit に書く。

## 本体の変更で足すテストの目安

- `AbstractRoute` の挙動変更: `tests/Unit/REST/AbstractRouteTest.php` に加え、E2E で WP コア経由の結果も 1 ケース
- 登録・ライフサイクル: `tests/Integration/RestaIntegrationTest.php`
- OpenAPI / TypeScript 出力: `tests/Unit/OpenApi/`、`tests/Unit/CodeGen/` のスナップショット
