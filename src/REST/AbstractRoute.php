<?php
namespace Wp\Resta\REST;

use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use PsrMock\Psr7\Response;
use PsrMock\Psr7\Stream;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Wp\Resta\DI\Container;
use Wp\Resta\REST\Http\RestaRequestInterface;

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
    /** @var array<string, array<string, string|bool>>*/
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

    public function invoke(RestaRequestInterface $request): ResponseInterface
    {
        if (is_callable([$this, 'callback'])) {
            try {
                $result = $this->invokeCallback(
                    new ReflectionMethod($this, 'callback'),
                    $request
                );

                if ($result instanceof ResponseInterface) {
                    return $result;
                }

                $this->body = $result;
            } catch (\Exception $e) {
                if ($this->status === 200) {
                    $this->status = 500;
                }
            }
        }

        return $this->createResponse();
    }

    private function invokeCallback(ReflectionMethod $callback, RestaRequestInterface $request) : mixed
    {
        $parameters = $callback->getParameters();
        $args = [];
        $define = $this->getArgs();
        foreach ($parameters as $param) {
            // URL定義されている値の解決
            if (isset($define[$param->name])) {
                $value = $request->getAttribute($param->name);

                if ($define[$param->name]['required'] && $value === null) {
                    throw new RuntimeException($param->name . ' is missing.');
                }

                if ($value !== null) {
                    $regex = '/' . $define[$param->name]['regex'] . '/';
                    if (preg_match($regex, (string)$value)) {
                        $args[$param->name] = $value;
                    }
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
                $args[$param] = $type;
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
        }
        return $this->args = $args;
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

    /**
     * Create PSR-7 Response from current state
     */
    private function createResponse(): ResponseInterface
    {
        $response = new Response($this->status);

        // Add headers
        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        // Encode body as JSON if it's an array
        $body = is_string($this->body)
            ? $this->body
            : json_encode($this->body, JSON_UNESCAPED_UNICODE);

        $stream = new Stream($body);
        return $response->withBody($stream);
    }
}
