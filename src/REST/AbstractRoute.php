<?php
namespace Wp\Resta\REST;

use Wp\Resta\Auth\Principal;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use Wp\Resta\REST\Parameter\BoundParameters;
use Wp\Resta\REST\Parameter\ParameterDefinitionBuilder;
use Wp\Resta\REST\Parameter\RouteParameters;

abstract class AbstractRoute implements RouteInterface
{
    protected const ROUTE = '';
    protected const URL_PARAMS = [];
    public const SCHEMA = null;

    /** @var array<string, string> */
    protected array $headers = [];

    /**
     * @var mixed
     */
    protected $body = '';
    protected int $status = 200;

    private RouteParameters $parameters;

    public string $namespace = 'default';

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace) : void
    {
        $this->namespace = $namespace;
    }

    /** regex を宣言していないパスパラメータに使う regex。次の / までの任意の文字列 */
    public function getRouteRegex(): string
    {
        if (!$this::ROUTE) {
            $classNameParts = explode('\\', $this::class);
            $class = end($classNameParts);
            return strtolower($class);
        }
        return $this->getParameters()->pathRegex($this::ROUTE);
    }

    /**
     * URL パラメータの宣言。ROUTE、URL_PARAMS、callback のシグニチャから導出され、ルートごとに 1 回だけ作られる
     */
    public function getParameters(): RouteParameters
    {
        return $this->parameters ??= (new ParameterDefinitionBuilder())->build(
            $this::ROUTE,
            $this::URL_PARAMS,
            $this->getCallback(),
            static::class,
        );
    }

    public function getCallback(): Callback
    {
        if (method_exists($this, 'callback')) {
            // 0.12 の橋: callback 内で $this->status に代入された値を、戻り値の包みに変換する。
            // プロパティへの代入は非推奨化の方向（#59）で、プロパティを削除するときにこの橋も消す。
            // 実行者はルートのプロパティを知らず、callback が body か RestaResponseInterface を返すとだけ見る
            return Callback::fromMethod($this, 'callback')->withResult(
                fn(mixed $result): mixed => $result instanceof RestaResponseInterface
                    ? $result
                    : new SimpleRestaResponse($result, $this->status)
            );
        }
        // callback を持たないルートは非推奨。0.13 で LogicException にする。
        // それまでは宣言された body と status を返す callback として扱い、実行者は「無い場合」を知らない
        trigger_error(static::class . ' has no callback(). Routes without callback() are deprecated; return the body from callback() instead.', E_USER_DEPRECATED);
        return Callback::returning(new SimpleRestaResponse($this->body, $this->status));
    }

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

    public function getArgs(): array
    {
        return $this->getParameters()->toCoreArgs();
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
