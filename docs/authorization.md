# 認可

ログイン状態や capability でルートの利用を制限する書きかた。保証される挙動は [spec.md](spec.md)「認可」にある。手本は `src/REST/Example/Routes/Secret.php`。

ログインしているか、特定の capability を持っているかで利用を制限したいルートは、`authorize()` をオーバーライドします。既定はすべてのリクエストを許可します。

```php
use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\Exception\ForbiddenException;
use Wp\Resta\REST\Exception\UnauthorizedException;
use Wp\Resta\REST\Http\RestaRequestInterface;

use Wp\Resta\REST\Parameter\BoundParameters;

public function authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal): void
{
    if (!$principal->isAuthenticated()) {
        throw new UnauthorizedException();   // 401
    }
    if (!$principal->can('edit_post', $params->get('id'))) {
        throw new ForbiddenException();      // 403
    }
}
```

`Principal` は WordPress の現在のユーザーに対応し、`can()` は `current_user_can()` と同じ引数を取ります。cookie + nonce でも Application Passwords でも、WordPress が認証した結果がそのまま見えます。`authorize()` は URL変数の検証が通った後、`callback` の前に呼ばれるので、`$params` には型変換済みの値が入っています。

複数のパラメータにまたがる検証や DB を引く検証は、callback の中に書きます。callback が呼ばれるのは、型と正規表現の検証と `authorize()` が通った後です。

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

WordPress のユーザーに対応づかない独自の認証を使う場合は、`Principal` の実装を書いて `dependencies` で差し替えます。
