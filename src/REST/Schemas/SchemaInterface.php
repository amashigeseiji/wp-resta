<?php
namespace Wp\Resta\REST\Schemas;

interface SchemaInterface
{
    public const ID = null;
    public const DESCRIPTION = null;
    /**
     * @return array<string, mixed>
     */
    public static function describe(): array;
    public static function getSchemaId(): string;
}
