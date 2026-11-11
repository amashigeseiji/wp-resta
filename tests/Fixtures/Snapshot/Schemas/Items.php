<?php
namespace Test\Resta\Fixtures\Snapshot\Schemas;

use Wp\Resta\REST\Attributes\Schema\Property;
use Wp\Resta\REST\Schemas\ArrayType;

class Items extends ArrayType
{
    public const DESCRIPTION = 'Snapshot item list';

    /** @var Item[] */
    #[Property(['$ref' => '#/components/schemas/Item'])]
    public array $items;
}
