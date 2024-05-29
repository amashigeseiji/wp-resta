<?php
namespace Wp\Resta\REST\Attributes\Schema;

use Attribute;

#[Attribute]
class Property
{
    /**
     * @var array<string, mixed>
     */
    public readonly array $define;

    /**
     * @param array<string, mixed> $define
     */
    public function __construct(array $define)
    {
        $this->define = $define;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->define;
    }
}
