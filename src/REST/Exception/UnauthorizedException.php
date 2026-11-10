<?php
namespace Wp\Resta\REST\Exception;

/**
 * 認証が必要なルートに未認証でアクセスした
 *
 * WordPress コアの rest_not_logged_in と同じ形式で 401 を返す。authorize() から投げる。
 */
class UnauthorizedException extends HttpException
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $message = 'You are not currently logged in.', array $data = [])
    {
        parent::__construct($message, 401, 'rest_not_logged_in', $data);
    }
}
