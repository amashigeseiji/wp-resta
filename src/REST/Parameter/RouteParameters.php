<?php
namespace Wp\Resta\REST\Parameter;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * 1 ルート分の URL パラメータの宣言。名前で引ける。
 *
 * @implements IteratorAggregate<string, ParameterDefinition>
 */
final class RouteParameters implements IteratorAggregate, Countable
{
    /** @var array<string, ParameterDefinition> */
    private array $definitions = [];

    /** @param ParameterDefinition[] $definitions */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->name] = $definition;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function get(string $name): ?ParameterDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    /** @return array<string, ParameterDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return array<string, ParameterDefinition> */
    public function path(): array
    {
        return array_filter($this->definitions, fn(ParameterDefinition $d) => $d->isPath());
    }

    /** @return array<string, ParameterDefinition> */
    public function query(): array
    {
        return array_filter($this->definitions, fn(ParameterDefinition $d) => !$d->isPath());
    }

    /**
     * ROUTE の [name] をパスパラメータの名前付きキャプチャに置き換えたルート正規表現
     */
    public function pathRegex(string $route): string
    {
        $search = [];
        $replace = [];
        foreach ($this->path() as $definition) {
            $search[] = '[' . $definition->name . ']';
            $replace[] = $definition->pathCapture();
        }
        return str_replace($search, $replace, $route);
    }

    /**
     * register_rest_route() の args
     *
     * @return array<string, array<string, mixed>>
     */
    public function toCoreArgs(): array
    {
        return array_map(fn(ParameterDefinition $d) => $d->toCoreArg(), $this->definitions);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->definitions);
    }

    public function count(): int
    {
        return count($this->definitions);
    }
}
