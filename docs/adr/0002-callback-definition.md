# 0002. callback の引数: パラメータと Request の 2 種類だけ。クラス型は型で解決する

- 状態: 採用（0.13 で実装）
- 日付: 2026-11-12（2026-11-17 に改訂。リリース前だったので、新しい ADR で置き換えずに書き換えた）
- 関連: [#99](https://github.com/amashigeseiji/wp-resta/issues/99)

## 背景

callback の引数の型を、そのままパラメータの宣言として読みたい。0.12 の callback には、それを妨げるものがあった。

- 引数に、URL パラメータ、Request、`WP_REST_Request`、コンテナで解決する任意のサービスが混ざる。引数の一覧が、パラメータの宣言として読めない
- クラス型の引数を、型より先に変数名で解決していた。パラメータと同じ名前の変数を使えない

## 決定

引数に書けるのは次の 2 種類だけ。

- パラメータ（ビルトイン型と backed enum）
- Request（`RestaRequestInterface`、またはそれを実装するクラス）

クラス型の引数は型で解決する。変数名は任意。

- それ以外の型の引数を持つルートは、登録時に `LogicException`。クラスや enum を含む union / intersection 型も同じ
- 引数での `WP_REST_Request` を削除する。必要なルートは、引数を `WpRestaRequest` で宣言し、`getWpRequest()` で取り出す。このメソッドは実クラスにだけ置き、`RestaRequestInterface` には足さない
- 引数でのサービスの注入（コンテナ解決）を削除する。サービスはコンストラクタで受ける
- 非推奨の期間は挟まない

## この ADR で決めないこと: 戻り値と、status・ヘッダの指定

改訂前のこの ADR は、戻り値についても決めていた。「戻り値は常に body にする。`$this->response()` と、callback が `RestaResponseInterface` を返す経路を削除する。status とヘッダは、引数で受け取る `ResponseContext` に書く」という内容である。実装してレビューする過程で、これを取り下げた。

- 削除の理由は「`Post|RestaResponseInterface` のような union になり、body の型がスキーマから消える」だった。これが言えているのは「body の型を静的に読めない包みは困る」までで、レスポンスを返すこと自体を禁じる理由にはなっていなかった
- 「body の型を宣言したレスポンスを返す」という案を、比べていなかった
- `ResponseContext` は、書き込んで返さないオブジェクトで、`$response` に書いて返すという一般的な期待とずれていた

戻り値まわりは 0.12 のまま残す。callback は body か `RestaResponseInterface` を返し、`$this->response()` も、`T|RestaResponseInterface` からのスキーマの推論も残る。0.12 のままで利用者ができないことは、いまのところ見つかっていない。OpenAPI のステータスが常に 200 になる件は [#110](https://github.com/amashigeseiji/wp-resta/issues/110) が扱う。

## 採らなかった案

- **引数を `Context` オブジェクト 1 つにまとめる。** パラメータの宣言が引数の一覧から読めなくなる
- **`RestaRequestInterface` に `getWpRequest()` を足す。** WP に依存しない interface に WP の型が入る
- **Request をコンテナから解決する仕組みを新しく作る。** 独自の Request を使いたい場合は `RequestHandler` を差し替えれば足りる。そのために `RequestHandler` の `final` を外す
- **非推奨にしてから削除する。** 引数でのサービスの注入は、コンストラクタで受ける形に機械的に直せる

## 帰結

- callback の引数の型から、パラメータの宣言をそのまま導出できる
- 引数の規則が閉じる。今後、引数で受け取れるものを増やすときは、Request を通じて行うか、この ADR を置き換える
- 引数の誤りは、リクエストが来る前（ルートの登録時）に分かる
