# 認可

ログイン状態や capability でルートの利用を制限する書きかた。保証される挙動は [spec.md](spec.md)「認可」にある。手本は `src/REST/Example/Routes/Drafts.php`（アトリビュート）と `src/REST/Example/Routes/Secret.php`（callback の中の判定）。

## アトリビュートで宣言する

ログインしているか、特定の capability を持っているかで制限したい操作には、そのメソッドにアトリビュートを付けます。`permission_callback` の書きかたとの対応は [wordpress.md](wordpress.md) にあります。

```php
use Wp\Resta\REST\Attributes\Authenticated;
use Wp\Resta\REST\Attributes\Permission;

class Drafts extends AbstractRoute
{
    // 何も付けなければ公開
    public function get(): array
    {
        // ...
    }

    #[Permission('edit_posts')]   // 未認証は 401、edit_posts が無ければ 403
    public function post(string $title): array
    {
        // ...
    }
}
```

- `#[Authenticated]` は、ログイン済みであることだけを求めます。未認証は 401 です
- `#[Permission('edit_posts')]` は `current_user_can('edit_posts')` に当たります。未認証は 401、capability が無ければ 403 です
- 付ける場所は操作のメソッド（`callback()` / `get()` / `post()` など）です。クラスに付けても効きません。静的なルート（`body` プロパティ）を許可制にするときは、callback を書きます
- アトリビュートを付けた操作は、OpenAPI に `security` が出ます。フロントエンドを書く人は、どの操作にログインが要るかを仕様から読めます。必要な capability までは出ません

## リクエストの値を見て判定する

「この投稿を編集できる人だけ」のように、リクエストの値に依る判定は、アトリビュートでは書けません。callback の中で確かめます。`Principal` を callback の引数に宣言すると、現在のユーザーを受け取れます。

```php
use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\Attributes\Authenticated;
use Wp\Resta\REST\Exception\ForbiddenException;

#[Authenticated]   // 未認証は 401
public function put(int $id, string $title, Principal $principal): array
{
    if (!$principal->can('edit_post', $id)) {
        throw new ForbiddenException();      // 403
    }
    // ...
}
```

- ログイン済みかどうかは値に依らないので、`#[Authenticated]` で宣言します。OpenAPI に `security` が出るのは、アトリビュートを付けた操作だけです
- 拒否は例外で表します。`ForbiddenException` は 403、`UnauthorizedException` は 401 になります
- `Principal` を使わない Route は、引数に宣言しなくてかまいません

`Principal` は WordPress の現在のユーザーに対応し、`can()` は `current_user_can()` と同じ引数を取ります。cookie + nonce でも Application Passwords でも、WordPress が認証した結果がそのまま見えます。callback が呼ばれるのは URL変数の検証が通った後なので、`$id` には型変換済みの値が入っています。

複数のパラメータにまたがる検証や DB を引く検証も、callback の中に書きます。

```php
use Wp\Resta\REST\Exception\InvalidParameterException;

public function callback(int $from = 0, int $to = 0): array
{
    if ($from > $to) {
        throw new InvalidParameterException('from', 'from must be <= to');   // 400
    }
    // ...
}
```

## 認証方式を変えるとき

WordPress のユーザーに対応づかない独自の認証を使う場合は、`Principal` の実装を書いて `dependencies` で差し替えます。アトリビュートも、callback が引数で受け取る `Principal` も、差し替えた実装になります。

OpenAPI の `security` が指す認証方式は、何も書かなければ WordPress 標準の cookie + nonce（`X-WP-Nonce` ヘッダ）です。Application Passwords を使うサイトや、`Principal` を差し替えたサイトは、設定の `securitySchemes` に実際の方式を書きます。

```php
(new Wp\Resta\Resta)->init([
    'dependencies' => [
        Principal::class => ApiKeyPrincipal::class,
    ],
    'securitySchemes' => [
        'apiKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Api-Key'],
    ],
]);
```

- 書いたものが、そのまま OpenAPI の `components.securitySchemes` に出ます（アトリビュートを付けた操作が 1 つも無ければ、出ません）。省略時の cookie + nonce に足されるのではなく、置き換えます。cookie + nonce も使うなら、両方書きます
- 複数書くと、「どれか 1 つで通る」として並びます
- 空の配列を書くと、`security` は出ません
- **`Principal` を差し替えたら、`securitySchemes` も書きます。** 2 つの設定はつながっていないので、書き忘れても、OpenAPI は cookie + nonce のまま出ます
