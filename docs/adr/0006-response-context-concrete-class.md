# 0006. `ResponseContext` は interface ではなく、final でない具象クラスにする

- 状態: 採用（0.13 で実装）
- 日付: 2026-11-17
- 関連: [#99](https://github.com/amashigeseiji/wp-resta/issues/99)、[ADR 0002](0002-callback-definition.md)（`ResponseContext` を interface とした部分を置き換える）

## 背景

ADR 0002 は、`ResponseContext` を interface にした。「interface の無い final クラスにする」案を、拡張を閉じる理由が無いとして退けたためである。

実装してみると、interface は拡張点として何も開けていなかった。

- 実行者（`RequestHandler`）が自分で実装を `new` し、callback の後で、interface に無い読み出しのメソッドを呼んでいた。別の実装を渡す経路が無い
- 経路が無いのに、callback の引数には `ResponseContext` の派生型を書けた。登録は通り、呼び出し時に `TypeError` になっていた
- callback を直接呼ぶテストを書く人は、`@internal` の実装クラスを使うか、偽物を自作するしかなかった

`ResponseContext` は、ステータスとヘッダを運ぶだけのオブジェクトである。callback の前後に処理を挟む仕組みを足すとしても、要るのは同じ書き込み先を持ち回ることで、別の実装ではない。

## 決定

`ResponseContext` を、final でない具象クラスにする。

- 書き込みは `status(int): void` と `header(string, string): void`。ADR 0002 のとおり
- 読み出しは `getStatus(): int` と `getHeaders(): array`。実行者と、callback を直接呼ぶテストが使う
- 引数なしで `new` できる
- callback の引数の型に書けるのは `ResponseContext` そのものだけ。継承したクラスを書いたルートは、登録時に `LogicException`

ADR 0002 の、これ以外の決定は変えない。

## 採らなかった案

- **interface のままにし、実装を DI コンテナから解決する。** 読み出しのメソッドも約束にする必要があり、`RequestHandler` がコンテナを知ることになる。差し替えたい利用者が具体的にいない
- **final にする。** 閉じる理由が無いのは ADR 0002 のときと同じ（[design.md](../design.md)）
- **読み出しのメソッドを callback から隠す。** そのために型を 1 つ持つほどの問題が起きていない。callback から読めるのは、自分が書いた値だけである

## 帰結

- 「callback は書くだけで、読むのは実行者」という役割の分け方は、型ではなく慣習になる
- Route クラスの作者が書く `ResponseContext $response` は、interface のときと変わらない
- 後で interface に戻すと、`new ResponseContext()` と書いたテストが壊れる
