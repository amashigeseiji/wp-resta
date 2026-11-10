<?php
namespace Wp\Resta\REST\Parameter;

use ArrayIterator;
use BackedEnum;
use Countable;
use IteratorAggregate;
use LogicException;
use Traversable;
use Wp\Resta\REST\Exception\InvalidParameterException;
use Wp\Resta\REST\Exception\MissingParameterException;
use Wp\Resta\REST\Http\RestaRequestInterface;

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

    /**
     * リクエストの値を宣言に照らして検証し、型変換した値を返す。
     *
     * 宣言されたパラメータすべてを見る。callback の引数にあるかどうかは関係ない。
     * 失敗は InvalidParameterException / MissingParameterException（400）。
     * 型変換の規則は WordPress コアの rest_sanitize_value_from_schema() に寄せている。
     */
    public function validate(RestaRequestInterface $request): BoundParameters
    {
        $values = [];
        foreach ($this->definitions as $name => $definition) {
            $raw = $request->getUrlParam($name);
            if ($raw === null) {
                if ($definition->required) {
                    throw new MissingParameterException($name);
                }
                if ($definition->hasDefault) {
                    // default は宣言では値（enum なら case の値）で持つので、束縛先の型があれば変換する
                    $values[$name] = self::bind($definition, $definition->default);
                }
                continue;
            }
            $values[$name] = self::convert($definition, $raw);
        }
        return new BoundParameters($values);
    }

    /** backed enum など、束縛先の型があればそのインスタンスにする */
    private static function bind(ParameterDefinition $definition, mixed $value): mixed
    {
        if ($definition->bindType !== null && is_subclass_of($definition->bindType, BackedEnum::class) && !($value instanceof BackedEnum)) {
            return $definition->bindType::from($value);
        }
        return $value;
    }

    private static function convert(ParameterDefinition $definition, mixed $raw): mixed
    {
        $name = $definition->name;

        if ($definition->type === 'array') {
            // tags[]=a&tags[]=b の配列と、tags=a,b のカンマ区切り（コアの rest_sanitize_array() と同じ）
            if (is_string($raw)) {
                $raw = $raw === '' ? [] : array_map('trim', explode(',', $raw));
            }
            if (!is_array($raw)) {
                throw new InvalidParameterException($name);
            }
            $itemType = $definition->items['type'] ?? null;
            $items = [];
            foreach (array_values($raw) as $item) {
                if (!is_scalar($item)) {
                    throw new InvalidParameterException($name);
                }
                $items[] = is_string($itemType) ? self::convertScalar($name, $itemType, null, $item) : $item;
            }
            return $items;
        }

        if (!is_scalar($raw)) {
            // ?name[]=x のような配列は string / integer などの宣言を満たさない。regex の有無によらず 400
            throw new InvalidParameterException($name);
        }
        $value = self::convertScalar($name, $definition->type, $definition->regex, $raw);

        if ($definition->enum !== null && !in_array($value, $definition->enum, true)) {
            throw new InvalidParameterException($name);
        }
        if ($definition->minimum !== null && $value < $definition->minimum) {
            throw new InvalidParameterException($name, "{$name} must be greater than or equal to {$definition->minimum}");
        }
        if ($definition->maximum !== null && $value > $definition->maximum) {
            throw new InvalidParameterException($name, "{$name} must be less than or equal to {$definition->maximum}");
        }
        return self::bind($definition, $value);
    }

    /**
     * regex に照合してから型に変換する。regex は WP コアの pattern と同じく文字列表現に対して照合する
     */
    private static function convertScalar(string $name, string $type, ?string $regex, bool|int|float|string $raw): bool|int|float|string
    {
        if ($regex !== null) {
            $text = is_bool($raw) ? ($raw ? '1' : '0') : (string)$raw;
            $matched = preg_match(ParameterRegex::compile($regex, "{$name}"), $text);
            if ($matched === false && in_array(preg_last_error(), [PREG_BAD_UTF8_ERROR, PREG_BAD_UTF8_OFFSET_ERROR], true)) {
                // 値が UTF-8 として不正。regex の誤りではなくクライアントの入力不正なので 400 にする
                throw new InvalidParameterException($name);
            }
            if ($matched === false) {
                // regex 自体が不正。クライアントの入力不正ではなくルート定義の誤りなので 500 にする
                throw new LogicException(sprintf('URL_PARAMS[%s] has invalid regex "%s": %s', $name, $regex, preg_last_error_msg()));
            }
            if ($matched === 0) {
                throw new InvalidParameterException($name);
            }
        }

        switch ($type) {
            case 'integer':
                $value = is_bool($raw) ? null : filter_var($raw, FILTER_VALIDATE_INT);
                if ($value === false || $value === null) {
                    throw new InvalidParameterException($name);
                }
                return $value;
            case 'number':
                $value = is_bool($raw) ? null : filter_var($raw, FILTER_VALIDATE_FLOAT);
                if ($value === false || $value === null) {
                    throw new InvalidParameterException($name);
                }
                return $value;
            case 'boolean':
                // コアの rest_is_boolean() と同じ受理集合
                if (in_array($raw, [true, 1, '1', 'true'], true)) {
                    return true;
                }
                if (in_array($raw, [false, 0, '0', 'false'], true)) {
                    return false;
                }
                throw new InvalidParameterException($name);
            default:
                return is_bool($raw) ? ($raw ? '1' : '0') : (string)$raw;
        }
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
