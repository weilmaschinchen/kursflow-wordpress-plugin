<?php
/**
 * Registers the Gutenberg block for kursflow.
 *
 * @package kursflow
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kursflow_Block {

    public static function init() {
        add_action('init', [__CLASS__, 'register_block']);
    }

    public static function register_block() {
        // Skip if block editor not available.
        if (!function_exists('register_block_type')) {
            return;
        }

        $asset_file = include(KURSFLOW_PLUGIN_DIR . 'assets/block/block.asset.php');

        // Register editor script.
        wp_register_script(
            'kursflow-block-editor',
            KURSFLOW_PLUGIN_URL . 'assets/block/editor.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        // Register editor style.
        wp_register_style(
            'kursflow-block-editor-style',
            KURSFLOW_PLUGIN_URL . 'assets/block/editor.css',
            [],
            $asset_file['version']
        );

        // Register frontend style.
        wp_register_style(
            'kursflow-block-style',
            KURSFLOW_PLUGIN_URL . 'assets/block/frontend.css',
            [],
            $asset_file['version']
        );

        register_block_type(KURSFLOW_PLUGIN_DIR . 'assets/block', [
            'render_callback' => [__CLASS__, 'render_block'],
        ]);
    }

    /**
     * Server-side render callback.
     *
     * @param array $atts Block attributes.
     * @return string HTML output.
     */
    public static function render_block($atts) {
        $atts = wp_parse_args($atts, [
            'branche' => '',
            'limit'   => 0,
            'layout'  => get_option('kursflow_default_layout', 'liste'),
        ]);

        return kursflow_render_widget([
            'branche' => $atts['branche'],
            'limit'   => $atts['limit'],
            'layout'  => $atts['layout'],
        ]);
    }
}
