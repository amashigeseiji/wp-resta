# 0003. `validate()` / `authorize()` を削除し、`Principal` は必要なクラスだけが知る

- 状態: 採用（0.13 で実装）
- 日付: 2026-11-12
- 関連: [#114](https://github.com/amashigeseiji/wp-resta/issues/114)、[#113](https://github.com/amashigeseiji/wp-resta/issues/113)

## 背景

`RouteInterface` が `validate(BoundParameters)` と `authorize(RestaRequestInterface, BoundParameters, Principal)` を要求していた。

- どちらも既定は「何もしない」の任意の機能で、interface で実装を強制する項目ではない
- `RequestHandler` は、`authorize()` に渡すためだけに `Principal` を持っていた。`RequestHandler` と `RouteInterface` の両方が `Principal` に依存し、ルートごとに差し替えることもできない
- 検証 → 認可という順序は `RequestHandler` の行の並びで決まっていて、構造的な根拠が無かった

### ここに至った経緯

次の連鎖が起きていた。

1. **元の問題は小さかった。** `RouteInterface::permissionCallback()` の形では、認可の実装を書けなかった
2. それを解くために、`authorize()` と `Principal` を入れた（#61）
3. 「認可が受け取る値は、検証済みか未検証か」という問いが出た。これは決めたほうがよい問題である。検証済みのほうが、利用する側の開発者に優しい
4. **ここで取り違えた。** 「検証の後に認可する」を、デフォルトではなく仕様として spec.md に書いた
5. 仕様になったので、保守する必要が生じた。`RequestHandler` が順序を固定した
6. `RequestHandler` が順に呼ぶために、`validate()` と `authorize()` が `RouteInterface` の public メソッドになった

解くべき問題は 1 だけだった。3 の答えは「検証済みの値を渡すのがデフォルト」で足りた。そこから「順序は仕様である」「`RouteInterface` にメソッドが必要である」という結論は出てこない（[design.md](../design.md)「仕様とデフォルトを区別する」）。

考え方として、システムは検証や認可を強制執行しない。提供するのは、意図を表明する場と、そのデフォルトの解釈である（[design.md](../design.md)「システムが担保するもの」）。

## 決定

- `validate()` と `authorize()` のメソッドを、`RouteInterface` からも `AbstractRoute` からも削除する
- 実行前にシステムが行う検査は、callback の引数宣言から導いた検証だけになる
- `RequestHandler` は `Principal` を持たない。`Principal` を使うルートは、コンストラクタで受ける
- 複数のパラメータにまたがる検証と、実行時の値に依存する許可（「この投稿を編集できる人だけ」など）は、callback の中で書いて `HttpException` を投げる
- 「この操作は許可制」という意図は、アトリビュートで表明する（[0004](0004-authorization-attributes.md)）
- 検証と認可の順序は、仕様として約束しない

## 採らなかった案

メソッドを残したまま、誰が実行するかを変える案を検討していた。メソッド自体が不要になったので、どれも選ぶ必要が無くなった。

- **検証と認可を操作（`Operation`）に持たせる。**
- **イベントディスパッチャーで実行する。** 購読者の登録が漏れると検査が飛ぶ。順序の表明にもならない
- **ステートマシンで順序を表明する。**
- **ルートクラスを Reflection で読み、`validate` / `authorize` という名前のメソッドがあれば実行する。**

`Principal` の扱いでは、次の案を採らなかった。

- **コンストラクタでの `Principal` の宣言を「許可制」と読む。** `Principal` を使うが公開のルート（ログイン中なら全文、未ログインなら抜粋）と区別できない

## 帰結

- `RouteInterface` からメソッドが 2 つ減る。`authorize()` / `validate()` をオーバーライドしているルートは、書き換えが要る
- テストでは、`RequestHandler` に `Principal` を渡す必要が無くなる。`Principal` を使うルートのテストは、ルートのコンストラクタに `TestPrincipal` を渡す
- 「実行前検査」は、システムの中に名前のある概念として存在せず、開発者が自分の検査を足す場所も無い。この問題は残る（#113）
