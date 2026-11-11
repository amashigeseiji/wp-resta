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
- REST API のルートは `{namespace}/{route}`。`namespace` は設定の `routeDirectory` で決まる。`route` は `ROUTE` 定数で宣言し、省略するとクラス名から決まる。ルートの前に付く `/wp-json/` などのプレフィックスは WordPress が決める

### 操作

- ルートは URL に属する宣言で、HTTP メソッドごとの処理は操作として分かれる。`get()` / `post()` / `put()` / `patch()` / `delete()` という名前のメソッドがそれぞれ 1 つの操作になり、同じ URL で GET と POST を分けられる
- `callback()` は、`getMethods()` で宣言したメソッド全部に対する 1 つの操作の省略形。`getMethods()` の既定は GET で、複数のメソッドは `'GET,POST'` のようにカンマ区切りで宣言する
- `callback()` と HTTP メソッド名のメソッドの両方を持つルートは `LogicException`
- 宣言していないメソッドのリクエストは 404 になる。HEAD の操作が無ければ GET の操作で応える
- URL パラメータの宣言は操作ごと。パスパラメータの宣言は操作をまたいで同じでなければならず、食い違えば `LogicException`

### URL パラメータ

- `ROUTE` の `[name]` はパスパラメータ。それ以外の宣言済みパラメータはクエリパラメータ
- `URL_PARAMS` では名前と型（string / integer）を宣言する。省略可能なパラメータは `?` を付けて宣言し、callback 引数のデフォルト値が使われる
- 操作のメソッド（`callback()` や `get()` など。以下 callback）の引数も宣言になる。`int` / `float` / `bool` / `string` / `array` / backed enum の引数は、`URL_PARAMS` に無くてもその名前のパラメータの宣言で、デフォルト値があれば省略可能。クラス型の引数は DI で解決され、宣言にはならない。同名の `URL_PARAMS` があればそちらが優先される
- 型で表せない制約（正規表現、最小値、最大値、説明）は `#[Param]` で宣言する。配列の要素型は docblock の `@param string[]` のように宣言する
- 値は宣言した型に変換されて callback に届く。`bool` は `true` / `false` / `1` / `0`、配列は `tags[]=a` の繰り返しとカンマ区切りの両方を受け取り、backed enum はそのインスタンスになる
- `ROUTE` の `[name]` には対応する宣言が必要で、パスパラメータにデフォルト値は付けられない
- 型に加えて正規表現で制約を宣言できる。正規表現は値全体に照合される。宣言しなければ型以外の制約はなく、`string` は任意の文字列を受け取る
- 同名のパスパラメータとクエリパラメータがあればパスパラメータが優先される
- パスパラメータの値はパーセントデコードされた状態で callback に届く
- 空文字は省略ではなく値として扱う。省略可能なパラメータに空文字を渡してもデフォルト値は使われず、宣言した制約に照合される

### 宣言から導出されるもの

- ルートの登録と入力の検証。宣言していない制約は検証されない
- OpenAPI 定義。path の下に操作ごとの operation が出る。レスポンスの形は操作の callback の戻り値型かスキーマクラスから導出され、`#[RouteMeta]` の説明とタグが載る。`#[Envelope]` のルートは包まれた形で出力される
- TypeScript の型定義。OpenAPI と同じ宣言から生成される

## HTTP 実行のパイプライン

リクエストは認証、操作の選択、バリデーション、認可、callback の順に段階を通る。バリデーションを通った入力だけが認可と callback に渡り、callback の結果がレスポンスになる。どの段階で止まっても、エラーの形は「エラーレスポンス」のとおり。ルートインスタンスの状態はリクエストをまたいで持ち越されない。

パイプラインは WordPress 無しで実行できる。`RequestHandler::handle(RouteInterface, RestaRequestInterface)` がその入口で、`TestRestaRequest` が URL 文字列からリクエストを作る（[testing.md](testing.md)）。ルートは宣言だけを持ち、実行の順序は `RequestHandler` が持つ。

### 認証

- 認証（cookie + nonce、Application Password など）は WordPress が行う。wp-resta は関与しない

### 操作の選択

- リクエストの HTTP メソッドに対応する操作が選ばれ、以降の段階はその操作の宣言で行われる。無ければ 404（`rest_no_route`）

### バリデーション

- 宣言された制約を満たさない入力は 400 になり、callback は呼ばれない。callback の引数で受け取るかどうかに関係なく、宣言されたパラメータはすべて検証される
- ルートは `validate(BoundParameters $params)` をオーバーライドして、型変換後の値に対する手続き的な検証を書ける。拒否は `HttpException` を投げる

### 認可

- ルートは `authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal)` をオーバーライドして認可を宣言する。既定は全リクエストを許可する
- 拒否は例外で表す。`UnauthorizedException` は 401、`ForbiddenException` は 403 になり、callback は呼ばれない
- `Principal` は認証状態（`isAuthenticated()`）と capability（`can()`）を答える。WordPress 上では現在のユーザーに対応し、認証方式（cookie + nonce、Application Passwords など）には依存しない。設定の `dependencies` で差し替えられる
- 認可はバリデーションの後に行われる。`authorize()` が受け取る `$params` は宣言した制約を満たし、型変換済みの値
- `validate()` / `authorize()` はルート単位で、受け取る `$params` は選ばれた操作の宣言で束縛した値。`authorize()` を操作ごとに分けるなら `$request->getMethod()` で分岐する

### callback

- 選ばれた操作の callback（`callback()` か `get()` などの HTTP メソッド名のメソッド）がリクエストごとに呼ばれる。どちらも持たないルートは非推奨で、次の minor で許可されなくなる
- callback の引数は、宣言済みの URL パラメータなら名前で型変換済みの値が、それ以外のクラス型なら DI コンテナから解決される。`RestaRequestInterface` は解決でき、`getMethod()` は HTTP メソッド、`getUrlParam()` はクライアントが送った値をそのまま返す（型変換はしない）
- WordPress の REST API から移行するコードのために、`WP_REST_Request` も callback 引数で受け取れる。新しく書くコードでは使わない
- callback の戻り値がレスポンスボディになる

### レスポンス

- `RestaResponseInterface` を返せばステータスとヘッダも含めてそのまま使われる
- `$this->response($data, $status, $headers)` でステータスとヘッダを指定したレスポンスを作れる
- ルートに宣言した既定ヘッダは、エラーを含むどのレスポンスにも付く。レスポンス自身が同名のヘッダを持てばそちらが優先される
- `RestaResponseInterface::withHeaders()` でヘッダを足した複製を作れる。購読者がレスポンスにヘッダを付けるときに使う
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
- `wp.rest_api_init`: WordPress の `rest_api_init` で発火する。WordPress が何度発火しても、そのたびに発火する。購読者が呼ばれる時点でルートは登録済み
- `RouteInvocationEvent`: callback の実行後に発火し、レスポンスを差し替えられる

購読方法は [extending.md](extending.md)。

## 設定

`Resta::init(array $config)` が受け取るキー。

| キー | 内容 |
|---|---|
| `routeDirectory` | `[ディレクトリ, 名前空間プレフィックス, REST namespace]` の組の配列。REST namespace を省略すると `default` |
| `schemaDirectory` | `[ディレクトリ, 名前空間プレフィックス]` の組の配列 |
| `dependencies` | DI の対応づけ |
| `hooks` | WordPress フックをまとめた `HookProvider` のクラス |
| `listeners` | イベントの購読者 |
| `adapters` | WordPress ライフサイクルの橋渡し。省略すると `WpKernelAdapter` が使われる |

## WordPress との境界

WordPress に依存するのは次の役割だけ。

- WordPress のフックを wp-resta のイベントに変換する（`WpKernelAdapter`）
- ルート定義を `register_rest_route()` に登録する（`RegisterRestRoutes`）
- `WP_REST_Request` / `WP_REST_Response` と wp-resta のリクエスト / レスポンスを相互に変換する
- WordPress の現在のユーザーを `Principal` として見せる（`WpPrincipal`）
- アトリビュートで宣言されたフックを `add_action()` / `add_filter()` に登録する（`HookProvider`）

これは wp-resta 本体の制約であって、利用者の制約ではない。ルートの callback や利用者のサービスが WordPress の関数を呼ぶのは自由。

本体が依存する WordPress の API は次のとおり。ここに無いものへの依存を増やすときは、対応バージョンへの影響を確認する。

- 関数: `add_action()`、`add_filter()`、`register_rest_route()`、`rest_url()`、`is_user_logged_in()`、`current_user_can()`、`__return_true()`
- クラス: `WP_REST_Request`、`WP_REST_Response`、`WP_HTTP_Response`、`WP_Error`
- フック: `init`、`rest_api_init`、`rest_request_parameter_order`
- `register_rest_route()` の引数: `args` の `type` / `required` / `pattern` / `sanitize_callback` / `validate_callback`、`permission_callback`
- 任意の `SwaggerHook` はこれに加えて管理画面とリライトルールの API を使う

## 互換性ポリシー

- PHP 8.2 以上、WordPress 6.1 以上で動作する。下限を上げるときは minor で行い、CHANGELOG に書く
- 1.0 未満のため、minor（0.y）の更新に破壊的変更を含むことがある。patch（0.y.z）は後方互換
- この文書に書かれた約束を変える変更は破壊的変更として扱い、CHANGELOG に代替手段とともに書く
- 非推奨化は `E_USER_DEPRECATED` で 1 minor 以上警告してから削除する
