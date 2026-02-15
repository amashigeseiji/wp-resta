<?php
namespace Wp\Resta\Hooks\Attributes;

use Attribute;
use BackedEnum;

/**
 * Attribute to register a method as a WordPress filter callback.
 *
 * Used to declaratively attach a method to a WordPress filter hook, mirroring
 * the parameters of the {@see add_filter()} function.
 *
 * @param string|BackedEnum $hook         The WordPress filter hook name this method should be attached to.
 *                                        Can be a string or a BackedEnum (e.g., RestApiHook::PRE_DISPATCH).
 * @param int               $priority     The priority at which the callback should run; lower numbers run first. Defaults to 10.
 * @param int               $acceptedArgs The number of arguments the callback accepts from WordPress. Defaults to 1.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class AddFilter
{
    public function __construct(
        public readonly string|BackedEnum $hook,
        public readonly int $priority = 10,
        public readonly int $acceptedArgs = 1
    ) {}

    /**
     * Get the hook name as a string.
     *
     * @return string
     */
    public function getHookName(): string
    {
        return $this->hook instanceof BackedEnum
            ? $this->hook->value
            : $this->hook;
    }
}
