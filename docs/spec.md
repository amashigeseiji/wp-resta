# wp-resta 仕様

wp-resta が利用者に約束することを書く。ここに書いてあることは依存してよく、変えるときは破壊的変更として CHANGELOG に載る。ここに書いていない挙動は実装の詳細であり、patch リリースで変わりうる。

使いかたは README、拡張方法は [extending.md](extending.md)、スキーマ定義は [SCHEMA.md](SCHEMA.md)、テストの書きかたは [testing.md](testing.md)。

## 設計原則

- **wp-resta は DSL である。** ルートクラスの宣言から、登録・検証・OpenAPI・TypeScript を導出する。利用者に WordPress REST API の語彙（`register_rest_route()` の引数、`permission_callback`、`WP_Error`）を直接書かせない
- **ルートは WordPress 無しでテストできる。** ルートの実行は `RestaRequestInterface` だけに依存する
- **WordPress との境界は薄い変換層に限定する。** WordPress の型・関数・フックを wp-resta の型に変換する箇所以外で WordPress に依存しない
- **プロセスの状態は WordPress が持つ。** WordPress のフックを起点にした状態を wp-resta は管理しない

## ルート

- `AbstractRoute` を継承したクラス 1 つがエンドポイント 1 つ。設定で指定したディレクトリから自動で収集される
- URL は `/wp-json/{namespace}/{route}`。`route` は `ROUTE` 定数で宣言し、省略するとクラス名から決まる
- `ROUTE` の `[name]` はパスパラメータ。それ以外の宣言済みパラメータはクエリパラメータ
- HTTP メソッドは `getMethods()` で宣言する。既定は GET
- `#[RouteMeta]` で OpenAPI の説明とタグを宣言できる
- `#[Envelope]` を付けると、レスポンスのボディが `{ "data": ..., "meta": ... }` に包まれる

## URL パラメータ

- パラメータは名前と型（string / integer）を宣言する。省略可能なパラメータは `?` を付けて宣言し、callback 引数のデフォルト値が使われる
- 型に加えて正規表現で制約を宣言できる。正規表現は値全体に照合される
- 宣言された制約を満たさない入力は 400 になり、callback は呼ばれない
- 同名のパスパラメータとクエリパラメータがあればパスパラメータが優先される
- 空文字は省略ではなく値として扱う

## callback

- ルートが `callback` メソッドを持つとき、リクエストごとに呼ばれる
- callback の引数は、宣言済みの URL パラメータなら名前で、それ以外のクラス型なら DI コンテナから解決される。`WP_REST_Request` と `RestaRequestInterface` は解決できる
- callback の戻り値がレスポンスボディになる。`RestaResponseInterface` を返せばステータスとヘッダも含めてそのまま使われる
- `$this->response($data, $status, $headers)` でステータスとヘッダを指定したレスポンスを作れる
- ルートインスタンスの状態はリクエストをまたいで持ち越されない

## エラーレスポンス

- ボディは WordPress コアの `WP_Error` と同じ形（`code` / `message` / `data.status`）
- 入力の不正は 400。callback から `HttpException` を投げると、そのステータスとコードで返る
- callback が投げたその他の例外は 500 になり、内容は握り潰さずログに残る。`WP_DEBUG` 有効時はボディにも載る

## 認証と認可

- 認証（cookie + nonce、Application Password など）は WordPress が行う。wp-resta は関与しない
- ルート単位の認可は既定で全リクエストを許可する。認可を宣言する手段は 0.12.0 で整備する（#61）

## DI

- クラス型はコンストラクタの型宣言から自動で解決される
- インターフェースは設定の `dependencies` で実装クラスか factory を対応づける
- コンストラクタインジェクションはルート生成時、callback 引数の解決はリクエスト時

## 設定

`Resta::init(array $config)` が受け取るキー。

| キー | 内容 |
|---|---|
| `routeDirectory` | ルートクラスのディレクトリ、名前空間プレフィックス、REST namespace の組 |
| `schemaDirectory` | スキーマクラスのディレクトリと名前空間プレフィックスの組 |
| `dependencies` | DI の対応づけ |
| `hooks` | WordPress フックをまとめた `HookProvider` のクラス |
| `listeners` | イベントの購読者 |
| `adapters` | WordPress ライフサイクルの橋渡し |

## イベント

- `wp.init`: WordPress の `init` で発火する
- `RouteInvocationEvent`: callback の実行後に発火し、レスポンスを差し替えられる

購読方法は [extending.md](extending.md)。

## WordPress との境界

WordPress に依存するのは次の役割だけ。

- WordPress のフックを wp-resta のイベントに変換する（`WpKernelAdapter`）
- ルート定義を `register_rest_route()` に登録する（`RegisterRestRoutes`）
- `WP_REST_Request` / `WP_REST_Response` と wp-resta のリクエスト / レスポンスを相互に変換する
- アトリビュートで宣言されたフックを `add_action()` / `add_filter()` に登録する（`HookProvider`）

ルートの callback や利用者のサービスが WordPress の関数を呼ぶのは自由。

## 互換性ポリシー

- 1.0 未満のため、minor（0.y）の更新に破壊的変更を含むことがある。patch（0.y.z）は後方互換
- この文書に書かれた約束を変える変更は破壊的変更として扱い、CHANGELOG に代替手段とともに書く
- 非推奨化は `E_USER_DEPRECATED` で 1 minor 以上警告してから削除する
