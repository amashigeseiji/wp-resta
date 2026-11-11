[![Continuous Integration](https://github.com/amashigeseiji/wp-resta/actions/workflows/ci.yml/badge.svg)](https://github.com/amashigeseiji/wp-resta/actions/workflows/ci.yml)

# Wp\Resta

WordPress 上で REST API 開発をするためのプラグインです。

アイデアやインターフェースは [BEAR.Sunday](https://bearsunday.github.io) から影響を受けています。

## How to install

前提: WordPress 管理画面からパーマリンク設定を「投稿名」などにしておいてください。

### 自作テーマで利用する

```
$ cd /path/to/theme
$ composer require tenjuu99/wp-resta
```

`functions.php` で初期化

以下は、サンプルのディレクトリにあるAPIを読みこむ設定です。

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

(new Wp\Resta\Resta)->init([
    'routeDirectory' => [
        [__DIR__ . '/vendor/wp/resta/src/REST/Example/Routes', 'Wp\\Resta\\REST\\Example\\Routes\\', 'example']
    ],
    'schemaDirectory' => [
        [__DIR__ . '/vendor/wp/resta/src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
    ],
    'hooks' => [
        \Wp\Resta\Hooks\SwaggerHook::class,  // Swagger UI を有効化
    ],
]);
```

`SwaggerHook` は `/rest-api/schema` のリライトルールを追加するだけで flush は行いません。追加後は管理画面の「設定 > パーマリンク」で保存するか、`flush_rewrite_rules()` を一度実行してください。

### プラグインで利用する場合

WordPress のプラグインとしても利用できます。

次の例は `composer/installers` を利用して WordPress プラグインを `wp-content/plugins/` 以下に配置しています。

```
$ composer config "extra.installer-paths.wp-content/plugins/{\$name}/" "['type:wordpress-plugin']"
$ composer require composer/installers tenjuu99/wp-resta
```

無事 wp-content/plugins 以下に展開できたら、管理画面からプラグインを有効化してください。

プラグインとして有効化した場合は、`plugin.php` のアクティベーションフックで `flush_rewrite_rules()` が実行されるので、上記の flush は不要です。


## Example

インストールしたら、管理画面に `REST API doc` というメニューが追加されます。

このページでは、 Swagger UI を使ってAPI定義をドキュメント化しています。

このサンプル実装は `src/REST/Example/Routes/` 以下にあります。


## How to develop

自分のルーティング定義を追加するためには、 `functions.php` での初期化時のコードにルーティング用ディレクトリの設定を記述してください。

`routeDirectory` に渡す配列は、 `['ディレクトリ名', 'php namespace', 'api namespace']` となっています。
`schemaDirectory` は `['ディレクトリ名', 'php namespace']` です。

```diff
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [
        ['wp-content/themes/mytheme/vendor/wp/resta/src/REST/Example/Routes', 'Wp\\Resta\\REST\\Example\\Routes\\', 'example'],
+       ['src/Routes', 'MyREST\\Routes\\', 'myroute']
    ],
    'schemaDirectory' => [
        ['wp-content/themes/mytheme/vendor/wp/resta/src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
+       ['src/Schemas', 'MyREST\\Schemas\\']
    ],
]);
```

必要に応じて `composer.json` にも autoload 設定を追加してください。

```diff
  "autoload": {
+     "psr-4": {
+         "MyREST\\": "src/"
+     }
  }
```


`src/Routes/HelloWorld.php` を作ります。

```php
<?php
namespace MyREST\Routes;

use Wp\Resta\REST\AbstractRoute;

class HelloWorld extends AbstractRoute
{
    public function callback(): string
    {
        return 'Hello, world!';
    }
}
```

次のURLが生成されます。

```
$ curl http://example.com/wp-json/myroute/helloworld
```

"Hello, world!" と値が返ってきていることを確かめてください。


### URL定義とURL変数

`myroute` が `functions.php` で定義した namespace ですが、 `helloworld` にはクラス名がそのまま利用されています。

URL 定義は `ROUTE` 定数を定義することで変更できます。ついでにURL変数も定義してみます。

```php
<?php
namespace MyREST\Routes;

use Wp\Resta\REST\AbstractRoute;

class HelloWorld extends AbstractRoute
{
    protected const ROUTE = 'hello/[name]';
    protected const URL_PARAMS = [
        'name' => 'string',
    ];

    public function callback(string $name) : string
    {
        return "Hello, {$name}!";
    }
}
```

次のURLが生成されるとおもいます。

```
$ curl http://example.com/wp-json/myroute/hello/amashige
```

`"Hello, amashige!"` と返ってくるとおもいます。

ROUTE定数のなかに `[var]` と `[]` で囲えばパスパラメータとして扱うことができます。

変数は次のようなパターンを許容します。

```php
    protected const URL_PARAMS = [
        'id' => 'integer',
        'id_not_required' => '?integer',
        'name' => 'string',
        'name_not_required' => '?string',
        'ok_or_ng' => '(ok|ng)',
        'first_name' => [
            'type' => 'string',
            'required' => false,
            'regex' => '[a-z]+'
        ],
    ];
```

`'string'` は任意の文字列、`'integer'` は整数を受け取り、`?` を付けると省略可能になります。それ以外の文字列は正規表現として、値全体に照合されます（`^` `$` を自分で書く必要はなく、`'[a-z/]+'` のように `/` を含めても構いません）。`'string'` を英数字だけに絞りたいなら `'\w+'` のように正規表現を書きます。

宣言したパラメータは、callback の引数で受け取るかどうかに関係なくすべて検証されます。regex は `register_rest_route()` の `args` に `pattern` としても渡されるので、WordPress の REST API discovery にも載ります。

`URL_PARAMS` を書かなくても、callback の引数の型がそのまま宣言になります。`int` / `float` / `bool` / `string` / `array` / backed enum が使え、デフォルト値があれば省略可能、無ければ必須です。型で表せない制約は `#[Param]` で足します。

```php
use Wp\Resta\REST\Attributes\Param;

protected const ROUTE = 'posts/[id]';

/**
 * @param string[] $tags
 */
public function callback(
    int $id,
    #[Param(min: 1, max: 100)] int $per_page = 10,
    Sort $sort = Sort::Asc,        // backed enum → asc / desc だけ受け付け、enum のインスタンスで届く
    bool $published = true,        // true / false / 1 / 0
    array $tags = [],              // ?tags[]=a&tags[]=b でも ?tags=a,b でも
    #[Param(pattern: '[a-z-]+')] string $slug = '',
): array
```

値は宣言した型に変換されて届きます（`'42'` → `42`、`'true'` → `true`）。同名の `URL_PARAMS` があればそちらが優先されます。

省略可能な変数は、URL に含まれなかった場合だけ callback のデフォルト値が使われます。`?name=` のように空文字で渡された値は省略とはみなさず、正規表現に一致しなければ 400 になります。

これらは、ROUTE定数に `user/[id]` のような形でURL埋め込み変数として定義されていればパスパラメータとして機能します。

パスパラメータとして利用されていないが `URL_PARAMS` に定義されている変数はクエリパラメータとして利用されます。

次のような例は、 `http://example.com/wp-json/myroute/user/2?name=tenjuu99` として展開されます。

```php
    protected const ROUTE = 'user/[id]';
    protected const URL_PARAMS = [
        'id' => 'integer',
        'name' => '?string',
    ];
```

これらの変数は callback メソッドで受け取ることができます。

パスパラメータとクエリパラメータに同じ名前の値がある場合（`user/2?id=5` など）、パスパラメータが優先されます。WordPress のデフォルトはクエリパラメータ優先ですが、wp-resta は `rest_request_parameter_order` フィルタでパスパラメータを先頭に移動しています。このフィルタは URL に埋め込みパラメータを持つリクエストにのみ適用されるため、`/wp/v2/posts/(?P<id>\d+)` のような WordPress コアのルートでもパスパラメータが優先されます。


### コールバック

`AbstractRoute` を継承したクラスが `callback` という名のメソッドを持っている場合、このメソッドを呼びだしてレスポンスの body にします。body として返してよいのは、配列、文字列、オブジェクトなど、WordPress REST API がレスポンスとして扱える任意のデータです。また、`Wp\Resta\REST\Http\RestaResponseInterface` を返した場合にはそのまま利用されます。

`callback` メソッドの引数は、URL変数を受けとることができます。 `URL変数` に `id` を定義していれば `callback(int $id)` と定義して問題ありません。

また、簡易な DI があるため、解決可能なクラスを引数に定義すると受け取ることができます。ランタイムに値が決まるもの(例えば `WP_REST_Request` )などはコンストラクタインジェクションでは値が決まっていませんが、コールバックが呼び出される時点では確定しているので、利用できます。

ステータスコードやヘッダを指定したい場合は、`$this->response()` でレスポンスを作って返します。

```php
public function callback(int $id): RestaResponseInterface
{
    $post = get_post($id);
    if ($post === null || $post->post_status !== 'publish') {
        return $this->response(null, 404);
    }
    // WP_Post をそのまま返すと post_password などの内部フィールドも JSON に出るので、返すフィールドを選ぶ
    return $this->response(['id' => $post->ID, 'title' => $post->post_title], 200, ['Cache-Control' => 'no-store']);
}
```

ルートの既定ヘッダは `HEADERS` 定数で宣言します。callback がどう返してもすべてのレスポンス（エラーを含む）に付き、同名ならレスポンス側の値が勝ちます。

```php
protected const HEADERS = ['Cache-Control' => 'no-store'];
```

### 同じ URL で HTTP メソッドごとに処理を分ける

`callback` の代わりに `get` / `post` / `put` / `patch` / `delete` という名前のメソッドを書くと、それぞれがその HTTP メソッドの処理になります。引数の宣言はメソッドごとに独立していて、`post` の引数は GET のリクエストでは要求されません。

```php
class Notes extends AbstractRoute
{
    protected const ROUTE = 'notes/[id]';

    public function get(int $id): array
    {
        return ['id' => $id];
    }

    public function post(int $id, string $title): array
    {
        return ['id' => $id, 'title' => $title];
    }
}
```

```
$ curl http://example.com/wp-json/myroute/notes/1
$ curl -X POST http://example.com/wp-json/myroute/notes/1 -d title=hello
$ curl -X DELETE http://example.com/wp-json/myroute/notes/1   # 404 rest_no_route
```

`callback` は、`getMethods()` で宣言したメソッド（既定は GET。`'GET,POST'` のようにカンマ区切りで複数）すべてを 1 つのメソッドで受ける書きかたです。`callback` と `get` などを同じクラスに書くことはできません。`[id]` のようなパスパラメータは、どのメソッドでも同じ型で宣言してください。

### 認可

ログインしているか、特定の capability を持っているかで利用を制限したいルートは、`authorize()` をオーバーライドします。既定はすべてのリクエストを許可します。

```php
use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\Exception\ForbiddenException;
use Wp\Resta\REST\Exception\UnauthorizedException;
use Wp\Resta\REST\Http\RestaRequestInterface;

use Wp\Resta\REST\Parameter\BoundParameters;

public function authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal): void
{
    if (!$principal->isAuthenticated()) {
        throw new UnauthorizedException();   // 401
    }
    if (!$principal->can('edit_post', $params->get('id'))) {
        throw new ForbiddenException();      // 403
    }
}
```

`Principal` は WordPress の現在のユーザーに対応し、`can()` は `current_user_can()` と同じ引数を取ります。cookie + nonce でも Application Passwords でも、WordPress が認証した結果がそのまま見えます。`authorize()` は URL変数の検証が通った後、`callback` の前に呼ばれるので、`$params` には型変換済みの値が入っています。

複数のパラメータにまたがる検証や DB を引く検証は `validate()` に書きます。型と正規表現の検証が通った後、`authorize()` の前に呼ばれます。

```php
public function validate(BoundParameters $params): void
{
    if ($params->get('from') > $params->get('to')) {
        throw new InvalidParameterException('from', 'from must be <= to');   // 400
    }
}
```

WordPress のユーザーに対応づかない独自の認証を使う場合は、`Principal` の実装を書いて `dependencies` で差し替えます。

### エラーレスポンス

URL変数の値が regex に一致しない、または required な値が無い場合は 400 を返します。`authorize()` で拒否した場合は 401 または 403 を返します。ボディは WordPress コアの `WP_Error` と同じ形（`code` / `message` / `data.status`）です。

```json
{"code": "rest_invalid_param", "message": "Invalid parameter(s): id", "data": {"status": 400, "params": {"id": "id is invalid."}}}
```

callback が例外を投げた場合は `error_log()` に記録したうえで 500 の `rest_internal_error` を返します。`WP_DEBUG` が有効なときは例外のメッセージとクラス名もボディに含まれます。

任意のステータスで返したい場合は、callback から `Wp\Resta\REST\Exception\HttpException` を投げるとそのステータスとコードでレスポンスになります。

```php
throw new HttpException('Post not found', 404, 'rest_post_invalid_id');
```

### キャッシュ

読み取り専用のルートに `#[Cache]` を付けると、未認証の GET の 200 レスポンスに `Cache-Control` と `ETag` が付きます。クライアントが `If-None-Match` で同じ ETag を送ってきたら、ボディ無しの 304 を返します。

```php
use Wp\Resta\REST\Attributes\Cache;

#[Cache(maxAge: 300)]
class Posts extends AbstractRoute
{
    // ...
}
```

`Cache-Control` は `public, max-age=300` になります。`public: false` で `private`、`sMaxAge:` で `s-maxage`、`staleWhileRevalidate:` で `stale-while-revalidate` を足せます。ETag はレスポンスボディから計算されるので、ボディが同じなら同じ値になります。

ログイン中のリクエストには付きません。WordPress がログイン中の REST リクエストに no-cache ヘッダを送るので、それがそのまま使われます。エラーレスポンスと GET 以外にも付きません。レスポンスが `Cache-Control` をすでに持っていれば（`$this->response($data, 200, ['Cache-Control' => 'no-store'])` など）、そちらが優先され、ETag も付きません。

## DI

簡易なDIを用意しています。

基本的には autorwiring で、ほとんどの場合に設定なしで利用できます。
また、基本的にはコンストラクタインジェクションのみ対応しています(`AbstractRoute::callback` は例外)。

```php
// src/Lib/Foo.php
namespace MyREST\Lib;

class Foo
{
    private Bar $bar;

    public function __construct(Bar $bar)
    {
        $this->bar = $bar;
    }

    public function getBarString(): string
    {
        return $this->bar->get();
    }
}

// src/Lib/Bar.php
namespace MyREST\Lib;

class Bar
{
    public function get(): string
    {
        return 'bar';
    }
}

// src/Routes/Sample.php

namespace MyREST\Routes;

use MyREST\Lib\Foo;

class Sample extends AbstractRoute
{
    private Foo $foo;
    public function __construct(Foo $foo)
    {
        $this->foo = $foo;
    }

    public function callback()
    {
        return $this->foo->getBarString();
    }
}
```

`Bar::get` の返り値が解決されます。

上記は DI の autowiring で解決可能ですが、interface を注入する場合は自動で解決できません。この場合は設定が必要になります。
設定は、初期化コードに `dependencies` を渡すことができます。


```diff
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [
    ...
    ],
    'schemaDirectory' => [
    ...
    ],
+   'dependencies' => [
+       PSR\Log\LoggerInterface::class => MonoLog\Logger::class,
+   ],
]);
```

また、autowiring 機構はクラスやインターフェースの依存しか解決しないため、コンストラクタがクラスやインターフェース以外の値を受けている場合は解決できません。
この場合は関数を使ってください。

```diff
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [
    ...
    ],
    'schemaDirectory' => [
    ...
    ],
    'dependencies' => [
        PSR\Log\LoggerInterface::class => MonoLog\Logger::class,
+       WP_Query::class => function () {
+           return new WP_Query(['post_type' => 'post']);
+       }
    ],
]);
```

## Testing Your Routes

wp-resta を使って作成した Route クラスは、**WordPress 環境なしで**テストできます。リクエストの処理（検証、認可、callback）は `RequestHandler` が行うので、テストでは `RequestHandler` にルートと `TestRestaRequest` を渡します。

### 基本的なテスト

```php
<?php
use PHPUnit\Framework\TestCase;
use Wp\Resta\Auth\TestPrincipal;
use Wp\Resta\EventDispatcher\Dispatcher;
use Wp\Resta\REST\Http\TestRestaRequest;
use Wp\Resta\REST\RequestHandler;
use MyREST\Routes\HelloWorld;

class HelloWorldTest extends TestCase
{
    public function testHelloWorld()
    {
        $route = new HelloWorld();
        $handler = new RequestHandler(new TestPrincipal(), new Dispatcher());

        $response = $handler->handle($route, new TestRestaRequest('/helloworld', $route));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello, world!', $response->getData());
    }
}
```

### 認可のテスト

`RequestHandler` に渡す `TestPrincipal` で、認証状態と capability を固定します。引数なしの `TestPrincipal` は未認証です。

```php
$handler = new RequestHandler(new TestPrincipal(authenticated: true, capabilities: ['edit_posts']), new Dispatcher());
$response = $handler->handle($route, new TestRestaRequest('/myapi/secret', $route));
$this->assertEquals(200, $response->getStatusCode());

$handler = new RequestHandler(new TestPrincipal(), new Dispatcher());
$response = $handler->handle($route, new TestRestaRequest('/myapi/secret', $route));
$this->assertEquals(401, $response->getStatusCode());
```

### URL パラメータを使ったテスト

URL パラメータが定義されている Route では、TestRestaRequest が自動的にパラメータをパースします：

```php
<?php
use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Http\TestRestaRequest;
use MyREST\Routes\HelloWorld;

class HelloWorldWithParamTest extends TestCase
{
    public function testHelloWithName()
    {
        // ROUTE = 'hello/[name]' の Route
        $route = new HelloWorld();
        $route->setNamespace('myroute');  // namespace を設定

        // URL パラメータは自動的にパースされる
        $request = new TestRestaRequest('/myroute/hello/amashige', $route);
        $response = (new RequestHandler(new TestPrincipal(), new Dispatcher()))->handle($route, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello, amashige!', $response->getData());
    }
}
```

### 配列データのテスト

レスポンスが配列の場合も、`getData()` で直接アクセスできます：

```php
<?php
public function testJsonResponse()
{
    $route = new MyApiRoute();
    $request = new TestRestaRequest('/api/user/123', $route);
    $response = (new RequestHandler(new TestPrincipal(), new Dispatcher()))->handle($route, $request);

    $data = $response->getData();
    $this->assertIsArray($data);
    $this->assertEquals(123, $data['user']['id']);
    $this->assertEquals('John', $data['user']['name']);
}
```

### 必要なパッケージ

```bash
composer require --dev phpunit/phpunit
```

---

### Docker で動作確認する

Docker を使って簡単に動作確認環境をセットアップできます。

```bash
# セットアップ（WordPress インストール + プラグイン有効化）
./docker/setup.sh

# API の動作確認
./docker/test.sh
```

セットアップ完了後、以下の URL にアクセスできます：

- WordPress: http://localhost:8080
- 管理画面: http://localhost:8080/wp-admin (admin / admin)
- サンプルAPI: http://localhost:8080/wp-json/example/sample/1
- Swagger UI: http://localhost:8080/wp-admin/admin.php?page=resta-swagger-ui

環境を停止するには：

```bash
docker compose down
```

## テスト

### Unit/Integration テスト

```bash
composer test
```

### E2E テスト

Docker 環境で実際の WordPress REST API に対してテストを実行:

```bash
# Docker 環境を起動（初回のみ）
./docker/setup.sh

# E2E テスト実行
composer test:e2e
# または
./docker/e2e-test.sh
```

## Schema

wp-resta は OpenAPI 3.0 によるスキーマ定義をサポートしています。ルートクラスで定義したスキーマは自動的に OpenAPI 仕様として出力され、Swagger UI でドキュメント化されます。

**スキーマ定義の詳細**: wp-resta では複数の方法でスキーマを定義できます。詳しくは [docs/SCHEMA.md](docs/SCHEMA.md) を参照してください。
- `ObjectType` クラスを使った型安全なスキーマ定義（推奨）
- PHPDoc の `@return` アノテーションによる自動推論
- `SCHEMA` 定数による完全な制御（複雑なスキーマ向け）

### Envelope Pattern

wp-resta では、REST API レスポンスを統一的な構造でラップする**Envelope Pattern**をサポートしています。

Envelope Patternは、すべての API レスポンスを以下の構造で統一します：

```json
{
  "data": {
    // 実際のレスポンスデータ
  },
  "meta": {
    // メタデータ（オプション）
  }
}
```

#### エンベロープパターンの使い方

エンベロープパターンを使うには、`#[Envelope]` 属性をルートクラスに追加します：

```php
<?php
namespace MyREST\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;

#[Envelope]
class Posts extends AbstractRoute
{
    protected const ROUTE = 'posts';

    // スキーマはデータ部分のみ定義
    public const SCHEMA = [
        'type' => 'array',
        'items' => [
            '$ref' => '#/components/schemas/Post'
        ],
    ];

    // 普通に配列を返すだけ（自動的にエンベロープでラップされる）
    public function callback(): array
    {
        $posts = get_posts(['post_type' => 'post']);
        return $posts;
    }
}
```

**EnvelopeHook は自動的に有効です**

`EnvelopeHook` はフレームワークに組み込まれており、設定不要で常に有効です。`#[Envelope]` 属性のないルートには適用されません（素通り）。包まれるのは成功（2xx）のレスポンスだけで、400 や 500 などのエラーは WordPress コアと同じ `WP_Error` の形のまま返ります。クライアントはステータスが 2xx なら `data` を、エラー（4xx / 5xx）なら `code` を見れば済みます。

**レスポンス：**
```json
{
  "data": [
    { "ID": 1, "post_title": "Hello World" },
    { "ID": 2, "post_title": "Sample Post" }
  ],
  "meta": {
    "processed_at": "2026-02-17 12:00:00",
    "plugin_version": "0.8.4",
    "request_route": "/myroute/posts"
  }
}
```

`#[Envelope]` 属性を追加すると、`callback()` メソッドで返した値が自動的に `data` プロパティにラップされます。OpenAPI スキーマもエンベロープ構造として自動生成されます。

#### メタデータのカスタマイズ

`meta` プロパティは `RouteInvocationEvent` のリスナーで追加できます：

```php
<?php
namespace MyREST\Listeners;

use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\RouteInvocationEvent;

class CustomMetaListener
{
    // RouteInvocationEvent は NamedEvent のサブクラスなので #[Listen] 不要
    public function addCustomMeta(RouteInvocationEvent $event): void
    {
        if (!$event->response instanceof EnvelopeResponse) {
            return;
        }

        $event->response = $event->response
            ->addMeta('processed_at', current_time('mysql'))
            ->addMeta('plugin_version', '0.8.4');
    }
}
```

設定ファイルに追加：

```php
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [ /* ... */ ],
    'listeners' => [
        \MyREST\Listeners\CustomMetaListener::class,
    ],
]);
```

EventDispatcher と listeners config の詳細は [docs/extending.md](docs/extending.md) を参照してください。

### OpenAPI スキーマの確認

生成された OpenAPI スキーマは以下の URL で確認できます：

```
http://localhost:8080/rest-api/schema
```

Swagger UI で視覚的に確認できます：

```
http://localhost:8080/wp-admin/admin.php?page=wp-resta
```

エンベロープパターンを使用したルートは、OpenAPI スキーマでも自動的にエンベロープ構造（`type: object` with `data` and `meta` properties）として定義されます。
