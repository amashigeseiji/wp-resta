<?php
namespace Wp\Resta\StateMachine;

#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
final class Transition
{
    public function __construct(
        public readonly \UnitEnum $to,
        public readonly string $on,
    ) {}
}
