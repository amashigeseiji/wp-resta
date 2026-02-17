# スキーマ定義ガイド

wp-restaでは、REST APIのレスポンススキーマを定義することで、自動的にOpenAPI 3.0仕様を生成し、Swagger UIでドキュメント化できます。

## 目次

- [スキーマ定義の3つの方法](#スキーマ定義の3つの方法)
- [ObjectType - オブジェクトスキーマ](#objecttype---オブジェクトスキーマ)
- [自動推論 - PHPDocから自動生成](#自動推論---phpdocから自動生成)
- [SCHEMA定数 - 完全な制御](#schema定数---完全な制御)
- [利用可能なJSON Schemaキーワード](#利用可能なjson-schemaキーワード)
- [ベストプラクティス](#ベストプラクティス)

---

## スキーマ定義の3つの方法

wp-restaでは、以下の3つの方法でスキーマを定義できます：

### 1. ObjectTypeクラス - 単一オブジェクト

型安全で保守性が高く、最もシンプルな方法です。

```php
class Post extends ObjectType {
    public function __construct(
        public int $ID,
        public string $post_title,
        public string $post_content,
    ) {}
}

// Routeで使用
public function callback(int $id): Post {
    return new Post(...);
}
```

### 2. PHPDocアノテーション - 配列の自動推論

ObjectTypeの配列やプリミティブ型の配列を返す場合、PHPDocから自動推論されます。

```php
/**
 * @return Post[]        // ObjectTypeの配列
 * @return string[]      // プリミティブ型の配列
 */
public function callback(): array {
    return [...];
}
```

### 3. SCHEMA定数 - 完全な制御

複雑なスキーマ（Union型、複雑なネスト、`oneOf`など）や、詳細な制約（`minItems`, `pattern`など）を定義する場合に使用します。

```php
public const SCHEMA = [
    'type' => 'array',
    'items' => ['type' => 'string'],
    'minItems' => 1,  // 詳細な制約
    'uniqueItems' => true,
];
```

---

## ObjectType - オブジェクトスキーマ

### 基本的な定義

コンストラクタプロモーションを使った定義が最もシンプルです：

```php
use Wp\Resta\REST\Schemas\ObjectType;

class User extends ObjectType {
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $bio = null,  // nullable
    ) {}
}
```

生成されるスキーマ：

```json
{
  "type": "object",
  "properties": {
    "id": {"type": "integer"},
    "name": {"type": "string"},
    "email": {"type": "string"},
    "bio": {"type": "string"}
  },
  "required": ["id", "name", "email"],
  "$id": "#/components/schemas/User"
}
```

### 型推論

以下のPHP型が自動的にJSON Schema型に変換されます：

| PHP型 | JSON Schema型 |
|-------|---------------|
| `int` | `integer` |
| `float` | `number` |
| `string` | `string` |
| `bool` | `boolean` |
| `array` | `array` |
| ObjectType継承クラス | `$ref` |

### metadata() - 追加情報の定義

`description`や`example`などの追加情報を定義できます：

```php
class User extends ObjectType {
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}

    public static function metadata(): array {
        return [
            'id' => [
                'description' => 'ユーザーID',
                'example' => 123,
            ],
            'name' => [
                'description' => 'ユーザー名',
                'example' => '山田太郎',
                'minLength' => 1,
                'maxLength' => 100,
            ],
            'email' => [
                'description' => 'メールアドレス',
                'format' => 'email',
                'example' => 'yamada@example.com',
            ],
        ];
    }
}
```

### カスタムスキーマID

デフォルトでは`#/components/schemas/クラス名`が使われますが、カスタマイズも可能：

```php
class User extends ObjectType {
    public const ID = '#/components/schemas/CustomUser';

    // ...
}
```

### ネストしたオブジェクト

他のObjectTypeを参照できます：

```php
class Author extends ObjectType {
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

class Post extends ObjectType {
    public function __construct(
        public int $id,
        public string $title,
        public Author $author,  // 他のObjectTypeを参照
    ) {}
}
```

生成されるスキーマ：

```json
{
  "type": "object",
  "properties": {
    "id": {"type": "integer"},
    "title": {"type": "string"},
    "author": {"$ref": "#/components/schemas/Author"}
  }
}
```

---

## 自動推論 - PHPDocから自動生成

### 単一オブジェクトを返す

`callback`の戻り値の型がObjectTypeのサブクラスの場合、自動的にスキーマが推論されます：

```php
class GetPost extends AbstractRoute {
    public function callback(int $id): Post {
        return new Post(...);
    }
}
```

**SCHEMA定数は不要です！**

### プリミティブ型を返す

`callback`の戻り値の型がプリミティブ型（`string`, `int`, `bool`, `float`）の場合、自動的にスキーマが推論されます：

```php
class GetMessage extends AbstractRoute {
    public function callback(): string {
        return 'Hello, World!';
    }
}
```

生成されるスキーマ：

```json
{
  "type": "string"
}
```

**対応するプリミティブ型**:
- `string` → `"type": "string"`
- `int`, `integer` → `"type": "integer"`
- `float`, `double` → `"type": "number"`
- `bool`, `boolean` → `"type": "boolean"`

**nullable型（`?string`など）**:

```php
class GetOptionalMessage extends AbstractRoute {
    public function callback(): ?string {
        return null;
    }
}
```

生成されるスキーマ：

```json
{
  "anyOf": [
    {"type": "string"},
    {"type": "null"}
  ]
}
```

### 配列を返す

PHPDocを使って配列の要素型を指定すると、自動的にスキーマが推論されます：

#### ObjectTypeの配列

**パターン1: `Type[]`形式**

```php
class GetPosts extends AbstractRoute {
    /**
     * @return Post[]
     */
    public function callback(): array {
        return array_map(fn($wp_post) => new Post(...), $posts);
    }
}
```

**パターン2: `array<Type>`形式**

```php
/**
 * @return array<Post>
 */
public function callback(): array {
    return [...];
}
```

**パターン3: `array<string, Type>`形式**

```php
/**
 * @return array<string, Post>
 */
public function callback(): array {
    return ['featured' => $post1, 'recent' => $post2];
}
```

生成されるスキーマ：

```json
{
  "type": "array",
  "items": {
    "$ref": "#/components/schemas/Post"
  }
}
```

#### プリミティブ型の配列

**対応するプリミティブ型**: `string`, `int`, `integer`, `float`, `double`, `bool`, `boolean`

**パターン1: `type[]`形式**

```php
class GetTags extends AbstractRoute {
    /**
     * @return string[]
     */
    public function callback(): array {
        return ['WordPress', 'REST API', 'PHP'];
    }
}
```

**パターン2: `array<type>`形式**

```php
/**
 * @return array<int>
 */
public function callback(): array {
    return [1, 2, 3];
}
```

**パターン3: `array<int, type>`形式**

```php
/**
 * @return array<string, float>
 */
public function callback(): array {
    return ['price' => 29.99, 'tax' => 2.40];
}
```

生成されるスキーマ（例: string[]の場合）：

```json
{
  "type": "array",
  "items": {
    "type": "string"
  }
}
```

### use文の自動解決

短縮名（`Post`）は自動的に完全修飾名（`Wp\Resta\REST\Example\Schemas\Post`）に解決されます：

```php
use Wp\Resta\REST\Example\Schemas\Post;

class GetPosts extends AbstractRoute {
    /**
     * @return Post[]  // ← 自動的に完全修飾名に解決される
     */
    public function callback(): array {
        return [...];
    }
}
```

以下の形式に対応：
- 基本形式: `use Foo\Bar\Baz;`
- エイリアス: `use Foo\Bar\Baz as Qux;`
- グループuse: `use Foo\Bar\{Baz, Qux};`

### 自動推論の対応範囲

自動推論は以下に対応しています：
- **ObjectTypeのサブクラス**（単一オブジェクトまたは配列）
- **プリミティブ型**（`string`, `int`, `bool`, `float`, および nullable型）
- **プリミティブ型の配列**（`string[]`, `int[]`, `array<bool>`など）

以下のケースではSCHEMA定数が必要です：

- Union型（`Post|string`など、ただし nullable型 `?string` は対応済み）
- 複雑なネスト構造（オブジェクトの中に配列、など）
- `oneOf`/`anyOf`/`allOf`（nullable型以外）
- `additionalProperties`などの高度な機能
- 詳細な制約（`minItems`, `maxItems`, `pattern`, `format`など）

→ 詳細は「[SCHEMA定数 - 完全な制御](#schema定数---完全な制御)」を参照

---

## SCHEMA定数 - 完全な制御

`SCHEMA`定数を使うと、OpenAPI仕様のあらゆる機能を使用できます。

### プリミティブ型の配列（明示的な定義）

プリミティブ型の配列は`@return string[]`などのPHPDocで自動推論されますが、より詳細な制御（例: `minItems`, `maxItems`, `uniqueItems`など）が必要な場合はSCHEMA定数を使用できます：

```php
class GetTags extends AbstractRoute {
    protected const ROUTE = 'tags';

    public const SCHEMA = [
        'type' => 'array',
        'items' => ['type' => 'string'],
        'minItems' => 1,
        'maxItems' => 10,
        'uniqueItems' => true,
    ];

    /**
     * @return string[]
     */
    public function callback(): array {
        return ['WordPress', 'REST API', 'PHP'];
    }
}
```

### 複数の型（Union型）

```php
class GetResource extends AbstractRoute {
    public const SCHEMA = [
        'oneOf' => [
            ['$ref' => '#/components/schemas/Post'],
            ['type' => 'string'],
            ['type' => 'null'],
        ],
    ];

    public function callback(int $id): Post|string|null {
        // ...
    }
}
```

### 複雑なネスト構造

```php
class GetGroupedPosts extends AbstractRoute {
    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'featured' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/Post'],
            ],
            'recent' => [
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/Post'],
            ],
        ],
    ];

    public function callback(): array {
        return [
            'featured' => [...],
            'recent' => [...],
        ];
    }
}
```

### additionalPropertiesの使用

```php
class GetMetadata extends AbstractRoute {
    public const SCHEMA = [
        'type' => 'object',
        'additionalProperties' => ['type' => 'string'],
    ];

    public function callback(): array {
        return [
            'key1' => 'value1',
            'key2' => 'value2',
            // 動的なキー
        ];
    }
}
```

### allOf/anyOfの使用

```php
class GetExtendedPost extends AbstractRoute {
    public const SCHEMA = [
        'allOf' => [
            ['$ref' => '#/components/schemas/Post'],
            [
                'type' => 'object',
                'properties' => [
                    'view_count' => ['type' => 'integer'],
                    'likes' => ['type' => 'integer'],
                ],
            ],
        ],
    ];

    // ...
}
```

### 数値の範囲指定

```php
class GetScore extends AbstractRoute {
    public const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'score' => [
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 100,
            ],
        ],
    ];

    // ...
}
```

---

## 利用可能なJSON Schemaキーワード

`metadata()`や`SCHEMA`定数で指定できる主なキーワード：

### 文字列関連

```php
'email' => [
    'type' => 'string',
    'format' => 'email',       // email, uri, uuid, date, date-time など
    'pattern' => '^[a-z]+$',   // 正規表現
    'minLength' => 5,          // 最小文字列長
    'maxLength' => 100,        // 最大文字列長
    'enum' => ['draft', 'published', 'archived'],  // 列挙型
]
```

### 数値関連

```php
'age' => [
    'type' => 'integer',
    'minimum' => 0,            // 最小値
    'maximum' => 120,          // 最大値
    'exclusiveMinimum' => 0,   // 排他的最小値
    'multipleOf' => 5,         // 倍数
]
```

### 配列関連

```php
'tags' => [
    'type' => 'array',
    'items' => ['type' => 'string'],
    'minItems' => 1,           // 最小要素数
    'maxItems' => 10,          // 最大要素数
    'uniqueItems' => true,     // 要素の一意性
]
```

### オブジェクト関連

```php
'metadata' => [
    'type' => 'object',
    'properties' => [...],
    'required' => ['id'],
    'additionalProperties' => false,  // 追加プロパティを許可しない
]
```

### スキーマ組み合わせ

```php
'value' => [
    'oneOf' => [              // いずれか1つ
        ['type' => 'string'],
        ['type' => 'integer'],
    ],
    'anyOf' => [...],         // いずれか1つ以上
    'allOf' => [...],         // すべて
    'not' => [...],           // 否定
]
```

### その他

```php
'status' => [
    'type' => 'string',
    'default' => 'draft',      // デフォルト値
    'deprecated' => true,      // 非推奨フラグ (OpenAPI)
    'readOnly' => true,        // 読み取り専用 (OpenAPI)
    'writeOnly' => true,       // 書き込み専用 (OpenAPI)
    'title' => 'Status',       // タイトル
    'description' => '...',    // 説明
    'example' => 'draft',      // 例示値
    'examples' => ['draft', 'published'],  // 複数の例示値
]
```

---

## ベストプラクティス

### ✅ DO: シンプルなケースは自動推論を使う

```php
// Good: ObjectType + 自動推論
class GetPost extends AbstractRoute {
    public function callback(int $id): Post {
        return new Post(...);
    }
}
```

```php
// Good: ObjectTypeの配列
/**
 * @return Post[]
 */
public function callback(): array {
    return [...];
}
```

### ✅ DO: 複雑なケースはSCHEMA定数を使う

```php
// Good: プリミティブ型の配列
public const SCHEMA = [
    'type' => 'array',
    'items' => ['type' => 'string'],
];
```

### ✅ DO: コンストラクタプロモーションと名前付き引数を使う

```php
// Good: 簡潔で読みやすい
return new Post(
    ID: $wp_post->ID,
    post_title: $wp_post->post_title,
    post_content: $wp_post->post_content,
);
```

### ✅ DO: metadata()で追加情報を定義する

```php
// Good: 型定義とメタデータが分離
class User extends ObjectType {
    public function __construct(
        public string $email,
    ) {}

    public static function metadata(): array {
        return [
            'email' => [
                'format' => 'email',
                'example' => 'user@example.com',
            ],
        ];
    }
}
```

### ✅ DO: スキーマクラスはクリーンに保つ

```php
// Good: データ構造の定義のみ
class Post extends ObjectType {
    public function __construct(
        public int $ID,
        public string $post_title,
    ) {}
}

// Route側でマッピング
class GetPost extends AbstractRoute {
    public function callback(int $id): Post {
        $wp_post = get_post($id);
        return new Post(
            ID: $wp_post->ID,
            post_title: $wp_post->post_title,
        );
    }
}
```

### ✅ DO: nullable型を適切に使う

```php
// Good: nullableを明示
public function __construct(
    public int $id,              // 必須
    public ?string $bio = null,  // オプション
) {}
```

これにより、`bio`は自動的に`required`から除外されます。

### ❌ DON'T: 自動推論できないケースで無理にObjectTypeを使わない

```php
// Bad: string[]のためだけにObjectTypeを作る
class StringList extends ArrayType { /* ... */ }
```

```php
// Good: SCHEMA定数を使う
public const SCHEMA = [
    'type' => 'array',
    'items' => ['type' => 'string'],
];
```

---

## 選択のガイドライン

### ObjectType + 自動推論を使うべき場合

- ✅ 単一のオブジェクトを返す
- ✅ ObjectTypeのサブクラスの配列を返す
- ✅ ネストしたObjectTypeを返す

### SCHEMA定数を使うべき場合

- ✅ プリミティブ型の配列（`string[]`, `int[]`など）
- ✅ Union型（`Post|string`）
- ✅ `oneOf`/`anyOf`/`allOf`が必要
- ✅ `additionalProperties`などの高度な機能が必要
- ✅ 複雑なネスト構造
- ✅ OpenAPI仕様の全機能を活用したい

---

## トラブルシューティング

### スキーマが推論されない

**原因**: PHPDocのクラス名が解決できない

**解決策**: use文を追加するか、完全修飾名を使用

```php
// 解決策1: use文を追加
use Wp\Resta\REST\Example\Schemas\Post;

/** @return Post[] */
```

```php
// 解決策2: 完全修飾名を使用
/** @return \Wp\Resta\REST\Example\Schemas\Post[] */
```

### プロパティがrequiredにならない

**原因**: nullable型になっている

**解決策**: `?`を外す

```php
// requiredにならない
public ?int $id;

// requiredになる
public int $id;
```

### プリミティブ型の配列が推論されない

**原因**: 自動推論はObjectTypeのみ対応

**解決策**: SCHEMA定数を使用

```php
public const SCHEMA = [
    'type' => 'array',
    'items' => ['type' => 'string'],
];
```

---

## まとめ

- **シンプルなケース**: ObjectType + 自動推論
- **複雑なケース**: SCHEMA定数
- **保守性**: 型推論により、PHPの型とスキーマが自動的に同期
- **柔軟性**: metadata()やSCHEMA定数で詳細な定義も可能

詳細な実装例は`src/REST/Example/`ディレクトリを参照してください。
