<?php
namespace Wp\Resta\REST\Parameter;

use LogicException;

/**
 * URL パラメータの regex を、値全体に照合する形に組み立てる。
 * WP コアの args に渡す pattern と、wp-resta 側の preg_match の両方で使う。
 */
final class ParameterRegex
{
    /** regex が無い（制約なし）パスパラメータは次の / までの任意の文字列。
     *  Unicode に依存しないので、WP コアの照合（u なし）と wp-resta の照合（u あり）で結果が揃う */
    public const PATH_SEGMENT = '[^/]+';

    /**
     * preg_match の区切り文字の候補。regex に含まれていないものを先頭から順に選ぶ。
     * / は '[a-z/]+' のように URL 向けの regex に現れやすいので候補に入れない
     */
    private const DELIMITERS = ['#', '~', '%', '@', '!', '`', ';'];

    /**
     * 値全体に対して照合する形にする（区切り文字なし）。
     *
     * - ^(?:...)$ で囲んで部分一致を許さない。(?:...) は 'a|b' が '^a|b$' に化けるのを防ぐため
     * - (*UTF) (*UCP) のような先頭専用の指示子はパターンの先頭にしか置けないので、^(?:...) の外に出す
     */
    public static function anchor(string $regex): string
    {
        $verbs = '';
        if (preg_match('/^(?:\(\*[A-Z_]+(?:=\d+)?\))+/', $regex, $m) === 1) {
            $verbs = $m[0];
            $regex = substr($regex, strlen($verbs));
        }
        return $verbs . '^(?:' . $regex . ')$';
    }

    /**
     * anchor() の結果を preg_match に渡せるパターンにする。
     *
     * - 区切り文字は regex に含まれていない文字を選ぶ。エスケープで対処すると、regex 中の '\#' や
     *   '\Q...\E' の引用範囲まで書き換えてしまい、元々有効だった regex が壊れる
     * - u 修飾子で UTF-8 モードにする。WP コアの rest_validate_json_schema_pattern() も u 付きなので判定が
     *   揃う。PHP の u は Unicode プロパティも有効にするため、\w が日本語などの文字にも一致し、.{n} が
     *   バイト数ではなく文字数になる
     * - D 修飾子で $ が末尾の改行の手前にマッチしないようにする
     *
     * @param string $owner エラーメッセージに出す宣言元（クラス名など）
     */
    public static function compile(string $regex, string $owner = 'URL_PARAMS'): string
    {
        $anchored = self::anchor($regex);
        foreach (self::DELIMITERS as $delimiter) {
            if (!str_contains($anchored, $delimiter)) {
                return $delimiter . $anchored . $delimiter . 'uD';
            }
        }
        throw new LogicException(sprintf(
            '%s regex "%s" contains every delimiter candidate (%s).',
            $owner,
            $regex,
            implode('', self::DELIMITERS),
        ));
    }

    /**
     * WP コアの pattern として渡せる regex か。コンパイルできない regex は pattern を付けずに
     * wp-resta 側の検証に到達させ、そちらで 500 とログにする
     */
    public static function isCompilable(string $regex): bool
    {
        try {
            $compiled = self::compile($regex);
        } catch (LogicException) {
            return false;
        }
        return @preg_match($compiled, '') !== false;
    }
}
