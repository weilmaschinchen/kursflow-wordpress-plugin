<?php
/**
 * WP-Cron based synchronisation of courses.
 *
 * @package kursflow
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kursflow_Cron_Sync {

    const CRON_HOOK = 'kursflow_sync_kurse';

    public static function init() {
        add_action(self::CRON_HOOK, [__CLASS__, 'sync']);
        add_action('admin_post_kursflow_trigger_sync', [__CLASS__, 'handle_manual_sync']);
    }

    /**
     * Main sync routine: fetch events and store in transient.
     */
    public static function sync() {
        $use_cache = get_option('kursflow_use_cache', 0);
        if (!$use_cache) {
            return;
        }

        $slug = get_option('kursflow_tenant_slug', '');
        if (empty($slug)) {
            return;
        }

        $response = Kursflow_API_Client::get($slug, '/api/public/events');
        if (is_wp_error($response)) {
            error_log('kursflow sync error: ' . $response->get_error_message());
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('kursflow sync invalid status: ' . $code);
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!$data) {
            error_log('kursflow sync invalid JSON');
            return;
        }

        set_transient('kursflow_courses', $data, get_option('kursflow_cache_ttl', 300));
    }

    /**
     * Manually trigger a sync from the settings page.
     */
    public static function handle_manual_sync() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'kursflow'));
        }

        check_admin_referer('kursflow_manual_sync', 'kursflow_nonce');

        $slug = get_option('kursflow_tenant_slug', '');
        if (empty($slug)) {
            wp_die(esc_html__('Tenant slug is not configured.', 'kursflow'));
        }

        $response = Kursflow_API_Client::get($slug, '/api/public/events');
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $message = __('Sync failed. Please check your settings.', 'kursflow');
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if ($data) {
                set_transient('kursflow_courses', $data, get_option('kursflow_cache_ttl', 300));
                $message = __('Sync completed successfully.', 'kursflow');
            } else {
                $message = __('Sync received invalid data.', 'kursflow');
            }
        }

        wp_redirect(add_query_arg('kursflow_sync_message', urlencode($message), wp_get_referer()));
        exit;
    }
}
