<?php
namespace Wp\Resta\StateMachine;

use Wp\Resta\StateMachine\Transition;

trait TransitionMetadata
{
    /**
     * Transition アトリビュートから action → from state のマッピングを取得します。
     *
     * このメソッドは enum に定義されたすべての Transition を走査し、
     * アクション名をキー、遷移元の状態を値とする配列を返します。
     * 結果は静的にキャッシュされます。
     *
     * @return array<string, static> アクション名 => 遷移元状態のマップ
     */
    public static function actions(): array
    {
        static $result = [];
        if (isset($result[static::class])) {
            return $result[static::class];
        }
        $result[static::class] = [];
        foreach ((new \ReflectionEnum(static::class))->getCases() as $case) {
            foreach ($case->getAttributes(Transition::class) as $attr) {
                $value = $case->getValue();
                $result[static::class][$attr->newInstance()->on] = $value;
            }
        }
        return $result[static::class];
    }
}
