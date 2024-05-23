<?php
namespace Wp\Resta\REST\Schemas;

use ReflectionAttribute;
use ReflectionClass;
use Wp\Resta\DI\Container;
use Wp\Resta\REST\Attributes\Schema\Property;

class Schemas
{
    public readonly array $schemas;

    public function __construct()
    {
        $container = Container::getInstance();
        $schemaSettings = $container->get('__schemaDirectory');

        $schemas = [];
        foreach ($schemaSettings as $schemaDir) {
            $dir = $schemaDir[0];
            $namespace = $schemaDir[1];
            $schemaFiles = glob(ABSPATH . "/" . $dir . '/*.php');
            foreach ($schemaFiles as $schema) {
                $typeName = basename($schema, '.php');
                $class = $namespace . $typeName;
                if (!is_subclass_of($class, SchemaInterface::class)) {
                    continue;
                }

                $schemas[$typeName] = $class::describe();
            }
        }

        $this->schemas = $schemas;
    }
}
