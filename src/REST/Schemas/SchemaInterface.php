<?php
namespace Wp\Resta\REST\Schemas;

interface SchemaInterface
{
    /**
     * @return array<string, mixed>
     */
    public static function describe(): array;
}
