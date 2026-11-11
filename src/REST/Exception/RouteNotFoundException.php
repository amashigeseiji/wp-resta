<?php
namespace Wp\Resta\REST\Exception;

/**
 * ルートはあるが、リクエストの HTTP メソッドに対応する操作が宣言されていない
 *
 * WordPress コアの rest_no_route と同じ形式で 404 を返す。
 */
class RouteNotFoundException extends HttpException
{
    public function __construct(string $message = 'No route was found matching the URL and request method.')
    {
        parent::__construct($message, 404, 'rest_no_route');
    }
}
