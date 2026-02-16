<?php
namespace Wp\Resta\REST\Example\Schemas;

use Wp\Resta\REST\Attributes\Schema\Property;
use Wp\Resta\REST\Schemas\ArrayType;

/**
 * array type の事例
 *
 * {@see Wp\Resta\REST\Example\Routes\Posts} を次のようにすると
 * 配列タイプとして認識される。
 *
 * ```
 * namespace Wp\Resta\REST\Example\Routes;
 * use Wp\Resta\REST\Example\Schemas\Posts as SchemasPosts;
 * use Wp\Resta\REST\Attributes\Envelope;
 *
 * [Envelope]
 * class Posts extends AbstractRoute
 * {
 *   public const SCHEMA = ['$ref' => SchemasPosts::ID];
 * }
 * ```
 * ただし、ここに独自の配列型を定義しても必要となるユースケースは少ないと考えるため、
 * サンプルとしてのみ残している。
 */
class Posts extends ArrayType
{
    public const ID = '#/components/schemas/Posts';

    /** @var Post[] */
    #[Property(['$ref' => Post::ID])]
    public array $items;
}
