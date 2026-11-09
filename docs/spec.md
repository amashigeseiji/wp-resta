# wp-resta 仕様

wp-resta が利用者に約束することを書く。ここに書いてあることは依存してよく、変えるときは破壊的変更として CHANGELOG に載る。ここに書いていない挙動は実装の詳細であり、patch リリースで変わりうる。

使いかたは README、拡張方法は [extending.md](extending.md)、スキーマ定義は [SCHEMA.md](SCHEMA.md)、テストの書きかたは [testing.md](testing.md)。

この文書は 3 つの部分からなる。

- **目的**: 「ルーティング定義」と「HTTP 実行のパイプライン」。wp-resta で何を宣言でき、リクエストがどう処理されるか。利用者はまずここを読む
- **機構**: 「DI」「イベント」「設定」。目的を成り立たせている仕組み
- **ポリシー**: 「WordPress との境界」と「互換性ポリシー」。wp-resta を開発し、維持するうえでの約束

## 設計原則

- **wp-resta は DSL である。** ルートクラスの宣言から、登録・検証・OpenAPI・TypeScript を導出する。利用者に WordPress REST API の語彙（`register_rest_route()` の引数、`permission_callback`、`WP_Error`）を直接書かせない
- **ルートは WordPress 無しでテストできる。** ルートの実行は `RestaRequestInterface` だけに依存する
- **WordPress との境界は薄い変換層に限定する。** WordPress の型・関数・フックを wp-resta の型に変換する箇所以外で WordPress に依存しない
- **プロセスの状態は WordPress が持つ。** WordPress のフックを起点にした状態を wp-resta は管理しない

## ルーティング定義

### ルート

- `AbstractRoute` を継承したクラス 1 つがエンドポイント 1 つ。設定で指定したディレクトリから自動で収集される
- REST API のルートは `{namespace}/{route}`。`namespace` は設定の `routeDirectory` で、`route` は `ROUTE` 定数で宣言し、省略するとクラス名から決まる。ルートの前に付く `/wp-json/` などのプレフィックスは WordPress が決める
- HTTP メソッドは `getMethods()` で宣言する。既定は GET

### URL パラメータ

- `ROUTE` の `[name]` はパスパラメータ。それ以外の宣言済みパラメータはクエリパラメータ
- パラメータは名前と型（string / integer）を宣言する。省略可能なパラメータは `?` を付けて宣言し、callback 引数のデフォルト値が使われる
- 型に加えて正規表現で制約を宣言できる。正規表現は値全体に照合される
- 同名のパスパラメータとクエリパラメータがあればパスパラメータが優先される
- 空文字は省略ではなく値として扱う

### 宣言から導出されるもの

- ルートの登録と入力の検証。宣言していない制約は検証されない
- OpenAPI 定義。レスポンスの形は callback の戻り値型かスキーマクラスから導出され、`#[RouteMeta]` の説明とタグが載る。`#[Envelope]` のルートは包まれた形で出力される
- TypeScript の型定義。OpenAPI と同じ宣言から生成される

## HTTP 実行のパイプライン

リクエストは次の段階を順に通る。どの段階で止まっても、エラーの形は「エラーレスポンス」のとおり。ルートインスタンスの状態はリクエストをまたいで持ち越されない。

### 認証

- 認証（cookie + nonce、Application Password など）は WordPress が行う。wp-resta は関与しない

### バリデーション

- 宣言された制約を満たさない入力は 400 になり、callback は呼ばれない

### 認可

- ルート単位の認可は既定で全リクエストを許可する。認可を宣言する手段と、バリデーションとの順序は 0.12.0 で決める（#61）

### callback

- ルートが `callback` メソッドを持つとき、リクエストごとに呼ばれる
- callback の引数は、宣言済みの URL パラメータなら名前で、それ以外のクラス型なら DI コンテナから解決される。`WP_REST_Request` と `RestaRequestInterface` は解決できる
- callback の戻り値がレスポンスボディになる

### レスポンス

- `RestaResponseInterface` を返せばステータスとヘッダも含めてそのまま使われる
- `$this->response($data, $status, $headers)` でステータスとヘッダを指定したレスポンスを作れる
- `#[Envelope]` を付けたルートは、ボディが `{ "data": ..., "meta": ... }` に包まれる
- `RouteInvocationEvent` の購読者はレスポンスを差し替えられる（「イベント」）

### エラーレスポンス

- ボディは WordPress コアの `WP_Error` と同じ形（`code` / `message` / `data.status`）
- 入力の不正は 400。callback から `HttpException` を投げると、そのステータスとコードで返る
- callback が投げたその他の例外は 500 になり、内容は握り潰さずログに残る。`WP_DEBUG` 有効時はボディにも載る

## DI

- クラス型はコンストラクタの型宣言から自動で解決される
- インターフェースは設定の `dependencies` で実装クラスか factory を対応づける
- コンストラクタインジェクションはルート生成時、callback 引数の解決はリクエスト時

## イベント

- `wp.init`: WordPress の `init` で発火する
- `RouteInvocationEvent`: callback の実行後に発火し、レスポンスを差し替えられる

購読方法は [extending.md](extending.md)。

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

## WordPress との境界

WordPress に依存するのは次の役割だけ。

- WordPress のフックを wp-resta のイベントに変換する（`WpKernelAdapter`）
- ルート定義を `register_rest_route()` に登録する（`RegisterRestRoutes`）
- `WP_REST_Request` / `WP_REST_Response` と wp-resta のリクエスト / レスポンスを相互に変換する
- アトリビュートで宣言されたフックを `add_action()` / `add_filter()` に登録する（`HookProvider`）

これは wp-resta 本体の制約であって、利用者の制約ではない。ルートの callback や利用者のサービスが WordPress の関数を呼ぶのは自由。

## 互換性ポリシー

- 1.0 未満のため、minor（0.y）の更新に破壊的変更を含むことがある。patch（0.y.z）は後方互換
- この文書に書かれた約束を変える変更は破壊的変更として扱い、CHANGELOG に代替手段とともに書く
- 非推奨化は `E_USER_DEPRECATED` で 1 minor 以上警告してから削除する
