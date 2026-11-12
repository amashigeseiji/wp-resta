# WordPress との関係

WordPress の REST API を `register_rest_route()` で書いたことがある人に向けて、wp-resta がそれとどう対応するかを書く。保証される挙動は [spec.md](spec.md) にある。

認可のアトリビュートなど、一部は 0.13 の書きかたで説明している。

## 出どころは `register_rest_route()`

wp-resta は、`register_rest_route()` のシンタックスシュガーとして始まった。Route クラスはクラスの形をしているが、やっていることは `register_rest_route()` に渡す内容を宣言することである。`callback` という名前も、そこから来ている。

同じエンドポイントを、両方の書きかたで並べる。

```php
add_action('rest_api_init', function () {
    register_rest_route('myroute', '/posts/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => function (WP_REST_Request $request) {
            $id = (int) $request['id'];
            return ['id' => $id, 'title' => get_the_title($id)];
        },
        'permission_callback' => fn () => current_user_can('edit_posts'),
        'args' => [
            'id' => ['type' => 'integer', 'required' => true],
        ],
    ]);
});
```

```php
class Posts extends AbstractRoute
{
    protected const ROUTE = 'posts/[id]';

    #[Permission('edit_posts')]
    public function callback(int $id): array
    {
        return ['id' => $id, 'title' => get_the_title($id)];
    }
}
```

## 対応

| `register_rest_route()` | Route クラスでの書きかた |
|---|---|
| `$namespace` | 設定の `routeDirectory` で決まる |
| `$route` | `ROUTE` 定数。`(?P<id>\d+)` の代わりに `[id]` と書く |
| `methods` | `getMethods()`、または `get()` / `post()` などのメソッド |
| `callback` | `callback()` |
| `args` | callback の引数の型、`URL_PARAMS` 定数、`#[Param]` |
| `schema` | callback の戻り値型、`SCHEMA` 定数、`getSchema()` |
| `permission_callback` | `#[Authenticated]`、`#[Permission]` |

`permission_callback` の書きかたとの対応は次のとおり。

| `permission_callback` | wp-resta |
|---|---|
| `'__return_true'` | 何も付けない |
| `'is_user_logged_in'` | `#[Authenticated]` |
| `fn () => current_user_can('edit_posts')` | `#[Permission('edit_posts')]` |
| `fn ($request) => current_user_can('edit_post', $request['id'])` | 対応するアトリビュートは無い。callback の中で確かめる |

`AbstractRoute` は、書かなくてよいものをデフォルトで埋めている。`ROUTE` を省略すればクラス名から決まり、`getMethods()` を書かなければ GET になる。

## WordPress に任せていること

wp-resta は、次のことを自分ではやらない。

- **認証。** cookie と nonce、Application Passwords などは WordPress が処理する。wp-resta は、その結果（現在のユーザー）を見るだけである
- **ルーティング。** リクエストを受けて、どのルートに渡すかを決めるのは WordPress の REST サーバーである。wp-resta は `register_rest_route()` でルートを登録し、callback として呼ばれる
- **capability の判定。** `#[Permission('edit_posts')]` は `current_user_can('edit_posts')` に行き着く。role と capability の設定は WordPress のものがそのまま効く

callback の中や、そこから使うサービスで、WordPress の関数を呼ぶのは自由である。

## wp-resta が足していること

- **ルートをどこに書くかの規約。** Route クラス 1 つがエンドポイント 1 つで、設定で指定したディレクトリに置く。ルートの定義を探し回らなくてよい
- **型による検証。** callback の引数に `int $id` と書けば、整数であることの検証と型変換が行われる。`validate_callback` や `sanitize_callback` を書かなくてよい
- **OpenAPI。** Route クラスの宣言から、エンドポイントの仕様が生成される。フロントエンドのコードは、その仕様に依存できる

## `register_rest_route()` と違うところ

対応から意図して離れている箇所が 2 つある。

- **callback の引数と戻り値。** `register_rest_route()` の callback は `WP_REST_Request` を受け取り、`WP_REST_Response` か `WP_Error` を返す。wp-resta の callback は、型の付いたパラメータを受け取り、レスポンスの body を返す。引数と戻り値の型が、そのまま検証とスキーマになるからである。エラーは例外（`HttpException`）を投げる。レスポンスの形は `WP_Error` を返したときと同じになる
- **`permission_callback` に当たるものが、関数ではない。** 許可制であることを、アトリビュートで宣言する。宣言から OpenAPI に 401 / 403 を出せるからである。リクエストの値を見て判定する場合は、callback の中で確かめる
