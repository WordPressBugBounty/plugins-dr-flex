<?php

/**
 * 
 *
 * @category	The Database cache for the Dr. Flex plugins custom resources.
 * @author		Johannes Dato
 * @copyright	Copyright (c) 2020
 * @link		https://dr-flex.de
 * @version		2.0.0
 */
include_once('drflex_constants.php');
include_once('drflex_utils.php');

/**
 * Function to check if the drflex db cache is ready to be filled.
 * 
 * @return boolean true if ready, false if not
 */
function drflex_cache_db_ready()
{
    global $wpdb;

    $rs = $wpdb->get_results("SELECT count(1) FROM information_schema.tables WHERE table_name='{$wpdb->base_prefix}drflex_custom_resources';");
    $success = empty($wpdb->last_error);

    if (!empty($rs)) {
        $rs_vars = get_object_vars($rs[0]);

        if ($success && ($rs_vars['count(1)'] > 0)) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}


/**
 * Upgrade method since v0.9
 * 
 * @return boolean indicating success of upgrade
 */
function drflex_cache_upgrade_internal()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `{$wpdb->base_prefix}drflex_custom_resources` (
      id INT NOT NULL AUTO_INCREMENT,
      resource_uri VARCHAR(128) NOT NULL,
      resource_uri_arguments VARCHAR(128),
      resource_data LONGBLOB,
      resource_hash_md5 VARCHAR(32) NOT NULL,
      resource_mime_type VARCHAR(64),
      resource_encoding VARCHAR(8),
      rest_api_exposed BOOLEAN NOT NULL DEFAULT FALSE,
      permanent BOOLEAN NOT NULL DEFAULT FALSE,
      created_at datetime NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;
    
    CREATE TABLE `{$wpdb->base_prefix}drflex_config_items` (
      id INT NOT NULL AUTO_INCREMENT,
      config_item_key VARCHAR(128) NOT NULL,
      config_item_value VARCHAR(128),
      created_at datetime NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    $success = empty($wpdb->last_error);

    return $success;
}

/**
 * Check if database needs to be updated.
 * 
 * @return boolean indicating success of upgrade
 */
function drflex_plugin_database_needs_update() {
    $required_db_version = 2;
    $saved_db_version = (int) get_site_option('wp_drflex_cache_db_version');

    return $saved_db_version < $required_db_version;
}

/**
 * General upgrade method
 * 
 * @return boolean indicating success of upgrade
 */
function drflex_cache_upgrade()
{
    if (drflex_plugin_database_needs_update() && drflex_cache_upgrade_internal()) {
        if (!drflex_cache_clear_cache()) {
            drflex_error("Error clearing cache after db update.");
        }
        update_site_option('wp_drflex_cache_db_version', 2);
        return true;
    } else {
        return false;
    }
}

/**
 * Downgrade method since v0.9
 * 
 * @return boolean indicating success of downgrade
 */
function drflex_cache_downgrade_09()
{
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->base_prefix . "drflex_custom_resources;");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->base_prefix . "drflex_config_items;");
    
    $success = empty($wpdb->last_error);

    return $success;
}

/**
 * Gemeral downgrade method
 * 
 * @return boolean indicating success of downgrade
 */
function drflex_cache_downgrade()
{
    $saved_db_version = (int) get_site_option('wp_drflex_cache_db_version');

    if ($saved_db_version > 1 && drflex_cache_downgrade_09()) {
        delete_option('wp_drflex_cache_db_version');
        return true;
    } else {
        return false;
    }
}

// Data methods.
/**
 * Insert or update method.
 *
 * @param string $uri indicating the uri of the resource
 * @param blob $resource contains the data of the cached resource
 * @param string $mimetype of the cached resource
 * @param boolean $restExposed indicating if the resource is exposed through the wordpress rest api
 * @param boolean $permanent indicates if resource is permanent or should be updated in cahching process
 * @return boolean indicating success
 */
function drflex_cache_insert_or_update_resource($uri, $resource, $mimetype, $xContentEncoding, $restExposed = false, $permanent = false)
{
    $drflex_rest_prefix = drflex_utils_get_rest_route();

    global $wpdb;

    $path_with_arguments = drflex_utils_parse_url_with_arguments($uri);
    $path = $path_with_arguments['path'];
    $arguments = $path_with_arguments['arguments'];

    // Prepare override if exists
    $overrideResource = drflex_cache_check_latest_resource_by_uri($path);
    if (count($overrideResource) > 0) {
        if ($resource == null) return true;
        // FIXME when button configs feature is supported
        if ($path === "buttonConfigurations") return true;
        $delete_ids = array_map(function ($a) {
            $b = get_object_vars($a);
            return $b['id'];
        }, $overrideResource);
    } else {
        $delete_ids = null;
    }

    // debug($path);
    $hash = hash('md5', $resource);

    // Trim mime type
    if ($mimetype != null && $mimetype != '') {
        $mimetype = trim($mimetype);
        $mime = hash('md5', $mimetype);
        $hash = hash('md5', "$hash$mime");
    }
    
    // Trim x-content-encoding
    if ($xContentEncoding != null && $xContentEncoding != '') {
        $xContentEncoding = trim($xContentEncoding);
        $xce = hash('md5', $xContentEncoding);
        $hash = hash('md5', "$hash$xce");
    }
    
    // debug("Body: " . $hash);
    // if ($mimetype != null || $mimetype != '') {
        
        // debug("Mime: " . $mime);
    // }
    // debug("B+M : " . $hash);
    // debug("------------------------------");



    $wpdb->insert(
        $wpdb->base_prefix . 'drflex_custom_resources',
        array(
            'resource_uri' => $path,
            'resource_uri_arguments' => maybe_serialize($arguments),
            'resource_data' => $resource,
            'resource_hash_md5' => $hash,
            'resource_mime_type' => $mimetype,
            'resource_encoding' => $xContentEncoding,
            'rest_api_exposed' => $restExposed,
            'permanent' => $permanent,
            'created_at' => current_time('mysql', 1)
        )
    );

    $success = empty($wpdb->last_error);

    // Execute delete for override if exists
    if ($success & $delete_ids != null) {
        drflex_cache_delete_resources($delete_ids);
    }

    return $success;
}

/**
 * Delete resource by id.
 *
 * @param int $id of resource
 * @return boolean indicating success
 */
function drflex_cache_delete_resource($id)
{
    global $wpdb;
    $wpdb->query(
        "DELETE FROM " . $wpdb->base_prefix . "drflex_custom_resources 
        WHERE id = '" . $id . "';"
    );
    $success = empty($wpdb->last_error);

    return $success;
}

/**
 * Delete resource by uri.
 *
 * @param string $uri of resource
 * @return boolean indicating success
 */
function drflex_cache_delete_resource_by_uri($uri)
{
    global $wpdb;
    $wpdb->query(
        "DELETE FROM " . $wpdb->base_prefix . "drflex_custom_resources 
        WHERE resource_uri = '" . $uri . "';"
    );
    $success = empty($wpdb->last_error);

    return $success;
}

/**
 * Delete multiple resources by ids.
 *
 * @param array $delete_ids integer ids of resources
 * @return boolean indicating success
 */
function drflex_cache_delete_resources($delete_ids)
{
    global $wpdb;

    $where_str = "id = '" . implode("'OR id = '", $delete_ids) . "'";
    $qry = "DELETE FROM " . $wpdb->base_prefix . "drflex_custom_resources 
    WHERE " . $where_str . ";";

    $wpdb->query($qry);
    $success = empty($wpdb->last_error);

    return $success;
}

/**
 * Clears the cache.
 *
 * @param string $sql_exception exception uri of sql statement to exclude from clear step
 * @return boolean indicating success
 */
function drflex_cache_clear_cache($sql_exception = '')
{
    global $wpdb;
    $wpdb->query(
        "DELETE FROM " . $wpdb->base_prefix . "drflex_custom_resources
        WHERE resource_uri != '$sql_exception' AND PERMANENT != true;"
    );
    $wpdb->query(
        "DELETE FROM " . $wpdb->base_prefix . "drflex_config_items
        WHERE config_item_key = 'last_update_timestamp';"
    );
    
    $success = empty($wpdb->last_error);

    return $success;
}

/**
 * Checks the cache if the resource with uri exists
 *
 * @param string $uri to look for in cache
 * @return array with the queried elements or empty if not present in database
 */
function drflex_cache_check_latest_resource_by_uri($uri)
{
    global $wpdb;

    return $wpdb->get_results(
        "SELECT id, resource_uri, resource_mime_type, resource_data, created_at, resource_hash_md5, resource_encoding
        FROM " . $wpdb->base_prefix . "drflex_custom_resources 
        WHERE resource_uri = '" . $uri . "'
        ORDER BY id desc
        ;"
    );
}

/**
 * Count number of entries with particular resource hash
 *
 * @param string $resource_hash resource hash
 * @return int number of resources
 */
function drflex_cache_number_of_cached_entries($resource_hash)
{
    global $wpdb;
    $arr = get_object_vars($wpdb->get_results(
        "SELECT count(id)
        FROM " . $wpdb->base_prefix . "drflex_custom_resources 
        WHERE resource_hash_md5 = '" . $resource_hash . "'
        ;"
    )[0]);
    return $arr['count(id)'];
}

/**
 * Get all the items in cache
 * 
 * @return array with all the cached entries
 */
function drflex_cache_get_cache()
{
    global $wpdb;

    return $wpdb->get_results(
        "SELECT id, resource_uri, resource_mime_type, resource_hash_md5, resource_data, permanent
        FROM " . $wpdb->base_prefix . "drflex_custom_resources 
        ORDER BY id desc
        ;"
    );
}

/**
 * Insert a config item to config table
 *
 * @param string $config_item_key key of config item
 * @param string $config_item_value value of config item
 * @return boolean indicating success
 */
function drflex_cache_insert_config_item($config_item_key, $config_item_value)
{

    global $wpdb;

    $res = $wpdb->update(
        $wpdb->base_prefix . 'drflex_config_items',
        array(
            'config_item_key' => $config_item_key,
            'config_item_value' => $config_item_value,
            'created_at' => current_time('mysql', 1)
        ),
        array(
            'config_item_key' => $config_item_key
        )
    );

    if ($res < 1 && $res !== false) {
        $res = $wpdb->insert(
            $wpdb->base_prefix . 'drflex_config_items',
            array(
                'config_item_key' => $config_item_key,
                'config_item_value' => $config_item_value,
                'created_at' => current_time('mysql', 1)
            )
        );
    }
    
    if ($res === false) {
        error_log("An error occurred, while trying to insert config item.");
    }

    return  empty($wpdb->last_error);
}

/**
 * Get a config item from database by its key
 *
 * @param string $config_item_key
 * @return array config item found
 */
function drflex_cache_get_config_item($config_item_key)
{
    global $wpdb;

    return $wpdb->get_results(
        "SELECT id, config_item_value, created_at
        FROM " . $wpdb->base_prefix . "drflex_config_items
        WHERE config_item_key = '" . $config_item_key . "'
        ORDER BY id desc LIMIT 1
        ;"
    );
}