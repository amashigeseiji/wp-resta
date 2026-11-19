# Route クラスの書きかた

URL とパラメータ、callback、HTTP メソッドごとの処理、エラーレスポンスの書きかた。保証される挙動は [spec.md](spec.md) にある。最初のルートの作りかたは [README](../README.md) にある。

実行できる手本は `src/REST/Example/Routes/` にある。`Hello.php`（`URL_PARAMS`）、`Posts.php`（callback の引数と `#[Param]`）、`Drafts.php`（GET と POST を分ける）。

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
    Order $order = Order::Desc,    // backed enum → asc / desc だけ受け付け、enum のインスタンスで届く
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

`AbstractRoute` を継承したクラスが `callback` という名のメソッドを持っている場合、このメソッドを呼びだしてレスポンスの body にします。body として返してよいのは、配列、文字列、オブジェクトなど、WordPress REST API がレスポンスとして扱える任意のデータです。また、`Wp\Resta\REST\Http\RestaResponseInterface` を返した場合にはそのまま利用されます。

`callback` メソッドの引数は、URL変数を受けとることができます。 `URL変数` に `id` を定義していれば `callback(int $id)` と定義して問題ありません。

また、簡易な DI があるため、解決可能なクラスを引数に定義すると受け取ることができます。ランタイムに値が決まるもの(例えば `WP_REST_Request` )などはコンストラクタインジェクションでは値が決まっていませんが、コールバックが呼び出される時点では確定しているので、利用できます。

ステータスコードやヘッダを指定したい場合は、`$this->response()` でレスポンスを作って返します。

```php
public function callback(int $id): RestaResponseInterface
{
    $post = get_post($id);
    if ($post === null || $post->post_status !== 'publish') {
        return $this->response(null, 404);
    }
    // WP_Post をそのまま返すと post_password などの内部フィールドも JSON に出るので、返すフィールドを選ぶ
    return $this->response(['id' => $post->ID, 'title' => $post->post_title], 200, ['Cache-Control' => 'no-store']);
}
```

ルートの既定ヘッダは `HEADERS` 定数で宣言します。callback がどう返してもすべてのレスポンス（エラーを含む）に付き、同名ならレスポンス側の値が勝ちます。

```php
protected const HEADERS = ['Cache-Control' => 'no-store'];
```

## 静的なルート

リクエストによって変わらない値を返すだけなら、callback を書かずに、応答を `public readonly` の `body` プロパティで宣言できます。手本は `src/REST/Example/Routes/Version.php` です。

```php
class Version extends AbstractRoute
{
    protected const ROUTE = 'version';

    /**
     * @var array{version: string}
     */
    public readonly array $body;

    public function __construct()
    {
        $this->body = ['version' => '1.0'];
    }
}
```

- `body` の値が、ステータス 200 のレスポンスボディになります。既定のヘッダは `HEADERS` 定数で宣言します
- プロパティの型が、callback の戻り値型と同じようにスキーマになります。配列の形は `@var array{version: string}` や `@var Post[]` のように docblock で書けます。スキーマクラスを型にすることもできます
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

URL変数の値が regex に一致しない、または required な値が無い場合は 400 を返します。認可のアトリビュートや callback の中の判定で拒否した場合は 401 または 403 を返します（[authorization.md](authorization.md)）。ボディは WordPress コアの `WP_Error` と同じ形（`code` / `message` / `data.status`）です。

```json
{"code": "rest_invalid_param", "message": "Invalid parameter(s): id", "data": {"status": 400, "params": {"id": "id is invalid."}}}
```

callback が例外を投げた場合は `error_log()` に記録したうえで 500 の `rest_internal_error` を返します。`WP_DEBUG` が有効なときは例外のメッセージとクラス名もボディに含まれます。

任意のステータスで返したい場合は、callback から `Wp\Resta\REST\Exception\HttpException` を投げるとそのステータスとコードでレスポンスになります。

```php
throw new HttpException('Post not found', 404, 'rest_post_invalid_id');
```
