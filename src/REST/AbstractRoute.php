<?php
namespace Wp\Resta\REST;

use InvalidArgumentException;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;
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
                $replace[] = "(?P<{$paramName}>{$type['regex']})";
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
        if (is_callable([$this, 'callback'])) {
            try {
                $result = $this->invokeCallback(
                    new ReflectionMethod($this, 'callback'),
                    $request
                );

                if ($result instanceof RestaResponseInterface) {
                    return $result;
                }

                $this->body = $result;
            } catch (HttpException $e) {
                // 入力不正などクライアント起因のエラー。WP_Error と同じ形で返す
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
        }

        return new SimpleRestaResponse($this->body, $this->status, $this->headers);
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
     * WP コアの args に渡す pattern と、invokeCallback() の preg_match の両方で使う。
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
     * - D 修飾子で $ が末尾の改行の手前にマッチしないようにする
     */
    private function compileRegex(string $regex): string
    {
        $anchored = $this->anchorRegex($regex);
        foreach (self::REGEX_DELIMITERS as $delimiter) {
            if (!str_contains($anchored, $delimiter)) {
                return $delimiter . $anchored . $delimiter . 'D';
            }
        }
        throw new LogicException(sprintf(
            '%s::URL_PARAMS regex "%s" contains every delimiter candidate (%s).',
            static::class,
            $regex,
            implode('', self::REGEX_DELIMITERS),
        ));
    }

    private function invokeCallback(ReflectionMethod $callback, RestaRequestInterface $request) : mixed
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

                if ($value !== null) {
                    $matched = preg_match($this->compileRegex($define[$param->name]['regex']), (string)$value);
                    if ($matched === false) {
                        // regex 自体が不正。クライアントの入力不正ではなくルート定義の誤りなので 500 にする
                        throw new LogicException(sprintf(
                            '%s::URL_PARAMS[%s] has invalid regex "%s": %s',
                            static::class,
                            $param->name,
                            $define[$param->name]['regex'],
                            preg_last_error_msg(),
                        ));
                    }
                    if ($matched === 0) {
                        throw new InvalidParameterException($param->name);
                    }
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
            if (!class_exists($class)) {
                throw new RuntimeException('cannot resolve type: ' . $class);
            }
            // クラス/インターフェースなど一意に確定できるものだけインジェクトする
            $args[$param->name] = Container::getInstance()->get($class);
        }
        return $callback->invokeArgs($this, $args);
    }

    public function permissionCallback() : string
    {
        return '__return_true';
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
                $args[$param] = [
                    'type' => 'string',
                    'required' => $type === 'string',
                    'regex' => '\w+',
                    'description' => $type['description'] ?? $param,
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
     * - regex はコアの知らないキーなので、string 型なら pattern にも展開する。コアは pattern を
     *   部分一致で照合するため、invokeCallback() と同じく anchorRegex() で全体一致にしてから渡す
     * - コアは validate_callback が無い args の type や pattern を検証しない（register_rest_route() は
     *   validate_callback を補わない）ので、rest_validate_request_arg を指定する
     *
     * 利用側が同名のキーを明示している場合はそちらを優先する。
     *
     * @param array<string, mixed> $arg
     * @return array<string, mixed>
     */
    private function withCoreValidation(array $arg): array
    {
        if (($arg['type'] ?? null) === 'string'
            && isset($arg['regex']) && is_string($arg['regex'])
            && !array_key_exists('pattern', $arg)
        ) {
            $arg['pattern'] = $this->anchorRegex($arg['regex']);
        }
        if (isset($arg['type']) && !array_key_exists('validate_callback', $arg)) {
            $arg['validate_callback'] = 'rest_validate_request_arg';
        }
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
