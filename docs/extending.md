# フレームワーク拡張ガイド

wp-resta のコア処理は WordPress に依存しない設計になっています。フレームワークを拡張する手段は主に2つあります。

| 手段 | 対象 | 推奨度 |
|---|---|---|
| **EventDispatcher** | フレームワーク内部のイベントを購読する | ✅ 推奨 |
| **HookProvider** | WordPress フックを宣言的に登録する | 移行レイヤー（レガシーコードの整理向け） |

---

## EventDispatcher

フレームワーク内部のイベントを購読するための仕組みです。WordPress のフック（`add_action`/`add_filter`）を使わないため、**WP 環境なしでテスト可能**です。

### イベントの種類

wp-resta のイベントには2種類あります。

#### NamedEvent（型安全・推奨）

`NamedEvent` を継承したイベントは、**クラス名がイベント識別子**になります。リスナーメソッドのパラメータ型から自動的に購読対象が決まるため、文字列でのイベント名指定が不要です。

```php
// NamedEvent サブクラスを受け取るメソッド → #[Listen] 不要
public function onRoute(RouteInvocationEvent $event): void
{
    // RouteInvocationEvent::class がイベントキーになる
}
```

#### Event（文字列名・TransitionEvent など）

`Event` を直接継承し、コンストラクタで文字列名を渡すイベントです。TransitionEvent（ステートマシン遷移イベント）や `'wp.init'` のような WP ライフサイクルイベントがこれにあたります。これらを購読するには `#[Listen]` アトリビュートが必要です。

```php
#[Listen('wp.init')]
public function onInit(Event $event): void { ... }
```

---

### リスナーの実装と登録

#### リスナークラスを作る

```php
<?php
namespace MyREST\Listeners;

use Wp\Resta\EventDispatcher\Attributes\Listen;
use Wp\Resta\EventDispatcher\Event;
use Wp\Resta\REST\RouteInvocationEvent;

class MyListener
{
    public function __construct(private MyService $service) {} // DI 対応

    // NamedEvent サブクラス → #[Listen] 不要（型から自動推論）
    public function onRoute(RouteInvocationEvent $event): void
    {
        // ルート実行後のレスポンスを加工できる
        $this->service->log($event->route, $event->response);
    }

    // 文字列名イベント → #[Listen] が必要
    #[Listen('wp.init')]
    public function onInit(Event $event): void
    {
        $this->service->setup();
    }
}
```

#### `listeners` config に登録する

```php
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [ /* ... */ ],
    'listeners' => [
        \MyREST\Listeners\MyListener::class,
    ],
]);
```

DI コンテナ経由でインスタンス化されるため、コンストラクタインジェクションが使えます。

---

### 組み込みイベント

#### `RouteInvocationEvent`

ルートの `invoke()` 実行後、WordPress にレスポンスを返す前に発火します。`$event->response` を書き換えることでレスポンスを変換できます。

```php
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\RouteInvocationEvent;

class ResponseTransformer
{
    public function transform(RouteInvocationEvent $event): void
    {
        // response は RestaResponseInterface（非 null 保証）
        $original = $event->response;

        // 書き換えると WordPress に返るレスポンスが変わる
        $event->response = new MyCustomResponse($original);
    }
}
```

| プロパティ | 型 | 説明 |
|---|---|---|
| `$event->request` | `RestaRequestInterface` | 現在のリクエスト（読み取り専用） |
| `$event->route` | `RouteInterface` | 実行中のルート（読み取り専用） |
| `$event->response` | `RestaResponseInterface` | レスポンス（書き換え可） |

#### WP ライフサイクルイベント（`'wp.init'`）

WordPress の `init` アクションに対応します。管理画面やフロントエンドの初期化処理に利用できます。

```php
#[Listen('wp.init')]
public function onInit(Event $event): void
{
    // WordPress の init 相当のタイミングで実行される
}
```

---

### ステートマシン遷移イベント（上級者向け）

カーネルのライフサイクルは `KernelState` ステートマシンで管理されています。

```
Booting ──boot──▶ Bootstrapped ──registerRoutes──▶ RoutesRegistered
```

各遷移では **遷移前（guard）** と **遷移後（after）** の2種類のイベントが発火します。

```php
use Wp\Resta\EventDispatcher\Attributes\Listen;
use Wp\Resta\Kernel\KernelState;
use Wp\Resta\StateMachine\TransitionEvent;

class LifecycleListener
{
    // 遷移後イベント: 'Wp\Resta\Kernel\KernelState::registerRoutes'
    #[Listen('Wp\Resta\Kernel\KernelState::registerRoutes')]
    public function onRoutesRegistered(TransitionEvent $event): void
    {
        // ルート登録完了後に実行
    }

    // 遷移前ガードイベント: $event->path を変えると遷移先を切り替えられる
    // ※ stopPropagation() は後続リスナーを止めるだけで遷移自体はキャンセルされない
    #[Listen('Wp\Resta\Kernel\KernelState::boot.guard')]
    public function guardBoot(TransitionEvent $event): void
    {
        // 複数遷移先を持つ Transition なら $event->path = 'stop' のように切り替え可能
        // 単一遷移先の場合は path を変えても例外になるため注意
    }
}
```

イベント名を文字列で書くと typo のリスクがあるため、`TransitionEvent` のヘルパーメソッドを使うのが確実です。

```php
use Wp\Resta\StateMachine\TransitionEvent;
use Wp\Resta\Kernel\KernelState;

$eventName = TransitionEvent::afterEventName(KernelState::Bootstrapped, 'registerRoutes');
// → 'Wp\Resta\Kernel\KernelState::registerRoutes'

$guardName = TransitionEvent::guardEventName(KernelState::Booting, 'boot');
// → 'Wp\Resta\Kernel\KernelState::boot.guard'
```

ただし、`#[Listen]` アトリビュートの引数はコンパイル時定数でなければならないため、ヘルパーメソッドはアトリビュートの中では使えません。文字列リテラルを使ってください。

---

## HookProvider

WordPress のフック（`add_action`/`add_filter`）を宣言的に登録するための基底クラスです。

> **位置づけ**: HookProvider は、`functions.php` に散在した WP フックをオートロード・DI 対応のクラスに引き上げるための**移行レイヤー**です。新規実装では EventDispatcher の利用を推奨します。

### 移行パス

```
Stage 1（レガシー）
  functions.php に add_action / add_filter が散在

Stage 2（HookProvider — このクラス）
  オートロード・DI 対応、#[AddAction] / #[AddFilter] で宣言的に登録

Stage 3（推奨）
  Dispatcher::addSubscriber() + listeners config
  WP 非依存・テスト可能
```

### 使い方

`HookProvider` を継承し、`#[AddAction]` または `#[AddFilter]` アトリビュートをメソッドに付与します。

```php
<?php
namespace MyREST\Hooks;

use Wp\Resta\Hooks\HookProvider;
use Wp\Resta\Hooks\Attributes\AddAction;
use Wp\Resta\Hooks\Attributes\AddFilter;

class MyHookProvider extends HookProvider
{
    public function __construct(private MyService $service) {}

    #[AddAction('init', priority: 10)]
    public function onInit(): void
    {
        $this->service->setup();
    }

    #[AddFilter('the_content', priority: 20, acceptedArgs: 1)]
    public function filterContent(string $content): string
    {
        return $this->service->transform($content);
    }
}
```

### `hooks` config に登録する

```php
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [ /* ... */ ],
    'hooks' => [
        \MyREST\Hooks\MyHookProvider::class,
    ],
]);
```

DI コンテナ経由でインスタンス化されるため、コンストラクタインジェクションが使えます。

### `#[AddAction]` / `#[AddFilter]` の引数

| 引数 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `$hook` | `string\|BackedEnum` | 必須 | フック名 |
| `$priority` | `int` | `10` | 優先度（小さい値が先に実行） |
| `$acceptedArgs` | `int` | `1` | WordPress から受け取る引数の数 |

### EventDispatcher との使い分け

| 用途 | 推奨手段 |
|---|---|
| REST レスポンスの変換 | `RouteInvocationEvent` + `listeners` |
| WP 管理画面の UI 登録 | `HookProvider` + `#[AddAction('admin_menu')]` |
| レガシーコードの `add_filter` 整理 | `HookProvider`（移行の足場として） |
| WP フックを使う新機能 | `HookProvider`（WP 依存を局所化する意味で） |
