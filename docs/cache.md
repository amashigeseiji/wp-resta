# キャッシュ

`#[Cache]` で `Cache-Control` と `ETag` を付ける書きかた。保証される挙動は [spec.md](spec.md)「レスポンス」にある。手本は `src/REST/Example/Routes/Posts.php`。

読み取り専用のルートに `#[Cache]` を付けると、未認証の GET の 200 レスポンスに `Cache-Control` と `ETag` が付きます。クライアントが `If-None-Match` で同じ ETag を送ってきたら、ボディ無しの 304 を返します。

```php
use Wp\Resta\REST\Attributes\Cache;

#[Cache(maxAge: 300)]
class Posts extends AbstractRoute
{
    // ...
}
```

`Cache-Control` は `public, max-age=300` になります。`public: false` で `private`、`sMaxAge:` で `s-maxage`、`staleWhileRevalidate:` で `stale-while-revalidate` を足せます。ETag はレスポンスボディから計算されるので、ボディが同じなら同じ値になります。

ログイン中のリクエストには付きません。WordPress がログイン中の REST リクエストに no-cache ヘッダを送るので、それがそのまま使われます。エラーレスポンスと GET 以外にも付きません。レスポンスが `Cache-Control` をすでに持っていれば（`$this->response($data, 200, ['Cache-Control' => 'no-store'])` など）、そちらが優先され、ETag も付きません。
