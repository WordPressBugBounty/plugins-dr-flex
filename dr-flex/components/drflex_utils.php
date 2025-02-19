<?php

/**
 * 
 *
 * @category	The Dr. Flex plugin utility functions.
 * @author		Johannes Dato
 * @copyright	Copyright (c) 2020
 * @link		https://dr-flex.de
 * @version		2.0.0
 */
include_once('drflex_constants.php');

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
// Plugin specific utilities.

/**
 * Fetches a resource from a URL.
 *
 * Function to fetch a resource from a URL and return it in an array in the following format:
 * array(
 *   'content' => 'This is an example result.',
 *   'content-type'   => 'text/plain'
 * );
 * 
 * @since 1.0.0
 *
 * @param string $url The url to fetch a resource from.
 * @return array|false Array with content and content-type of the requested resource or false if request results in error.
 */
function drflex_utils_fetch_from_url($url, $is_dr_flex_host = false)
{
    $content = null;
    $content_type = null;
    $status = null;

    $request_headers = array(
    );
    
    $api_key = get_option('drflex_api_key', null);
    if ($api_key !== null) {
        $request_headers["Authorization"] = "Bearer " . $api_key;
    }

    $encodings = explode(', ', $_SERVER['HTTP_ACCEPT_ENCODING']);
    if (in_array('br', $encodings)) {
        $request_headers["X-Accept-Encoding"] = "br";
    }
    
    if ($is_dr_flex_host) {
        $url = $GLOBALS['drflex_host'] . $url;
    }
    
    $args = array(
        "headers" => $request_headers,
    );
    
    $response = null;    
    $attenpts = 1;
    
    $was_request_successful = false;
    while ( ( !$was_request_successful ) && $attenpts < 4 ) {
        $attenpts++;

        $response = wp_remote_get( $url, $args );
        $was_request_successful = ! is_array( $response ) || is_wp_error( $response );
    }
    
    if ( is_array( $response ) && ! is_wp_error( $response ) ) {
        $headers = $response['headers'];
        $content_type = $headers["Content-Type"];
        $x_content_encoding = $headers["X-Content-Encoding"];
        $status = $response["response"]["code"];
        $content = $response['body'];
    } else {
        drflex_error("Could not receive a response for url: $url.");
        return false;
    }

    return array(
        'content' => $content,
        'content-type' => $content_type,
        'x-content-encoding' => $x_content_encoding,
        'status' => $status
    );
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
// General utilities.

/**
 * Checks if a target string ends with an end string.
 * 
 * @since 1.0.0
 *
 * @param string $target String that will be checked.
 * @param string $end sequence that the target string will be checked for.
 * @return boolean Boolean indicating whether the target string ends with end.
 * 
 */
function drflex_utils_ends_with($target, $end)
{
    $length = strlen($end);
    if (!$length) {
        return true;
    }
    return substr($target, -$length) === $end;
}

/**
 * Unescapes an escaped JSON document.
 * 
 * The following rules are applied with the unescaping process: 
 * 
 * Newline is replaced with \n
 * Carriage return is replaced with \r
 * Double quote is replaced with \"
 * Backslash is replaced with \\
 * 
 * @since 1.0.0
 *
 * @param string $escaped_json The json string to be unescaped.
 * @return string The unescaped json string.
 * 
 */
function drflex_utils_json_unescape($escaped_json)
{
    $result = str_replace("\\\\", "\\", $escaped_json);
    $result = str_replace("\\\"", "\"", $result);
    $result = str_replace("\\r", "\r", $result);
    $result = str_replace("\\n", "\n", $result);
    return $result;
}

/**
 * Function to separate a url and its arguments
 * 
 * When supplied with a given URL e.g. https://example.com?arg1=foo&arg2=bar
 * it returns the following array:
 * 
 *   array(
 *        'path' => 'https://example.com',
 *        'arguments' => 
 *          array(
 *            'arg1' = 'foo',
 *            'arg2' = 'bar',
 *          )
 *    );
 *  
 * @since 1.0.0
 *
 * @param string $url_with_args URL with arguments
 * @return array path and arguments separated
 * 
 */
function drflex_utils_parse_url_with_arguments($url_with_args)
{
    // Parse arguments
    $path_and_arguments = explode('?', $url_with_args);
    $path = $path_and_arguments[0];
    $parsed_arguments = array();

    if (count($path_and_arguments) > 1) {
        $arguments = $path_and_arguments[1];

        if (!empty($arguments)) {
            $args_array = explode('&', $arguments);
            if (!empty($args_array)) {
                foreach ($args_array as $arg) {
                    if ($arg != '') {
                        $arg_map = explode('=', $arg);
                        if (!empty($arg_map)) {
                            if (count($arg_map) ==  2) {
                                $parsed_arguments[$arg_map[0]] = $arg_map[1];
                            } else if (count($arg_map) == 1) {
                                $parsed_arguments[$arg_map[0]] = 'no_value';
                            }
                        }
                    }
                }
            }
        }
    }

    return array(
        'path' => $path,
        'arguments' => $parsed_arguments
    );
}

/**
 * Function that returns the rest route that is the base rest route for all the cache requests.
 * 
 * @return string rest route base url
 */
function drflex_utils_get_rest_route()
{
    return '?rest_route=/' . $GLOBALS['drflex_wordpress_rest_prefix'] . '/';
}