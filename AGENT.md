# AGENT.md - wp-resta プロジェクトガイド

このドキュメントは AI アシスタントが wp-resta プロジェクトを理解し、効率的に開発作業を行うためのガイドです。

## プロジェクト概要

**wp-resta** は WordPress 上で REST API 開発を行うための PHP フレームワークです。

### 主要な特徴

- **クラスベースのルーティング**: `AbstractRoute` を継承したクラスでエンドポイントを定義
- **BEAR.Sunday からインスパイアされた設計**: リソース指向の API 設計
- **DI コンテナ**: Autowiring による依存性注入（コンストラクタインジェクション + コールバック引数の解決）
- **Swagger 統合**: OpenAPI 仕様に基づく自動ドキュメント生成
- **WordPress 非依存のテスト**: `TestRestaRequest` を使って WordPress 環境なしでユニットテスト可能
- **Attribute ベースのメタデータ**: `#[RouteMeta]` などの PHP Attribute による設定

### 技術スタック

- **PHP**: 8.2+ (型宣言、Attribute を活用)
- **WordPress**: REST API 基盤として利用
- **PSR-4**: オートローディング規約
- **PHPUnit**: テストフレームワーク
- **PHPStan**: 静的解析
- **Docker**: 開発環境とE2Eテスト環境

---

## アーキテクチャ

### コアコンポーネント

```
Wp\Resta\Resta (エントリーポイント)
    ├─ Config (設定管理)
    ├─ DI\Container (シングルトンの DI コンテナ)
    ├─ REST\RegisterRestRoutes (ルート登録)
    ├─ REST\AbstractRoute (ルートの基底クラス)
    ├─ Hooks\HookProvider (WordPress フック管理)
    └─ OpenApi\Doc (Swagger ドキュメント生成)
```

### リクエスト/レスポンスフロー

1. WordPress REST API がリクエストを受信
2. `RegisterRestRoutes` がルートクラスを解決
3. DI コンテナがルートクラスをインスタンス化（コンストラクタインジェクション）
4. `AbstractRoute::invoke()` が呼ばれる
5. `callback()` メソッドが呼ばれる（URL パラメータと DI 解決された引数）
6. レスポンスを返す（配列、文字列、または `RestaResponseInterface`）

---

## ディレクトリ構造

```
wp-resta/
├── src/
│   ├── Resta.php              # エントリーポイント、init() メソッド
│   ├── Config.php             # 設定クラス
│   ├── DI/
│   │   └── Container.php      # DI コンテナ（Singleton）
│   ├── REST/
│   │   ├── AbstractRoute.php  # ルートの基底クラス【重要】
│   │   ├── RouteInterface.php
│   │   ├── RegisterRestRoutes.php  # WordPress へのルート登録
│   │   ├── Http/              # Request/Response インターフェース
│   │   │   ├── RestaRequestInterface.php
│   │   │   ├── RestaResponseInterface.php
│   │   │   ├── TestRestaRequest.php  # テスト用リクエスト【重要】
│   │   │   └── SimpleRestaResponse.php
│   │   ├── Attributes/        # PHP Attribute
│   │   │   └── RouteMeta.php  # ルートメタデータ
│   │   ├── Schemas/           # OpenAPI スキーマ基底
│   │   └── Example/           # サンプル実装【参考にする】
│   │       ├── Routes/        # ルートクラスのサンプル
│   │       │   ├── Sample.php
│   │       │   ├── Post.php
│   │       │   └── Posts.php
│   │       └── Schemas/       # スキーマクラスのサンプル
│   ├── Hooks/                 # WordPress フック管理
│   │   ├── HookProviderInterface.php
│   │   ├── HookProvider.php
│   │   ├── InternalHooks.php  # 内部フック
│   │   ├── SwaggerHooks.php   # Swagger UI 統合
│   │   ├── Enum/RestApiHook.php  # REST API フック定義
│   │   └── Attributes/        # フック用 Attribute
│   └── OpenApi/               # Swagger ドキュメント生成
├── tests/
│   ├── Unit/                  # ユニットテスト
│   │   ├── REST/              # ルートのテスト例【重要】
│   │   ├── DI/
│   │   └── Hooks/
│   ├── Integration/           # 統合テスト
│   └── E2E/                   # E2Eテスト（Docker 環境）
├── docker/                    # Docker 環境
│   ├── setup.sh
│   └── e2e-test.sh
├── composer.json
├── phpunit.xml
└── README.md
```

---

## 開発ワークフロー

### 新しいルートの追加

#### 1. ルートクラスを作成

```php
<?php
// src/REST/Example/Routes/MyRoute.php
namespace Wp\Resta\REST\Example\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\RouteMeta;

#[RouteMeta(
    description: "マイルートの説明",
    tags: ["カテゴリ名"]
)]
class MyRoute extends AbstractRoute
{
    // URL パターン: /wp-json/{namespace}/myroute/[id]
    protected const ROUTE = 'myroute/[id]';

    // URL パラメータ定義
    protected const URL_PARAMS = [
        'id' => 'integer',           // 必須の整数パラメータ
        'name' => '?string',         // オプションの文字列パラメータ
    ];

    // OpenAPI スキーマ（オプション）
    public const SCHEMA = [
        '$schema' => 'http://json-schema.org/draft-04/schema#',
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
        ],
    ];

    /**
     * コールバックメソッド
     * URL パラメータと DI 解決されたオブジェクトを引数で受け取る
     */
    public function callback(int $id, ?string $name = null): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'message' => 'Hello from MyRoute!',
        ];
    }
}
```

#### 2. functions.php で登録

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

(new Wp\Resta\Resta)->init([
    'routeDirectory' => [
        [__DIR__ . '/src/Routes', 'MyApp\\Routes\\', 'myapi']
    ],
]);
```

#### 3. テストを書く

```php
<?php
namespace Test\MyApp;

use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Http\TestRestaRequest;
use MyApp\Routes\MyRoute;

class MyRouteTest extends TestCase
{
    public function testMyRoute()
    {
        $route = new MyRoute();
        $route->setNamespace('myapi');

        $request = new TestRestaRequest('/myapi/myroute/123?name=test', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData();
        $this->assertEquals(123, $data['id']);
        $this->assertEquals('test', $data['name']);
    }
}
```

---

## ルートクラスの詳細

### AbstractRoute の定数

#### `protected const ROUTE`

URL パターンを定義します。`[param]` で囲んだ部分がパスパラメータになります。

```php
protected const ROUTE = 'user/[id]/posts/[post_id]';
// → /wp-json/{namespace}/user/123/posts/456
```

定義しない場合は**クラス名の小文字版**が使われます。

```php
class HelloWorld extends AbstractRoute {}
// → /wp-json/{namespace}/helloworld
```

#### `protected const URL_PARAMS`

URL パラメータの型と制約を定義します。

```php
protected const URL_PARAMS = [
    // 基本的な型
    'id' => 'integer',           // 必須の整数 (\d+)
    'name' => 'string',          // 必須の文字列 (\w+)
    'slug' => '?string',         // オプションの文字列

    // カスタム正規表現
    'status' => '(active|inactive)',  // 列挙型

    // 詳細設定
    'email' => [
        'type' => 'string',
        'required' => false,
        'regex' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
        'description' => 'メールアドレス',
    ],
];
```

**重要**:
- `ROUTE` に含まれるパラメータは**パスパラメータ**
- `ROUTE` に含まれないパラメータは**クエリパラメータ**

#### `public const SCHEMA`

OpenAPI スキーマを定義します（Swagger ドキュメント用）。

```php
public const SCHEMA = [
    '$schema' => 'http://json-schema.org/draft-04/schema#',
    'type' => 'object',
    'properties' => [
        'id' => ['type' => 'integer', 'example' => 1],
        'name' => ['type' => 'string', 'example' => 'John'],
    ],
];
```

スキーマクラスを参照することも可能：

```php
public const SCHEMA = [
    'type' => 'object',
    'properties' => [
        'post' => ['$ref' => '#/components/schemas/Post']
    ]
];
```

### コールバックメソッド

#### 基本形

```php
public function callback(): mixed
{
    return ['message' => 'Hello'];
}
```

#### URL パラメータを受け取る

```php
public function callback(int $id, ?string $name = null): array
{
    // URL_PARAMS で定義したパラメータを引数で受け取る
    return ['id' => $id, 'name' => $name];
}
```

**重要**: 引数名は `URL_PARAMS` のキー名と一致させる必要があります。

#### DI でオブジェクトを受け取る

```php
public function callback(int $id, wpdb $wpdb): array
{
    // wpdb は DI コンテナによって自動注入される
    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id
    ));
    return ['post' => $post];
}
```

**引数の解決順序**:
1. `URL_PARAMS` で定義された値 → URL から取得
2. クラス/インターフェース型 → DI コンテナから解決
3. その他のビルトイン型 → エラー（`URL_PARAMS` に定義されていない場合）

### プロパティによるレスポンス設定

`callback` メソッドを定義しない場合、プロパティでレスポンスを設定できます：

```php
class Simple extends AbstractRoute
{
    protected $body = 'Hello, world!';
    protected int $status = 200;
    protected array $headers = ['X-Custom-Header' => 'value'];
}
```

`callback` メソッド内でもプロパティを変更可能：

```php
public function callback(int $id): ?array
{
    $post = get_post($id);
    if ($post === null) {
        $this->status = 404;  // ステータスコードを変更
        return null;
    }
    return ['post' => $post];
}
```

### RestaResponseInterface を返す

より細かい制御が必要な場合は `RestaResponseInterface` を返します：

```php
use Wp\Resta\REST\Http\SimpleRestaResponse;

public function callback(int $id): RestaResponseInterface
{
    return new SimpleRestaResponse(
        ['data' => 'value'],
        200,
        ['X-Custom-Header' => 'value']
    );
}
```

---

## DI (依存性注入)

### コンストラクタインジェクション

ルートクラスのコンストラクタで依存を注入できます：

```php
class MyRoute extends AbstractRoute
{
    public function __construct(
        private MyService $service,
        private LoggerInterface $logger
    ) {}

    public function callback(int $id): array
    {
        $this->logger->info("Accessing ID: {$id}");
        return $this->service->getData($id);
    }
}
```

### Autowiring

DI コンテナは自動的にクラスの依存関係を解決します：

```php
class MyService
{
    public function __construct(
        private AnotherService $another  // 自動的に注入される
    ) {}
}
```

### インターフェースのバインド

インターフェースは自動解決できないため、`init()` で設定が必要です：

```php
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [...],
    'dependencies' => [
        LoggerInterface::class => MonologLogger::class,
    ],
]);
```

### ファクトリ関数

複雑な初期化が必要な場合は関数を使います：

```php
'dependencies' => [
    WP_Query::class => function () {
        return new WP_Query(['post_type' => 'post', 'posts_per_page' => 10]);
    },
],
```

### コールバックの DI

`callback()` メソッドの引数は**実行時に解決**されます。これにより `WP_REST_Request` のようなランタイム値も注入可能：

```php
use Wp\Resta\REST\Http\RestaRequestInterface;

public function callback(int $id, RestaRequestInterface $request): array
{
    // $request は invoke() 時に注入される
    return ['id' => $id, 'method' => $request->getMethod()];
}
```

---

## テスト戦略

### WordPress 非依存のユニットテスト

**`TestRestaRequest` を使う**ことで、WordPress 環境なしでルートをテストできます。

#### 基本的なテスト

```php
use PHPUnit\Framework\TestCase;
use Wp\Resta\REST\Http\TestRestaRequest;
use MyApp\Routes\HelloWorld;

class HelloWorldTest extends TestCase
{
    public function testHelloWorld()
    {
        $route = new HelloWorld();
        $request = new TestRestaRequest('/myapi/helloworld', $route);
        $response = $route->invoke($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello, world!', $response->getData());
    }
}
```

#### パラメータ付きルートのテスト

```php
public function testWithParameters()
{
    $route = new MyRoute();
    $route->setNamespace('myapi');

    // URL パラメータは自動的にパースされる
    $request = new TestRestaRequest('/myapi/myroute/123?name=test', $route);
    $response = $route->invoke($request);

    $data = $response->getData();
    $this->assertEquals(123, $data['id']);
    $this->assertEquals('test', $data['name']);
}
```

#### DI のモック

```php
use Mockery;

public function testWithDependency()
{
    $mockService = Mockery::mock(MyService::class);
    $mockService->shouldReceive('getData')
                ->with(123)
                ->andReturn(['result' => 'mocked']);

    $route = new MyRoute($mockService);
    $request = new TestRestaRequest('/myapi/myroute/123', $route);
    $response = $route->invoke($request);

    $this->assertEquals('mocked', $response->getData()['result']);
}
```

### テストコマンド

```bash
# ユニット/統合テスト
composer test

# E2Eテスト（Docker 環境で実際の WordPress に対してテスト）
composer test:e2e

# カバレッジレポート
composer test:coverage

# 静的解析
composer lint
```

---

## Swagger 統合

### 有効化

```php
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [...],
    'schemaDirectory' => [
        [__DIR__ . '/src/Schemas', 'MyApp\\Schemas\\'],
    ],
    'use-swagger' => true,  // Swagger UI を有効化
]);
```

### アクセス

WordPress 管理画面に「REST API doc」メニューが追加されます：
- URL: `http://yoursite.com/wp-admin/admin.php?page=resta-swagger-ui`

### スキーマクラス

```php
namespace MyApp\Schemas;

use JsonSerializable;

class Post implements JsonSerializable
{
    public const SCHEMA = [
        '$schema' => 'http://json-schema.org/draft-04/schema#',
        'title' => 'Post',
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
        ],
    ];

    public function __construct(private \WP_Post $post) {}

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->post->ID,
            'title' => $this->post->post_title,
        ];
    }
}
```

---

## WordPress フック統合

### HookProvider の使用

WordPress フックを管理するには `HookProvider` を使います：

```php
namespace MyApp\Hooks;

use Wp\Resta\Hooks\HookProvider;
use Wp\Resta\Hooks\Attributes\AddAction;
use Wp\Resta\Hooks\Attributes\AddFilter;

class MyHooks extends HookProvider
{
    #[AddAction('init', priority: 10)]
    public function onInit(): void
    {
        // 初期化処理
    }

    #[AddFilter('the_content', priority: 10)]
    public function filterContent(string $content): string
    {
        return $content . ' [Modified]';
    }
}
```

### 登録

```php
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [...],
    'hooks' => [
        MyApp\Hooks\MyHooks::class,
    ],
]);
```

---

## コーディング規約とベストプラクティス

### 命名規則

- **ルートクラス名**: PascalCase、リソース名を表す（例: `Post`, `UserProfile`, `Articles`）
- **URL パラメータ**: snake_case（例: `user_id`, `post_slug`）
- **名前空間**: PSR-4 に準拠

### ファイル配置

```
MyPlugin/
├── src/
│   ├── Routes/          # すべてのルートクラス
│   ├── Schemas/         # OpenAPI スキーマクラス
│   ├── Services/        # ビジネスロジック
│   └── Hooks/           # WordPress フックハンドラ
└── tests/
    └── Unit/
        └── Routes/      # ルートのテスト
```

### ルートクラスのベストプラクティス

1. **単一責任**: 1つのルートクラスは1つのエンドポイントを表す
2. **ビジネスロジックは分離**: `callback` は薄く保ち、ロジックは Service クラスに
3. **必ずテストを書く**: `TestRestaRequest` を使ってユニットテスト
4. **型宣言を使う**: PHP 8.2+ の型機能を活用
5. **OpenAPI スキーマを定義**: ドキュメント生成のため
6. **RouteMeta Attribute を使う**: ルートの説明とタグを追加

### 避けるべきパターン

❌ **callback に WordPress グローバル関数を直接書く**
```php
public function callback(int $id): array
{
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM ...");  // ❌
}
```

✅ **DI で注入する**
```php
public function callback(int $id, wpdb $wpdb): array
{
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM ..."));  // ✅
}
```

❌ **URL_PARAMS に定義していないビルトイン型を callback の引数に追加**
```php
protected const URL_PARAMS = ['id' => 'integer'];

public function callback(int $id, string $name): array  // ❌ $name が定義されていない
```

✅ **オプショナルパラメータとして定義する**
```php
protected const URL_PARAMS = [
    'id' => 'integer',
    'name' => '?string',  // ✅
];

public function callback(int $id, ?string $name = null): array
```

---

## AI アシスタント向けの特別な指示

### 新しいルートを追加する際

1. **必ず `src/REST/Example/Routes/` のサンプルを参考にする**
   - `Sample.php`: 基本的なパラメータ処理
   - `Post.php`: スキーマ参照の例
   - `Posts.php`: 複数リソースの返却例

2. **ルートクラスを作成したら必ずテストを書く**
   - `tests/Unit/REST/HelloWorldSimpleTest.php` を参考に
   - `TestRestaRequest` を使う

3. **URL_PARAMS の定義を忘れない**
   - パスパラメータは `ROUTE` と `URL_PARAMS` 両方に定義
   - クエリパラメータは `URL_PARAMS` のみに定義

4. **DI の依存関係を確認する**
   - インターフェースを使う場合は `dependencies` に登録
   - コンストラクタで受け取る依存は必ず型宣言

### テストを書く際

1. **WordPress 環境は不要**
   - `TestRestaRequest` を使えば WordPress なしでテスト可能
   - Brain\Monkey を使う必要もない（WordPress 関数を使わない限り）

2. **DI のモックは Mockery を使う**
   ```php
   $mock = Mockery::mock(MyService::class);
   $mock->shouldReceive('method')->andReturn('value');
   ```

3. **tearDown で Container をリセット**
   ```php
   protected function tearDown(): void
   {
       $reflection = new ReflectionClass(Container::class);
       $instance = $reflection->getProperty('instance');
       $instance->setAccessible(true);
       $instance->setValue(null, null);
       parent::tearDown();
   }
   ```

### DI の設定を変更する際

1. **`Resta::init()` の `dependencies` に追加**
   - インターフェース → 実装クラス のマッピング
   - 複雑な初期化は関数を使う

2. **Singleton である Container を意識する**
   - テスト間で状態が共有される可能性があるため、必ずリセット

### コミットメッセージ

- 機能追加: `Add [ルート名] endpoint`
- バグ修正: `Fix [問題の説明]`
- テスト追加: `Add test for [対象]`
- リファクタリング: `Refactor [対象]`

### 注意事項

- **WordPress のパーマリンク設定を「投稿名」などに設定する必要がある**（デフォルト設定では動作しない）
- **PHP 8.2+ が必須**（型システムと Attribute を使用）
- **コンストラクタインジェクションは初期化時、コールバックの DI は実行時**
- **`ROUTE` 定数の `[var]` と Swagger の `{var}` は自動変換される**

---

## トラブルシューティング

### ルートが登録されない

- `routeDirectory` の設定を確認
- 名前空間とディレクトリパスが一致しているか確認
- `composer dump-autoload` を実行

### URL パラメータが取得できない

- `URL_PARAMS` のキー名と `callback` の引数名が一致しているか確認
- パスパラメータは `ROUTE` にも定義されているか確認

### DI でクラスが解決されない

- インターフェースの場合は `dependencies` に登録されているか確認
- クラスが存在し、autoload されているか確認
- コンストラクタの依存が複雑すぎないか確認（ファクトリ関数を使う）

### テストが失敗する

- `TestRestaRequest` の URL が正しいか確認
- `setNamespace()` を呼んでいるか確認
- WordPress 関数を使っている場合は Brain\Monkey でモック

---

## 外部リソース

- **README.md**: 基本的な使い方とセットアップ
- **src/REST/Example/**: 実装サンプル
- **tests/Unit/REST/**: テストサンプル
- **BEAR.Sunday**: https://bearsunday.github.io (設計思想の元)
- **OpenAPI Specification**: https://swagger.io/specification/

---

このドキュメントは wp-resta プロジェクトの開発をサポートするために作成されました。
質問や不明点があれば、README.md やサンプルコードを参照してください。
