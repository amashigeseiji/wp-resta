[![Continuous Integration](https://github.com/amashigeseiji/wp-resta/actions/workflows/ci.yml/badge.svg)](https://github.com/amashigeseiji/wp-resta/actions/workflows/ci.yml)

# Wp\Resta

WordPress 上で REST API 開発をするためのプラグインです。

用途別のエンドポイントを、決まった場所に、決まった形で書けるようにします。

- **規約**: Route クラス 1 つがエンドポイント 1 つ。決まったディレクトリに置きます
- **契約**: Route クラスの宣言（定数、callback の引数と戻り値の型、アトリビュート）から OpenAPI を導出します。フロントエンドはこの仕様に依存できます
- **テスト**: Route クラスは WordPress 無しでテストできます

誰のどの問題を解くかは [docs/design.md](docs/design.md) にあります。

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
        [__DIR__ . '/vendor/tenjuu99/wp-resta/src/REST/Example/Routes', 'Wp\\Resta\\REST\\Example\\Routes\\', 'example']
    ],
    'schemaDirectory' => [
        [__DIR__ . '/vendor/tenjuu99/wp-resta/src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
    ],
    'hooks' => [
        \Wp\Resta\Hooks\SwaggerHook::class,  // Swagger UI を有効化
    ],
]);
```

`SwaggerHook` は OpenAPI のスキーマを `/wp-json/resta/v1/schema` で返し、管理画面の Swagger UI はこれを読みます。スキーマは誰でも取得できます。

場所と、取得できる相手を変えるときは、`dependencies` に `SwaggerOptions` を書きます。

```php
    'dependencies' => [
        \Wp\Resta\Hooks\SwaggerOptions::class => new \Wp\Resta\Hooks\SwaggerOptions(
            namespace: 'myapp/v1',       // /wp-json/myapp/v1/schema で返す
            route: '/schema',
            capability: 'edit_pages',    // この capability を持つ人にだけ返す。省略すると誰でも
        ),
    ],
```

- `capability` を指定すると、管理画面のメニューも同じ capability を持つ人にだけ見えます。省略したときのメニューは `edit_pages` です
- 既定の `resta/v1` と `/schema` は、今後のバージョンで変わることがあります。URL に依存するときは、`namespace` と `route` を明示してください
- プラグインとして利用する場合、設定ファイルはオートローダーより先に読まれます。`fn () => new \Wp\Resta\Hooks\SwaggerOptions(...)` のようにクロージャで書いてください

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
        ['wp-content/themes/mytheme/vendor/tenjuu99/wp-resta/src/REST/Example/Routes', 'Wp\\Resta\\REST\\Example\\Routes\\', 'example'],
+       ['src/Routes', 'MyREST\\Routes\\', 'myroute']
    ],
    'schemaDirectory' => [
        ['wp-content/themes/mytheme/vendor/tenjuu99/wp-resta/src/REST/Example/Schemas', 'Wp\\Resta\\REST\\Example\\Schemas\\'],
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

`ROUTE` 定数での URL の変更、パラメータの宣言、HTTP メソッドごとの処理は [docs/routes.md](docs/routes.md) にあります。


## 文書の案内

やりたいことから、読む文書と手本を引けます。手本は `src/REST/Example/Routes/` にある、実際に動くコードです。記事と下書きを題材にした、1 つの小さな API になっています。テストは `tests/Unit/REST/Example/` にあります。

手本になるのは、Route クラスの宣言（URL、パラメータ、アトリビュート、HTTP メソッドごとの処理）と、テストの書きかたです。データの取りかた（`WP_Query` をどこに書くか、何に詰め替えて返すか）は一例で、決まった書きかたはありません。

| やりたいこと | 読むもの | 手本 |
|---|---|---|
| URL とパラメータを宣言する | [docs/routes.md](docs/routes.md) | `Hello.php`、`Posts.php`、`Post.php` |
| クエリの組み立てを分けて、テストで固定する | [docs/di.md](docs/di.md) | `LatestPosts.php`、`src/REST/Example/Services/LatestPostsQuery.php` |
| 同じ URL で GET と POST を分ける | [docs/routes.md](docs/routes.md) | `Drafts.php` |
| ステータスやヘッダを指定する、エラーを返す | [docs/routes.md](docs/routes.md) | |
| ログイン状態や権限で制限する | [docs/authorization.md](docs/authorization.md) | `Drafts.php`、`Draft.php` |
| キャッシュのヘッダを付ける | [docs/cache.md](docs/cache.md) | `Posts.php` |
| レスポンスを `data` / `meta` で包む | [docs/envelope.md](docs/envelope.md) | `Post.php`、`Posts.php` |
| レスポンスの形（スキーマ）を宣言する | [docs/SCHEMA.md](docs/SCHEMA.md) | `Post.php`、`Posts.php` |
| Service クラスを注入する | [docs/di.md](docs/di.md) | `LatestPosts.php` |
| Route クラスをテストする | [docs/testing.md](docs/testing.md) | `tests/Unit/REST/Example/` |
| フロントエンドの TypeScript の型を生成する | [docs/frontend.md](docs/frontend.md) | |
| イベントを購読する、WordPress のフックを登録する | [docs/extending.md](docs/extending.md) | `src/REST/Example/Hooks/` |
| `register_rest_route()` との対応を知る | [docs/wordpress.md](docs/wordpress.md) | |
| 何が保証されているかを確かめる | [docs/spec.md](docs/spec.md) | |
| 動かない | [docs/troubleshooting.md](docs/troubleshooting.md) | |

### AI エージェントに書かせるとき

wp-resta を使うプロジェクトの `AGENTS.md` や `CLAUDE.md` に、次を足してください。パスは wp-resta を置いた場所に合わせます。

```markdown
wp-resta で API を書くときは、vendor/tenjuu99/wp-resta/README.md の「文書の案内」から読む。
Route クラスの宣言とテストの書きかたは vendor/tenjuu99/wp-resta/src/REST/Example/Routes/ をまねる。データの取りかたは一例なので、このプロジェクトのやりかたに合わせる。
何が保証されているかは vendor/tenjuu99/wp-resta/docs/spec.md で確かめる。
```

## wp-resta 自体を開発する

- AI エージェント向けの指示: [AGENTS.md](AGENTS.md)
- 設計の考え方: [docs/design.md](docs/design.md)
- テストの実行と Docker での動作確認: [docs/testing.md](docs/testing.md)
