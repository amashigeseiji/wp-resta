<?php
namespace Test\Resta\Fixtures\Snapshot\Schemas;

use Wp\Resta\REST\Schemas\ObjectType;

class Item extends ObjectType
{
    public const DESCRIPTION = 'Snapshot item';

    public int $id;
    public string $name;
    public ?string $note;
    /** 要素型を metadata() で宣言した配列 */
    public array $tags;
    /** 要素型のわからない配列。items は {} になる */
    public array $extra;

    public static function metadata(): array
    {
        return [
            'id' => ['description' => 'Item ID'],
            'tags' => ['items' => ['type' => 'string']],
        ];
    }
}
