# トラブルシューティング

## ルートが登録されない（404）

- WordPress のパーマリンク設定が「基本」だと REST API が `?rest_route=` 形式になり、`/wp-json/` が動かない。「投稿名」などに変更する
- `routeDirectory` のディレクトリと名前空間プレフィックスが PSR-4 と一致しているか。`composer dump-autoload` を実行する
- ルートクラスが `AbstractRoute` を継承しているか
- ルート追加後に 404 のままなら `flush_rewrite_rules()`（プラグインの再有効化で実行される）

## URL パラメータが取れない

- `URL_PARAMS` のキー名と `callback` の引数名が一致しているか
- パスパラメータは `ROUTE` の `[name]` と `URL_PARAMS` の両方に必要
- `?name=` のような空文字は省略ではなく値。regex に一致しなければ 400、`'string'` ならそのまま届く

## DI で解決できない

- インターフェースは autowiring できない。`dependencies` に実装クラスか factory を bind する
- callback 引数のビルトイン型が `URL_PARAMS` に無いと `LogicException`。URL パラメータなら `URL_PARAMS` に足し、そうでなければ Service に包んでクラス型で受ける
- union 型・intersection 型の引数は解決できない

## テストが失敗する

- `TestRestaRequest` に渡す URL の namespace と `setNamespace()` が一致しているか
- 前のテストで `Container` に bind したものが残っていないか（[testing.md](testing.md)「Container のリセット」）
- WP 関数を呼ぶコードは Brain Monkey でモックしているか

## 401 / 403 が返る

- `authorize()` が投げている。`code` が `rest_not_logged_in` なら未認証、`rest_forbidden` なら capability 不足
- cookie 認証では `X-WP-Nonce` ヘッダが無いと WordPress が未認証として扱う（`wp_rest` の nonce）
- Unit テストで 401 になるなら `Principal` を bind していない。`TestPrincipal` を bind する（[testing.md](testing.md)）

## 500 が返る

- `error_log()` に `[wp-resta] Uncaught ...` が出ている。`WP_DEBUG` を有効にするとレスポンスにもメッセージと例外クラスが載る
- regex 自体が不正な場合もここに出る（`has invalid regex`）
