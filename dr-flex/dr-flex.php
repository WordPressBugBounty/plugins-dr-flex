<?php
/*
Plugin Name: Dr. Flex
Plugin URI: https://dr-flex.de/
Description: The official Dr. Flex® Wordpress plugin for easy integration of the Dr. Flex® booking tool on your website.
Version: 2.0.1
Requires at least: 5.0
Author: Dr. Flex
Author URI: https://dr-flex.de
Text Domain: dr-flex
Domain Path: /languages
License: GNU GPLv3
Contributors: Johannes Dato
 */

/*
Copyright (C) 2020 Dr. Flex® GmbH, https://dr-flex.de

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 3 of the License.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('ABSPATH')) {
    return;
}

include_once 'components/drflex_constants.php';
include_once 'components/drflex_cache.php';
include_once 'components/drflex_utils.php';
include_once 'components/drflex_rest_api.php';
include_once 'components/drflex_shortcode.php';

/**
 * Debug logger.
 */
function drflex_debug($log)
{
    if (true === WP_DEBUG) {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log($log);
        }
    }
}

/**
 * Error logger for debugging.
 */
function drflex_error($message)
{
    if (is_array($message)) {
        $message = json_encode($message);
    }
    $file = fopen("drflex_error.log", "a");
    fwrite($file, '[' . date('j-M-Y H:i:s e') . "] " . $message . "\n");
    fclose($file);
}

////////////////////////////////////////////////////////////////////////////////////////////////////////

// Activation and Deactivation Hooks.

/**
 * On activation of plugin.
 */
function drflex_activate()
{
    if (!drflex_cache_upgrade()) {
        drflex_error('Upgrade failed.');
    }
    // else {
    //     drflex_debug('Upgrade succeeded.');
    // }
    // debug("Dr.Flex plugin activated!");
}

register_activation_hook(__FILE__, 'drflex_activate');

/**
 * On deactivation of plugin.
 */
function drflex_deactivate()
{
    if (drflex_cache_downgrade()) {
        delete_option('drflex_connection_status');
        delete_option('drflex_api_key');
        delete_option('drflex_callback_textarea');
        delete_option('drflex_button_configs');
        // debug('Downgrade succeeded.');
    } else {
        drflex_error('Downgrade failed.');
    }
    // debug("Dr.Flex plugin deactivated!");
}

register_deactivation_hook(__FILE__, 'drflex_deactivate');

////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Initialize Plugin Internationalization.
*/
function drflex_load_plugin_textdomain() {
    load_plugin_textdomain( 'dr-flex', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}

add_action( 'plugins_loaded', 'drflex_load_plugin_textdomain' );

/**
 * Registers stylesheets and scripts for admin page.
 */
function drflex_register_plugin_styles_and_scripts()
{
    $p = plugin_dir_path( __FILE__ );
    
    // At first styles - order matters!
    $styles = array(
        'codemirror' => 'css/codemirror.css',
        'drflex' => 'css/drflex.css'
    );

    // Second scripts
    $scripts = array(
        'drflex' => 'js/drflex.js',
        'codemirror' => 'js/codemirror.js',
        'cmjavascript' => 'js/javascript.js'
    );

    foreach ($styles as $handle => $uri) {
        wp_register_style($handle, plugins_url('dr-flex/' . $uri), array(), date('YmdHis', filemtime($p . $uri)));
        wp_enqueue_style($handle);
    }

    foreach ($scripts as $handle => $uri) {
        wp_register_script($handle, plugins_url('dr-flex/' . $uri), array(), date('YmdHis', filemtime($p . $uri)));
        wp_enqueue_script($handle);
    }
}

// Register style sheets and scripts.
add_action('admin_enqueue_scripts', 'drflex_register_plugin_styles_and_scripts');

/**
 * Registers stylesheets and scripts for site.
 */
function drflex_register_styles_and_scripts()
{
    $p = plugin_dir_path(__FILE__);

    // At first styles - order matters!
    $styles = array(
        'drflex-site' => 'css/drflex-site.css'
    );

    foreach ($styles as $handle => $uri) {
        wp_register_style($handle, plugins_url('dr-flex/' . $uri), array(), date('YmdHis', filemtime($p . $uri)));
        wp_enqueue_style($handle);
    }

    if (get_option('drflex_callback_textarea', null) != null) {
        $handle = $GLOBALS['drflex_callback_function_file_name'];
        wp_register_script($handle, drflex_utils_get_rest_route() . $handle, array(), date('YmdHis', filemtime($p . $uri)));
        wp_enqueue_script($handle);
    }

    $corejs = drflex_cache_check_latest_resource_by_uri('generatedEmbedScript');

    if (count($corejs) > 0) {
        $vars = get_object_vars($corejs[0]);

        $content = $vars['resource_data'];
        wp_register_script('generated-embed-script', false);
        wp_enqueue_script('generated-embed-script');
        wp_add_inline_script('generated-embed-script', $content);
    }
}

add_action('wp_enqueue_scripts', 'drflex_register_styles_and_scripts');

////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Function to create the Dr. Flex Admin Menu Page Settings
 *
 */
function drflex_register_plugin_settings()
{
    register_setting('drflex-plugin-settings-group', 'drflex_connection_status');
    register_setting('drflex-plugin-settings-group', 'drflex_api_key');
    register_setting('drflex-plugin-settings-group', 'drflex_callback_textarea');
    register_setting('drflex-plugin-settings-group', 'drflex_button_configs');
}

/**
 * Function to create the Dr. Flex Admin Menu Page
 * 
 */
function drflex_plugin_create_menu()
{
    $title = __('Dr. Flex', 'dr-flex');
    //create new top-level menu
    add_menu_page($title, $title, 'administrator', 'drflex_plugin_settings', 'drflex_plugin_settings_page', 'dashicons-calendar-alt');
    add_submenu_page('drflex_plugin_settings', $title, __('Configuration', 'dr-flex'), 'administrator', 'drflex_plugin_settings', '');

    add_submenu_page('drflex_plugin_settings', $title, __('Button integration', 'dr-flex'), 'administrator', 'drflex_plugin_settings_integration', 'drflex_plugin_settings_integration_subpage');
    add_submenu_page('drflex_plugin_settings', $title, __('Code examples', 'dr-flex'), 'administrator', 'drflex_plugin_settings_examples', 'drflex_plugin_settings_examples_subpage');

    //call register settings function
    add_action('admin_init', 'drflex_register_plugin_settings');
}

// create custom plugin settings menu
add_action('admin_menu', 'drflex_plugin_create_menu');



function drflex_heading($title)
{
    ?> <h1><?php echo get_admin_page_title(); ?> › <?php echo $title; ?> </h1> <?php
}

function drflex_plugin_settings_page()
{
    $fn_arr = explode('(', $GLOBALS['drflex_callback_function_name']);
    $fn_name = $fn_arr[0];
    $fn_arg = rtrim($fn_arr[1], ")");

    ?>
<div class="wrap">
    <?php drflex_heading(__('Plugin Settings', 'dr-flex'));?>

    <form action="options.php" method="post">

        <?php settings_fields('drflex-plugin-settings-group');?>
        <?php do_settings_sections('drflex-plugin-settings-group');?>

        <h2><?php _e("Settings for the booking tool on your website", 'dr-flex') ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                <th scope="row"><label for="drflex_connection_status"><?php _e("Connection status", 'dr-flex') ?></label></th>
                <td><input class="regular-text" id="drflex_connection_status"
                        name="drflex_connection_status" title="<?php _e("Connection status", 'dr-flex') ?>" type="hidden"
                        value="">
                        <p class="help">
                            <?php echo drflex_check_status(); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="drflex_api_key"><?php _e("Dr. Flex API Key", 'dr-flex') ?></label></th>
                    <td><input class="regular-text" id="drflex_api_key" name="drflex_api_key"
                        placeholder="<?php _e("Please enter your Dr. Flex API Key", 'dr-flex') ?>" title="<?php _e("Dr. Flex API Key", 'dr-flex') ?>" type="password"
                            value="<?php echo esc_attr(get_option('drflex_api_key')); ?>">
                        <span class="dashicons dashicons-visibility" id="togglePassword"></span>
                        <p class="help">
                            <?php printf( '%s<br>%s<br>%s%s', 
                            __('The API key can be found in the settings in the Dr. Flex client application (or <a href="https://dr-flex.de/doctors" target="_blank">here online</a>).', 'dr-flex'),
                            __('Navigate to the <strong>Account tab</strong> and then go to <strong>Settings</strong>.', 'dr-flex'),
                            __('Here you can generate a <strong>new API Key for the Wordpress Plugin</strong>.', 'dr-flex'),
                            __('Please insert it into the field <strong>API Key</strong>.', 'dr-flex')
                        ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                <th scope="row"><label for="drflex_callback_textarea"><?php _e("Callback", 'dr-flex') ?></label></th>
                    <td>
                <div class="drflex-code-snippet cm-s-default"><span class="cm-keyword"><?php echo $fn_name; ?></span>(<span class="cm-def"><?php echo $fn_arg; ?></span>) {</div>
                        <textarea class="large-text drflex-textarea" id="drflex_callback_textarea"
                            name="drflex_callback_textarea"
                        placeholder="<?php _e("Optional JavaScript callback.", 'dr-flex') ?>" rows="5" cols="100" wrap="hard"
                        style="width: auto; white-space: pre-wrap; display: none;"
                            title="<?php _e("Callback", 'dr-flex') ?>"><?php echo esc_attr(get_option('drflex_callback_textarea')); ?></textarea>
                        <div class="drflex-code-snippet">}</div>
                        <p class="help">
                            <?php printf(
                            '%s <a href="%s">%s</a>.',
                            __('The JavaScript code you enter is executed during certain user actions. How this code might look like in different scenarios can be found at', 'dr-flex'),
                            menu_page_url("drflex_plugin_settings_examples", false),
                            __('Examples', 'dr-flex')
                        ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                <th scope="row"><label for="drflex_button_configs"><?php _e("Your Buttons", 'dr-flex') ?></label></th>
                <td><input class="regular-text" id="drflex_button_configs"
                           name="drflex_button_configs" title="<?php _e("Your Buttons", 'dr-flex') ?>" type="hidden"
                           value="">
                        <p class="help"></p>
                        <?php echo drflex_check_configuration(); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php submit_button(null, "primary drflex-submit"); ?>
    </form>
</div>

<?php
}

/**
 * Function to create the Dr. Flex Admin Menu Integration Subpage
 * 
 */
function drflex_plugin_settings_integration_subpage()
{
    ?>
<div class="wrap">
    <?php
    drflex_heading(__("Optimal button integration", 'dr-flex'));
    ?>
    <div class="drflex_integration">
        <h2><?php _e("The following points should be considered when integrating the booking button on the practice website.", 'dr-flex') ?></h2>

        <ol>
            <li>
                <div><?php _e("<span class='green'>Appearance and placement of the booking button</span> for the user (patient) on the practice website (user interface = UI).", 'dr-flex'); ?></div>
                <ul>
                    <li><?php _e("How does the button stand out from the rest of the website (color, shape, placement)? <span class='green'>(the more striking, the better the service is used)</span>", 'dr-flex'); ?></li>
                    <li><?php _e("Is a button placed in the header, footer and under contact / appointment / service (combination with the best conversion) and thus clearly visible to the user on all sub-pages?", 'dr-flex'); ?></li>
                </ul>
            </li>
            <li>
                <div><?php _e("<span class='green'>Behavior of the booking button</span> (User Experience = UX)", 'dr-flex'); ?></div>
                <ul>
                    <li><?php _e("Does the button react or move when the mouse is moved or clicked on it?", 'dr-flex'); ?></li>
                    <li><?php _e("Does a click open the Dr. Flex booking calendar?", 'dr-flex'); ?></li>
                </ul>
            </li>
            <li>
                <div class="green"><?php _e("Desktop, Tablet & Mobile", 'dr-flex'); ?></div>
                <ul>
                    <li><?php _e("Is the button also well integrated in the mobile and tablet version under the above mentioned criteria?", 'dr-flex'); ?></li>
                </ul>
            </li>
        </ol>
    </div>
    <?php
}

/**
 * Function to create the Dr. Flex Admin Menu Examples Subpage
 * 
 */
function drflex_plugin_settings_examples_subpage()
{
    ?>
    <div class="wrap">
        <?php drflex_heading(__("Callback Code Examples", 'dr-flex'));?>
        <p><?php _e("Here are some sample code snippets for the callback function which could be used on the configuration page. 
    When using such functions, data protection aspects must be considered, observed and marked accordingly on the page!
    These examples are provided without any warranty and for informative purposes only.", 'dr-flex'); ?>
        </p>
        <div>
            <?php
    $drflex_callback_examples_url = $GLOBALS['drflex_host'] . $GLOBALS['drflex_wordpress_callback_examples_relurl'];
    $response = drflex_utils_fetch_from_url($drflex_callback_examples_url);

    if ($response != false && count($response) > 1 && $response['status'] == 200) {
        $examples_array = json_decode($response['content']);

        foreach($examples_array as $example_obj) {
            $example = get_object_vars($example_obj)
            ?>
            <h2><?php echo $example['title']; ?></h2>
            <p><?php echo $example['description']; ?></p>
            <textarea class="large-text drflex-textarea drflex_examples_textarea"
            name="drflex_examples_textarea"
                placeholder="<?php _e("If you reload the page, you will see the code snippets again.", 'dr-flex'); ?>"
                title="<?php _e("Callback examples", 'dr-flex'); ?>"><?php echo $example['code']; ?></textarea>
            <?php
        }
    }
    ?>
        </div>
        <?php
}

/**
 * Function attached to Add and Update Hooks for the plugin options in wordpress.
 *
 * @param array $old_option_value the old option value
 * @param array $new_option_value the new option value
 * @param string $op options
 */
function drflex_update_api_key_option($old_option_value, $new_option_value, $op = '')
{
    if ($old_option_value === $old_option_value) {
        drflex_cache_clear_cache();
    }

    if ($new_option_value !== '') {
        drflex_update_resources();
    }
}

/**
 * Function attached to Add and Update Hooks for the plugin options in wordpress.
 *
 * @param array $old_option_value the old option value
 * @param array $new_option_value the new option value
 * @param string $op options
 */
function drflex_update_callback_option($old_option_value, $new_option_value, $op = '')
{
    $label = '/' . $GLOBALS['drflex_callback_function_file_name'];

    if (!empty($new_option_value)) {
        $drflex_callable = "function {$GLOBALS['drflex_callback_function_name']} {\n\t" . $new_option_value . "\n}";
        // debug("Insert: $drflex_callable");
        drflex_cache_insert_or_update_resource($label, $drflex_callable, 'application/javascript; charset=utf-8', null, true, true);
    } else {
        drflex_cache_delete_resource_by_uri($label);
    }
}

add_action('add_option_drflex_api_key', 'drflex_update_api_key_option', 10, 2);
add_action('update_option_drflex_api_key', 'drflex_update_api_key_option', 10, 3);

add_action('add_option_drflex_callback_textarea', 'drflex_update_callback_option', 10, 2);
add_action('update_option_drflex_callback_textarea', 'drflex_update_callback_option', 10, 3);

////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Check configuration, function that returns the cofiguration HTML for the settings page.
 *
 * @return string settings HTML
 */
function drflex_check_configuration()
{

    $buttons = '';

    function button_html($button_id, $button_text, $default = false)
    {

        if (!$default) {
            $display = "\ndisplay: none;";
        } else {
            $display = '';
        }

        printf("<div class='dr-flex-button'>
            <div class='dr-flex-btn-header'>
                <div class='dr-flex-btn-title'>%s [ <span>%s</span> ]</div>
                <div class='dr-flex-btn-type'><span style='%s'>%s</span></div>
            </div>
            <div class='dr-flex-btn-body'><strong>%s: </strong>%s</div>
            <div class='dr-flex-btn-shortcode'><strong>%s:</strong> <span class='dr-flex-code'>[drflex id=%s]</span></div>
        </div>",
            __('Button ID', 'dr-flex'),
            $button_id,
            $display,
            __('Standard', 'dr-flex'),
            __('Description', 'dr-flex'),
            $button_text,
            __('Minimal shortcode', 'dr-flex'),
            $button_id
        );
    }

    if (drflex_cache_db_ready()) {
        $button_configs = drflex_cache_check_latest_resource_by_uri('buttonConfigurations');

        if (!empty($button_configs)) {
            $object_vars = get_object_vars($button_configs[0]);
            $button_configs_objects = json_decode($object_vars['resource_data']);
            if ($button_configs_objects != null) {
                $button_configs_array = array_map(function ($a) {
                    return get_object_vars($a);
                }, $button_configs_objects);
                foreach ($button_configs_array as $button) {
                    button_html($button['id'], $button['html_description'], $button['is_default']);
                }

                $hw = menu_page_url('drflex_plugin_settings_integration', false);

                $buttons .= sprintf("<h3><span class=\"dashicons dashicons-shortcode dr-flex-heading-icon\"></span> %s:</h3>
                    %s <a href=\"%s\">%s</a>.
                    <br>
                    <br>
                    %s
                    <ol>
                        <li>%s: <span class=\"dr-flex-code\">background=#68e697</span></li>
                        <li>%s: <span class=\"dr-flex-code\">color=#ffffff</span></li>
                        <li>%s: <span class=\"dr-flex-code\">border_radius=1.0em</span></li>
                        <li>%s: <span class=\"dr-flex-code\">padding=1.0em</span></li>
                        <li>%s: <span class=\"dr-flex-code\">text='Termin online buchen'</span></li>
                        <li>%s: <span class=\"dr-flex-code\">css-classes='drflex-button'</span></li>
                    </ol>
                    %s
                    <br><br>
                    %s:<br><span class=\"dr-flex-code\">[drflex id=1 background=#f50 color=#00f border-radius=15.0px padding=10.0px text='Termin mit Dr. Flex buchen!' css-classes='custom-css-button weitere-klasse']</span>
                    <br>
                    <br>
                    <h3><span class=\"dashicons dashicons-menu-alt3 dr-flex-heading-icon\"></span> %s:</h3>
                    %s:
                    <br>
                    <br>
                    <span class=\"dr-flex-code\">https://toggleDrFlexAppointments()</span>
                    <br>
                    <br>
                    %s
                    <br>
                    %s
                    ",
                    __('Button integration with shortcode on pages', 'dr-flex'),
                    __('The buttons can be integrated via their ID\'s (e.g. id=1) via <a href="https://codex.wordpress.org/shortcode" target="_blank">Wordpress Shortcodes</a>. Please see also the', 'dr-flex'),
                    $hw,
                    __('Notes on button integration', 'dr-flex'),
                    __('The following optional attributes can be used in addition to <strong>id</strong> (CSS notation):', 'dr-flex'),
                    __('Background color as HEX value', 'dr-flex'),
                    __('Font color as HEX value', 'dr-flex'),
                    __('Rounded edges as decimal number + CSS unit (px, em, vw, vh, rem, %)', 'dr-flex'),
                    __('Padding as decimal number + CSS unit (px, em, vw, vh, rem, %)', 'dr-flex'),
                    __('Individual text for the button', 'dr-flex'),
                    __('Custom CSS classes for the button', 'dr-flex'),
                    __('If the attributes are not set, the above default values are used. By the way, the attributes can also only be added individually.', 'dr-flex'),
                    __('Here is an example short code with all attributes', 'dr-flex'),
                    
                    __('Button integration as wordpress menu item', 'dr-flex'),
                    __('To integrate the booking button as a menu item in the site navigation, create a new individual link item in the wordpress menu and add the follwing text as the link URL', 'dr-flex'),
                    __('Now add a text to your link such as "Schedule appointment online" and save the new menu configuration.', 'dr-flex'),
                    __('Note that, the URL has to contain all the characters above, including the parentheses, in order to work properly.', 'dr-flex')
                );

                $msg = $buttons;
            } else {
                $msg = __("Configuration data have the wrong format. Error 230", 'dr-flex');
            }
        } else {
            $msg = __("There is no configuration data available at the moment.", 'dr-flex');
        }
    } else {
        $msg = __("The data for your account could not be loaded.", 'dr-flex');
    }

    return $msg;
}

/**
 * Function that checks whether the entered API Key is valid or not.
 *
 * @return string HTML API Key validity indicator
 */
function drflex_check_status()
{

    $indicator_gen = '<span class="dr-flex-status-indicator" style="background-color: <<status_color>>;"><<status_text>></span>';

    function invalid($indicator_gen)
    {
        $indicator = str_replace('<<status_color>>', $GLOBALS['drflex_colors_primary_red'], $indicator_gen);
        $indicator = str_replace('<<status_text>>', __('API key invalid', 'dr-flex'), $indicator);
        return $indicator;
    }

    function incomplete($indicator_gen)
    {
        $indicator = str_replace('<<status_color>>', $GLOBALS['drflex_colors_primary_orange'], $indicator_gen);
        $indicator = str_replace('<<status_text>>', __('Not yet connected', 'dr-flex'), $indicator);
        return $indicator;
    }

    // debug(db_ready());
    if (drflex_cache_db_ready()) {
        $api_key_data = drflex_cache_check_latest_resource_by_uri('generatedEmbedScript');

        if (!empty($api_key_data) && get_option('drflex_api_key', null) != null) {
            $indicator = str_replace('<<status_color>>', $GLOBALS['drflex_colors_primary_green'], $indicator_gen);
            $indicator = str_replace('<<status_text>>', __('API key valid', 'dr-flex'), $indicator);
            return $indicator;
        } else {
            return invalid($indicator_gen);
        }
    } else {
        return incomplete($indicator_gen);
    }
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Helper function to fetch a resource form a url and insert it into the database cache.
 *
 * @param string $resource_uri uri of the resource to fetch
 */
function drflex_fetch_and_insert_latest_resource_version($resource_uri)
{

    $response = drflex_utils_fetch_from_url($resource_uri, true);

    if ($response != false && count($response) > 1) {
        $content = $response['content'];
        $contentType = $response['content-type'];
        $xContentEncoding = $response['x-content-encoding'];
        drflex_debug("X-Content-Encoding: " . $xContentEncoding);

        drflex_cache_insert_or_update_resource($resource_uri, $content, $contentType, $xContentEncoding, true);
    } else {
        drflex_error("CURL fehler.");
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Main function that is being excecuted on every pageload. This function checks if the resources
 * that are listed in the config json file, that was fetched from the Dr. Flex service, are
 * already cached in the database and if not it fetches and inserts them. On every load the hash
 * values of the files are checked and if they have changed the resource will be updated. There is
 * a delay which is defined in components/constants.php that will be applied to the update process
 * in order to make the operation less resource-intensive.
 *
 */
function drflex_update_resources()
{
    if (drflex_plugin_database_needs_update()) {
        drflex_debug("Database needs update. Updating...");
        if (drflex_cache_upgrade()) {
            drflex_debug("Update successful.");
        } else {
            drflex_error("Error ocurred updating database");
        }
    }

    function update()
    {
        // drflex_debug("Update Resources.");

        function has_hash($im_cache, $hash)
        {
            foreach ($im_cache as $item) {
                if ($hash === $item['resource_hash_md5']) {
                    return true;
                }
            }

            return false;
        }

        function get_data($im_cache, $uri)
        {
            foreach ($im_cache as $item) {
                if ($uri === $item['resource_uri']) {
                    return $item['resource_data'];
                }
            }

            return array();
        }

        function clean_up_deprecated($im_cache, $config_files)
        {
            $items_to_remove = array();
            foreach ($im_cache as $cached_item) {
                if (
                    $cached_item['resource_uri'] !== "generatedEmbedScript" &&
                    $cached_item['resource_uri'] !== "buttonConfigurations" &&
                    $cached_item['permanent'] != true
                ) {
                    $items_to_remove[] = $cached_item;
                    $position = count($items_to_remove) - 1;
                    foreach ($config_files as $config_item) {
                        if ($cached_item['resource_uri'] === $config_item['drflex_url']) {
                            array_splice($items_to_remove, $position, 1);
                        }
                    }
                }
            }

            $delete_ids = array_map(function ($a) {
                return $a['id'];
            }, $items_to_remove);

            drflex_cache_delete_resources($delete_ids);
        }

        $cache = drflex_cache_get_cache();
        $im_cache = array();

        foreach ($cache as $item) {
            $cachable_item = get_object_vars($item);
            $im_cache[] = $cachable_item;
        }

        $drflex_config_file_url = $GLOBALS['drflex_host'] . $GLOBALS['drflex_cofig_relpath'];

        $response = drflex_utils_fetch_from_url($drflex_config_file_url);

        if ($response != false && count($response) > 1 && $response['status'] == 200) {
            $configObject = json_decode($response['content']);
            $config = get_object_vars($configObject);

            // Fetch embed script
            $embed_script_data = $config['generated_embed_script'];
            $es_vars = get_object_vars($embed_script_data);

            if (!has_hash($im_cache, $es_vars['md5_hash'])) {
                if (!drflex_cache_insert_or_update_resource('generatedEmbedScript', drflex_utils_json_unescape($es_vars['data']), null, null)) {
                    drflex_error("An error occurred while writing the generated embed script to the database.");
                }
            }

            $config_files = array();

            // Fetch files
            foreach ($config['files'] as $fileObject) {
                $file = get_object_vars($fileObject);
                $config_files[] = $file;
                $resource_uri = $file['drflex_url'];

                if (!has_hash($im_cache, $file['md5_hash'])) {
                    // debug( "Resource does not exist or is deprecated in mysql db." );
                    if ($file['md5_hash'] !== null) {
                        drflex_fetch_and_insert_latest_resource_version($resource_uri);
                    } else {
                        drflex_cache_insert_or_update_resource($resource_uri, null, null, null, true);
                    }
                } // else {
                    // debug("Latest resource version already exists in mysql db.");
                    // Do nothing
                    // debug("f cache: $resource_uri");
                // }
                ;
            }

            // DB cleanup
            clean_up_deprecated($im_cache, $config_files);

            // Fetch configs
            if (array_key_exists('button_configurations', $config)) {
                $btn_cnf = json_encode($config['button_configurations']);
                drflex_cache_insert_or_update_resource('buttonConfigurations', $btn_cnf, null, null);
            } else {
                drflex_error("Button configuration missing in config json.");
            }

            // Update fetch timestamp
            drflex_cache_insert_config_item("last_update_timestamp", null);

        } else {
            if ($response['status'] === 401){
                drflex_cache_clear_cache();
                drflex_error($response['content']);
            } else {
                drflex_error("Error trying to fetch config.json.");
            }
        }
    }

    $last_update = drflex_cache_get_config_item("last_update_timestamp");

    if (count($last_update) > 0) {
        $last_update = get_object_vars($last_update[0]);

        if (array_key_exists("created_at", $last_update)) {

            $created_date_time = date_create_from_format("Y-m-d H:i:s", $last_update["created_at"]);
            $last_timestamp = $created_date_time->getTimestamp();

            $drflex_update_interval_secs = $GLOBALS['drflex_update_interval_secs'];

            if (time() - $drflex_update_interval_secs > $last_timestamp) {
                update();
            } // else {
                // debug("Wait");
            //}
        } else {
            update();
        }
    } else {
        update();
    }
}

add_action('wp', 'drflex_update_resources');

////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Function to replace Dr. Flex Menu items with the toggle logic.
 * 
 * @param string $items Menu items from wordpress navigation fuction
 * @return string the modified items sting with the proper toggle command
 */
function drflex_replace_dr_flex_placeholer_menu_item($items) {
    
    $replacement = $GLOBALS['drflex_toggle_href'];

    $items = str_replace('https://toggleDrFlexAppointments()', $replacement, $items);
    $items = str_replace('http://toggleDrFlexAppointments()', $replacement, $items);
    $items = str_replace('https://javascript:toggleDrFlexAppointments()', $replacement, $items);
    $items = str_replace('http://javascript:toggleDrFlexAppointments()', $replacement, $items);

    return $items;
}

add_filter('wp_nav_menu_items', 'drflex_replace_dr_flex_placeholer_menu_item');