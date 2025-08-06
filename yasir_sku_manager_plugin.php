<?php

/**
 * Plugin Name: WooCommerce SKU Manager
 * Plugin URI: https://yasirshabbir.com
 * Description: Advanced SKU management system with logging and automated filtering for WooCommerce products
 * Version: 1.0.0
 * Author: Yasir Shabbir
 * Author URI: https://yasirshabbir.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yasir-sku-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Initialize the plugin
new YasirSKUManager();

// Create JavaScript file if it doesn't exist
if (!function_exists('ysm_create_js_file')) {
    function ysm_create_js_file()
    {
        $js_content = 'jQuery(document).ready(function($) {
    // Run cleanup functionality
    $("#run-cleanup").on("click", function() {
        const button = $(this);
        const originalText = button.html();
        
        button.prop("disabled", true).html("<span class=\"dashicons dashicons-update-alt\"></span> Running...");
        
        $.ajax({
            url: ysmAjax.ajaxurl,
            type: "POST",
            data: {
                action: "ysm_bulk_delete",
                nonce: ysmAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert("Cleanup completed! Deleted " + response.data.deleted + " products.");
                    location.reload();
                } else {
                    alert("Error: " + (response.data || "Unknown error"));
                }
            },
            error: function() {
                alert("AJAX error occurred");
            },
            complete: function() {
                button.prop("disabled", false).html(originalText);
            }
        });
    });
    
    // Delete blocked SKU functionality
    window.deleteBlockedSKU = function(id) {
        if (confirm(ysmAjax.messages.confirm_delete)) {
            $.ajax({
                url: ysmAjax.ajaxurl,
                type: "POST",
                data: {
                    action: "ysm_delete_blocked_sku",
                    id: id,
                    nonce: ysmAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert("Error: " + (response.data || "Unknown error"));
                    }
                }
            });
        }
    };
    
    // Logs functionality
    let currentPage = 1;
    let currentFilter = "";
    
    function loadLogs(page = 1, filter = "") {
        $.ajax({
            url: ysmAjax.ajaxurl,
            type: "POST",
            data: {
                action: "ysm_get_logs",
                page: page,
                filter: filter,
                nonce: ysmAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateLogsTable(response.data);
                    currentPage = page;
                    currentFilter = filter;
                }
            }
        });
    }
    
    function updateLogsTable(data) {
        const tbody = $("#logs-table tbody");
        tbody.empty();
        
        if (data.logs.length === 0) {
            tbody.append("<tr><td colspan=\"6\" style=\"text-align: center; color: var(--secondary-text);\">No logs found</td></tr>");
            return;
        }
        
        data.logs.forEach(log => {
            const row = `
                <tr>
                    <td><span class="ysm-badge ysm-badge-info">${log.action_type}</span></td>
                    <td>${log.product_id || "-"}</td>
                    <td><code>${log.product_sku || "-"}</code></td>
                    <td>${log.product_title || "-"}</td>
                    <td>${log.message}</td>
                    <td>${log.created_at}</td>
                </tr>
            `;
            tbody.append(row);
        });
        
        updatePagination(data);
    }
    
    function updatePagination(data) {
        const pagination = $(".ysm-pagination");
        pagination.empty();
        
        const totalPages = Math.ceil(data.total / data.per_page);
        
        if (totalPages <= 1) return;
        
        for (let i = 1; i <= totalPages; i++) {
            const button = $(`<button class="ysm-btn ${i === data.page ? "ysm-btn-primary" : "ysm-btn-secondary"}">${i}</button>`);
            button.on("click", () => loadLogs(i, currentFilter));
            pagination.append(button);
        }
    }
    
    // Filter logs
    $("#log-filter").on("change", function() {
        loadLogs(1, $(this).val());
    });
    
    // Clear logs
    $("#clear-logs").on("click", function() {
        if (confirm(ysmAjax.messages.confirm_clear_logs)) {
            $.ajax({
                url: ysmAjax.ajaxurl,
                type: "POST",
                data: {
                    action: "ysm_clear_logs",
                    nonce: ysmAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        loadLogs();
                        alert("Logs cleared successfully!");
                    }
                }
            });
        }
    });
    
    // Load logs on page load if on logs page
    if ($("#logs-table").length) {
        loadLogs();
    }
    
    // Settings toggle functionality
    $(".ysm-setting-toggle").on("change", function() {
        const setting = $(this).data("setting");
        const value = $(this).is(":checked") ? "1" : "0";
        
        $.ajax({
            url: ysmAjax.ajaxurl,
            type: "POST",
            data: {
                action: "ysm_toggle_setting",
                setting: setting,
                value: value,
                nonce: ysmAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success indicator
                    const indicator = $("<span class=\"ysm-success-indicator\">✓</span>");
                    $(this).parent().append(indicator);
                    setTimeout(() => indicator.fadeOut(), 2000);
                }
            }
        });
    });
});';

        // Create assets directory if it doesn\'t exist
        if (!file_exists(YSM_PLUGIN_DIR . "assets/")) {
            wp_mkdir_p(YSM_PLUGIN_DIR . "assets/");
        }

        // Write JavaScript file
        file_put_contents(YSM_PLUGIN_DIR . "assets/admin.js", $js_content);
    }

    // Create JS file on activation
    register_activation_hook(__FILE__, "ysm_create_js_file");

    // Also create it on init if it doesn\'t exist
    add_action("init", function () {
        if (!file_exists(YSM_PLUGIN_DIR . "assets/admin.js")) {
            ysm_create_js_file();
        }
    });
}

// Define plugin constants
define('YSM_VERSION', '1.0.0');
define('YSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YSM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YSM_PLUGIN_FILE', __FILE__);

class YasirSKUManager
{

    private $table_logs;
    private $table_blocked_skus;
    private $table_settings;

    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'check_woocommerce'));

        // Only initialize if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $this->init_plugin();
        }
    }

    public function check_woocommerce()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }

    public function woocommerce_missing_notice()
    {
        echo '<div class="notice notice-error"><p>';
        echo __('WooCommerce SKU Manager requires WooCommerce to be installed and active.', 'yasir-sku-manager');
        echo '</p></div>';
    }

    private function init_plugin()
    {
        global $wpdb;

        $this->table_logs = $wpdb->prefix . 'ysm_logs';
        $this->table_blocked_skus = $wpdb->prefix . 'ysm_blocked_skus';
        $this->table_settings = $wpdb->prefix . 'ysm_settings';

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Product filtering hooks
        add_action('save_post', array($this, 'filter_product_on_save'), 10, 3);
        add_action('woocommerce_new_product', array($this, 'filter_new_product'), 10, 1);
        add_action('woocommerce_update_product', array($this, 'filter_updated_product'), 10, 1);

        // Cron hooks
        add_action('ysm_cleanup_cron', array($this, 'scheduled_cleanup'));
        add_action('ysm_log_cleanup_cron', array($this, 'cleanup_old_logs'));

        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // AJAX handlers
        add_action('wp_ajax_ysm_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_ysm_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_ysm_toggle_setting', array($this, 'ajax_toggle_setting'));
        add_action('wp_ajax_ysm_bulk_delete', array($this, 'ajax_bulk_delete'));
        add_action('wp_ajax_ysm_delete_blocked_sku', array($this, 'ajax_delete_blocked_sku'));
    }

    public function init()
    {
        if (class_exists('WooCommerce')) {
            $this->create_tables();
            $this->schedule_cron_jobs();
            load_plugin_textdomain('yasir-sku-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }
    }

    public function activate()
    {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('WooCommerce SKU Manager requires WooCommerce to be installed and active.', 'yasir-sku-manager'));
        }

        $this->create_tables();
        $this->set_default_settings();
        $this->schedule_cron_jobs();

        $this->log_action('plugin_activated', 'Plugin activated successfully', 'system');
    }

    public function deactivate()
    {
        wp_clear_scheduled_hook('ysm_cleanup_cron');
        wp_clear_scheduled_hook('ysm_log_cleanup_cron');
        $this->log_action('plugin_deactivated', 'Plugin deactivated', 'system');
    }

    private function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Logs table
        $sql_logs = "CREATE TABLE IF NOT EXISTS {$this->table_logs} (
            id int(11) NOT NULL AUTO_INCREMENT,
            action_type varchar(50) NOT NULL,
            product_id int(11) DEFAULT NULL,
            product_sku varchar(255) DEFAULT NULL,
            product_title varchar(255) DEFAULT NULL,
            message text NOT NULL,
            user_type varchar(20) DEFAULT 'system',
            user_id int(11) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action_type (action_type),
            KEY product_id (product_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Blocked SKUs table
        $sql_blocked = "CREATE TABLE IF NOT EXISTS {$this->table_blocked_skus} (
            id int(11) NOT NULL AUTO_INCREMENT,
            sku varchar(255) NOT NULL,
            pattern varchar(255) DEFAULT NULL,
            is_regex tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_by int(11) DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sku (sku),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Settings table
        $sql_settings = "CREATE TABLE IF NOT EXISTS {$this->table_settings} (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext DEFAULT NULL,
            setting_type varchar(20) DEFAULT 'string',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_logs);
        dbDelta($sql_blocked);
        dbDelta($sql_settings);
    }

    private function set_default_settings()
    {
        $default_settings = array(
            'auto_delete_empty_sku' => '1',
            'auto_delete_blocked_sku' => '1',
            'log_retention_days' => '30',
            'cleanup_schedule' => 'hourly',
            'delete_immediately' => '1'
        );

        foreach ($default_settings as $key => $value) {
            $this->update_setting($key, $value);
        }
    }

    private function schedule_cron_jobs()
    {
        if (!wp_next_scheduled('ysm_cleanup_cron')) {
            $schedule = $this->get_setting('cleanup_schedule', 'hourly');
            wp_schedule_event(time(), $schedule, 'ysm_cleanup_cron');
        }

        if (!wp_next_scheduled('ysm_log_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'ysm_log_cleanup_cron');
        }
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'SKU Manager',
            'SKU Manager',
            'manage_woocommerce',
            'yasir-sku-manager',
            array($this, 'admin_dashboard'),
            'dashicons-filter',
            56
        );

        add_submenu_page(
            'yasir-sku-manager',
            'Dashboard',
            'Dashboard',
            'manage_woocommerce',
            'yasir-sku-manager',
            array($this, 'admin_dashboard')
        );

        add_submenu_page(
            'yasir-sku-manager',
            'Blocked SKUs',
            'Blocked SKUs',
            'manage_woocommerce',
            'yasir-sku-manager-blocked',
            array($this, 'admin_blocked_skus')
        );

        add_submenu_page(
            'yasir-sku-manager',
            'Logs',
            'Logs',
            'manage_woocommerce',
            'yasir-sku-manager-logs',
            array($this, 'admin_logs')
        );

        add_submenu_page(
            'yasir-sku-manager',
            'Settings',
            'Settings',
            'manage_woocommerce',
            'yasir-sku-manager-settings',
            array($this, 'admin_settings')
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'yasir-sku-manager') === false) {
            return;
        }

        // Inline CSS
        wp_add_inline_style('wp-admin', $this->get_admin_css());

        wp_enqueue_script('yasir-sku-manager-admin', YSM_PLUGIN_URL . 'assets/admin.js', array('jquery'), YSM_VERSION, true);

        wp_localize_script('yasir-sku-manager-admin', 'ysmAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ysm_nonce'),
            'messages' => array(
                'confirm_delete' => __('Are you sure you want to delete this?', 'yasir-sku-manager'),
                'confirm_clear_logs' => __('Are you sure you want to clear all logs?', 'yasir-sku-manager'),
                'success' => __('Action completed successfully', 'yasir-sku-manager'),
                'error' => __('An error occurred', 'yasir-sku-manager')
            )
        ));
    }

    // Product filtering methods
    public function filter_product_on_save($post_id, $post, $update)
    {
        if ($post->post_type !== 'product') {
            return;
        }

        $this->process_product_filter($post_id);
    }

    public function filter_new_product($product_id)
    {
        $this->process_product_filter($product_id);
    }

    public function filter_updated_product($product_id)
    {
        $this->process_product_filter($product_id);
    }

    private function process_product_filter($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $product_sku = $product->get_sku();
        $product_title = $product->get_name();

        // Check if auto-delete empty SKU is enabled
        if (empty($product_sku) && $this->get_setting('auto_delete_empty_sku') == '1') {
            $this->delete_product($product_id, 'empty_sku', $product_title);
            return;
        }

        // Check if SKU is blocked
        if (!empty($product_sku) && $this->is_sku_blocked($product_sku)) {
            $this->delete_product($product_id, 'blocked_sku', $product_title, $product_sku);
            return;
        }

        // Log product creation/update
        $this->log_action(
            'product_processed',
            "Product processed: {$product_title}",
            'system',
            $product_id,
            $product_sku,
            $product_title
        );
    }

    private function delete_product($product_id, $reason, $product_title, $product_sku = '')
    {
        if ($this->get_setting('delete_immediately') == '1') {
            wp_delete_post($product_id, true);
            $status = 'deleted';
        } else {
            wp_trash_post($product_id);
            $status = 'trashed';
        }

        $message = "Product {$status}: {$product_title} (Reason: {$reason})";

        $this->log_action(
            'product_' . $status,
            $message,
            'system',
            $product_id,
            $product_sku,
            $product_title
        );
    }

    // SKU blocking methods
    public function is_sku_blocked($sku)
    {
        global $wpdb;

        // Check exact matches
        $exact_match = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_blocked_skus} WHERE sku = %s AND is_regex = 0 AND is_active = 1",
            $sku
        ));

        if ($exact_match > 0) {
            return true;
        }

        // Check regex patterns
        $patterns = $wpdb->get_results(
            "SELECT sku, pattern FROM {$this->table_blocked_skus} WHERE is_regex = 1 AND is_active = 1"
        );

        foreach ($patterns as $pattern_obj) {
            $pattern = !empty($pattern_obj->pattern) ? $pattern_obj->pattern : $pattern_obj->sku;
            if (@preg_match('/' . $pattern . '/i', $sku)) {
                return true;
            }
        }

        return false;
    }

    public function add_blocked_sku($sku, $pattern = '', $is_regex = false)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_blocked_skus,
            array(
                'sku' => $sku,
                'pattern' => $pattern,
                'is_regex' => $is_regex ? 1 : 0,
                'created_by' => get_current_user_id()
            ),
            array('%s', '%s', '%d', '%d')
        );

        if ($result) {
            $this->log_action('blocked_sku_added', "Added blocked SKU: {$sku}", 'admin');
        }

        return $result;
    }

    // Logging methods
    public function log_action($action_type, $message, $user_type = 'system', $product_id = null, $product_sku = null, $product_title = null)
    {
        global $wpdb;

        $wpdb->insert(
            $this->table_logs,
            array(
                'action_type' => $action_type,
                'product_id' => $product_id,
                'product_sku' => $product_sku,
                'product_title' => $product_title,
                'message' => $message,
                'user_type' => $user_type,
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
    }

    private function get_client_ip()
    {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    // Settings methods
    public function get_setting($key, $default = '')
    {
        global $wpdb;

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$this->table_settings} WHERE setting_key = %s",
            $key
        ));

        return $value !== null ? $value : $default;
    }

    public function update_setting($key, $value)
    {
        global $wpdb;

        $wpdb->replace(
            $this->table_settings,
            array(
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => is_numeric($value) ? 'numeric' : (is_bool($value) ? 'boolean' : 'string')
            ),
            array('%s', '%s', '%s')
        );
    }

    // Scheduled cleanup
    public function scheduled_cleanup()
    {
        global $wpdb;

        $deleted_count = 0;

        // Find products without SKU
        if ($this->get_setting('auto_delete_empty_sku') == '1') {
            $products_without_sku = $wpdb->get_results("
                SELECT p.ID, p.post_title FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                WHERE p.post_type = 'product' AND p.post_status = 'publish' 
                AND (pm.meta_value IS NULL OR pm.meta_value = '')
                LIMIT 100
            ");

            foreach ($products_without_sku as $product) {
                $this->delete_product($product->ID, 'empty_sku', $product->post_title);
                $deleted_count++;
            }
        }

        // Find products with blocked SKUs
        if ($this->get_setting('auto_delete_blocked_sku') == '1') {
            $blocked_products = $wpdb->get_results("
                SELECT p.ID, p.post_title, pm.meta_value as sku FROM {$wpdb->posts} p 
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                WHERE p.post_type = 'product' AND p.post_status = 'publish' 
                AND pm.meta_value != ''
                LIMIT 100
            ");

            foreach ($blocked_products as $product) {
                if ($this->is_sku_blocked($product->sku)) {
                    $this->delete_product($product->ID, 'blocked_sku', $product->post_title, $product->sku);
                    $deleted_count++;
                }
            }
        }

        $this->log_action('cleanup_completed', "Cleanup completed. Deleted {$deleted_count} products.", 'system');

        return $deleted_count;
    }

    public function cleanup_old_logs()
    {
        global $wpdb;

        $retention_days = (int) $this->get_setting('log_retention_days', 30);

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_logs} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));

        $this->log_action('logs_cleaned', "Cleaned {$deleted} old log entries", 'system');

        return $deleted;
    }

    // Admin page methods
    public function admin_dashboard()
    {
        global $wpdb;

        // Get statistics
        $stats = array(
            'total_products' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status NOT IN ('trash', 'auto-draft')"),
            'products_without_sku' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->posts} p 
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                WHERE p.post_type = 'product' AND p.post_status NOT IN ('trash', 'auto-draft') 
                AND (pm.meta_value IS NULL OR pm.meta_value = '')
            "),
            'blocked_skus' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_blocked_skus} WHERE is_active = 1"),
            'total_logs' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_logs}"),
            'deleted_today' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$this->table_logs} 
                WHERE action_type IN ('product_deleted', 'product_trashed') 
                AND DATE(created_at) = CURDATE()
            ")
        );

        // Get recent activity
        $recent_logs = $wpdb->get_results("
            SELECT * FROM {$this->table_logs} 
            ORDER BY created_at DESC 
            LIMIT 10
        ");

        $this->render_dashboard($stats, $recent_logs);
    }

    public function admin_blocked_skus()
    {
        // Handle form submissions
        if (isset($_POST['add_blocked_sku']) && wp_verify_nonce($_POST['_wpnonce'], 'ysm_add_blocked_sku')) {
            $sku = sanitize_text_field($_POST['sku']);
            $pattern = sanitize_text_field($_POST['pattern']);
            $is_regex = isset($_POST['is_regex']) ? 1 : 0;

            if (!empty($sku)) {
                $this->add_blocked_sku($sku, $pattern, $is_regex);
                echo '<div class="notice notice-success"><p>SKU added to blocklist successfully!</p></div>';
            }
        }

        // Get blocked SKUs
        global $wpdb;
        $blocked_skus = $wpdb->get_results("SELECT * FROM {$this->table_blocked_skus} ORDER BY created_at DESC");

        $this->render_blocked_skus($blocked_skus);
    }

    public function admin_logs()
    {
        $this->render_logs();
    }

    public function admin_settings()
    {
        // Handle settings update
        if (isset($_POST['update_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'ysm_update_settings')) {
            $settings = array(
                'auto_delete_empty_sku',
                'auto_delete_blocked_sku',
                'delete_immediately'
            );

            foreach ($settings as $setting) {
                $value = isset($_POST[$setting]) ? '1' : '0';
                $this->update_setting($setting, $value);
            }

            $text_settings = array(
                'log_retention_days',
                'cleanup_schedule'
            );

            foreach ($text_settings as $setting) {
                if (isset($_POST[$setting])) {
                    $this->update_setting($setting, sanitize_text_field($_POST[$setting]));
                }
            }

            echo '<div class="notice notice-success"><p>Settings updated successfully!</p></div>';
        }

        $this->render_settings();
    }

    // AJAX handlers
    public function ajax_get_logs()
    {
        check_ajax_referer('ysm_nonce', 'nonce');

        global $wpdb;

        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : '';

        $where_clause = '';
        if (!empty($filter)) {
            $where_clause = $wpdb->prepare("WHERE action_type = %s", $filter);
        }

        $logs = $wpdb->get_results("
            SELECT * FROM {$this->table_logs} 
            {$where_clause}
            ORDER BY created_at DESC 
            LIMIT {$per_page} OFFSET {$offset}
        ");

        $total = $wpdb->get_var("
            SELECT COUNT(*) FROM {$this->table_logs} {$where_clause}
        ");

        wp_send_json_success(array(
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page
        ));
    }

    public function ajax_clear_logs()
    {
        check_ajax_referer('ysm_nonce', 'nonce');

        global $wpdb;
        $deleted = $wpdb->query("DELETE FROM {$this->table_logs}");

        $this->log_action('logs_cleared', 'All logs cleared by admin', 'admin');

        wp_send_json_success(array('deleted' => $deleted));
    }

    public function ajax_toggle_setting()
    {
        check_ajax_referer('ysm_nonce', 'nonce');

        $setting = sanitize_text_field($_POST['setting']);
        $value = sanitize_text_field($_POST['value']);

        $this->update_setting($setting, $value);

        wp_send_json_success();
    }

    public function ajax_bulk_delete()
    {
        check_ajax_referer('ysm_nonce', 'nonce');

        $deleted = $this->scheduled_cleanup();

        wp_send_json_success(array('deleted' => $deleted));
    }

    public function ajax_delete_blocked_sku()
    {
        check_ajax_referer('ysm_nonce', 'nonce');

        $id = (int) $_POST['id'];

        global $wpdb;
        $result = $wpdb->delete($this->table_blocked_skus, array('id' => $id), array('%d'));

        if ($result) {
            $this->log_action('blocked_sku_deleted', "Deleted blocked SKU ID: {$id}", 'admin');
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete blocked SKU');
        }
    }

    // Render methods
    private function render_dashboard($stats, $recent_logs)
    {
?>
<div class="wrap ysm-admin-wrap">
    <div class="ysm-header">
        <div class="ysm-header-content">
            <div class="ysm-logo">
                <h1><?php _e("SKU Manager", "yasir-sku-manager"); ?></h1>
                <p><?php _e("Advanced WooCommerce SKU Management System", "yasir-sku-manager"); ?></p>
            </div>
            <div class="ysm-header-actions">
                <button type="button" class="ysm-btn ysm-btn-primary" id="run-cleanup">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e("Run Cleanup Now", "yasir-sku-manager"); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="ysm-content">
        <div class="ysm-stats-grid">
            <div class="ysm-stat-card">
                <div class="ysm-stat-icon">
                    <span class="dashicons dashicons-products"></span>
                </div>
                <div class="ysm-stat-content">
                    <h3><?php echo number_format($stats['total_products']); ?></h3>
                    <p><?php _e("Total Products", "yasir-sku-manager"); ?></p>
                </div>
            </div>

            <div class="ysm-stat-card ysm-stat-warning">
                <div class="ysm-stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="ysm-stat-content">
                    <h3><?php echo number_format($stats['products_without_sku']); ?></h3>
                    <p><?php _e("Products Without SKU", "yasir-sku-manager"); ?></p>
                </div>
            </div>

            <div class="ysm-stat-card ysm-stat-danger">
                <div class="ysm-stat-icon">
                    <span class="dashicons dashicons-no"></span>
                </div>
                <div class="ysm-stat-content">
                    <h3><?php echo number_format($stats['blocked_skus']); ?></h3>
                    <p><?php _e("Blocked SKUs", "yasir-sku-manager"); ?></p>
                </div>
            </div>

            <div class="ysm-stat-card ysm-stat-info">
                <div class="ysm-stat-icon">
                    <span class="dashicons dashicons-trash"></span>
                </div>
                <div class="ysm-stat-content">
                    <h3><?php echo number_format($stats['deleted_today']); ?></h3>
                    <p><?php _e("Deleted Today", "yasir-sku-manager"); ?></p>
                </div>
            </div>
        </div>

        <div class="ysm-two-column">
            <div class="ysm-column">
                <div class="ysm-card">
                    <div class="ysm-card-header">
                        <h2><?php _e("Recent Activity", "yasir-sku-manager"); ?></h2>
                    </div>
                    <div class="ysm-card-content">
                        <div class="ysm-activity-list">
                            <?php foreach ($recent_logs as $log): ?>
                            <div class="ysm-activity-item">
                                <div class="ysm-activity-icon ysm-activity-<?php echo esc_attr($log->action_type); ?>">
                                    <span class="dashicons dashicons-<?php
                                                                                    echo $log->action_type == "product_deleted" ? "trash" : ($log->action_type == "blocked_sku_added" ? "no" : "yes");
                                                                                    ?>"></span>
                                </div>
                                <div class="ysm-activity-content">
                                    <p><?php echo esc_html($log->message); ?></p>
                                    <small><?php echo esc_html($log->created_at); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ysm-column">
                <div class="ysm-card">
                    <div class="ysm-card-header">
                        <h2><?php _e("Quick Actions", "yasir-sku-manager"); ?></h2>
                    </div>
                    <div class="ysm-card-content">
                        <div class="ysm-quick-actions">
                            <a href="<?php echo admin_url("admin.php?page=yasir-sku-manager-blocked"); ?>"
                                class="ysm-quick-action">
                                <span class="dashicons dashicons-no"></span>
                                <?php _e("Manage Blocked SKUs", "yasir-sku-manager"); ?>
                            </a>
                            <a href="<?php echo admin_url("admin.php?page=yasir-sku-manager-logs"); ?>"
                                class="ysm-quick-action">
                                <span class="dashicons dashicons-list-view"></span>
                                <?php _e("View All Logs", "yasir-sku-manager"); ?>
                            </a>
                            <a href="<?php echo admin_url("admin.php?page=yasir-sku-manager-settings"); ?>"
                                class="ysm-quick-action">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php _e("Plugin Settings", "yasir-sku-manager"); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ysm-footer">
        <p><?php _e("Developed by", "yasir-sku-manager"); ?> <a href="https://yasirshabbir.com" target="_blank">Yasir
                Shabbir</a>
        </p>
    </div>
</div>
<?php
    }

    private function render_blocked_skus($blocked_skus)
    {
    ?>
<div class="wrap ysm-admin-wrap">
    <div class="ysm-header">
        <div class="ysm-header-content">
            <h1><?php _e("Blocked SKUs Management", "yasir-sku-manager"); ?></h1>
            <p><?php _e("Manage SKUs that should be automatically deleted", "yasir-sku-manager"); ?></p>
        </div>
    </div>

    <div class="ysm-content">
        <div class="ysm-card">
            <div class="ysm-card-header">
                <h2><?php _e("Add New Blocked SKU", "yasir-sku-manager"); ?></h2>
            </div>
            <div class="ysm-card-content">
                <form method="post" class="ysm-form">
                    <?php wp_nonce_field('ysm_add_blocked_sku'); ?>
                    <div class="ysm-form-row">
                        <div class="ysm-form-group">
                            <label for="sku"><?php _e("SKU or Pattern", "yasir-sku-manager"); ?></label>
                            <input type="text" name="sku" id="sku" class="ysm-input" required
                                placeholder="<?php _e("e.g., TEMP-001 or ^TEST-", "yasir-sku-manager"); ?>">
                        </div>
                        <div class="ysm-form-group">
                            <label for="pattern"><?php _e("Description", "yasir-sku-manager"); ?></label>
                            <input type="text" name="pattern" id="pattern" class="ysm-input"
                                placeholder="<?php _e("Optional description", "yasir-sku-manager"); ?>">
                        </div>
                    </div>
                    <div class="ysm-form-row">
                        <div class="ysm-form-group">
                            <label class="ysm-checkbox-label">
                                <input type="checkbox" name="is_regex" value="1">
                                <?php _e("This is a regex pattern", "yasir-sku-manager"); ?>
                                <small><?php _e("Check this if you want to use regular expressions", "yasir-sku-manager"); ?></small>
                            </label>
                        </div>
                    </div>
                    <div class="ysm-form-actions">
                        <button type="submit" name="add_blocked_sku" class="ysm-btn ysm-btn-primary">
                            <?php _e("Add Blocked SKU", "yasir-sku-manager"); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="ysm-card">
            <div class="ysm-card-header">
                <h2><?php _e("Current Blocked SKUs", "yasir-sku-manager"); ?></h2>
            </div>
            <div class="ysm-card-content">
                <div class="ysm-table-responsive">
                    <table class="ysm-table">
                        <thead>
                            <tr>
                                <th><?php _e("SKU/Pattern", "yasir-sku-manager"); ?></th>
                                <th><?php _e("Type", "yasir-sku-manager"); ?></th>
                                <th><?php _e("Description", "yasir-sku-manager"); ?></th>
                                <th><?php _e("Status", "yasir-sku-manager"); ?></th>
                                <th><?php _e("Created", "yasir-sku-manager"); ?></th>
                                <th><?php _e("Actions", "yasir-sku-manager"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocked_skus as $blocked): ?>
                            <tr>
                                <td><code><?php echo esc_html($blocked->sku); ?></code></td>
                                <td>
                                    <span
                                        class="ysm-badge ysm-badge-<?php echo $blocked->is_regex ? "warning" : "info"; ?>">
                                        <?php echo $blocked->is_regex ? __("Regex", "yasir-sku-manager") : __("Exact", "yasir-sku-manager"); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($blocked->pattern); ?></td>
                                <td>
                                    <span
                                        class="ysm-badge ysm-badge-<?php echo $blocked->is_active ? "success" : "secondary"; ?>">
                                        <?php echo $blocked->is_active ? __("Active", "yasir-sku-manager") : __("Inactive", "yasir-sku-manager"); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($blocked->created_at); ?></td>
                                <td>
                                    <button class="ysm-btn ysm-btn-small ysm-btn-danger"
                                        onclick="deleteBlockedSKU(<?php echo $blocked->id; ?>)">
                                        <?php _e("Delete", "yasir-sku-manager"); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    }

    private function render_logs()
    {
    ?>
<div class="wrap ysm-admin-wrap">
    <div class="ysm-header">
        <div class="ysm-header-content">
            <h1><?php _e("Activity Logs", "yasir-sku-manager"); ?></h1>
            <div class="ysm-header-actions">
                <button type="button" class="ysm-btn ysm-btn-danger" id="clear-logs">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e("Clear All Logs", "yasir-sku-manager"); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="ysm-content">
        <div class="ysm-card">
            <div class="ysm-card-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><?php _e("System Activity", "yasir-sku-manager"); ?></h2>
                    <div>
                        <select id="log-filter" class="ysm-input" style="width: auto; min-width: 200px;">
                            <option value=""><?php _e("All Actions", "yasir-sku-manager"); ?></option>
                            <option value="product_deleted"><?php _e("Products Deleted", "yasir-sku-manager"); ?>
                            </option>
                            <option value="product_trashed"><?php _e("Products Trashed", "yasir-sku-manager"); ?>
                            </option>
                            <option value="blocked_sku_added"><?php _e("SKUs Blocked", "yasir-sku-manager"); ?></option>
                            <option value="cleanup_completed"><?php _e("Cleanups", "yasir-sku-manager"); ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="ysm-card-content">
                <div class="ysm-table-responsive">
                    <table class="ysm-table" id="logs-table">
                        <thead>
                            <tr>
                                <th><?php _e("Action", "yasir-sku-manager"); ?></th>
                                <th><?php _e("Product ID", "yasir-sku-manager"); ?></th>
                                <th><?php _e("SKU", "yasir-sku-manager"); ?></th>
                                <th><?php _e("Product Title", "yasir-sku-manager"); ?></th>
                                <th><?php _e("Message", "yasir-sku-manager"); ?></th>
                                <th><?php _e("Date/Time", "yasir-sku-manager"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">
                                    <span class="dashicons dashicons-update-alt"
                                        style="animation: spin 1s linear infinite;"></span>
                                    <?php _e("Loading logs...", "yasir-sku-manager"); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="ysm-pagination" style="margin-top: 1rem; text-align: center;"></div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.ysm-pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
</style>
<?php
    }

    private function render_settings()
    {
    ?>
<div class="wrap ysm-admin-wrap">
    <div class="ysm-header">
        <div class="ysm-header-content">
            <h1><?php _e("Plugin Settings", "yasir-sku-manager"); ?></h1>
            <p><?php _e("Configure how the SKU Manager plugin behaves", "yasir-sku-manager"); ?></p>
        </div>
    </div>

    <div class="ysm-content">
        <form method="post" class="ysm-form">
            <?php wp_nonce_field('ysm_update_settings'); ?>
            <div class="ysm-two-column">
                <div class="ysm-column">
                    <div class="ysm-card">
                        <div class="ysm-card-header">
                            <h2><?php _e("Product Management", "yasir-sku-manager"); ?></h2>
                        </div>
                        <div class="ysm-card-content">
                            <div class="ysm-form-group">
                                <label class="ysm-checkbox-label">
                                    <input type="checkbox" name="auto_delete_empty_sku" value="1"
                                        <?php checked($this->get_setting("auto_delete_empty_sku"), "1"); ?>
                                        class="ysm-setting-toggle" data-setting="auto_delete_empty_sku">
                                    <?php _e("Auto-delete products without SKU", "yasir-sku-manager"); ?>
                                    <small><?php _e("Automatically remove products that don't have SKUs", "yasir-sku-manager"); ?></small>
                                </label>
                            </div>

                            <div class="ysm-form-group">
                                <label class="ysm-checkbox-label">
                                    <input type="checkbox" name="auto_delete_blocked_sku" value="1"
                                        <?php checked($this->get_setting("auto_delete_blocked_sku"), "1"); ?>
                                        class="ysm-setting-toggle" data-setting="auto_delete_blocked_sku">
                                    <?php _e("Auto-delete products with blocked SKUs", "yasir-sku-manager"); ?>
                                    <small><?php _e("Automatically remove products with SKUs in the blocklist", "yasir-sku-manager"); ?></small>
                                </label>
                            </div>

                            <div class="ysm-form-group">
                                <label class="ysm-checkbox-label">
                                    <input type="checkbox" name="delete_immediately" value="1"
                                        <?php checked($this->get_setting("delete_immediately"), "1"); ?>
                                        class="ysm-setting-toggle" data-setting="delete_immediately">
                                    <?php _e("Delete immediately (bypass trash)", "yasir-sku-manager"); ?>
                                    <small><?php _e("Permanently delete products instead of moving to trash", "yasir-sku-manager"); ?></small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ysm-column">
                    <div class="ysm-card">
                        <div class="ysm-card-header">
                            <h2><?php _e("Maintenance", "yasir-sku-manager"); ?></h2>
                        </div>
                        <div class="ysm-card-content">
                            <div class="ysm-form-group">
                                <label
                                    for="cleanup_schedule"><?php _e("Cleanup Schedule", "yasir-sku-manager"); ?></label>
                                <select name="cleanup_schedule" id="cleanup_schedule" class="ysm-input">
                                    <option value="hourly"
                                        <?php selected($this->get_setting("cleanup_schedule"), "hourly"); ?>>
                                        <?php _e("Every Hour", "yasir-sku-manager"); ?></option>
                                    <option value="twicedaily"
                                        <?php selected($this->get_setting("cleanup_schedule"), "twicedaily"); ?>>
                                        <?php _e("Twice Daily", "yasir-sku-manager"); ?></option>
                                    <option value="daily"
                                        <?php selected($this->get_setting("cleanup_schedule"), "daily"); ?>>
                                        <?php _e("Daily", "yasir-sku-manager"); ?></option>
                                </select>
                            </div>

                            <div class="ysm-form-group">
                                <label
                                    for="log_retention_days"><?php _e("Log Retention (Days)", "yasir-sku-manager"); ?></label>
                                <input type="number" name="log_retention_days" id="log_retention_days"
                                    value="<?php echo esc_attr($this->get_setting("log_retention_days")); ?>"
                                    class="ysm-input" min="1" max="365">
                                <small><?php _e("How many days to keep logs before automatic cleanup", "yasir-sku-manager"); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ysm-form-actions" style="text-align: center; margin-top: 2rem;">
                <button type="submit" name="update_settings" class="ysm-btn ysm-btn-primary"
                    style="padding: 1rem 3rem;">
                    <?php _e("Save Settings", "yasir-sku-manager"); ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php
    }

    // Get admin CSS
    private function get_admin_css()
    {
        return '
        :root {
            --accent-color: #16e791;
            --primary-text: #ffffff;
            --secondary-text: #e0e0e0;
            --background-dark: #121212;
            --background-medium: #1e1e1e;
            --background-light: #2a2a2a;
            --border-color: #333333;
            --border-light: #444444;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --secondary-color: #6c757d;
        }

        .ysm-admin-wrap {
            font-family: "Lato", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--background-dark);
            color: var(--primary-text);
            border-radius: 3px;
            margin: 0 -20px 0 -10px;
            min-height: calc(100vh - 160px);
        }

        .ysm-header {
            background: var(--background-medium);
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        .ysm-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .ysm-logo h1 {
            color: var(--accent-color);
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            font-weight: 300;
        }

        .ysm-logo p {
            color: var(--secondary-text);
            margin: 0;
            font-size: 1rem;
        }

        .ysm-content {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .ysm-card {
            background: var(--background-medium);
            border: 1px solid var(--border-color);
            border-radius: 3px;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .ysm-card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--background-light);
        }

        .ysm-card-header h2 {
            margin: 0;
            color: var(--primary-text);
            font-size: 1.25rem;
            font-weight: 400;
        }

        .ysm-card-content {
            padding: 2rem;
        }

        .ysm-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .ysm-stat-card {
            background: var(--background-medium);
            border: 1px solid var(--border-color);
            border-radius: 3px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .ysm-stat-card.ysm-stat-warning {
            border-left: 4px solid var(--warning-color);
        }

        .ysm-stat-card.ysm-stat-danger {
            border-left: 4px solid var(--danger-color);
        }

        .ysm-stat-card.ysm-stat-info {
            border-left: 4px solid var(--info-color);
        }

        .ysm-stat-icon {
            font-size: 2rem;
            color: var(--accent-color);
        }

        .ysm-stat-content h3 {
            margin: 0;
            font-size: 2rem;
            font-weight: 300;
            color: var(--primary-text);
        }

        .ysm-stat-content p {
            margin: 0.25rem 0 0 0;
            color: var(--secondary-text);
            font-size: 0.875rem;
        }

        .ysm-two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .ysm-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .ysm-btn-primary {
            background: var(--accent-color);
            color: var(--background-dark);
        }

        .ysm-btn-primary:hover {
            background: #14d182;
            transform: translateY(-1px);
        }

        .ysm-btn-secondary {
            background: var(--secondary-color);
            color: var(--primary-text);
        }

        .ysm-btn-danger {
            background: var(--danger-color);
            color: var(--primary-text);
        }

        .ysm-btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        .ysm-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            background: var(--background-light);
            color: var(--primary-text);
            font-family: inherit;
        }

        .ysm-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(22, 231, 145, 0.2);
        }

        .ysm-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--background-medium);
            border-radius: 3px;
            overflow: hidden;
        }

        .ysm-table th,
        .ysm-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .ysm-table th {
            background: var(--background-light);
            font-weight: 600;
            color: var(--primary-text);
        }

        .ysm-table td {
            color: var(--secondary-text);
        }

        .ysm-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .ysm-badge-success {
            background: var(--success-color);
            color: var(--primary-text);
        }

        .ysm-badge-warning {
            background: var(--warning-color);
            color: var(--background-dark);
        }

        .ysm-badge-info {
            background: var(--info-color);
            color: var(--primary-text);
        }

        .ysm-badge-secondary {
            background: var(--secondary-color);
            color: var(--primary-text);
        }

        .ysm-footer {
            text-align: center;
            padding: 2rem;
            border-top: 1px solid var(--border-color);
            color: var(--secondary-text);
            font-size: 0.875rem;
        }

        .ysm-footer a {
            color: var(--accent-color);
            text-decoration: none;
        }

        .ysm-footer a:hover {
            text-decoration: underline;
        }

        /* Form Styles */
        .ysm-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .ysm-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .ysm-form-group label {
            color: var(--primary-text);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .ysm-checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .ysm-checkbox-label input[type="checkbox"] {
            margin: 0;
        }

        .ysm-form-actions {
            margin-top: 2rem;
        }

        /* Activity List */
        .ysm-activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .ysm-activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--background-light);
            border-radius: 3px;
            border-left: 3px solid var(--accent-color);
        }

        .ysm-activity-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-color);
            color: var(--background-dark);
            flex-shrink: 0;
        }

        .ysm-activity-content p {
            margin: 0;
            color: var(--primary-text);
        }

        .ysm-activity-content small {
            color: var(--secondary-text);
        }

        /* Quick Actions */
        .ysm-quick-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .ysm-quick-action {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--background-light);
            color: var(--primary-text);
            text-decoration: none;
            border-radius: 3px;
            transition: all 0.2s ease;
        }

        .ysm-quick-action:hover {
            background: var(--border-light);
            transform: translateX(5px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .ysm-two-column {
                grid-template-columns: 1fr;
            }
            
            .ysm-form-row {
                grid-template-columns: 1fr;
            }
            
            .ysm-header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .ysm-stats-grid {
                grid-template-columns: 1fr;
            }
        }
        ';
    }
}