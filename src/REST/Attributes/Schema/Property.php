<?php
namespace Wp\Restafari\REST\Attributes\Schema;

use Attribute;

#[Attribute]
class Property
{
    public readonly array $define;

    public function __construct(array $define)
    {
        $this->define = $define;
    }

    public function toArray(): array
    {
        return $this->define;
    }
}
