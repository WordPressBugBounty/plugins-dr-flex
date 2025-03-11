<?php

/**
 * 
 *
 * @category	The Shortcode configuration for the Dr. Flex plugin.
 * @author		Johannes Dato
 * @copyright	Copyright (c) 2020
 * @link		https://dr-flex.de
 * @version		2.0.1
 */

//////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Creates a custom Dr. Flex button via a Wordpress Shortcode.
 * 
 * @param array atts Array containing all the configuration data for the HTML button
 */
function drflex_simple_button_shortcode($atts)
{

    global $wpdb;

    $background_color_button = '#68e697';
    $text_color_button = '#ffffff';
    $padding = "1em";
    $border_radius = "1em";
    $button_text = "Termin online buchen";
    $css_classes = array("drflex-button");

    if (array_key_exists("background", (array) $atts)) {
        $bg_color = sanitize_text_field($atts['background']);
        preg_match('/^[#]{1}([0-9A-Fa-f]{8}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{3})/', $bg_color, $matches);
        if (count($matches) > 0) {
            $background_color_button = $matches[0];
        }
    }

    if (array_key_exists("color", (array) $atts)) {
        $color = sanitize_text_field($atts['color']);
        preg_match('/^[#]{1}([0-9A-Fa-f]{8}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{3})/', $color, $matches);
        if (count($matches) > 0) {
            $text_color_button = $matches[0];
        }
    }

    if (array_key_exists("border-radius", (array) $atts)) {
        $br = sanitize_text_field($atts['border-radius']);
        preg_match('/^[0-9]+(\.[0-9]{1,2})?(px|em|vw|vh|rem|%)?/', $br, $matches);
        if (count($matches) > 0) {
            $border_radius = $matches[0];
        }
    }

    if (array_key_exists("padding", (array) $atts)) {
        $pad = sanitize_text_field($atts['padding']);
        preg_match('/^[0-9]+(\.[0-9]{1,2})?(px|em|vw|vh|rem|%)?/', $pad, $matches);
        if (count($matches) > 0) {
            $padding = $matches[0];
        }
    }

    if (array_key_exists("text", (array) $atts)) {
        $text = sanitize_text_field($atts['text']);
        if (strlen($text) <= 50) {
            $button_text = $text;
        }
    }

    if (array_key_exists("css-classes", (array)$atts)) {
        // Sanitize CSS classes properly
        $user_classes = sanitize_text_field($atts['css-classes']);
        if (!empty($user_classes)) {
            // Split by spaces and sanitize each class individually
            $class_array = explode(" ", $user_classes);
            $sanitized_classes = array();
            foreach ($class_array as $class) {
                // sanitize_html_class removes any characters that are not allowed in CSS class names
                $sanitized_class = sanitize_html_class($class);
                if (!empty($sanitized_class)) {
                    $sanitized_classes[] = $sanitized_class;
                }
            }
            if (!empty($sanitized_classes)) {
                $css_classes = array_merge($css_classes, $sanitized_classes);
            }
        }
    }

    return "<div class=\"drflex-button-wrapper\">
        <a href=\"javascript:toggleDrFlexAppointments()\">
            <div class=\"" . esc_attr(implode(" ", $css_classes)) . "\"
                style=\"" . esc_attr('border-radius: ' . $border_radius . ';
                    background-color: ' . $background_color_button . ';
                    color: ' . $text_color_button . ';
                    padding: ' . $padding . ';') . "\">" . esc_html(trim($button_text)) . "
            </div>
        </a>
    </div>";
}

/**
 * Shorcode button addition.
 */
add_shortcode('drflex', 'drflex_simple_button_shortcode');
