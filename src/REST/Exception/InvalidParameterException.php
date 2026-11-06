<?php
namespace Wp\Resta\REST\Exception;

/**
 * URL_PARAMS の regex に一致しない値が渡された
 *
 * WordPress コアの rest_invalid_param と同じ形式で 400 を返す。
 */
class InvalidParameterException extends HttpException
{
    public function __construct(private readonly string $param, ?string $detail = null)
    {
        $detail ??= "{$param} is invalid.";
        parent::__construct(
            "Invalid parameter(s): {$param}",
            400,
            'rest_invalid_param',
            ['params' => [$param => $detail]],
        );
    }

    public function getParam(): string
    {
        return $this->param;
    }
}
