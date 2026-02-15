<?php
namespace Wp\Resta\Hooks\Enum;

/**
 * WordPress REST API lifecycle hooks
 *
 * WordPress REST API のライフサイクルで使用される主要なフックを定義
 */
enum RestApiHook: string
{
    /**
     * Fires when preparing to serve a REST API request.
     *
     * Endpoint objects should be created and register their hooks on this action rather
     * than another action to ensure they're only loaded when needed.
     *
     * @since 4.4.0
     * @see https://developer.wordpress.org/reference/hooks/rest_api_init/
     */
    case API_INIT = 'rest_api_init';

    /**
     * Filters REST API authentication errors.
     *
     * This is used to pass a WP_Error from an authentication method back to
     * the API for JSON error formatting.
     *
     * @since 4.4.0
     * @param WP_Error|null|true $errors WP_Error if authentication error, null if authentication
     *                                   method wasn't used, true if authentication succeeded.
     * @return WP_Error|null|true
     * @see https://developer.wordpress.org/reference/hooks/rest_authentication_errors/
     */
    case AUTHENTICATION_ERRORS = 'rest_authentication_errors';

    /**
     * Filters the REST API cookie check errors.
     *
     * @since 4.4.0
     * @param WP_Error|true $result Error from cookie authentication or true if authenticated.
     * @return WP_Error|true
     * @see https://developer.wordpress.org/reference/hooks/rest_cookie_check_errors/
     */
    case COOKIE_CHECK_ERRORS = 'rest_cookie_check_errors';

    /**
     * Filters whether the REST API request has already been served.
     *
     * Allow sending the request manually. If `$served` is true, the result will not
     * be sent to the client and normal execution will stop.
     *
     * @since 4.4.0
     * @param bool                      $served  Whether the request has already been served.
     *                                           Default false.
     * @param WP_HTTP_Response|WP_Error $result  Result to send to the client. Usually a WP_REST_Response.
     * @param WP_REST_Request           $request Request used to generate the response.
     * @param WP_REST_Server            $server  Server instance.
     * @return bool
     * @see https://developer.wordpress.org/reference/hooks/rest_pre_serve_request/
     */
    case PRE_SERVE_REQUEST = 'rest_pre_serve_request';

    /**
     * Filters the pre-calculated result of a REST API dispatch request.
     *
     * Allow hijacking the request before dispatching by returning a non-empty. The returned value
     * will be used to serve the request instead.
     *
     * @since 4.4.0
     * @param mixed           $result  Response to replace the requested version with. Can be anything
     *                                 a normal endpoint can return, or null to not hijack the request.
     * @param WP_REST_Server  $server  Server instance.
     * @param WP_REST_Request $request Request used to generate the response.
     * @return mixed
     * @see https://developer.wordpress.org/reference/hooks/rest_pre_dispatch/
     */
    case PRE_DISPATCH = 'rest_pre_dispatch';

    /**
     * Filters the REST API dispatch request result.
     *
     * Allow plugins to override dispatching the request.
     *
     * @since 4.4.0
     * @param mixed           $dispatch_result Dispatch result, will be used if not empty.
     * @param WP_REST_Request $request         Request used to generate the response.
     * @param string          $route           Route matched for the request.
     * @param array           $handler         Route handler used for the request.
     * @return mixed
     * @see https://developer.wordpress.org/reference/hooks/rest_dispatch_request/
     */
    case DISPATCH_REQUEST = 'rest_dispatch_request';

    /**
     * Filters the REST API response before executing any REST API callbacks.
     *
     * Allows plugins to perform additional validation after a request is initialized and matched to a
     * registered route, but before it is executed.
     *
     * @since 4.7.0
     * @param WP_HTTP_Response|WP_Error $response Result to send to the client. Usually a WP_REST_Response
     *                                            or WP_Error.
     * @param array                     $handler  Route handler used for the request.
     * @param WP_REST_Request           $request  Request used to generate the response.
     * @return WP_HTTP_Response|WP_Error
     * @see https://developer.wordpress.org/reference/hooks/rest_request_before_callbacks/
     */
    case REQUEST_BEFORE_CALLBACKS = 'rest_request_before_callbacks';

    /**
     * Filters the REST API response after executing any REST API callbacks.
     *
     * Allows plugins to modify the response after executing the REST API callback.
     *
     * @since 4.4.0
     * @param WP_HTTP_Response|WP_Error $response Result to send to the client. Usually a WP_REST_Response
     *                                            or WP_Error.
     * @param array                     $handler  Route handler used for the request.
     * @param WP_REST_Request           $request  Request used to generate the response.
     * @return WP_HTTP_Response|WP_Error
     * @see https://developer.wordpress.org/reference/hooks/rest_request_after_callbacks/
     */
    case REQUEST_AFTER_CALLBACKS = 'rest_request_after_callbacks';

    /**
     * Filters the REST API response after dispatching.
     *
     * Allows modification of the response after dispatching. Note that while a filter,
     * this also runs after responses are finalized.
     *
     * @since 4.4.0
     * @param WP_HTTP_Response $result  Result to send to the client. Usually a WP_REST_Response.
     * @param WP_REST_Server   $server  Server instance.
     * @param WP_REST_Request  $request Request used to generate the response.
     * @return WP_HTTP_Response
     * @see https://developer.wordpress.org/reference/hooks/rest_post_dispatch/
     */
    case POST_DISPATCH = 'rest_post_dispatch';

    /**
     * Filters the REST API response before it is echoed out.
     *
     * Allows modification of the response data right before output.
     *
     * @since 4.4.0
     * @param array            $result  Response data to send to the client.
     * @param WP_REST_Server   $server  Server instance.
     * @param WP_REST_Request  $request Request used to generate the response.
     * @return array
     * @see https://developer.wordpress.org/reference/hooks/rest_pre_echo_response/
     */
    case PRE_ECHO_RESPONSE = 'rest_pre_echo_response';

    /**
     * Fires after a REST API response is echoed.
     *
     * @since 4.4.0
     * @see https://developer.wordpress.org/reference/hooks/rest_post_echo_response/
     */
    case POST_ECHO_RESPONSE = 'rest_post_echo_response';

    /**
     * Filters the REST API response for the index.
     *
     * @since 4.4.0
     * @param WP_REST_Response $response Response data.
     * @return WP_REST_Response
     * @see https://developer.wordpress.org/reference/hooks/rest_index/
     */
    case INDEX = 'rest_index';

    /**
     * Filters the array of available REST API endpoints.
     *
     * @since 4.4.0
     * @param array $endpoints The available endpoints. An array of matching regex patterns, each mapped
     *                         to an array of callbacks for the endpoint. These take the format
     *                         `'/path/regex' => array( $callback, $bitmask )` or
     *                         `'/path/regex' => array( array( 'callback' => $callback, 'methods' => $bitmask ) )`.
     * @return array
     * @see https://developer.wordpress.org/reference/hooks/rest_endpoints/
     */
    case ENDPOINTS = 'rest_endpoints';

    /**
     * Filters the list of response headers that are allowed for REST API CORS requests.
     *
     * @since 5.5.0
     * @param string[] $allow_headers The list of response headers to allow.
     * @return string[]
     * @see https://developer.wordpress.org/reference/hooks/rest_allowed_cors_headers/
     */
    case ALLOWED_CORS_HEADERS = 'rest_allowed_cors_headers';

    /**
     * Filters the REST API validation check for a request argument.
     *
     * @since 5.5.0
     * @param true|WP_Error $validity   Validation result. True if valid, WP_Error if invalid.
     * @param mixed         $value      The value being validated.
     * @param array         $param      The parameter schema array.
     * @param string        $param_key  Parameter name.
     * @return true|WP_Error
     * @see https://developer.wordpress.org/reference/hooks/rest_validate_value_from_schema/
     */
    case VALIDATE_VALUE_FROM_SCHEMA = 'rest_validate_value_from_schema';

    /**
     * Filters the REST API sanitization check for a request argument.
     *
     * @since 5.5.0
     * @param mixed  $value     The sanitized value.
     * @param array  $param     The parameter schema array.
     * @param string $param_key Parameter name.
     * @return mixed
     * @see https://developer.wordpress.org/reference/hooks/rest_sanitize_value_from_schema/
     */
    case SANITIZE_VALUE_FROM_SCHEMA = 'rest_sanitize_value_from_schema';

    /**
     * Filters the parameter priority order for a REST API request.
     *
     * The order affects which parameters are checked when using `get_param()` and family.
     * This acts similarly to PHP's `request_order` setting.
     *
     * @since 5.4.0
     * @param string[]        $order   Array of types to check, in order of priority.
     * @param WP_REST_Request $request The request object.
     * @return string[]
     * @see https://developer.wordpress.org/reference/hooks/rest_request_parameter_order/
     */
    case REQUEST_PARAMETER_ORDER = 'rest_request_parameter_order';

    /**
     * Filters the REST URL prefix.
     *
     * @since 4.4.0
     * @param string $prefix URL prefix. Default 'wp-json'.
     * @return string
     * @see https://developer.wordpress.org/reference/hooks/rest_url_prefix/
     */
    case URL_PREFIX = 'rest_url_prefix';

    /**
     * Filters whether to send no-cache headers on a REST API request.
     *
     * @since 4.4.0
     * @param bool $rest_send_nocache_headers Whether to send no-cache headers.
     * @return bool
     * @see https://developer.wordpress.org/reference/hooks/rest_send_nocache_headers/
     */
    case SEND_NOCACHE_HEADERS = 'rest_send_nocache_headers';
}
