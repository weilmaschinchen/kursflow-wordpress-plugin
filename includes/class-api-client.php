<?php
/**
 * HTTP wrapper for kursflow API.
 *
 * @package kursflow
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kursflow_API_Client {

    /**
     * Get API base URL from tenant slug.
     *
     * @param string $slug Tenant slug.
     * @return string Base URL (e.g., https://meine-fahrschule.kursflow.de).
     */
    private static function base_url($slug) {
        $slug = sanitize_text_field($slug);
        return 'https://' . $slug . '.kursflow.de';
    }

    /**
     * Send a GET request.
     *
     * @param string $slug Tenant slug.
     * @param string $endpoint API endpoint (e.g., /api/v1/health).
     * @param array  $args    Additional wp_remote_get args.
     * @return array|WP_Error Response or error.
     */
    public static function get($slug, $endpoint, $args = []) {
        $api_key = get_option('kursflow_api_key', '');
        $url = self::base_url($slug) . $endpoint;

        $headers = ['Accept' => 'application/json'];
        if (!empty($api_key)) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }
        $defaults = ['headers' => $headers, 'timeout' => 15];

        $args = wp_parse_args($args, $defaults);
        return wp_remote_get($url, $args);
    }

    /**
     * Send a POST request.
     *
     * @param string $slug Tenant slug.
     * @param string $endpoint API endpoint.
     * @param array  $body     POST body (assoc array).
     * @return array|WP_Error Response or error.
     */
    public static function post($slug, $endpoint, $body = []) {
        $api_key = get_option('kursflow_api_key', '');
        $url = self::base_url($slug) . $endpoint;

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($api_key)) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }
        $args = ['headers' => $headers, 'body' => wp_json_encode($body), 'timeout' => 15];

        return wp_remote_post($url, $args);
    }

    /**
     * Test the connection to the health endpoint.
     *
     * @param string $slug Tenant slug.
     * @return array{success: bool, code: int, message: string}
     */
    public static function test_connection($slug) {
        $response = self::get($slug, '/api/public/events');

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'code'    => 0,
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 200) {
            $data  = json_decode($body, true);
            $count = isset($data['data']) && is_array($data['data']) ? count($data['data']) : '?';
            return [
                'success' => true,
                'code'    => $code,
                'message' => sprintf(
                    /* translators: %1$d: number of courses, %2$s: tenant URL */
                    __('Verbunden — %1$d Kurs(e) gefunden (%2$s.kursflow.de)', 'kursflow'),
                    $count,
                    esc_html($slug)
                ),
            ];
        }

        return [
            'success' => false,
            'code'    => $code,
            'message' => sprintf(__('Unexpected status code: %d', 'kursflow'), $code),
        ];
    }
}
