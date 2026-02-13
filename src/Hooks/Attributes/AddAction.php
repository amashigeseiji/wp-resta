<?php
namespace Wp\Resta\Hooks\Attributes;

use Attribute;

/**
 * Attribute to register a method as a WordPress action callback.
 *
 * When applied to a method, this attribute describes which WordPress action
 * the method should be attached to, as well as its execution priority and
 * the number of arguments it expects from WordPress.
 *
 * @param string $hook         The WordPress action name to hook into.
 * @param int    $priority     The priority at which the callback should run
 *                             (lower values run earlier, default is 10).
 * @param int    $acceptedArgs The number of arguments the callback accepts
 *                             from WordPress (default is 1).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class AddAction
{
    public function __construct(
        public readonly string $hook,
        public readonly int $priority = 10,
        public readonly int $acceptedArgs = 1
    ) {}
}
