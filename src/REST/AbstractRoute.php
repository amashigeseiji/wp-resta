<?php
namespace Wp\Resta\REST;

use ReflectionProperty;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use LogicException;
use Wp\Resta\REST\Parameter\ParameterDefinitionBuilder;

abstract class AbstractRoute implements RouteInterface
{
    protected const ROUTE = '';
    protected const URL_PARAMS = [];
    public const SCHEMA = null;

    /** HTTP メソッド名のメソッドが操作の宣言になる */
    private const OPERATION_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /**
     * ルートの既定ヘッダ。エラーを含むどのレスポンスにも付き、同名ならレスポンス側が勝つ
     *
     * @var array<string, string>
     */
    protected const HEADERS = [];

    /** @var list<Operation>|null */
    private ?array $operations = null;

    /** 操作を導出したインスタンス。callback はインスタンスに束縛されるので、clone には使い回せない */
    private ?AbstractRoute $operationsOf = null;

    public string $namespace = 'default';

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace) : void
    {
        $this->namespace = $namespace;
    }

    public function getRouteRegex(): string
    {
        if (!$this::ROUTE) {
            $classNameParts = explode('\\', $this::class);
            $class = end($classNameParts);
            return strtolower($class);
        }
        // パスパラメータの宣言は操作をまたいで一致している（getOperations() が確かめる）ので、どの操作のものでも同じ
        return $this->getOperations()[0]->getParameters()->pathRegex($this::ROUTE);
    }

    public function getOperation(string $method): ?Operation
    {
        $method = strtoupper($method);
        foreach ($this->getOperations() as $operation) {
            if ($operation->handles($method)) {
                return $operation;
            }
        }
        // WP コアと同じく、HEAD の操作が無ければ GET で応える
        return $method === 'HEAD' ? $this->getOperation('GET') : null;
    }

    /**
     * 操作の宣言。ルートごとに 1 回だけ導出される。
     *
     * - get() / post() / put() / patch() / delete() があれば、それぞれが 1 つの操作
     * - callback() は getMethods() で宣言したメソッド全部に対する 1 つの操作の省略形
     * - どちらも無いルートは、public readonly の body プロパティの値を返す 1 つの操作（静的なルート）
     * - 書きかたを併用したルートと、どれも無いルートは LogicException
     */
    public function getOperations(): array
    {
        // RegisterRestRoutes がリクエストごとに clone するので、複製には元の操作（元のインスタンスに束縛された
        // callback）が残っている。自分で導出したものだけを使う
        if ($this->operations === null || $this->operationsOf !== $this) {
            $this->operations = $this->buildOperations();
            $this->operationsOf = $this;
        }
        return $this->operations;
    }

    /** @return list<Operation> */
    private function buildOperations(): array
    {
        $httpMethods = array_values(array_filter(self::OPERATION_METHODS, fn(string $m) => method_exists($this, $m)));
        $hasCallback = method_exists($this, 'callback');
        $hasBody = $this->declaresBody();

        if ($hasCallback && $httpMethods !== []) {
            throw new LogicException(sprintf(
                '%s declares both callback() and %s(). Use callback() for a single operation, or HTTP method names for one operation per method',
                static::class,
                implode('() / ', $httpMethods),
            ));
        }
        if ($hasBody && ($hasCallback || $httpMethods !== [])) {
            // プロパティの型と callback の戻り値型が違うと、レスポンスの型が 1 つに決まらない
            throw new LogicException(sprintf(
                '%s declares both a $body property and %s(). Declare the response either as a public readonly $body property (static route) or as the return value of the method',
                static::class,
                $hasCallback ? 'callback' : implode('() / ', $httpMethods),
            ));
        }
        if (!$hasBody && !$hasCallback && $httpMethods === []) {
            throw new LogicException(sprintf(
                '%s declares no operation. Declare callback(), get() / post() / put() / patch() / delete(), or a public readonly $body property',
                static::class,
            ));
        }

        $builder = new ParameterDefinitionBuilder();
        $operations = [];
        if ($httpMethods !== []) {
            foreach ($httpMethods as $method) {
                $callback = Callback::fromMethod($this, $method);
                $operations[] = new Operation(
                    strtoupper($method),
                    $callback,
                    $builder->build($this::ROUTE, $this::URL_PARAMS, $callback, static::class . '::' . $method),
                );
            }
        } elseif ($hasCallback) {
            $callback = Callback::fromMethod($this, 'callback');
            $operations[] = new Operation(
                $this->getMethods(),
                $callback,
                $builder->build($this::ROUTE, $this::URL_PARAMS, $callback, static::class . '::callback'),
            );
        } else {
            // 静的なルート。プロパティの型が、callback の戻り値型と同じようにスキーマになる
            $callback = Callback::fromProperty($this, 'body');
            $operations[] = new Operation(
                $this->getMethods(),
                $callback,
                $builder->build($this::ROUTE, $this::URL_PARAMS, $callback, static::class),
            );
        }

        // ルートの正規表現は 1 つなので、パスパラメータの宣言は操作をまたいで一致していなければならない。
        // 正規表現が同じでも型や制約が違えば（float と string など）検証結果が変わるので、宣言そのものを比べる
        $first = $operations[0];
        foreach (array_slice($operations, 1) as $operation) {
            foreach ($first->getParameters()->path() as $name => $definition) {
                $other = $operation->getParameters()->get($name);
                if ($other === null || !$definition->sameConstraintsAs($other)) {
                    throw new LogicException(sprintf(
                        '%s: path parameter [%s] of ROUTE "%s" must be declared the same way in every operation (%s vs %s)',
                        static::class,
                        $name,
                        $this::ROUTE,
                        $first->getMethods(),
                        $operation->getMethods(),
                    ));
                }
            }
        }

        return $operations;
    }

    /**
     * 静的なルートの宣言（public readonly の body プロパティ）があるか。
     * public でない、または readonly でない body は宣言の誤りとして扱い、黙って無視しない
     */
    private function declaresBody(): bool
    {
        if (!property_exists($this, 'body')) {
            return false;
        }
        $property = new ReflectionProperty($this, 'body');
        if (!$property->isPublic() || !$property->isReadOnly()) {
            throw new LogicException(sprintf(
                '%s::$body must be declared as public readonly to be used as the response of a static route. To set the response per request, return it from callback()',
                static::class,
            ));
        }
        return true;
    }

    /**
     * callback() が受け持つ HTTP メソッド。既定は GET。'GET,POST' のようにカンマ区切りで複数を宣言できる。
     * get() / post() などで操作を分けたルートでは使われない
     */
    public function getMethods(): string
    {
        return 'GET';
    }

    /**
     * ルートの既定ヘッダ。既定実装は HEADERS 定数を返す
     */
    public function getHeaders(): array
    {
        return static::HEADERS;
    }

    /**
     * callback からステータスやヘッダを指定してレスポンスを返すためのヘルパ。
     *
     * `return $this->response(null, 404);` のように使う。ルートの既定ヘッダ（HEADERS）は実行者が合成するので、
     * ここで渡すのはこのレスポンス固有のヘッダだけでよい。同名なら渡したほうが勝つ
     *
     * @param array<string, string> $headers
     */
    protected function response(mixed $data, int $status = 200, array $headers = []): RestaResponseInterface
    {
        return new SimpleRestaResponse($data, $status, $headers);
    }

    public function getReadableRoute(): string
    {
        if ($this::ROUTE) {
            // [var] か {var} はどちらでも構わないけど、OpenAPIのライブラリが {var} でないとパスパラメータとして認識しないために変換する
            // {var} に統一してしまってもいいかもしれない
            $route = str_replace(['[', ']'], ['{', '}'], $this::ROUTE);
        } else {
            $classNameParts = explode('\\', $this::class);
            $class = end($classNameParts);
            $route = strtolower($class);
        }

        return '/' . $this->getNamespace() . '/' . $route;
    }

    public function getSchema(): array|null
    {
        return $this::SCHEMA ?? null;
    }
}
