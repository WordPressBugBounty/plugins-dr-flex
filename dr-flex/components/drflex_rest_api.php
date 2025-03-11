<?php

/**
 * Custom Dr. Flex REST API
 *
 * http://[...]/wp-json/drflex/v1/...
 *
 *
 * TBD:
 * 1. Permalinks
 * FIXME: On sites without pretty permalinks, the route is instead added to the URL as the rest_route parameter.
 * For the above example, the full URL would then be http://example.com/?rest_route=/wp/v2/posts/123
 *
 * @category    The Custom rest API for the Dr. Flex plugin.
 * @author      Johannes Dato
 * @copyright   Copyright (c) 2020
 * @link        https://dr-flex.de
 * @version     2.0.1
 */
include_once 'drflex_constants.php';
include_once 'drflex_utils.php';

/**
 * Method that registers the endpoints for the resources and the RPC requests.
 *
 * @since 1.0.0
 */
function drflex_register_custom_routes()
{
    register_rest_route($GLOBALS['drflex_wordpress_rest_prefix'], '/(/w)', array(
        'methods' => array(
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
        ),
        'callback' => function () {
            return new WP_REST_Response("The requested resource could not be found.", 404);
        },
        'permission_callback' => '__return_true',
    ));
}

/**
 * Wordpress add action statement to register custom routes on rest api init hook.
 *
 * @since 1.0.0
 */
add_action('rest_api_init', 'drflex_register_custom_routes');

/**
 * Interceptor function hooked to rest pre serve request.
 *
 * This function is called with every request on the rest api and decides whether it serves
 * a request before the API serves it or not. In this way the API can be completely generic
 * and the resources are matched at runtime.
 *
 * @since 1.0.0
 *
 * (From https://developer.wordpress.org/reference/hooks/rest_pre_serve_request/)
 * @param bool $served Whether the request has already been served. Default false.
 * @param WP_HTTP_Response $result Result to send to the client. Usually a WP_REST_Response.
 * @param WP_REST_Request $request Request used to generate the response.
 * @param WP_REST_Server $this Server instance.
 *
 */
function drflex_serve_static_resources($served, $result, $request, $server)
{
    $route = $request->get_route();

    $path_with_arguments = drflex_utils_parse_url_with_arguments($route);
    $path = $path_with_arguments['path'];
    // TODO check for the correct arguments in future release
    // $arguments = $path_with_arguments['arguments'];

    $file_uri = str_replace('/' . $GLOBALS['drflex_wordpress_rest_prefix'], '', $path);

    if ($file_uri != null) {
        $resource = drflex_cache_check_latest_resource_by_uri($file_uri);

        if (count($resource) > 0) {

            $vars = get_object_vars($resource[0]);
            $rdata = $vars['resource_data'];
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            if ($rdata != null) {
                // Resources case - if non rpc call route to the database and fetch the cached resource
                // drflex_debug("Resource call.");
                $if_none_match = $request->get_header("If-None-Match");

                if ($if_none_match == $vars['resource_hash_md5']) {
                    header($protocol . ' ' . 304 . ' ' . 'Not Modified');
                    header('Etag: ' . $vars['resource_hash_md5'], true);
                    echo '';
                } else {
                    header($protocol . ' ' . 200 . ' ' . 'OK');
                    header('Content-Type: ' . $vars['resource_mime_type'], true);
                    if ($vars['resource_encoding'] != null) {
                        header('Content-Encoding: ' . $vars['resource_encoding'], true);
                    }
                    header('Etag: ' . $vars['resource_hash_md5'], true);

                    // TODO Content encoding based on DB value
                    // $is_resource_compressed = in_array($vars['resource_uri'], $GLOBALS['drflex_compressed_resources']);
                    // if ($is_resource_compressed) {
                    //     header('Content-Encoding: br');
                    // }

                    echo $rdata;
                }

                $served = true;
            } else {
                // Proxy case - If RPC curl the Dr. Flex API and fetch the data
                // drflex_debug("RPC");

                $headers = $request->get_headers();
                $body = $request->get_body();

                $content = null;
                $content_type = null;
                $status = null;

                $request_headers = array();

                foreach($headers as $header => $header_value) {
                    $request_headers[$header] = $header_value[0];
                }

                $api_key = get_option('drflex_api_key', null);
                if ($api_key !== null) {
                    $request_headers["Authorization"] = "Bearer " . $api_key;
                }
                
                $url = $GLOBALS['drflex_host'] . $file_uri;
                $request_headers['content-type'] = 'application/binary';

                $args = array(
                    'headers' => $request_headers,
                    'body' => $body,
                );
                
                $response = null;
                $attenpts = 1;
                
                while ( ( ! is_array( $response ) || is_wp_error( $response ) ) && $attenpts < 4 ) {
                    $attenpts++;
                    $response = wp_remote_post( $url, $args );
                }
                
                if ( is_array( $response ) && ! is_wp_error( $response ) ) {
                    $headers = $response['headers'];
                    $body = $response['body'];

                    header($protocol . ' ' . 200 . ' ' . 'OK');
                    header('Content-type: application/octet-stream', true);
                    echo $body;

                    $served = true;
                } else {
                    $served = false;
                }
            }
        }
    }

    return $served;
}

/**
 * Wordpress add filter statement to register custom pre serve request function.
 * Hint: Important to set priority higher 10, to not interfere with default filter from wordpress.
 * (wp-includes/rest-api.php line 596)
 *
 * @since 1.0.0
 */
add_filter('rest_pre_serve_request', 'drflex_serve_static_resources', 11, 4);