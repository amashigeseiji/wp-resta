<?php
namespace Wp\Resta\DI;

use Exception;
use LogicException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

class Container
{
    private static Container|null $instance = null;

    private array $binder = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     * @param T $class
     */
    public function bind(string $interface, string|object|callable $class = null) : void
    {
        $this->binder[$interface] = $class ?: $interface;
    }

    public function unbind(string $interface) : void
    {
        unset($this->binder[$interface]);
    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     * @return T
     */
    public function get(string $interface) : object
    {
        // 未定義の場合 class であれば呼びだすことができる
        if (!isset($this->binder[$interface])) {
            if (class_exists($interface)) {
                $this->binder[$interface] = $interface;
            } else {
                throw new Exception($interface . ' does not defined.');
            }
        }
        $bind = $this->binder[$interface];
        // "$bind" is already resolved.
        if ($bind instanceof $interface) {
            return $bind;
        }
        if (is_callable($bind)) {
            $func = is_array($bind) ? new ReflectionMethod($bind[0], $bind[1]) : new ReflectionFunction($bind);
            if ($func->getNumberOfParameters() === 0) {
                $resolved = $bind();
            } else {
                $args = [];
                foreach ($func->getParameters() as $param) {
                    $type = $param->getType();
                    // ReflectionUnionType or ReflectionIntersectionType cannot resolve because of multiple types.
                    if (!($type instanceof ReflectionNamedType)) {
                        throw new Exception('$' . $param->getName() . ' is invalid');
                    }
                    $typeName = $type->getName();
                    if (!class_exists($typeName)) {
                        throw new RuntimeException("\"\${$typeName}\" cannot resolve.");
                    }
                    $args[$param->name] = $this->get($typeName);
                }
                $resolved = $func instanceof ReflectionMethod
                    ? $func->invokeArgs($bind[0], $args)
                    : $func->invokeArgs($args);
            }
            if (!($resolved instanceof $interface)) {
                throw new RuntimeException();
            }
            return $resolved;
        }
        if (!is_string($bind)) {
            throw new LogicException("\"\${$interface}\" cannot resolved.");
        }
        // "$bind" is stil unresolved
        if ($bind === $interface || is_subclass_of($bind, $interface)) {
            return $this->binder[$interface] = $this->factory($bind);
        }
        throw new RuntimeException('Bound unresolve.');
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function factory(string $class) : object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            $instance = $reflection->newInstance();
        } else {
            $args = [];
            foreach ($constructor->getParameters() as $param) {
                /** @var ReflectionNamedType */
                $type = $param->getType();
                if (!($type instanceof ReflectionNamedType)) {
                    throw new Exception('$' . $param->getName() . ' is invalid');
                }
                $args[$param->name] = $this->get($type->getName());
            }
            $instance = $reflection->newInstanceArgs($args);
        }
        return $instance;
    }
}
