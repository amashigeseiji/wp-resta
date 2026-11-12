# 0002. callback の定義: 戻り値は body、status とヘッダは `ResponseContext`、引数は 3 種類だけ

- 状態: 採用（0.13 で実装）
- 日付: 2026-11-12
- 関連: [#99](https://github.com/amashigeseiji/wp-resta/issues/99)

## 背景

callback の引数と戻り値の型を、そのままスキーマにしたい。0.12 の callback には、それを妨げるものがあった。

- 戻り値が body のことも、`RestaResponseInterface`（`$this->response()`）のこともある。戻り値型が body の型に定まらない
- 引数に、URL パラメータ、Request、`WP_REST_Request`、コンテナで解決する任意のサービスが混ざる。引数の一覧が、パラメータの宣言として読めない
- クラス型の引数を、型より先に変数名で解決していた。パラメータと同じ名前の変数を使えない

## 決定

### 戻り値

- 戻り値型は body の型に確定する
- `$this->response()` と、callback が `RestaResponseInterface` を返す経路を削除する。返したら `LogicException`

### status とヘッダ

- `ResponseContext` という interface を新設する。メソッドは `status(int): void` と `header(string, string): void`
- 実行者が `ResponseContext` を作り、callback の後でレスポンスを組み立てる
- エラーは `HttpException` を投げる。投げる前に `ResponseContext` に書いたヘッダは捨てる
- ルートの既定のヘッダは `HEADERS` 定数

### 引数

引数に書けるのは次の 3 種類だけ。

- パラメータ（ビルトイン型と enum）
- Request（`RestaRequestInterface`、またはそれを実装するクラス）
- `ResponseContext`

クラス型の引数は型で解決する。変数名は任意。

- 引数での `WP_REST_Request` を削除する。必要なルートは、引数を `WpRestaRequest` で宣言し、そこから取り出す（`getWpRequest()`。名前は仮）。このメソッドは実クラスにだけ置き、`RestaRequestInterface` には足さない
- 引数でのサービスの注入（コンテナ解決）を削除する。サービスはコンストラクタで受ける
- 非推奨の期間は挟まない

## 採らなかった案

- **引数を `Context` オブジェクト 1 つにまとめる。** Request と `ResponseContext` の 2 つで足り、役割も分かれている
- **`RestaRequestInterface` に `getWpRequest()` を足す。** WP に依存しない interface に WP の型が入る
- **`ResponseContext` を interface の無い final クラスにする。** 拡張を閉じる理由が無い（[design.md](../design.md)）
- **Request をコンテナから解決する仕組みを新しく作る。** 独自の Request を使いたい場合は `RequestHandler` を差し替えれば足りる。そのために `RequestHandler` の `final` を外す
- **非推奨にしてから削除する。** `$this->response()` は 0.12 で入ったばかりで、利用者がいない

## 帰結

- callback の戻り値型と引数の型から、OpenAPI のスキーマをそのまま導出できる
- 引数の規則が閉じる。今後、引数で受け取れるものを増やすときは、Request か `ResponseContext` を通じて行う
