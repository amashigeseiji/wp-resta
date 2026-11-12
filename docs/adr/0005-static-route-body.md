# 0005. 静的なルートは `public readonly` の `body` で宣言する

- 状態: 採用（0.13 で実装）
- 日付: 2026-11-12
- 関連: [#105](https://github.com/amashigeseiji/wp-resta/issues/105)

## 背景

0.12 で、`$status` / `$body` / `$headers` への代入と、callback の無いルートを非推奨にした。0.13 では、プロパティを削除し、callback の無いルートを `LogicException` にすると予告していた。

経緯を見直すと、合意していたのは「callback の中での実行時の代入をなくす」ことだけだった。もとの問題（ルートの状態がリクエストをまたいで持ち越される）は、リクエストごとの `clone` ですでに止まっていた。それでも「状態を持つことが悪い」という一般論から、プロパティの削除と callback の必須化まで進めていた。callback の必須化には独立した理由が無く、プロパティの削除の巻き添えで決まっていた。「リクエストが来たら静的なデータを返す」ルートは、あってよい。

## 決定

callback は必須にしない。callback を持たないルートは、body を `public readonly` のプロパティで宣言し、コンストラクタで確定させる。

```php
class Version extends AbstractRoute
{
    public function __construct(public readonly VersionInfo $body) {}
}
```

- `body` という名前の public プロパティを、デフォルトの解釈対象にする
- `readonly` は型が必須なので、プロパティの型がそのままスキーマになる
- status は 200。既定のヘッダは `HEADERS` 定数

解釈の規則は次のとおり。

| ルートにあるもの | 解釈 |
|---|---|
| `callback()`、または `get()` / `post()` など | それを実行する |
| public の `body` プロパティだけ | その値を返す |
| どちらも無い | `LogicException` |
| 両方ある | `LogicException` |

`AbstractRoute` の `$body` / `$status` / `$headers` は削除する。callback の中での代入を検出する処理と、callback が無いときの非推奨の警告も削除する。

## 採らなかった案

- **callback を必須にする（0.12 の予告）。** 独立した理由が無い
- **`const BODY`。**
- **`callback()` を `body()` に改名する。** 中心のメソッドの名前を変える理由としては足りない
- **`body()` メソッドを統一した出口にして、その中で callback を解決する。** プロパティと callback の両方があるときに、レスポンスの型を 1 つに確定させるのが狙いだった。しかし `body()` が引数の解決と実行を担うことになり、ルートが実行者に戻る
- **両方あるときは callback を優先する。** プロパティの型と callback の戻り値型が違うと、OpenAPI に出す型が一意に決まらない

## 帰結

- 実行時の代入は、PHP がエラーにする。検査のコードで守る必要が無い
- 基底クラスに `$body` があると、子クラスで `readonly` として宣言し直せない（PHP の致命的エラー）。基底クラスのプロパティの削除は、この決定の前提でもある
- コンストラクタは、そのルートが呼ばれなくても、登録時に実行される。コンストラクタで重い処理をする静的なルートは、ほかのルートへのリクエストでもそれが走る。リクエストを待たずに決まる軽い値に使い、重い場合は callback で書く
