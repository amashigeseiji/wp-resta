# Changelog

wp-resta の変更履歴です。書式は [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に従います。バージョンは 1.0 未満のため [Semantic Versioning](https://semver.org/lang/ja/) の 0.y.z 運用で、minor（0.y）の更新に破壊的変更を含むことがあります。

v0.10.2 以前の履歴は [GitHub Releases](https://github.com/amashigeseiji/wp-resta/releases) を参照してください。

## [Unreleased]

0.12.0 で予定している内容は [milestone 0.12.0](https://github.com/amashigeseiji/wp-resta/milestone/1) を参照。

### 修正

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
