<?php
/**
 * Plugin Name: kursflow Kursliste
 * Plugin URI: https://kursflow.de
 * Description: Bindet die Kursliste von kursflow.de ein – als Gutenberg-Block, Shortcode oder automatisch im Footer.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: weilmaschinchen
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kursflow
 * Domain Path: /languages
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

define('KURSFLOW_VERSION', '0.1.0');
define('KURSFLOW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KURSFLOW_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Bootstrap the plugin.
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain('kursflow', false, dirname(plugin_basename(__FILE__)) . '/languages');

    require_once KURSFLOW_PLUGIN_DIR . 'includes/class-settings.php';
    require_once KURSFLOW_PLUGIN_DIR . 'includes/class-api-client.php';
    require_once KURSFLOW_PLUGIN_DIR . 'includes/class-block.php';
    require_once KURSFLOW_PLUGIN_DIR . 'includes/class-shortcode.php';
    require_once KURSFLOW_PLUGIN_DIR . 'includes/class-cron-sync.php';

    Kursflow_Settings::init();
    Kursflow_Block::init();
    Kursflow_Shortcode::init();
    Kursflow_Cron_Sync::init();

    $auto_embed = get_option('kursflow_auto_embed', 0);
    if ($auto_embed) {
        add_action('wp_footer', 'kursflow_render_widget');
    }
});

/**
 * Activation hook.
 */
register_activation_hook(__FILE__, function () {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('kursflow requires PHP 7.4 or higher.', 'kursflow'),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('kursflow requires WordPress 6.0 or higher.', 'kursflow'),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }

    add_option('kursflow_api_key', '');
    add_option('kursflow_tenant_slug', '');
    add_option('kursflow_cache_ttl', 300);
    add_option('kursflow_default_layout', 'liste');
    add_option('kursflow_branche_theme', '');
    add_option('kursflow_auto_embed', 0);
    add_option('kursflow_use_cache', 0);

    if (!wp_next_scheduled('kursflow_sync_kurse')) {
        wp_schedule_event(time(), 'hourly', 'kursflow_sync_kurse');
    }
});

/**
 * Uninstall hook.
 */
register_uninstall_hook(__FILE__, function () {
    delete_option('kursflow_api_key');
    delete_option('kursflow_tenant_slug');
    delete_option('kursflow_cache_ttl');
    delete_option('kursflow_default_layout');
    delete_option('kursflow_branche_theme');
    delete_option('kursflow_auto_embed');
    delete_option('kursflow_use_cache');

    wp_clear_scheduled_hook('kursflow_sync_kurse');
});

/**
 * Deactivation hook (cleanup cron).
 */
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('kursflow_sync_kurse');
});

/**
 * Shared helper to output the widget script.
 */
if (!function_exists('kursflow_render_widget')) {
    function kursflow_render_widget($atts = []) {
        $slug = !empty($atts['slug']) ? $atts['slug'] : get_option('kursflow_tenant_slug', '');
        if (empty($slug)) return '';
        $layout = !empty($atts['layout']) ? $atts['layout'] : get_option('kursflow_default_layout', 'liste');
        $branche = !empty($atts['branche']) ? $atts['branche'] : '';
        $limit = !empty($atts['limit']) ? absint($atts['limit']) : 0;

        $div_id = 'kursflow-widget-' . uniqid();
        $url = 'https://' . esc_attr($slug) . '.kursflow.de/widget.js';

        // Build a container div with data attributes for the widget JS.
        $output = '<div class="kursflow-widget" id="' . esc_attr($div_id) . '"';
        $output .= ' data-widget-url="' . esc_url($url) . '"';
        if ($branche) {
            $output .= ' data-branche="' . esc_attr($branche) . '"';
        }
        if ($limit > 0) {
            $output .= ' data-limit="' . $limit . '"';
        }
        $output .= ' data-layout="' . esc_attr($layout) . '"></div>';
        // Inject the actual widget script once (done by enqueue, but for shortcode we use inline).
        // In v0.1.0 we simply output script tag for simplicity; later we can enqueue properly.
        $output .= '<script async src="' . esc_url($url) . '"></script>';
        return $output;
    }
}
