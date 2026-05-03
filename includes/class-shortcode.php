<?php
/**
 * Shortcode implementation.
 *
 * @package kursflow
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kursflow_Shortcode {

    public static function init() {
        add_shortcode('kursflow_kurse', [__CLASS__, 'render']);
    }

    /**
     * Render [kursflow_kurse] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render($atts) {
        $atts = shortcode_atts([
            'slug'    => '',
            'branche' => '',
            'limit'   => 0,
            'layout'  => get_option('kursflow_default_layout', 'liste'),
        ], $atts, 'kursflow_kurse');

        return kursflow_render_widget($atts);
    }
}
