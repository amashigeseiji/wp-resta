<?php
namespace Wp\Resta\REST;

use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use LogicException;
use Wp\Resta\REST\Parameter\BoundParameters;
use Wp\Resta\REST\Parameter\ParameterDefinitionBuilder;

abstract class AbstractRoute implements RouteInterface
{
    protected const ROUTE = '';
    protected const URL_PARAMS = [];
    public const SCHEMA = null;

    /** HTTP メソッド名のメソッドが操作の宣言になる */
    private const OPERATION_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /** @var array<string, string> */
    protected array $headers = [];

    /**
     * @var mixed
     */
    protected $body = '';
    protected int $status = 200;

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
     * - 両方あれば LogicException。どちらも無ければ非推奨の警告付きで $body を返す操作を合成する
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

        if ($hasCallback && $httpMethods !== []) {
            throw new LogicException(sprintf(
                '%s declares both callback() and %s(). Use callback() for a single operation, or HTTP method names for one operation per method',
                static::class,
                implode('() / ', $httpMethods),
            ));
        }

        $builder = new ParameterDefinitionBuilder();
        $operations = [];
        if ($httpMethods !== []) {
            foreach ($httpMethods as $method) {
                $callback = $this->callbackFor($method);
                $operations[] = new Operation(
                    strtoupper($method),
                    $callback,
                    $builder->build($this::ROUTE, $this::URL_PARAMS, $callback, static::class . '::' . $method),
                );
            }
        } elseif ($hasCallback) {
            $callback = $this->callbackFor('callback');
            $operations[] = new Operation(
                $this->getMethods(),
                $callback,
                $builder->build($this::ROUTE, $this::URL_PARAMS, $callback, static::class . '::callback'),
            );
        } else {
            // callback を持たないルートは非推奨。0.13 で LogicException にする。
            // それまでは宣言された body と status を返す callback として扱い、実行者は「無い場合」を知らない
            trigger_error(static::class . ' has no callback(). Routes without callback() are deprecated; return the body from callback() instead.', E_USER_DEPRECATED);
            $callback = Callback::returning(new SimpleRestaResponse($this->body, $this->status));
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

    private function callbackFor(string $method): Callback
    {
        // 0.12 の橋: callback 内で $this->status に代入された値を、戻り値の包みに変換する。
        // プロパティへの代入は非推奨化の方向（#59）で、プロパティを削除するときにこの橋も消す。
        // 実行者はルートのプロパティを知らず、callback が body か RestaResponseInterface を返すとだけ見る
        return Callback::fromMethod($this, $method)->withResult(
            fn(mixed $result): mixed => $result instanceof RestaResponseInterface
                ? $result
                : new SimpleRestaResponse($result, $this->status)
        );
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
     * 宣言された制約を通った後の、手続き的な検証のフック。既定は何もしない。
     *
     * 複数のパラメータにまたがる検証や、DB を引く検証の置き場所。
     * 拒否するときは InvalidParameterException などの HttpException を投げる。
     */
    public function validate(BoundParameters $params): void
    {
    }

    /**
     * ルート単位の認可。既定は全リクエストを許可する。
     *
     * 拒否するときは UnauthorizedException（401）か ForbiddenException（403）を投げる。
     * バリデーションの後に呼ばれるので、$params は型変換済みの値。
     */
    public function authorize(RestaRequestInterface $request, BoundParameters $params, Principal $principal): void
    {
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * callback からステータスやヘッダを指定してレスポンスを返すためのヘルパ。
     *
     * `return $this->response(null, 404);` のように使う。$status / $body / $headers プロパティへの
     * 代入と違い、ルートに状態を持たせずに済む。ルートの既定ヘッダ（getHeaders()）は実行者が合成するので、
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
