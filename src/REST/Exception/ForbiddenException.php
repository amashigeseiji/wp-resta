<?php
namespace Wp\Resta\REST\Exception;

/**
 * 認証済みだが、そのルートを使う権限がない
 *
 * WordPress コアの rest_forbidden と同じ形式で 403 を返す。authorize() から投げる。
 */
class ForbiddenException extends HttpException
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $message = 'Sorry, you are not allowed to do that.', array $data = [])
    {
        parent::__construct($message, 403, 'rest_forbidden', $data);
    }
}
