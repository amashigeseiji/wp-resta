<?php
namespace Wp\Resta\REST\Http;

use WP_REST_Response;

/**
 * wp-resta のレスポンスを WordPress のレスポンスに変換する。WP 境界
 */
final class WpRestaResponse
{
    public static function toWp(RestaResponseInterface $response): WP_REST_Response
    {
        $wpResponse = new WP_REST_Response($response->getData(), $response->getStatusCode());
        foreach ($response->getHeaders() as $name => $value) {
            $wpResponse->header($name, $value);
        }
        return $wpResponse;
    }
}
