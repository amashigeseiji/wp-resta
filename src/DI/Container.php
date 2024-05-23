<?php
namespace Wp\Resta\DI;

use Exception;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

class Container
{
    private static Container|null $instance = null;

    private array $binder = [];

    private static $config = __DIR__ . '/../config.php';

    private function __construct()
    {
        $binds = require(self::$config);
        foreach ($binds as $interface => $bind) {
            if (is_string($interface)) {
                $this->bind($interface, $bind);
            } else {
                $this->bind($bind);
            }
        }
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function bind(string $interface, $class = null)
    {
        $this->binder[$interface] = $class ?: $interface;
    }

    public function unbind(string $interface)
    {
        unset($this->binder[$interface]);
    }

    public function get(string $interface)
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
        if (is_callable($bind)) {
            $func = is_array($bind) ? new ReflectionMethod($bind[0], $bind[1]) : new ReflectionFunction($bind);
            if ($func->getNumberOfParameters() === 0) {
                return $bind();
            }
            $args = [];
            foreach ($func->getParameters() as $param) {
                $type = $param->getType();
                if (!($type instanceof ReflectionNamedType)) {
                    throw new Exception('$' . $param->getName() . ' is invalid');
                }
                $args[$param->name] = $this->get($type->getName());
            }
            return $func instanceof ReflectionMethod
                ? $func->invokeArgs($bind[0], $args)
                : $func->invokeArgs($args);
        }
        // bindされているのが文字列である場合はクラスと見做すが、文字列以外はそのまま返す
        // 配列とかだとそのまま
        if (!is_string($bind)) {
            return $bind;
        }
        if ($bind === $interface || is_subclass_of($bind, $interface)) {
            return $this->binder[$interface] = $this->factory($interface);
        }
        return $bind;
    }

    private function factory(string $class)
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
