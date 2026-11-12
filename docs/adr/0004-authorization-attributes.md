# 0004. 許可制の操作を `#[Authenticated]` / `#[Permission]` で宣言する

- 状態: 採用（0.13 で実装）
- 日付: 2026-11-12
- 関連: [#116](https://github.com/amashigeseiji/wp-resta/issues/116)、[0003](0003-remove-validate-authorize.md)

## 背景

`authorize()` メソッドを削除する（[0003](0003-remove-validate-authorize.md)）。そのままでは、「この操作は許可制である」という意図を宣言する手段が無くなる。

認可のデフォルトは「何も無い」で、許可制にしたい操作だけが、その意図を表明する。

## 決定

WordPress の `permission_callback` の標準的な書きかたに、そのまま対応させる。

| `permission_callback` の書きかた | アトリビュート |
|---|---|
| `__return_true`（公開） | 何も付けない |
| `is_user_logged_in` | `#[Authenticated]` |
| `fn() => current_user_can('edit_posts')` | `#[Permission('edit_posts')]` |
| `fn($req) => current_user_can('edit_post', $req['id'])` | 扱わない。callback の中で書く |

```php
#[Permission('edit_posts')]
public function post(string $title): PostBody
```

- `#[Authenticated]` は `Principal::isAuthenticated()` を、`#[Permission($capability)]` は `Principal::can($capability)` を聞く。`Principal` の interface は変えない
- 付ける場所は、操作のメソッド（`callback()` / `get()` / `post()` など）。クラスには付けない
- 未認証は 401、認証済みで capability が無ければ 403
- 宣言から、OpenAPI の操作ごとの `security` と 401 / 403 を導出する
- 独自の認証は、これまでどおりコンテナの bind で `Principal` の実装を差し替える

## 採らなかった案

- **`#[Authorize(can: 'edit_posts')]`。** 中身は同じ。`Authorize` は「認可せよ」という指示にも読める。`Permission` は `permission_callback` と同じ語で、必要な権限の記述として読める
- **ユーザーの種類を enum で名指しする（`#[Only(WpUser::Editor)]`）。** WordPress の role には、構造としての階層が無い。標準の role の capability が入れ子になっているのは、初期設定の慣習である。capability で判定すると「Administrator も Editor に当てはまる」という暗黙の包含が出る。role 名の完全一致での判定は、WordPress が非推奨にしている。WordPress のモデルで確実に言えるのは capability だけなので、それをそのまま書かせる
- **`#[Deny]`。** `#[Only]` を採らないので不要
- **`Principal` を拡張した独自の型で、許可の中身を表明する。** 型が「通してよいか」を答えるメソッドが、interface に要る
- **capability を複数書く。** 「全部持つ」と「どれかを持つ」の規約が要る。必要になったら足す

## 後回しにしたもの

**対象つきの capability**（`#[Permission('edit_post', 'id')]` のように、パラメータが指すリソースに対する権限）。特定のリソースを操作するルートでは、これが正確な表明になる。たとえば Author は `edit_posts` を持つが、他人の投稿は編集できない。

入れるには、次を決める必要がある。

- 認可が検証済みのパラメータに依存するので、検証と認可の順序を約束することになる
- パラメータを文字列の名前で指すことになる
- 存在しないリソースが、404 ではなく 403 になる

それまでは、`Principal` をコンストラクタで受け、callback の中で `$this->principal->can('edit_post', $id)` を確かめる。

## 帰結

- ルートに WordPress の capability の名前が直接出る。アプリケーションの言葉で名前を付けたい場合は、開発者が定数を定義して使う
- 追加だけの変更
