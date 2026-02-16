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
 *   public const SCHEMA = ['$ref' => SchemasPosts::ID];
 * ```
 *
 * Route クラス内で以下のように記述することと実質的に等価
 *
 * ```
 *   public const SCHEMA = [
 *       'type' => 'array',
 *       'items' => [
 *           '$ref' => Post::ID
 *       ],
 *   ];
 * ```
 *
 *
 * ただし、ここに独自の配列型を定義しても必要となるユースケースは少ないと考えるため、
 * サンプルとしてのみ残している。
 *
 */
class Posts extends ArrayType
{
    public const ID = '#/components/schemas/Posts';

    /** @var Post[] */
    #[Property(['$ref' => Post::ID])]
    public array $items;
}
