<?php
namespace Wp\Restafari\REST;

use InvalidArgumentException;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use RuntimeException;
use Wp\Restafari\DI\Container;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

abstract class AbstractRoute implements RouteInterface
{
    protected const ROUTE = '';
    protected const URL_PARAMS = [];
    public const SCHEMA = null;

    protected $body = '';
    protected $status = 200;

    private string $routeRegex;
    private array $args;

    public string $namespace = 'default';

    public function getNamespace(): string
    {
        return $this->namespace;
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

    public function invoke(WP_REST_Request $request): WP_REST_Response
    {
        $container = Container::getInstance();
        $container->bind(WP_REST_Request::class, $request);
        $container->bind(WP_Query::class, [$this, 'wpQueryResolver']);
        if (is_callable([$this, 'callback'])) {
            $callback = new ReflectionMethod($this, 'callback');
            $parameters = $callback->getParameters();
            $args = [];
            $define = $this->getArgs();
            foreach ($parameters as $param) {
                // URL定義されている値の解決
                if (isset($define[$param->name])) {
                    if ($define[$param->name]['required'] && !isset($request[$param->name])) {
                        throw new RuntimeException($param->name . ' is missing.');
                    }
                    if (isset($request[$param->name])) {
                        $regex = '/' . $define[$param->name]['regex'] . '/';
                        if (preg_match($regex, $request[$param->name])) {
                            $args[$param->name] = $request[$param->name];
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
                // クラス/インターフェースなど一意に確定できるものだけインジェクトする
                $args[$param->name] = Container::getInstance()->get($type->getName());
            }

            try {
                $result = $callback->invokeArgs($this, $args);
                if ($result instanceof WP_REST_Response) {
                    return $result;
                }
                $this->body = $result;
            } catch (\Exception $e) {
                if ($this->status === 200) {
                    $this->status = 500;
                }
            }
        }

        return new WP_REST_Response($this->body, $this->status);
    }

    public function wpQueryResolver(WP_REST_Request $request) : WP_Query
    {
        return new WP_Query();
    }

    public function permissionCallback()
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

    public function registerSwaggerResponse(): void
    {
        $methodEndpoint = strtolower($this->getMethods()) .  str_replace('/', '_', $this->getReadableRoute());
        add_filter('swagger_api_responses_' . $methodEndpoint, function () {
            return [
                '200' => [
                    'description' => 'OK',
                    'content' => [
                        'application/json' => [
                            'schema' => $this::SCHEMA ?? [],
                        ]
                    ],
                ]
            ];
        });
    }
}
