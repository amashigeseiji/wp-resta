# フロントエンドから使う（TypeScript の型）

Route クラスの宣言から出る OpenAPI を使って、フロントエンドの TypeScript の型を生成する手順。

wp-resta が出すのは OpenAPI までで、TypeScript の型は生成しない。型やクライアントの生成は、OpenAPI を読む標準のツールに任せる。

## 1. OpenAPI を手に入れる

方法は 2 つある。

### 動いている WordPress から取得する

`SwaggerHook` を有効にしていれば、`/rest-api/schema` が OpenAPI の JSON を返す（[README.md](../README.md)）。

```bash
curl -s http://localhost:8080/rest-api/schema -o openapi.json
```

### WordPress を起動せずに書き出す

フロントエンドのビルドや CI のように、WordPress が動いていない場所で使う。

```bash
php vendor/tenjuu99/wp-resta/scripts/generate-openapi.php <config-file> [output-file] [server-url]
```

| 引数 | 内容 | 既定 |
|---|---|---|
| `config-file` | `Resta::init()` に渡すのと同じ配列を返す PHP ファイル | 必須 |
| `output-file` | 出力先 | `openapi.json` |
| `server-url` | `servers` に書く REST API の URL | `/wp-json/` |

読むのは `routeDirectory`、`schemaDirectory`、`dependencies`、`autoloader`。フックやアダプタは登録しない。

WordPress を読み込まないので、次のものがあると失敗する。そのときは、動いている WordPress から取得する。

- 設定ファイルが WordPress の定数や関数を使っている（`ABSPATH`、`plugin_dir_path()` など）。パスは `__DIR__` から書く
- Route クラスや、注入される Service のコンストラクタが、WordPress の関数を呼んでいる

wp-resta 自体のリポジトリでは、`composer generate:openapi` がサンプルのルート（`src/REST/Example/`）の OpenAPI を書き出す。

## 2. 型を生成する

[openapi-typescript](https://openapi-ts.dev/) で、OpenAPI から型定義を生成する。

```bash
npx openapi-typescript openapi.json -o src/api.d.ts
```

`paths` という型が生成され、URL と HTTP メソッドから、パラメータとレスポンスの型を引ける。

## 3. 型を使って呼び出す

[openapi-fetch](https://openapi-ts.dev/openapi-fetch/) は、生成した `paths` を使う fetch のラッパー。URL、パラメータ、レスポンスに型が付く。Astro でも Next.js でも同じように書ける。

```ts
import createClient from 'openapi-fetch';
import type { paths } from './api';

const client = createClient<paths>({ baseUrl: 'https://example.com/wp-json' });

const { data, error } = await client.GET('/example/post/{id}', {
    params: { path: { id: 1 } },
});
```

`#[Envelope]` を付けたルートは、OpenAPI に `{data, meta}` の形で出る（[envelope.md](envelope.md)）。生成される型も包まれた形になるので、上の例の本体は `data.data` にある。

## ほかのツール

OpenAPI を読むツールなら、どれでも使える。

- [orval](https://orval.dev/): 型に加えて、fetch クライアントや TanStack Query のフックを生成する
- [openapi-zod-client](https://github.com/astahmer/openapi-zod-client): zod のスキーマを生成する。実行時にレスポンスを検証したいときに使う
