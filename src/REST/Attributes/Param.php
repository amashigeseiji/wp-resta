<?php
namespace Wp\Resta\REST\Attributes;

use Attribute;

/**
 * callback の引数に付けて、PHP の型では表現できない URL パラメータの制約を宣言する。
 *
 * <code>
 * public function callback(
 *     #[Param(pattern: '[a-z/]+', description: 'スラッシュ区切りのパス')] ?string $path = null,
 *     #[Param(min: 1, max: 100)] int $per_page = 10,
 * ): array
 * </code>
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Param
{
    public function __construct(
        /** 値全体に照合する正規表現（区切り文字なし） */
        public readonly ?string $pattern = null,
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
        public readonly ?string $description = null,
    ) {}
}
