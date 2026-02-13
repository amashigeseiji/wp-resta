<?php
namespace Wp\Resta\Hooks\Attributes;

use Attribute;

/**
 * Attribute to register a method as a WordPress filter callback.
 *
 * Used to declaratively attach a method to a WordPress filter hook, mirroring
 * the parameters of the {@see add_filter()} function.
 *
 * @param string $hook         The WordPress filter hook name this method should be attached to.
 * @param int    $priority     The priority at which the callback should run; lower numbers run first. Defaults to 10.
 * @param int    $acceptedArgs The number of arguments the callback accepts from WordPress. Defaults to 1.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class AddFilter
{
    public function __construct(
        public readonly string $hook,
        public readonly int $priority = 10,
        public readonly int $acceptedArgs = 1
    ) {}
}
