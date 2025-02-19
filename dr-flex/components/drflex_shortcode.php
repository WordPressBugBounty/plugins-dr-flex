<?php

/**
 * 
 *
 * @category	The Shortcode configuration for the Dr. Flex plugin.
 * @author		Johannes Dato
 * @copyright	Copyright (c) 2020
 * @link		https://dr-flex.de
 * @version		2.0.0
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
        preg_match('/^[#]{1}([0-9A-Fa-f]{8}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{3})/', $atts['background'], $matches);
        if (count($matches) > 0) {
            $background_color_button = $matches[0];
        }
    }

    if (array_key_exists("color", (array) $atts)) {
        preg_match('/^[#]{1}([0-9A-Fa-f]{8}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{3})/', $atts['color'], $matches);
        if (count($matches) > 0) {
            $text_color_button = $matches[0];
        }
    }

    if (array_key_exists("border-radius", (array) $atts)) {
        preg_match('/^[0-9]+(\.[0-9]{1,2})?(px|em|vw|vh|rem|%)?/', $atts['border-radius'], $matches);
        if (count($matches) > 0) {
            $border_radius = $matches[0];
        }
    }

    if (array_key_exists("padding", (array) $atts)) {
        preg_match('/^[0-9]+(\.[0-9]{1,2})?(px|em|vw|vh|rem|%)?/', $atts['padding'], $matches);
        if (count($matches) > 0) {
            $padding = $matches[0];
        }
    }

    if (array_key_exists("text", (array) $atts)) {
        preg_match('/^[\s\S]{0,50}+$/', $atts['text'], $matches);
        if (count($matches) > 0) {
            $button_text = $matches[0];
        }
    }

    if (array_key_exists("css-classes", (array)$atts)) {
        preg_match('/^[\s\S]+$/', $atts['css-classes'], $matches);
        if (count($matches) > 0) {
            $css_classes = array_merge($css_classes, explode(" ", $matches[0]));
        }
    }

    return "<div class=\"drflex-button-wrapper\">
        <a href=\"javascript:toggleDrFlexAppointments()\">
            <div class=\"" . implode(" ", $css_classes) . "\"
                style=\"" . 'border-radius: ' . $border_radius . ';
                    background-color: ' . $background_color_button . ';
                    color: ' . $text_color_button . ';
                    padding: ' . $padding . ';' . "\">
                    " . $button_text . "
            </div>
        </a>
    </div>";
}

/**
 * Shorcode button addition.
 */
add_shortcode('drflex', 'drflex_simple_button_shortcode');
