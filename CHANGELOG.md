# Changelog

wp-resta の変更履歴です。書式は [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に従います。バージョンは 1.0 未満のため [Semantic Versioning](https://semver.org/lang/ja/) の 0.y.z 運用で、minor（0.y）の更新に破壊的変更を含むことがあります。

v0.10.2 以前の履歴は [GitHub Releases](https://github.com/amashigeseiji/wp-resta/releases) を参照してください。

## [Unreleased]

0.12.0 で予定している内容は [milestone 0.12.0](https://github.com/amashigeseiji/wp-resta/milestone/1) を参照。

### 削除

- ステートマシン（`Wp\Resta\StateMachine\*`、`Kernel`、`KernelState`、`Lifecycle\Request`、`RequestState`、`RequestContext`）を撤去した（#60）。`rest_api_init` が同一プロセスで 2 回発火すると `registerRoutes` 遷移で例外になっていたのは、WordPress が所有するライフサイクルを一方向の状態機械に写し取っていたことが原因で、WordPress のフックは発火回数も順序も保証しないため状態を持たないイベント変換に戻した。あわせて、使われていなかった遷移イベントも消えた
  - 廃止イベント: `KernelState::boot` / `boot.guard` / `registerRoutes` / `registerRoutes.guard`、`RequestState::convert` / `invoke` / `respond` とその `.guard`
  - 代替: `KernelState::registerRoutes` → `wp.rest_api_init`（発火のたびに dispatch される。購読者が呼ばれる時点でルートは登録済み）。`RequestState::invoke` の after → `RouteInvocationEvent`。guard の代替はない
  - コンストラクタ変更: `RequestHandler(DispatcherInterface)`、`WpKernelAdapter(DispatcherInterface, Config)`。`WpKernelAdapter` を継承している場合は影響する
- `AbstractRoute::invoke()` を削除した（#79）。ルートは宣言だけを持ち、検証・認可・callback・`RouteInvocationEvent` の順序は WP 非依存の `RequestHandler::handle(RouteInterface, RestaRequestInterface)` が持つ。テストは `(new RequestHandler(new TestPrincipal(), new Dispatcher()))->handle($route, $request)` に書き換える。`Wp\Resta\Lifecycle\RequestHandler` は `Wp\Resta\REST\RequestHandler` に移り、コンストラクタは `(Principal, DispatcherInterface)` になった。`Lifecycle` 名前空間は無くなった
- `RouteInterface::permissionCallback()` と `AbstractRoute::permissionCallback()` を削除した（#61）。戻り値型が `string` 固定で、WordPress の `permission_callback` として呼ばれても拒否を表せず、オーバーライドしても常に許可になっていた。代替は `authorize()`。オーバーライドしていたルートは呼ばれなくなるだけでエラーにはならないので、`authorize()` に書き換える

### 追加

- callback のシグニチャからの URL パラメータ宣言（#79、#77）。`int` / `float` / `bool` / `string` / `array` / backed enum の引数は `URL_PARAMS` に書かなくても宣言になり、値は宣言した型に変換されて届く。`#[Param(pattern:, min:, max:, description:)]` で制約を足せ、配列の要素型は `@param string[]` から読む
- 同じ URL で HTTP メソッドごとに処理を分けられるようになった（#92）。ルートクラスに `get()` / `post()` / `put()` / `patch()` / `delete()` を書くと、それぞれが 1 つの操作（`Operation`: メソッド、callback、そのシグニチャから導出したパラメータ）になる。`callback()` は `getMethods()` の全メソッドに対する 1 操作の省略形としてそのまま使え、両方書くと `LogicException`。宣言していないメソッドのリクエストは 404（`rest_no_route`）。`RouteInterface` に `getOperation(string $method): ?Operation` / `getOperations(): list<Operation>`、`RestaRequestInterface` に `getMethod(): string` を追加。`TestRestaRequest` は第 3 引数でメソッドを指定できる
- `RestaResponseInterface::withHeaders(array): static`。ヘッダを上書きマージした複製を返す。`RouteInterface::getHeaders()` でルートの既定ヘッダを宣言し、エラーを含むどのレスポンスにも付く（レスポンス自身の同名が優先）。これまでは callback が `RestaResponseInterface` を直接返すと既定ヘッダが付かなかった
- 手続き的な検証のフック `validate(BoundParameters $params)`（#79）。型と正規表現の検証の後、`authorize()` の前に呼ばれる
- ルート単位の認可（#61）。`authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal)` をオーバーライドし、`UnauthorizedException`（401 `rest_not_logged_in`）か `ForbiddenException`（403 `rest_forbidden`）を投げて拒否する。順序はバリデーション → 認可 → callback で WordPress コアと同じ
- `Wp\Resta\Auth\Principal`。認証状態と capability を答えるインターフェースで、`Resta::init()` が WordPress の現在ユーザーに委譲する `WpPrincipal` を bind する。`dependencies` で差し替え可能。テスト用に `TestPrincipal`

### 変更

- `RouteInterface` から `getMethods()` / `getArgs()` が無くなり、`Operation` の `getMethods()` と `getParameters()->toCoreArgs()` になった（#92）。`AbstractRoute::getMethods()` は `callback()` の受け持つメソッドの宣言として残る。`register_rest_route()` には操作ごとのエンドポイントの配列を渡す。`SchemaInference::inferSchema()` は `RouteInterface` ではなく操作の `Callback` を受け取り、`SCHEMA` / `getSchema()` の優先は `ResponseSchema` が行う
- callback の引数に書いたビルトイン型の引数が、`URL_PARAMS` に無くても URL パラメータの宣言として扱われるようになった（#79）。これまでは `LogicException` で 500 になっていた。同名の `URL_PARAMS` があればそちらが優先される
- `ROUTE` の `[name]` に対応する宣言が無いと `LogicException` になる。これまでは `[name]` が文字どおりのパスとして登録されていた
- 宣言されたパラメータは、callback の引数で受け取るかどうかに関係なくすべて wp-resta が検証するようになった（#79）。これまでは callback の引数にあるものだけ wp-resta が検証し、それ以外は WordPress コアの検証に頼っていた
- `register_rest_route()` に渡す args が `'sanitize_callback' => null` を含むようになり、WordPress コアの型変換を止めた。型変換は wp-resta が行い、`RestaRequestInterface::getUrlParam()` はクライアントが送った値をそのまま返す。`WP_REST_Request::get_param()` から直接読んでいたコードには文字列のまま届く
- `callback` メソッドを持たないルート（`$body` だけを返す）は非推奨になった（`E_USER_DEPRECATED`）。0.13 で `LogicException` にする。`callback()` で body を返す形に書き換える
- WordPress コアに渡す `permission_callback` は常に `__return_true` になった（#61）。認可は `invoke()` の中で行われるので、`RouteInvocationEvent` の購読者には 401 / 403 のレスポンスも渡る
- `rest_api_init` が何度発火しても、そのたびにルートを登録し直すようになった（#60）。WordPress のテストフレームワーク（`WP_Test_REST_Controller_Testcase`）のように `$wp_rest_server` をテストごとに作り直す環境で、2 つ目以降のテストが致命的エラーになっていた
- 設定の `adapters` を省略すると `WpKernelAdapter` が使われる。これまでは明示しないとルートが登録されなかった
- `URL_PARAMS` の `'string'` が任意の文字列を受け取るようになった（#81）。これまでは `\w+` の省略形で、パスパラメータに置くと WordPress コアの照合（UTF-8 モード無し）で日本語などが 404 になり、クエリパラメータでは通るという食い違いがあった。パスパラメータは次の `/` までの任意の文字列、クエリパラメータは制約なしになる。英数字に絞りたいルートは `'\w+'` と明示する
- 上記に伴い、`'string'` / `'?string'` のクエリパラメータに空文字を渡すと 400 ではなく空文字のまま callback に届く。非空を要求するなら `'.+'` のように正規表現を書く
- `register_rest_route()` に渡す `'string'` の定義は `regex` が `null` になり、`pattern` を含まない
- パスパラメータの値がパーセントデコードされて callback に届くようになった。パーマリンクが有効な環境では WordPress がエンコードされたまま渡してくるため、`/hello/日本語` が `%E6%97%A5...` として届いていた。callback 側で `rawurldecode()` していたコードは二重デコードになるので外す。`?rest_route=` 形式や `rest_do_request()` で組み立てたリクエストの値は元からデコード済み・文字どおりなので変わらない
- `URL_PARAMS` に宣言したパラメータに `?name[]=x` のような配列が渡されたとき、`invoke()` が 400 を返す。これまでは callback の型宣言で `TypeError` になり 500 だった

### 修正

- サンプルの `SampleHook::addMetaToResponse()` が、配列を返す WordPress コアのルート（`/wp/v2/settings` など）で TypeError になっていた（#94）。`rest_request_after_callbacks` には callback の生の戻り値が渡るので、`WP_REST_Response` 以外はそのまま返す。ブロックエディタが `/wp/v2/settings` をプリロードするため、`SampleHook` を登録していると投稿編集画面が開けなかった
- `TestRestaRequest` がクエリ文字列を解釈するようになった（#84）。`'/myapi/items/123?name=test'` のように渡せる。同名はパスパラメータ優先
- callback の引数にインターフェース型を書くと `cannot resolve type` で 500 になっていた（#83）。`RestaRequestInterface` は `invoke()` に渡したリクエストがそのまま渡り、`dependencies` で bind したインターフェースは DI コンテナから解決される

### ドキュメント

- CHANGELOG.md を追加。0.11.0 以降の履歴を GitHub Releases のノートから転記
- AGENT.md を AI アシスタント向けの作業指示だけに整理し、`CLAUDE.md` から参照するようにした。保証する挙動は `docs/spec.md`、テストの書きかたは `docs/testing.md`、トラブルシューティングは `docs/troubleshooting.md` に分離

## [0.11.3] - 2026-11-09

URL 変数の検証まわりの不具合修正と、エラーレスポンスの整理。API の破壊的変更は含まない。

### 変更

既存のルート定義のまま、リクエストに対する応答が変わる可能性があるもの。

- URL 変数の入力不正が 400 になる（#55）。これまで `RuntimeException` が握り潰されて 500 や不定な応答になっていたものが、WordPress コアの `WP_Error` と同じ形（`code` / `message` / `data.status`）の 400 `rest_invalid_param` / `rest_missing_callback_param` になる
- regex が値全体に対して照合される（#56）。`'(ok|ng)'` に `zzz-ok-zzz` のような値が部分一致で通っていたケースは 400 になる。`^` `$` を自分で書く必要はない
- `'string'`（`\w+`）が日本語などの Unicode 文字を受け付ける（#66）。regex を UTF-8 モード（`u` 修飾子）で照合するようになったため。`.{n}` のような量指定子はバイト数ではなく文字数になる。不正な UTF-8 の値は 400。パスパラメータは WordPress コアがルートにマッチさせるため対象外
- callback の引数で受け取らない URL 変数も検証される（#66）。regex を `pattern` として `register_rest_route()` の args にも渡すので、WordPress コアが dispatch 前に検証する
- パスパラメータがクエリパラメータより優先される（#58）。`user/2?id=5` のような場合に `2` が使われる。このフィルタは URL 埋め込みパラメータを持つリクエストにのみ適用されるため、WordPress 内部の `set_param()` を消してしまう問題も直った

### 追加

- `AbstractRoute::response(mixed $data, int $status = 200, array $headers = [])`。`return $this->response(null, 404);` のように、プロパティへの代入なしでステータスとヘッダを指定できる。ヘッダはクラス宣言の `$headers` に上書きで合成される（#59 の手順 1。プロパティへの代入の非推奨化は 0.12.0 以降）
- callback から `Wp\Resta\REST\Exception\HttpException` を投げると、そのステータスとコードでレスポンスになる（#55）

### 修正

- callback が投げた例外を握り潰さず、`error_log()` に記録して 500 `rest_internal_error` を返す。`WP_DEBUG` 有効時はメッセージと例外クラス名もボディに含まれる（#55）
- regex に `/` を含めると常に失敗していた問題。区切り文字は regex に含まれない文字を選ぶので、`'[a-z/]+'` のように書ける。regex 自体が不正な場合は 500 にしてログに残す（#56）
- ルートがプロセス内でシングルトンなため、callback 内で `$this->status` などに代入した値が次のリクエスト（`/batch/v1`、`rest_do_request()` など）に持ち越される問題。リクエストごとに clone する（#57）
- OpenAPI スキーマで、parameters の `required` と `description` が定義を反映していなかった問題と、同一パスに複数メソッド（GET と POST など）があると後勝ちで上書きされていた問題（#63）
- TypeScript 生成で、インラインの object スキーマの `required` を見てプロパティに `?` を付ける（#64）
- `glob()` が `false` を返したときの `foreach` Warning（#65）
- 無意味な `ReflectionClass::setAccessible()` 呼び出しを削除（#67）

### ドキュメント

- README の `URL_PARAMS` の記述（`'?integer'` / `'?string'`、変数の展開例）を修正し、regex の仕様、省略と空文字の違い、パラメータの優先順位、エラーレスポンスの形、`response()` の使い方を追記（#62 ほか）
- `SwaggerHook` の `flush_rewrite_rules()` に関する注記を簡潔にした

## [0.11.2] - 2026-07-22

### 修正

- `AbstractRoute::invokeCallback()` で、URL 変数の値が `URL_PARAMS` の `regex` に一致しない場合、エラーにも既定値のフォールバックにもならず `$args` にキーが乗らないまま `invokeArgs()` が呼ばれていた問題（#49）
  - optional パラメータ: 不正な値が黙ってデフォルト値にフォールバックしていた
  - required パラメータ: `$args` にキーが無いまま呼ばれ `ArgumentCountError` 等の分かりにくい例外になっていた
  - 「required なのに値が無い」ケースと同様に、regex 不一致も `RuntimeException` を明示的に投げる。独自に厳しめの regex（特にフルアンカー `^...$`）を設定しているルートでは、これまで黙って通っていた不正な入力が明示的にエラーになる
  - 0.8 / 0.9 / 0.10 系にも同じ修正をリリース（v0.8.6 / v0.9.2 / v0.10.2）

## [0.11.1] - 2026-07-22

### 修正

- `Resta::init()` で config の `dependencies` にキーとして interface を渡すと、`assert(class_exists($interface))` が false になり、`zend.assertions` 有効環境で `AssertionError` になる問題。`interface_exists()` も許容する（#45）
  - 0.8 / 0.9 / 0.10 系にも同じ修正をリリース（v0.8.5 / v0.9.1 / v0.10.1）

## [0.11.0] - 2026-02-25

### 追加

- Route クラスの callback の return type からスキーマを推測する（#36）
- スキーマからフロントエンド向けの TypeScript 型と API クライアントを生成する CodeGen（#35）
- EventDispatcher。内部処理を StateMachine として記述し、状態の変化に対してイベントを発生させる（#38）
- 各 Route クラスに `#[Envelope]` を付けると、レスポンスが `{ "data": ..., "meta": ... }` の envelope 構造になる（#34）
- `flush_rewrite_rules()` をプラグインのアクティベーション時に実行する（#40）
- メタデータからアクション名を収集する（#42）

### 変更

- `RegisterRestRoutes` の routes オブジェクトを一階層にする（#39）
- ライセンスを AGPL にする（#41）

[Unreleased]: https://github.com/amashigeseiji/wp-resta/compare/v0.11.3...HEAD
[0.11.3]: https://github.com/amashigeseiji/wp-resta/compare/v0.11.2...v0.11.3
[0.11.2]: https://github.com/amashigeseiji/wp-resta/compare/v0.11.1...v0.11.2
[0.11.1]: https://github.com/amashigeseiji/wp-resta/compare/v0.11.0...v0.11.1
[0.11.0]: https://github.com/amashigeseiji/wp-resta/compare/v0.10.2...v0.11.0
