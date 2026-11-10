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

    private string $routeRegex;
    /** @var array<string, array<string, mixed>> */
    private array $args;

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
    public const PATH_SEGMENT_REGEX = '[^/]+';

    public function getRouteRegex(): string
    {
        if (isset($this->routeRegex)) {
            return $this->routeRegex;
        }

        if (!$this::ROUTE) {
            $classNameParts = explode('\\', $this::class);
            $class = end($classNameParts);
            $route = strtolower($class);
        } elseif (!$this::URL_PARAMS) {
            $route = $this::ROUTE;
        } else {
            $key = [];
            $replace = [];
            foreach ($this->getArgs() as $paramName => $type) {
                $key[] = '[' . $paramName . ']';
                // regex が無い（制約なし）パスパラメータは次の / までの任意の文字列。
                // Unicode に依存しないので、WP コアの照合（u なし）と wp-resta の照合（u あり）で結果が揃う
                $regex = $type['regex'] ?? self::PATH_SEGMENT_REGEX;
                $replace[] = "(?P<{$paramName}>{$regex})";
            }
            $route = str_replace($key, $replace, $this::ROUTE);
        }
        return $this->routeRegex = $route;
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
     * preg_match の区切り文字の候補。regex に含まれていないものを先頭から順に選ぶ。
     * / は '[a-z/]+' のように URL 向けの regex に現れやすいので候補に入れない
     */
    private const REGEX_DELIMITERS = ['#', '~', '%', '@', '!', '`', ';'];

    /**
     * URL_PARAMS の regex を、値全体に対して照合する形に組み立てる（区切り文字なし）。
     * WP コアの args に渡す pattern と、resolveArguments() の preg_match の両方で使う。
     *
     * - ^(?:...)$ で囲んで部分一致を許さない。(?:...) は 'a|b' が '^a|b$' に化けるのを防ぐため
     * - (*UTF) (*UCP) のような先頭専用の指示子はパターンの先頭にしか置けないので、^(?:...) の外に出す
     */
    private function anchorRegex(string $regex): string
    {
        $verbs = '';
        if (preg_match('/^(?:\(\*[A-Z_]+(?:=\d+)?\))+/', $regex, $m) === 1) {
            $verbs = $m[0];
            $regex = substr($regex, strlen($verbs));
        }
        return $verbs . '^(?:' . $regex . ')$';
    }

    /**
     * anchorRegex() の結果を preg_match に渡せるパターンにする。
     *
     * - 区切り文字は regex に含まれていない文字を選ぶ。エスケープで対処すると、regex 中の '\#' や
     *   '\Q...\E' の引用範囲まで書き換えてしまい、元々有効だった regex が壊れる
     * - u 修飾子で UTF-8 モードにする。WP コアの rest_validate_json_schema_pattern() も u 付きなので判定が
     *   揃う。PHP の u は Unicode プロパティも有効にするため、\w が日本語などの文字にも一致し、.{n} が
     *   バイト数ではなく文字数になる
     * - D 修飾子で $ が末尾の改行の手前にマッチしないようにする
     */
    private function compileRegex(string $regex): string
    {
        $anchored = $this->anchorRegex($regex);
        foreach (self::REGEX_DELIMITERS as $delimiter) {
            if (!str_contains($anchored, $delimiter)) {
                return $delimiter . $anchored . $delimiter . 'uD';
            }
        }
        throw new LogicException(sprintf(
            '%s::URL_PARAMS regex "%s" contains every delimiter candidate (%s).',
            static::class,
            $regex,
            implode('', self::REGEX_DELIMITERS),
        ));
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
                    $matched = preg_match($this->compileRegex($regex), (string)$value);
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
            if ($type->isBuiltin()) {
                // ビルトイン型はURL定義にないものがこちらに紛れているとおもわれる
                throw new LogicException($this::class . "::callback() has invalid argument `{$type->getName()} \${$param->name}`. Please check URL_PARAMS has `{$param->name}` parameter.");
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
        if (isset($this->args)) {
            return $this->args;
        }
        $args = [];
        foreach ($this::URL_PARAMS as $param => $type) {
            if (is_array($type)) {
                $args[$param] = $this->withCoreValidation($type);
                continue;
            }

            switch($type) {
            case 'string':
            case '?string':
                // 'string' は任意の文字列。regex による制約は付けない（#81）
                $args[$param] = [
                    'type' => 'string',
                    'required' => $type === 'string',
                    'regex' => null,
                    'description' => $param,
                ];
                break;
            case 'integer':
            case '?integer':
                $args[$param] = [
                    'type' => 'integer',
                    'required' => $type === 'integer',
                    'regex' => '\d+',
                    'description' => $type['description'] ?? $param,
                ];
                break;
            default:
                $args[$param] = [
                    'type' => 'string',
                    'required' => true,
                    'regex' => $type,
                    'description' => $type['description'] ?? $param,
                ];
            }
            $args[$param] = $this->withCoreValidation($args[$param]);
        }
        return $this->args = $args;
    }

    /**
     * register_rest_route() の args として WP コアに渡したときに、コア側でも検証されるようにキーを補う。
     *
     * regex はコアの知らないキーなので、string 型なら pattern にも展開する。コアは pattern を
     * 部分一致で照合するため、resolveArguments() と同じく anchorRegex() で全体一致にしてから渡す。
     *
     * コアは type を持つ arg に sanitize_callback の既定値として rest_parse_request_arg を入れ
     * （WP_REST_Request::sanitize_params()）、その中で rest_validate_request_arg も呼ぶので、
     * validate_callback を別途指定しなくても pattern は検証される。
     *
     * 利用側が pattern を明示している場合はそちらを優先する。
     *
     * regex 自体が不正な場合は pattern を付けない。コアは preg_match() の失敗を不一致として扱い、
     * すべての値を 400 にしてしまうため、定義ミスがクライアント側のエラーとして報告されてしまう。
     * pattern を付けなければ resolveArguments() の検証に到達し、そちらで 500 とログになる。
     *
     * @param array<string, mixed> $arg
     * @return array<string, mixed>
     */
    private function withCoreValidation(array $arg): array
    {
        if (($arg['type'] ?? null) !== 'string'
            || !isset($arg['regex']) || !is_string($arg['regex'])
            || array_key_exists('pattern', $arg)
        ) {
            return $arg;
        }
        try {
            $compiled = $this->compileRegex($arg['regex']);
        } catch (LogicException) {
            return $arg;
        }
        if (@preg_match($compiled, '') === false) {
            return $arg;
        }
        $arg['pattern'] = $this->anchorRegex($arg['regex']);
        return $arg;
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
