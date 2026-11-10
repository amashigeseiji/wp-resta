<?php
namespace Wp\Resta\REST\Parameter;

/**
 * 1 リクエスト分の、検証と型変換を通った URL パラメータの値。RouteParameters::validate() の結果。
 *
 * 宣言（RouteParameters）がルートごとに 1 回作られるのに対し、これはリクエストごとに作られる。
 * 省略された省略可能なパラメータは含まれない（callback 側のデフォルト値が使われる）。
 */
final class BoundParameters
{
    /** @param array<string, mixed> $values 名前 => 検証・変換済みの値 */
    public function __construct(private readonly array $values)
    {
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }
}
