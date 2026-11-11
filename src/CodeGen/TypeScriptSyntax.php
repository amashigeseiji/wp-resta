<?php
namespace Wp\Resta\CodeGen;

/**
 * TypeScript の構文片を組み立てる。ジェネレータ間で共有する
 */
final class TypeScriptSyntax
{
    /**
     * 文字列リテラル。引用符・バックスラッシュ・改行をエスケープした単一引用符の形
     */
    public static function stringLiteral(string $value): string
    {
        return "'" . strtr($value, [
            '\\' => '\\\\',
            "'" => "\\'",
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]) . "'";
    }

    /**
     * リテラルの union。文字列は stringLiteral()、数値はそのまま
     *
     * @param list<mixed> $values
     */
    public static function literalUnion(array $values): string
    {
        return implode(' | ', array_map(
            fn(mixed $value): string => is_string($value) ? self::stringLiteral($value) : (string) json_encode($value),
            $values,
        ));
    }

    /**
     * 要素型の配列。要素型が union なら括弧で囲む（'a' | 'b'[] は 'a' | ('b'[]) と読まれる）
     */
    public static function arrayOf(string $elementType): string
    {
        return (str_contains($elementType, ' | ') ? "({$elementType})" : $elementType) . '[]';
    }
}
