# AGENT.md

wp-resta を開発する AI アシスタント向けの指示。保証される挙動は [docs/spec.md](docs/spec.md)、使いかたは [README.md](README.md) にあり、ここには書かない。

## プロジェクト

- WordPress 上で REST API を作る PHP 8.2+ のフレームワーク。`AbstractRoute` を継承したクラス 1 つがエンドポイント 1 つ
- ルートの定数・callback シグニチャ・アトリビュートから登録・検証・OpenAPI・TypeScript を導出する DSL
- ルートを WordPress 無しでテストできること、WP との境界を薄い変換層に閉じることを設計原則として守る（spec.md「設計原則」）

## 読む順

1. [docs/spec.md](docs/spec.md): 契約。挙動を変えるときは必ず読み、同じ PR で更新する
2. `src/REST/RequestHandler.php`: パイプライン（操作の選択 → `validate()` → `authorize()` → callback → `RouteInvocationEvent`）。`src/REST/AbstractRoute.php` は宣言（`getOperations()`、フック）だけを持つ
3. `src/REST/Example/Routes/`: 実装サンプル。`Sample.php`（パラメータ）、`Post.php`（スキーマ参照）、`Posts.php`（一覧）
4. 目的別: [docs/testing.md](docs/testing.md)、[docs/extending.md](docs/extending.md)（イベント、HookProvider）、[docs/SCHEMA.md](docs/SCHEMA.md)、[docs/troubleshooting.md](docs/troubleshooting.md)

## 構成

```
src/
  Resta.php                 init(): 設定、DI、Dispatcher、アダプタの組み立て
  Config.php                init() の設定キー
  DI/Container.php          シングルトンの DI コンテナ（autowiring）
  REST/
    AbstractRoute.php       ルート基底。宣言のみ: getOperations()（get()/post()… か callback() から導出）、validate() / authorize() フック、response()
    Operation.php           1 操作 = HTTP メソッド + Callback + RouteParameters。内部の値オブジェクト
    RequestHandler.php      パイプライン。WP 非依存。テストもこれを通す
    Callback.php            callback の定義。シグニチャを読む、resolve()（引数の束縛 + DI）、invoke()
    Parameter/              ParameterDefinition、RouteParameters（宣言。validate() で検証・型変換）、BoundParameters（値）、ParameterDefinitionBuilder
    RegisterRestRoutes.php  ルート収集と register_rest_route()。WP_REST_Request/Response の変換を呼ぶ。WP 境界
    Http/                   RestaRequestInterface、WpRestaRequest / WpRestaResponse（WP 境界）、TestRestaRequest
    Exception/              HttpException と 400 / 401 / 403 のサブクラス
    Attributes/             RouteMeta、Envelope
    Hooks/                  RouteInvocationEvent の組み込み購読者。EnvelopeHook（包む）、CacheHook（#[Cache] のヘッダと 304）
    Example/                サンプルのルートとスキーマ
  Auth/                     Principal、WpPrincipal（WP 境界）、TestPrincipal
  Kernel/WpKernelAdapter.php  WP フック → Dispatcher イベント（wp.init、wp.rest_api_init）。WP 境界
  EventDispatcher/          Dispatcher、Event、NamedEvent、#[Listen]
  Hooks/                    HookProvider（#[AddAction] / #[AddFilter]）、SwaggerHook
  OpenApi/                  OpenAPI 生成、スキーマ推論
  CodeGen/                  TypeScript 生成
tests/Unit, tests/Integration, tests/E2E   docs/testing.md 参照
```

## 作業ルール

- 応答・コメント・コミットメッセージ・PR は日本語。識別子と技術用語は英語のまま
- ブランチは `fix/<issue>-<slug>`、`feature/<slug>`、`release/<x.y.z>-bump`。`main` に直接コミットしない
- PR タイトルに issue 番号を含める。例: `URL_PARAMS の regex を pattern として WP コアにも渡す (#66)`
- 挙動を変える PR は `CHANGELOG.md` の `[Unreleased]` と `docs/spec.md` を同じ PR で更新する
- `docs/spec.md` には利用者が依存してよい約束だけを書く。実装の詳細（正規表現の修飾子、内部クラスの判定、フックの処理順など）は書かない。判断基準は「patch リリースで変えても利用者のコードが壊れないことは仕様ではない」
- 実装が spec.md と食い違っていたら、spec を実装に寄せるのではなく issue にして実装を直す。レビューで「実装はこうなっている」と指摘されたときの選択肢は「実装を直す」か「ドキュメントから落とす」で、「実装どおりに詳しく書く」は取らない。README に注意書きが増えるのは設計の歪みのサインとして扱う
- コミットメッセージは 1 行目で何を変えたかを言い切る。レビュー対応は `レビュー指摘の反映: ...`
- PR を出す前に `composer test` と `composer lint` を通す
- WP の関数・クラスへの依存を spec.md「WordPress との境界」に列挙した箇所の外に増やさない
- リリース計画は GitHub の milestone にある。`gh issue list --milestone <version>` で見る。設計の判断とその理由は issue のコメントに残す

## コマンド

```bash
composer test            # Unit + Integration（WP 不要）
composer test:e2e        # Docker 上の WP に対する E2E
composer test:coverage   # カバレッジ
composer lint            # PHPStan
composer generate:types  # TypeScript 型の生成
```

## ルートを追加するとき

- callback は薄く保つ。ロジックは Service クラスに置き、コンストラクタで DI する
- テストを必ず書く。`RequestHandler` に `TestRestaRequest` を渡して WP 無しに実行する（docs/testing.md）
- インターフェースを注入するなら `init()` の `dependencies` に bind する
- `#[RouteMeta]` で説明とタグを付け、戻り値型かスキーマクラスでレスポンスの形を宣言する

## 落とし穴

- `Container` はシングルトン。bind したテストは `tearDown()` でリセットする
- ルートインスタンスはプロセス内で共有される。`$status` / `$body` / `$headers` への代入は 0.12 で非推奨（`E_USER_DEPRECATED`）、0.13 でプロパティごと削除（#59）。`$this->response()` と `HEADERS` 定数を使う。callback の無いルート（`$body` だけ）は 0.12 で非推奨、0.13 で `LogicException`
- `callback` の引数のビルトイン型は `URL_PARAMS` に無くても URL パラメータの宣言になる。`ROUTE` の `[name]` に対応する宣言が無いと `LogicException`
- 同じ URL で GET / POST を分けるなら `get()` / `post()`。`callback()` と両方書くと `LogicException`。操作の `Callback` はインスタンスに束縛されるので、`clone` したルートでは導出し直す（`getOperations()` の owner チェック）
- WP のパーマリンク設定が「基本」だと `/wp-json/` が動かない
