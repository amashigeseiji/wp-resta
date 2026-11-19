# Envelope

`#[Envelope]` でレスポンスを `data` / `meta` の形に包む書きかた。保証される挙動は [spec.md](spec.md)「レスポンス」にある。手本は `src/REST/Example/Routes/Post.php` と `Posts.php`。包まない例は `SimpleApi.php`。

wp-resta では、REST API レスポンスを統一的な構造でラップする**Envelope Pattern**をサポートしています。

Envelope Patternは、すべての API レスポンスを以下の構造で統一します：

```json
{
  "data": {
    // 実際のレスポンスデータ
  },
  "meta": {
    // メタデータ（オプション）
  }
}
```

## エンベロープパターンの使い方

エンベロープパターンを使うには、`#[Envelope]` 属性を Route クラスに追加します：

```php
<?php
namespace MyREST\Routes;

use Wp\Resta\REST\AbstractRoute;
use Wp\Resta\REST\Attributes\Envelope;

#[Envelope]
class Posts extends AbstractRoute
{
    protected const ROUTE = 'posts';

    // スキーマはデータ部分のみ定義
    public const SCHEMA = [
        'type' => 'array',
        'items' => [
            '$ref' => '#/components/schemas/Post'
        ],
    ];

    // 普通に配列を返すだけ（自動的にエンベロープでラップされる）
    public function callback(): array
    {
        $posts = get_posts(['post_type' => 'post']);
        return $posts;
    }
}
```

**EnvelopeHook は自動的に有効です**

`EnvelopeHook` はフレームワークに組み込まれており、設定不要で常に有効です。`#[Envelope]` 属性のないルートには適用されません（素通り）。包まれるのは成功（2xx）のレスポンスだけで、400 や 500 などのエラーは WordPress コアと同じ `WP_Error` の形のまま返ります。クライアントはステータスが 2xx なら `data` を、エラー（4xx / 5xx）なら `code` を見れば済みます。

**レスポンス：**
```json
{
  "data": [
    { "ID": 1, "post_title": "Hello World" },
    { "ID": 2, "post_title": "Sample Post" }
  ],
  "meta": {
    "processed_at": "2026-02-17 12:00:00",
    "plugin_version": "0.8.4",
    "request_route": "/myroute/posts"
  }
}
```

`#[Envelope]` 属性を追加すると、`callback()` メソッドで返した値が自動的に `data` プロパティにラップされます。OpenAPI スキーマもエンベロープ構造として自動生成されます。

## メタデータのカスタマイズ

`meta` プロパティは `RouteInvocationEvent` のリスナーで追加できます：

```php
<?php
namespace MyREST\Listeners;

use Wp\Resta\REST\Http\EnvelopeResponse;
use Wp\Resta\REST\RouteInvocationEvent;

class CustomMetaListener
{
    // RouteInvocationEvent は NamedEvent のサブクラスなので #[Listen] 不要
    public function addCustomMeta(RouteInvocationEvent $event): void
    {
        if (!$event->response instanceof EnvelopeResponse) {
            return;
        }

        $event->response = $event->response
            ->addMeta('processed_at', current_time('mysql'))
            ->addMeta('plugin_version', '0.8.4');
    }
}
```

設定ファイルに追加：

```php
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [ /* ... */ ],
    'listeners' => [
        \MyREST\Listeners\CustomMetaListener::class,
    ],
]);
```

EventDispatcher と listeners config の詳細は [extending.md](extending.md) を参照してください。

## OpenAPI スキーマ

エンベロープパターンを使用したルートは、OpenAPI スキーマでも自動的にエンベロープ構造（`type: object` with `data` and `meta` properties）として定義されます。
