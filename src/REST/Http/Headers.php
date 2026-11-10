<?php
namespace Wp\Resta\REST\Http;

/**
 * HTTP ヘッダ名は大文字小文字を区別しないので、表記が違う同名ヘッダも同じものとして合成する
 */
final class Headers
{
    /**
     * $base に $override を上書きマージする。同名（大文字小文字を区別しない）は $override が勝つ
     *
     * @param array<string, string> $base
     * @param array<string, string> $override
     * @return array<string, string>
     */
    public static function merge(array $base, array $override): array
    {
        $merged = $base;
        foreach ($override as $name => $value) {
            foreach (array_keys($merged) as $existing) {
                if (strcasecmp((string)$existing, (string)$name) === 0) {
                    unset($merged[$existing]);
                }
            }
            $merged[$name] = $value;
        }
        return $merged;
    }
}
