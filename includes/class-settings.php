<?php
/**
 * Settings page for kursflow plugin.
 *
 * @package kursflow
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kursflow_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_kursflow_test_connection', [__CLASS__, 'ajax_test_connection']);
    }

    public static function add_menu_page() {
        add_options_page(
            __('kursflow Settings', 'kursflow'),
            __('kursflow', 'kursflow'),
            'manage_options',
            'kursflow-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        register_setting('kursflow_settings', 'kursflow_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('kursflow_settings', 'kursflow_tenant_slug', [
            'sanitize_callback' => function ($value) {
                $value = sanitize_text_field($value);
                // Remove protocol/domain parts if user accidentally pastes entire URL.
                $value = str_replace(['http://', 'https://', '.kursflow.de', '/'], '', $value);
                return trim($value);
            },
        ]);
        register_setting('kursflow_settings', 'kursflow_cache_ttl', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('kursflow_settings', 'kursflow_default_layout', [
            'sanitize_callback' => function ($value) {
                $allowed = ['liste', 'grid', 'kompakt'];
                return in_array($value, $allowed, true) ? $value : 'liste';
            },
        ]);
        register_setting('kursflow_settings', 'kursflow_branche_theme', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('kursflow_settings', 'kursflow_auto_embed', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('kursflow_settings', 'kursflow_use_cache', [
            'sanitize_callback' => 'absint',
        ]);
    }

    public static function enqueue_assets($hook) {
        if ('settings_page_kursflow-settings' !== $hook) {
            return;
        }
        wp_enqueue_style('kursflow-admin', KURSFLOW_PLUGIN_URL . 'assets/admin/settings.css', [], KURSFLOW_VERSION);
        wp_enqueue_script('kursflow-admin', KURSFLOW_PLUGIN_URL . 'assets/admin/settings.js', ['jquery'], KURSFLOW_VERSION, true);
        wp_localize_script('kursflow-admin', 'kursflowSettings', [
            'nonce'  => wp_create_nonce('kursflow_test_connection'),
            'action' => 'kursflow_test_connection',
        ]);
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $sync_message = isset($_GET['kursflow_sync_message']) ? sanitize_text_field(wp_unslash($_GET['kursflow_sync_message'])) : '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('kursflow Settings', 'kursflow'); ?></h1>

            <?php if ($sync_message): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($sync_message); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('kursflow_settings');
                do_settings_sections('kursflow_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="kursflow_api_key"><?php esc_html_e('API-Key', 'kursflow'); ?></label></th>
                        <td>
                            <input type="password" id="kursflow_api_key" name="kursflow_api_key" value="<?php echo esc_attr(get_option('kursflow_api_key', '')); ?>" class="regular-text" autocomplete="off"/>
                            <p class="description"><?php esc_html_e('kf_live_... – Your secret API key from kursflow.', 'kursflow'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kursflow_tenant_slug"><?php esc_html_e('Tenant-Slug', 'kursflow'); ?></label></th>
                        <td>
                            <input type="text" id="kursflow_tenant_slug" name="kursflow_tenant_slug" value="<?php echo esc_attr(get_option('kursflow_tenant_slug', '')); ?>" class="regular-text"/>
                            <p class="description">
                                <?php esc_html_e('e.g., "meine-fahrschule"', 'kursflow'); ?><br>
                                <?php echo esc_html__('Your tenant URL:', 'kursflow') . ' <code>' . esc_html('https://' . esc_html(get_option('kursflow_tenant_slug', '')) . '.kursflow.de') . '</code>'; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kursflow_cache_ttl"><?php esc_html_e('Cache-TTL (Sekunden)', 'kursflow'); ?></label></th>
                        <td>
                            <input type="number" id="kursflow_cache_ttl" name="kursflow_cache_ttl" value="<?php echo esc_attr(get_option('kursflow_cache_ttl', 300)); ?>" class="small-text" min="0" step="1"/>
                            <p class="description"><?php esc_html_e('Default: 300 seconds.', 'kursflow'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kursflow_default_layout"><?php esc_html_e('Default Layout', 'kursflow'); ?></label></th>
                        <td>
                            <select id="kursflow_default_layout" name="kursflow_default_layout">
                                <option value="liste" <?php selected(get_option('kursflow_default_layout', 'liste'), 'liste'); ?>>Liste</option>
                                <option value="grid" <?php selected(get_option('kursflow_default_layout', 'liste'), 'grid'); ?>>Grid</option>
                                <option value="kompakt" <?php selected(get_option('kursflow_default_layout', 'liste'), 'kompakt'); ?>>Kompakt</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="kursflow_branche_theme"><?php esc_html_e('Branche / Theme Override (optional)', 'kursflow'); ?></label></th>
                        <td>
                            <input type="text" id="kursflow_branche_theme" name="kursflow_branche_theme" value="<?php echo esc_attr(get_option('kursflow_branche_theme', '')); ?>" class="regular-text"/>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-Embed in Footer', 'kursflow'); ?></th>
                        <td>
                            <label><input type="checkbox" name="kursflow_auto_embed" value="1" <?php checked(get_option('kursflow_auto_embed', 0), 1); ?>> <?php esc_html_e('Widget auf jeder Seite anzeigen', 'kursflow'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Live oder Cached?', 'kursflow'); ?></th>
                        <td>
                            <label><input type="checkbox" name="kursflow_use_cache" value="1" <?php checked(get_option('kursflow_use_cache', 0), 1); ?>> <?php esc_html_e('Cached Daten verwenden (Cron-Sync)', 'kursflow'); ?></label>
                            <p class="description"><?php esc_html_e('Faster loading, but data might be slightly outdated.', 'kursflow'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>
            <h2><?php esc_html_e('API Connection Test', 'kursflow'); ?></h2>
            <p><button id="kursflow-test-connection" class="button"><?php esc_html_e('Test Connection', 'kursflow'); ?></button></p>
            <div id="kursflow-test-result" style="margin-top:10px;"></div>

            <hr>
            <h2><?php esc_html_e('Manual Sync', 'kursflow'); ?></h2>
            <p><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=kursflow_trigger_sync'), 'kursflow_manual_sync', 'kursflow_nonce')); ?>" class="button"><?php esc_html_e('Sync now', 'kursflow'); ?></a></p>
        </div>
        <?php
    }

    public static function ajax_test_connection() {
        check_ajax_referer('kursflow_test_connection');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'kursflow')]);
        }

        $slug = isset($_POST['slug']) ? sanitize_text_field(wp_unslash($_POST['slug'])) : '';
        if (empty($slug)) {
            wp_send_json_error(['message' => __('Tenant slug is required.', 'kursflow')]);
        }

        $result = Kursflow_API_Client::test_connection($slug);
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
