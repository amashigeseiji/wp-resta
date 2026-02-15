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
    'use-swagger' => true,
    'schemaDirectory' => [
        [__DIR__ . '/vendor/wp/resta/src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
    ],
]);
```

### プラグインで利用する場合

WordPress のプラグインとしても利用できます。

次の例は `composer/installers` を利用して WordPress プラグインを `wp-content/plugins/` 以下に配置しています。

```
$ composer config "extra.installer-paths.wp-content/plugins/{\$name}/" "['type:wordpress-plugin']"
$ composer require composer/installers tenjuu99/wp-resta
```

無事 wp-content/plugins 以下に展開できたら、管理画面からプラグインを有効化してください。


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
    public $body = 'Hello, world!';
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
        return "Hello, ${name}!";
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
        'id_not_required' => '!integer',
        'name' => 'string',
        'name_not_required' => '!string',
        'ok_or_ng' => '(ok|ng)',
        'first_name' => [
            'type' => 'string',
            'required' => false,
            'regex' => '[a-z]+'
        ],
    ];
```

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


### コールバック

`AbstractRoute` を継承したクラスが `callback` という名のメソッドを持っている場合、このメソッドを呼びだしてレスポンスの body にします。body として返してよいのは、配列、文字列、オブジェクトなど、WordPress REST API がレスポンスとして扱える任意のデータです。また、`Wp\Resta\REST\Http\RestaResponseInterface` を返した場合にはそのまま利用されます。

`callback` メソッドの引数は、URL変数を受けとることができます。 `URL変数` に `id` を定義していれば `callback(int $id)` と定義して問題ありません。

また、簡易な DI があるため、解決可能なクラスを引数に定義すると受け取ることができます。ランタイムに値が決まるもの(例えば `WP_REST_Request` )などはコンストラクタインジェクションでは値が決まっていませんが、コールバックが呼び出される時点では確定しているので、利用できます。

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

wp-resta を使って作成した Route クラスは、**WordPress 環境なしで**テストできます。

### 基本的なテスト

```php
<?php
use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Http\TestRestaRequest;
use MyREST\Routes\HelloWorld;

class HelloWorldTest extends TestCase
{
    public function testHelloWorld()
    {
        $route = new HelloWorld();

        // TestRestaRequest でシンプルにテスト
        $request = new TestRestaRequest('/helloworld', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello, world!', $response->getData());
    }
}
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
        $response = $route->invoke($request);

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
    $response = $route->invoke($request);

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
