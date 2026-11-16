# Route クラスの書きかた

URL とパラメータ、callback、HTTP メソッドごとの処理、エラーレスポンスの書きかた。保証される挙動は [spec.md](spec.md) にある。最初のルートの作りかたは [README](../README.md) にある。

実行できる手本は `src/REST/Example/Routes/` にある。`Hello.php`（`URL_PARAMS`）、`Items.php`（callback の引数と `#[Param]`）、`Notes.php`（GET と POST を分ける）。

## URL定義とURL変数

`myroute` が `functions.php` で定義した namespace ですが、 `helloworld` にはクラス名がそのまま利用されています。

URL 定義は `ROUTE` 定数を定義することで変更できます。ついでにURL変数も定義してみます。

```php
<?php
namespace MyREST\Routes;

use Wp\Resta\REST\AbstractRoute;

class HelloWorld extends AbstractRoute
{
    protected const ROUTE = 'hello/[name]';
    protected const URL_PARAMS = [
        'name' => 'string',
    ];

    public function callback(string $name) : string
    {
        return "Hello, {$name}!";
    }
}
```

次のURLが生成されるとおもいます。

```
$ curl http://example.com/wp-json/myroute/hello/amashige
```

`"Hello, amashige!"` と返ってくるとおもいます。

ROUTE定数のなかに `[var]` と `[]` で囲えばパスパラメータとして扱うことができます。

変数は次のようなパターンを許容します。

```php
    protected const URL_PARAMS = [
        'id' => 'integer',
        'id_not_required' => '?integer',
        'name' => 'string',
        'name_not_required' => '?string',
        'ok_or_ng' => '(ok|ng)',
        'first_name' => [
            'type' => 'string',
            'required' => false,
            'regex' => '[a-z]+'
        ],
    ];
```

`'string'` は任意の文字列、`'integer'` は整数を受け取り、`?` を付けると省略可能になります。それ以外の文字列は正規表現として、値全体に照合されます（`^` `$` を自分で書く必要はなく、`'[a-z/]+'` のように `/` を含めても構いません）。`'string'` を英数字だけに絞りたいなら `'\w+'` のように正規表現を書きます。

宣言したパラメータは、callback の引数で受け取るかどうかに関係なくすべて検証されます。regex は `register_rest_route()` の `args` に `pattern` としても渡されるので、WordPress の REST API discovery にも載ります。

`URL_PARAMS` を書かなくても、callback の引数の型がそのまま宣言になります。`int` / `float` / `bool` / `string` / `array` / backed enum が使え、デフォルト値があれば省略可能、無ければ必須です。型で表せない制約は `#[Param]` で足します。

```php
use Wp\Resta\REST\Attributes\Param;

protected const ROUTE = 'posts/[id]';

/**
 * @param string[] $tags
 */
public function callback(
    int $id,
    #[Param(min: 1, max: 100)] int $per_page = 10,
    Sort $sort = Sort::Asc,        // backed enum → asc / desc だけ受け付け、enum のインスタンスで届く
    bool $published = true,        // true / false / 1 / 0
    array $tags = [],              // ?tags[]=a&tags[]=b でも ?tags=a,b でも
    #[Param(pattern: '[a-z-]+')] string $slug = '',
): array
```

値は宣言した型に変換されて届きます（`'42'` → `42`、`'true'` → `true`）。同名の `URL_PARAMS` があればそちらが優先されます。

省略可能な変数は、URL に含まれなかった場合だけ callback のデフォルト値が使われます。`?name=` のように空文字で渡された値は省略とはみなさず、正規表現に一致しなければ 400 になります。

これらは、ROUTE定数に `user/[id]` のような形でURL埋め込み変数として定義されていればパスパラメータとして機能します。

パスパラメータとして利用されていないが `URL_PARAMS` に定義されている変数はクエリパラメータとして利用されます。

次のような例は、 `http://example.com/wp-json/myroute/user/2?name=tenjuu99` として展開されます。

```php
    protected const ROUTE = 'user/[id]';
    protected const URL_PARAMS = [
        'id' => 'integer',
        'name' => '?string',
    ];
```

これらの変数は callback メソッドで受け取ることができます。

パスパラメータとクエリパラメータに同じ名前の値がある場合（`user/2?id=5` など）、パスパラメータが優先されます。WordPress のデフォルトはクエリパラメータ優先ですが、wp-resta は `rest_request_parameter_order` フィルタでパスパラメータを先頭に移動しています。このフィルタは URL に埋め込みパラメータを持つリクエストにのみ適用されるため、`/wp/v2/posts/(?P<id>\d+)` のような WordPress コアのルートでもパスパラメータが優先されます。


## コールバック

`AbstractRoute` を継承したクラスが `callback` という名のメソッドを持っている場合、このメソッドを呼びだし、**戻り値をレスポンスの body にします**。body として返してよいのは、配列、文字列、オブジェクトなど、WordPress REST API がレスポンスとして扱える任意のデータです。戻り値型が、そのままレスポンスのスキーマになります（[SCHEMA.md](SCHEMA.md)）。

### 引数に書けるもの

`callback` の引数に書けるのは、次の 3 種類だけです。

| 引数 | 型 | 受け取るもの |
|---|---|---|
| パラメータ | `int` / `float` / `bool` / `string` / `array` / backed enum | URL のパラメータ。名前で対応し、型に変換された値が届く |
| リクエスト | `RestaRequestInterface` | 現在のリクエスト。ヘッダや、変換前の値を読む |
| レスポンスの書き込み先 | `ResponseContext` | ステータスとヘッダを書く |

リクエストと `ResponseContext` は型で決まるので、変数名は何でもかまいません。

これ以外の型（Service クラスなど）を引数に書くと、ルートの登録時に `LogicException` になります。Service はコンストラクタで受け取ります（[di.md](di.md)）。

### ステータスとヘッダを指定する

戻り値は body なので、ステータスとヘッダは戻り値では指定しません。引数で `ResponseContext` を受け取り、そこに書きます。PHP の `http_response_code()` や `header()` と同じで、書くだけでよく、返す必要はありません。

```php
use Wp\Resta\REST\Http\ResponseContext;

public function post(string $title, ResponseContext $response): Note
{
    $note = $this->service->create($title);
    $response->status(201);
    $response->header('Location', rest_url("myroute/notes/{$note->id}"));
    return $note;   // 返すのは body。$response は返さない
}
```

- `status()` を呼ばなければ 200 です
- エラーは `ResponseContext` ではなく、例外で返します（「エラーレスポンス」）。例外を投げると、それまでに `ResponseContext` に書いた値は使われません

どのレスポンスにも付けるヘッダは、`HEADERS` 定数で宣言します。エラーを含むすべてのレスポンスに付き、`ResponseContext` に同名のヘッダを書いた場合はそちらが勝ちます。

```php
protected const HEADERS = ['Cache-Control' => 'no-store'];
```

## 静的なルート

リクエストによって変わらない値を返すだけなら、callback を書かずに、応答を `public readonly` の `body` プロパティで宣言できます。手本は `src/REST/Example/Routes/SampleStatic.php` です。

```php
class Version extends AbstractRoute
{
    public readonly VersionInfo $body;

    public function __construct(VersionService $service)
    {
        $this->body = $service->current();
    }
}
```

- `body` の値が、ステータス 200 のレスポンスボディになります。既定のヘッダは `HEADERS` 定数で宣言します
- プロパティの型が、callback の戻り値型と同じようにスキーマになります。配列の要素の型は `@var Post[]` のように docblock で書けます
- `body` はコンストラクタで確定させます。`readonly` なので、あとから書き換えようとすると PHP がエラーにします
- callback（`callback()` や `get()` / `post()` など）と `body` の両方を書くことはできません。どちらも無いクラスも、ルートとして登録できません

コンストラクタは、そのルートが呼ばれなくても、ルートの登録時に実行されます。コンストラクタで DB のクエリのような重い処理をすると、ほかのルートへのリクエストでもそれが走ります。静的なルートは、リクエストを待たずに決まる軽い値に使い、重い処理は callback に書いてください。

## 同じ URL で HTTP メソッドごとに処理を分ける

`callback` の代わりに `get` / `post` / `put` / `patch` / `delete` という名前のメソッドを書くと、それぞれがその HTTP メソッドの処理になります。引数の宣言はメソッドごとに独立していて、`post` の引数は GET のリクエストでは要求されません。

```php
class Notes extends AbstractRoute
{
    protected const ROUTE = 'notes/[id]';

    public function get(int $id): array
    {
        return ['id' => $id];
    }

    public function post(int $id, string $title): array
    {
        return ['id' => $id, 'title' => $title];
    }
}
```

```
$ curl http://example.com/wp-json/myroute/notes/1
$ curl -X POST http://example.com/wp-json/myroute/notes/1 -d title=hello
$ curl -X DELETE http://example.com/wp-json/myroute/notes/1   # 404 rest_no_route
```

`callback` は、`getMethods()` で宣言したメソッド（既定は GET。`'GET,POST'` のようにカンマ区切りで複数）すべてを 1 つのメソッドで受ける書きかたです。`callback` と `get` などを同じクラスに書くことはできません。`[id]` のようなパスパラメータは、どのメソッドでも同じ型で宣言してください。

## エラーレスポンス

URL変数の値が regex に一致しない、または required な値が無い場合は 400 を返します。`authorize()` で拒否した場合は 401 または 403 を返します。ボディは WordPress コアの `WP_Error` と同じ形（`code` / `message` / `data.status`）です。

```json
{"code": "rest_invalid_param", "message": "Invalid parameter(s): id", "data": {"status": 400, "params": {"id": "id is invalid."}}}
```

callback が例外を投げた場合は `error_log()` に記録したうえで 500 の `rest_internal_error` を返します。`WP_DEBUG` が有効なときは例外のメッセージとクラス名もボディに含まれます。

任意のステータスで返したい場合は、callback から `Wp\Resta\REST\Exception\HttpException` を投げるとそのステータスとコードでレスポンスになります。

```php
throw new HttpException('Post not found', 404, 'rest_post_invalid_id');
```
