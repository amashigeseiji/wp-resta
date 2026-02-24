<?php
namespace Wp\Resta\StateMachine;

use Wp\Resta\StateMachine\Transition;

trait TransitionMetadata
{
    /**
     * @return array<string, static>
     */
    public static function actions(): array
    {
        static $result;
        if (!$result) {
            $result = [];
        }
        if (isset($result[static::class])) {
            return $result[static::class];
        }
        $result[static::class] = [];
        foreach ((new \ReflectionEnum(static::class))->getCases() as $case) {
            foreach ($case->getAttributes(Transition::class) as $attr) {
                /** @var self */
                $value = $case->getValue();
                $result[static::class][$attr->newInstance()->on] = $value;
            }
        }
        return $result[static::class];
    }
}
