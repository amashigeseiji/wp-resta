# wp-resta 仕様

wp-resta が利用者に保証する契約を書く。使いかたの説明は README、拡張方法は [extending.md](extending.md)、スキーマ定義は [SCHEMA.md](SCHEMA.md)、テストの書きかたは [testing.md](testing.md) にある。

この文書は `main` の実装を記述する。挙動を変える PR はこの文書も同じ PR で更新する。

## 設計原則

- **wp-resta は DSL である。** ルートクラスの定数・メソッドシグニチャ・アトリビュートから、登録・検証・OpenAPI・TypeScript を導出する。利用者に WordPress REST API の語彙（`register_rest_route()` の args、`permission_callback`、`WP_Error`）を直接書かせない
- **WordPress との境界は薄い変換層に限定する。** WP の型・関数・フックを wp-resta の型に変換する箇所は列挙できる状態に保つ（下記「WordPress との境界」）
- **ルートは WordPress 無しでテストできる。** `AbstractRoute::invoke()` は `RestaRequestInterface` だけに依存し、`TestRestaRequest` で駆動できる
- **プロセスの状態は WordPress が持つ。** WP のフックを起点にした状態を wp-resta 側で管理しない。フックは Dispatcher イベントに変換するだけにする

## ルート定義

ルートは `Wp\Resta\REST\AbstractRoute` を継承したクラス 1 つにつきエンドポイント 1 つ。`Resta::init()` の `routeDirectory` に指定したディレクトリから glob で収集し、DI コンテナでインスタンス化する。

| 定義 | 意味 | 省略時 |
|---|---|---|
| `protected const ROUTE` | URL パターン。`[name]` がパスパラメータ | クラス名の小文字 |
| `protected const URL_PARAMS` | URL パラメータの型と制約（下記） | パラメータなし |
| `public const SCHEMA` | レスポンスの OpenAPI スキーマ | callback の戻り値型から推論 |
| `getMethods()` | HTTP メソッド | `GET` |
| `#[RouteMeta(description:, tags:)]` | OpenAPI の説明とタグ | なし |
| `#[Envelope]` | レスポンスを `{ data, meta }` で包む | 包まない |

URL は `/wp-json/{namespace}/{ROUTE}`。namespace は `routeDirectory` の 3 番目の要素。

### URL_PARAMS

| 表記 | type | required | regex |
|---|---|---|---|
| `'integer'` / `'?integer'` | integer | `?` なしで必須 | `\d+` |
| `'string'` / `'?string'` | string | `?` なしで必須 | `\w+` |
| その他の文字列 | string | 必須 | その文字列 |
| `['type' => , 'required' => , 'regex' => , 'description' => ]` | 指定どおり | 指定どおり | 指定どおり |

- `ROUTE` に `[name]` があるものはパスパラメータ、無いものはクエリパラメータ
- 同名のパスパラメータとクエリパラメータがあればパスパラメータが優先される（`rest_request_parameter_order` フィルタ）
- regex は値全体に照合する。`^` `$` は書かない。区切り文字は wp-resta が付けるので `/` を含めてよい。UTF-8 モード（`u`）で照合する
- regex は `register_rest_route()` の args に `pattern` としても渡され、WP コアが dispatch 前に検証する
- 省略可能なパラメータは URL に無いときだけ callback のデフォルト値になる。空文字は省略ではなく値として扱い、regex に一致しなければ 400

## callback の呼び出し

`AbstractRoute::invoke(RestaRequestInterface $request): RestaResponseInterface` が入口。ルートが `callback` メソッドを持つとき、引数を次の規則で解決して呼ぶ。

1. 引数名が `URL_PARAMS` にある → `$request->getUrlParam()` の値。required で無ければ 400、regex 不一致なら 400
2. クラス・インターフェース型 → DI コンテナから解決。`WP_REST_Request` と `RestaRequestInterface` はリクエストごとに bind される
3. `URL_PARAMS` に無いビルトイン型 → `LogicException`（ルート定義の誤り）
4. union / intersection 型 → `InvalidArgumentException`

callback の戻り値がレスポンスになる。

| 戻り値 | 結果 |
|---|---|
| `RestaResponseInterface` | そのまま使う |
| それ以外（配列、スカラー、`JsonSerializable` など） | ステータス 200 のボディ。`$status` / `$headers` プロパティの値を使う |

`$this->response(mixed $data, int $status = 200, array $headers = [])` でステータスとヘッダを指定したレスポンスを作れる。ヘッダはクラス宣言の `$headers` に上書きで合成する。

callback を持たないルートは `$body` / `$status` / `$headers` プロパティの値をそのまま返す。

ルートインスタンスはプロセス内で共有されるため、リクエストごとに clone してから `invoke()` する。

## エラーレスポンス

ボディは WP コアの `WP_Error` と同じ形。`data.status` に HTTP ステータスが入る。

```json
{"code": "rest_invalid_param", "message": "Invalid parameter(s): id", "data": {"status": 400, "params": {"id": "id is invalid."}}}
```

| 原因 | ステータス | code |
|---|---|---|
| required なパラメータが無い | 400 | `rest_missing_callback_param` |
| regex 不一致、不正な UTF-8 | 400 | `rest_invalid_param` |
| callback が `HttpException` を投げた | 例外のステータス | 例外のコード |
| callback がそれ以外の例外を投げた | 500 | `rest_internal_error` |
| regex 自体が不正、ルート定義の誤り | 500 | `rest_internal_error` |

500 の場合は `error_log()` に例外クラス・ルートクラス・メッセージ・位置を記録する。`WP_DEBUG` 有効時はボディにメッセージと例外クラス名も載せる。

## 認可

`RouteInterface::permissionCallback(): string` が `register_rest_route()` の `permission_callback` にそのまま渡される。デフォルトは `'__return_true'` を返し、WP はこれを空でない文字列として許可と解釈する。`string` 型のため拒否を表現できない（#61、0.12.0 で変更予定）。

認証（cookie + nonce、Application Password）は WP が dispatch 前に行う。wp-resta は関与しない。

## DI

`Wp\Resta\DI\Container` はプロセス内シングルトン。

- クラス型はコンストラクタの型宣言から再帰的に autowiring する
- インターフェースは `init()` の `dependencies` で実装クラスか factory クロージャを bind する
- `bind($abstract, $concrete)` で任意の値も bind できる
- コンストラクタインジェクションはルート生成時、callback 引数の解決は `invoke()` 時

## 設定

`Resta::init(array $config)` が受け取るキー。

| キー | 型 | 内容 |
|---|---|---|
| `routeDirectory` | `array<array{string, string, string}>` | ディレクトリ、名前空間プレフィックス、REST namespace |
| `schemaDirectory` | `array<array{string, string}>` | スキーマクラスのディレクトリと名前空間プレフィックス |
| `dependencies` | `array<class-string, class-string\|callable\|object>` | DI バインディング |
| `hooks` | `array<class-string<HookProviderInterface>>` | WP フックをまとめたクラス |
| `listeners` | `array<class-string>` | Dispatcher の購読者 |
| `adapters` | `array<class-string<WpKernelAdapter>>` | WP ライフサイクルの橋渡し |

## イベント

| イベント | 発火 | 用途 |
|---|---|---|
| `wp.init` | WP の `init` | 初期化処理 |
| `RouteInvocationEvent` | `invoke()` 後 | レスポンスの差し替え（`EnvelopeHook` が使う） |
| `KernelState::*` / `RequestState::*` 遷移イベント | ステートマシンの遷移 | 0.12.0 で廃止予定（#60） |

詳細と購読方法は [extending.md](extending.md)。

## WordPress との境界

| 箇所 | 変換 |
|---|---|
| `Kernel\WpKernelAdapter` | WP フック → Dispatcher イベント |
| `REST\RegisterRestRoutes::register()` | ルート定義 → `register_rest_route()` |
| `REST\Http\WpRestaRequest::fromWpRequest()` | `WP_REST_Request` → `RestaRequestInterface` |
| `Lifecycle\RequestHandler` | `RestaResponseInterface` → `WP_REST_Response` |
| `Hooks\HookProvider` | アトリビュート → `add_action()` / `add_filter()` |

これ以外の場所で WP の関数・クラスに依存するコードを増やさない。ルートの callback や利用者のサービスが WP 関数を呼ぶのは自由。

## 互換性ポリシー

- 1.0 未満のため、minor（0.y）の更新に破壊的変更を含むことがある。patch（0.y.z）は後方互換
- 破壊的変更は CHANGELOG に「削除」「変更」として代替手段とともに書く
- 非推奨化は `E_USER_DEPRECATED` で 1 minor 以上警告してから削除する
