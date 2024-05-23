<?php
namespace Wp\Resta\REST\Attributes;

use Attribute;

#[Attribute]
class RouteMeta
{
    public readonly string $description;
    public readonly array $tags;

    public function __construct(string $description = '', array $tags = [])
    {
        $this->description = $description;
        $this->tags = $tags;
    }
}
