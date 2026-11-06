<?php
namespace Wp\Resta\REST\Exception;

/**
 * required な URL_PARAMS が渡されなかった
 *
 * WordPress コアの rest_missing_callback_param と同じ形式で 400 を返す。
 */
class MissingParameterException extends HttpException
{
    public function __construct(private readonly string $param)
    {
        parent::__construct(
            "Missing parameter(s): {$param}",
            400,
            'rest_missing_callback_param',
            ['params' => [$param]],
        );
    }

    public function getParam(): string
    {
        return $this->param;
    }
}
