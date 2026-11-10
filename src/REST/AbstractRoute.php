<?php
namespace Wp\Resta\REST;

use InvalidArgumentException;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;
use Wp\Resta\Auth\AnonymousPrincipal;
use Wp\Resta\Auth\Principal;
use Wp\Resta\DI\Container;
use Wp\Resta\REST\Exception\HttpException;
use Wp\Resta\REST\Exception\InvalidParameterException;
use Wp\Resta\REST\Exception\MissingParameterException;
use Wp\Resta\REST\Http\RestaRequestInterface;
use Wp\Resta\REST\Http\RestaResponseInterface;
use Wp\Resta\REST\Http\SimpleRestaResponse;
use Wp\Resta\REST\Parameter\ParameterDefinitionBuilder;
use Wp\Resta\REST\Parameter\ParameterRegex;
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
    /** @deprecated ParameterRegex::PATH_SEGMENT を使う */
    public const PATH_SEGMENT_REGEX = ParameterRegex::PATH_SEGMENT;

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
            return Callback::fromMethod($this, 'callback');
        }
        // callback を持たないルートは、宣言された body を返す callback として扱う
        return Callback::returning($this->body);
    }

    public function getMethods(): string
    {
        return 'GET';
    }

    public function invoke(RestaRequestInterface $request): RestaResponseInterface
    {
        try {
            // バリデーション → 認可 → callback の順（spec「HTTP 実行のパイプライン」）。
            // WP コアと同じ順序で、authorize() が見る URL パラメータは制約を通った後の値になる
            $callback = is_callable([$this, 'callback']) ? new ReflectionMethod($this, 'callback') : null;
            $args = $callback === null ? [] : $this->resolveArguments($callback, $request);

            $this->authorize($request, $this->resolvePrincipal());

            if ($callback !== null) {
                $result = $callback->invokeArgs($this, $args);
                if ($result instanceof RestaResponseInterface) {
                    return $result;
                }
                $this->body = $result;
            }
        } catch (HttpException $e) {
            // 入力不正・認可の拒否などクライアント起因のエラー。WP_Error と同じ形で返す
            return new SimpleRestaResponse(
                $this->errorBody($e->getErrorCode(), $e->getMessage(), $e->getStatusCode(), $e->getData()),
                $e->getStatusCode(),
                $this->headers,
            );
        } catch (Throwable $e) {
            // callback のバグや DI 解決失敗など。握り潰さずログに出して 500 を返す
            // 匿名クラスの名前は NUL 文字を含み error_log がそこで切れる。
            // クラス名はメッセージにも含まれうるので、整形後の文字列全体から取り除く
            error_log(str_replace("\0", '', sprintf(
                '[wp-resta] Uncaught %s in %s: %s in %s:%d',
                $e::class,
                static::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            )));
            $data = [];
            $message = 'Internal Server Error';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $message = $e->getMessage();
                $data['exception'] = $e::class;
            }
            return new SimpleRestaResponse(
                $this->errorBody('rest_internal_error', $message, 500, $data),
                500,
                $this->headers,
            );
        }

        return new SimpleRestaResponse($this->body, $this->status, $this->headers);
    }

    /**
     * ルート単位の認可。既定は全リクエストを許可する。
     *
     * 拒否するときは UnauthorizedException（401）か ForbiddenException（403）を投げる。
     * バリデーションの後、callback の前に呼ばれる。
     */
    protected function authorize(RestaRequestInterface $request, Principal $principal): void
    {
    }

    private function resolvePrincipal(): Principal
    {
        $container = Container::getInstance();
        if ($container->has(Principal::class)) {
            return $container->get(Principal::class);
        }
        // Resta::init() を通らない WordPress 無しの invoke() では、誰も認証していないものとして扱う
        return new AnonymousPrincipal();
    }

    /**
     * callback からステータスやヘッダを指定してレスポンスを返すためのヘルパ。
     *
     * `return $this->response(null, 404);` のように使う。$status / $body / $headers プロパティへの
     * 代入と違い、ルートに状態を持たせずに済む。$headers はクラス宣言の $headers に上書きで合成する。
     * HTTP ヘッダ名は大文字小文字を区別しないので、表記が違う同名ヘッダも callback 側の値で置き換える。
     *
     * @param array<string, string> $headers
     */
    protected function response(mixed $data, int $status = 200, array $headers = []): RestaResponseInterface
    {
        $merged = $this->headers;
        foreach ($headers as $name => $value) {
            foreach (array_keys($merged) as $existing) {
                if (strcasecmp($existing, $name) === 0) {
                    unset($merged[$existing]);
                }
            }
            $merged[$name] = $value;
        }
        return new SimpleRestaResponse($data, $status, $merged);
    }

    /**
     * WP_Error::get_error_data() を含む WP_REST_Server のエラーレスポンスと同じ形のボディを組み立てる
     *
     * @param array<string, mixed> $data
     * @return array{code: string, message: string, data: array<string, mixed>}
     */
    private function errorBody(string $code, string $message, int $status, array $data): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'data' => ['status' => $status] + $data,
        ];
    }

    /**
     * callback の引数を解決する。URL パラメータは宣言された制約に照合し、それ以外のクラス型は DI で解決する
     *
     * @return array<string, mixed> 引数名 => 値
     */
    private function resolveArguments(ReflectionMethod $callback, RestaRequestInterface $request) : array
    {
        $parameters = $callback->getParameters();
        $args = [];
        $define = $this->getArgs();
        foreach ($parameters as $param) {
            // URL定義されている値の解決
            if (isset($define[$param->name])) {
                $value = $request->getUrlParam($param->name);

                if ($define[$param->name]['required'] && $value === null) {
                    throw new MissingParameterException($param->name);
                }
                if ($value !== null && !is_scalar($value)) {
                    // ?name[]=x のような配列は string / integer の宣言を満たさない。regex の有無によらず 400
                    throw new InvalidParameterException($param->name);
                }

                $regex = $define[$param->name]['regex'] ?? null;
                if ($value !== null && $regex !== null) {
                    $matched = preg_match(ParameterRegex::compile($regex, static::class . '::URL_PARAMS'), (string)$value);
                    if ($matched === false && in_array(preg_last_error(), [PREG_BAD_UTF8_ERROR, PREG_BAD_UTF8_OFFSET_ERROR], true)) {
                        // 値が UTF-8 として不正。regex の誤りではなくクライアントの入力不正なので 400 にする
                        throw new InvalidParameterException($param->name);
                    }
                    if ($matched === false) {
                        // regex 自体が不正。クライアントの入力不正ではなくルート定義の誤りなので 500 にする
                        throw new LogicException(sprintf(
                            '%s::URL_PARAMS[%s] has invalid regex "%s": %s',
                            static::class,
                            $param->name,
                            $regex,
                            preg_last_error_msg(),
                        ));
                    }
                    if ($matched === 0) {
                        throw new InvalidParameterException($param->name);
                    }
                }
                if ($value !== null) {
                    $args[$param->name] = $value;
                } elseif ($param->isOptional()) {
                    $args[$param->name] = $param->getDefaultValue();
                }
                continue;
            }
            // URL定義中にない引数はDIでインジェクトする
            $type = $param->getType();
            if (!($type instanceof ReflectionNamedType)) {
                // ReflectionUnionType または ReflectionIntersectionType の場合は決定できない
                throw new InvalidArgumentException(static::class . '::callback() argument type ' . $type . ' cannot resolve.');
            }
            $class = $type->getName();
            if ($request instanceof $class) {
                // 現在のリクエスト（RestaRequestInterface やその実装）は DI コンテナを介さず直接渡す。
                // WordPress 無しの invoke() でも解決できるようにするため
                $args[$param->name] = $request;
                continue;
            }
            if (!class_exists($class) && !interface_exists($class)) {
                throw new RuntimeException('cannot resolve type: ' . $class);
            }
            // クラス/インターフェースなど一意に確定できるものだけインジェクトする
            $args[$param->name] = Container::getInstance()->get($class);
        }
        return $args;
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
