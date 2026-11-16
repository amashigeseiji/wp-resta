# DI

Route クラスに Service クラスなどを注入する書きかた。保証される挙動は [spec.md](spec.md)「DI」にある。

簡易なDIを用意しています。

基本的には autorwiring で、ほとんどの場合に設定なしで利用できます。
注入できるのはコンストラクタだけです。`callback` の引数には注入されません（引数はパラメータの宣言です。[routes.md](routes.md)「引数に書けるもの」）。

```php
// src/Lib/Foo.php
namespace MyREST\Lib;

class Foo
{
    private Bar $bar;

    public function __construct(Bar $bar)
    {
        $this->bar = $bar;
    }

    public function getBarString(): string
    {
        return $this->bar->get();
    }
}

// src/Lib/Bar.php
namespace MyREST\Lib;

class Bar
{
    public function get(): string
    {
        return 'bar';
    }
}

// src/Routes/Sample.php

namespace MyREST\Routes;

use MyREST\Lib\Foo;

class Sample extends AbstractRoute
{
    private Foo $foo;
    public function __construct(Foo $foo)
    {
        $this->foo = $foo;
    }

    public function callback()
    {
        return $this->foo->getBarString();
    }
}
```

`Bar::get` の返り値が解決されます。

上記は DI の autowiring で解決可能ですが、interface を注入する場合は自動で解決できません。この場合は設定が必要になります。
設定は、初期化コードに `dependencies` を渡すことができます。


```diff
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [
    ...
    ],
    'schemaDirectory' => [
    ...
    ],
+   'dependencies' => [
+       PSR\Log\LoggerInterface::class => MonoLog\Logger::class,
+   ],
]);
```

また、autowiring 機構はクラスやインターフェースの依存しか解決しないため、コンストラクタがクラスやインターフェース以外の値を受けている場合は解決できません。
この場合は関数を使ってください。

```diff
(new Wp\Resta\Resta)->init([
    'routeDirectory' => [
    ...
    ],
    'schemaDirectory' => [
    ...
    ],
    'dependencies' => [
        PSR\Log\LoggerInterface::class => MonoLog\Logger::class,
+       WP_Query::class => function () {
+           return new WP_Query(['post_type' => 'post']);
+       }
    ],
]);
```
