<?php

/**
 * Plugin Name: WooCommerce SKU Manager (Safe Version)
 * Plugin URI: https://yasirshabbir.com
 * Description: Advanced SKU management system with optimized logging and automated filtering for WooCommerce products
 * Version: 1.2
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
    
    // Simple logs functionality with better pagination
    let currentPage = 1;
    let currentFilters = {
        action_type: "",
        search: "",
        per_page: 50
    };
    
    function loadLogs(page = 1, resetFilters = false) {
        if (resetFilters) {
            currentFilters = {
                action_type: "",
                search: "",
                per_page: $("#per-page-select").val() || 50
            };
            currentPage = 1;
        } else {
            currentPage = page;
        }
        
        $("#logs-loading").show();
        $("#logs-table").hide();
        $("#simple-pagination").empty();
        
        const filters = {
            ...currentFilters,
            action_type: $("#log-filter").val(),
            search: $("#search-logs").val(),
            per_page: $("#per-page-select").val() || 50
        };
        
        // Add timeout and better error handling
        $.ajax({
            url: ysmAjax.ajaxurl,
            type: "POST",
            data: {
                action: "ysm_get_logs",
                page: currentPage,
                filters: filters,
                nonce: ysmAjax.nonce
            },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (response.success) {
                    updateLogsTable(response.data);
                    currentFilters = filters;
                } else {
                    $("#logs-loading").hide();
                    $("#logs-table").show();
                    $("#logs-table tbody").html(`
                        <tr>
                            <td colspan="7" style="text-align: center; color: red; padding: 2rem;">
                                <strong>Error loading logs:</strong><br>
                                ${response.data || "Unknown error occurred"}
                                <br><br>
                                <button class="ysm-btn ysm-btn-secondary" onclick="location.reload()">Reload Page</button>
                            </td>
                        </tr>
                    `);
                }
            },
            error: function(xhr, status, error) {
                $("#logs-loading").hide();
                $("#logs-table").show();
                
                let errorMessage = "Connection failed";
                if (status === "timeout") {
                    errorMessage = "Request timed out - too many logs to load at once";
                } else if (xhr.status === 500) {
                    errorMessage = "Server error - check error logs";
                } else if (xhr.status === 0) {
                    errorMessage = "Network connection failed";
                }
                
                $("#logs-table tbody").html(`
                    <tr>
                        <td colspan="7" style="text-align: center; color: red; padding: 2rem;">
                            <strong>Failed to load logs:</strong><br>
                            ${errorMessage}
                            <br><br>
                            <button class="ysm-btn ysm-btn-secondary" onclick="loadLogs(1, true)">Try Again</button>
                            <button class="ysm-btn ysm-btn-secondary" onclick="location.reload()">Reload Page</button>
                        </td>
                    </tr>
                `);
            }
        });
    }
    
    function updateLogsTable(data) {
        const tbody = $("#logs-table tbody");
        tbody.empty();
        
        if (data.logs.length === 0) {
            tbody.append("<tr><td colspan=\"7\" style=\"text-align: center; color: var(--secondary-text); padding: 3rem;\">No logs found</td></tr>");
            $("#simple-pagination").empty();
            return;
        }
        
        data.logs.forEach(log => {
            const actionBadge = getActionBadge(log.action_type);
            const row = `
                <tr>
                    <td>${actionBadge}</td>
                    <td>${log.product_id || "-"}</td>
                    <td>${log.product_sku ? `<code>${log.product_sku}</code>` : "-"}</td>
                    <td title="${log.product_title || ""}">${truncateText(log.product_title || "-", 30)}</td>
                    <td title="${log.message}">${truncateText(log.message, 50)}</td>
                    <td><span class="ysm-badge ysm-badge-info">${log.user_type}</span></td>
                    <td>${formatDateTime(log.created_at)}</td>
                </tr>
            `;
            tbody.append(row);
        });
        
        updateSimplePagination(data);
    }
    
    function getActionBadge(actionType) {
        const badges = {
            "product_deleted": `<span class="ysm-badge ysm-badge-danger">Deleted</span>`,
            "product_trashed": `<span class="ysm-badge ysm-badge-warning">Trashed</span>`,
            "blocked_sku_added": `<span class="ysm-badge ysm-badge-secondary">SKU Blocked</span>`,
            "cleanup_completed": `<span class="ysm-badge ysm-badge-success">Cleanup</span>`,
            "product_processed": `<span class="ysm-badge ysm-badge-info">Processed</span>`
        };
        
        return badges[actionType] || `<span class="ysm-badge ysm-badge-info">${actionType}</span>`;
    }
    
    function truncateText(text, length) {
        if (!text || text.length <= length) return text;
        return text.substring(0, length) + "...";
    }
    
    function formatDateTime(datetime) {
        const date = new Date(datetime);
        return date.toLocaleDateString() + " " + date.toLocaleTimeString();
    }
    
    // Simple pagination - max 10 buttons
    function updateSimplePagination(data) {
        const pagination = $("#simple-pagination");
        pagination.empty();
        
        const totalPages = Math.ceil(data.total / data.per_page);
        
        if (totalPages <= 1) return;
        
        // Show page info
        const pageInfo = `<span class="page-info">Page ${data.page} of ${totalPages.toLocaleString()} (${data.total.toLocaleString()} total)</span>`;
        pagination.append(pageInfo);
        
        const buttonsDiv = $("<div class=\"pagination-buttons\"></div>");
        
        // First and Previous
        if (data.page > 1) {
            buttonsDiv.append(`<button class="ysm-btn ysm-btn-secondary" onclick="loadLogs(1)">First</button>`);
            buttonsDiv.append(`<button class="ysm-btn ysm-btn-secondary" onclick="loadLogs(${data.page - 1})">Prev</button>`);
        }
        
        // Current page indicator
        buttonsDiv.append(`<span class="current-page">Page ${data.page}</span>`);
        
        // Next and Last
        if (data.page < totalPages) {
            buttonsDiv.append(`<button class="ysm-btn ysm-btn-secondary" onclick="loadLogs(${data.page + 1})">Next</button>`);
            buttonsDiv.append(`<button class="ysm-btn ysm-btn-secondary" onclick="loadLogs(${totalPages})">Last</button>`);
        }
        
        // Jump to page (for large datasets)
        if (totalPages > 10) {
            const jumpDiv = $(`
                <div class="jump-to-page">
                    <label>Go to page:</label>
                    <input type="number" id="jump-page" min="1" max="${totalPages}" placeholder="${data.page}" style="width: 80px;">
                    <button class="ysm-btn ysm-btn-secondary" onclick="jumpToPage()">Go</button>
                </div>
            `);
            buttonsDiv.append(jumpDiv);
        }
        
        pagination.append(buttonsDiv);
    }
    
    // Global function for jumping to page
    window.loadLogs = loadLogs;
    window.jumpToPage = function() {
        const page = parseInt($("#jump-page").val());
        const maxPage = parseInt($("#jump-page").attr("max"));
        if (page >= 1 && page <= maxPage) {
            loadLogs(page);
        }
    };
    
    // Filter events
    $("#log-filter, #per-page-select").on("change", function() {
        loadLogs(1, true);
    });
    
    // Search with delay
    let searchTimeout;
    $("#search-logs").on("keyup", function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadLogs(1, true);
        }, 500);
    });
    
    // Clear filters
    $("#clear-filters").on("click", function() {
        $("#log-filter").val("");
        $("#search-logs").val("");
        $("#per-page-select").val("50");
        loadLogs(1, true);
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
                        loadLogs(1, true);
                        alert("Logs cleared successfully!");
                    }
                }
            });
        }
    });
    
    // Load logs on page load
    if ($("#logs-table").length) {
        loadLogs(1, true);
    }
    
    // Settings toggle
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
                    const indicator = $("<span class=\"ysm-success-indicator\">✓</span>");
                    $(this).parent().append(indicator);
                    setTimeout(() => indicator.fadeOut(), 2000);
                }
            }
        });
    });
});';

        // Create assets directory if it doesn't exist
        if (!file_exists(YSM_PLUGIN_DIR . "assets/")) {
            wp_mkdir_p(YSM_PLUGIN_DIR . "assets/");
        }

        // Write JavaScript file
        file_put_contents(YSM_PLUGIN_DIR . "assets/admin.js", $js_content);
    }

    // Create JS file on activation
    register_activation_hook(__FILE__, "ysm_create_js_file");

    // Also create it on init if it doesn't exist
    add_action("init", function () {
        if (!file_exists(YSM_PLUGIN_DIR . "assets/admin.js")) {
            ysm_create_js_file();
        }
    });
}

// Define plugin constants
define('YSM_VERSION', '1.0.1');
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

        // Logs table with basic indexing
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

    // Simple AJAX handlers with better error handling
    public function ajax_get_logs()
    {
        // Add more debugging and error handling
        if (!check_ajax_referer('ysm_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        global $wpdb;

        try {
            $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
            $filters = isset($_POST['filters']) ? $_POST['filters'] : array();

            $per_page = isset($filters['per_page']) ? max(10, min(250, intval($filters['per_page']))) : 50;
            $offset = ($page - 1) * $per_page;

            // Build WHERE clause - simplified to prevent timeouts
            $where_conditions = array();
            $where_values = array();

            if (!empty($filters['action_type'])) {
                $where_conditions[] = "action_type = %s";
                $where_values[] = sanitize_text_field($filters['action_type']);
            }

            if (!empty($filters['search'])) {
                $search_term = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
                $where_conditions[] = "product_sku LIKE %s";
                $where_values[] = $search_term;
            }

            $where_clause = '';
            if (!empty($where_conditions)) {
                $where_clause = "WHERE " . implode(" AND ", $where_conditions);
            }

            // Get logs with timeout protection
            $query = "SELECT * FROM {$this->table_logs} {$where_clause} ORDER BY id DESC LIMIT %d OFFSET %d";
            $where_values[] = $per_page;
            $where_values[] = $offset;

            if (!empty($where_values)) {
                $logs = $wpdb->get_results($wpdb->prepare($query, ...$where_values));
            } else {
                $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_logs} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset));
            }

            if ($logs === false) {
                wp_send_json_error('Database query failed: ' . $wpdb->last_error);
                return;
            }

            // Use approximate count for performance
            $total = 0;
            if ($page == 1 && empty($where_conditions)) {
                // Fast approximate count for first page
                $count_result = $wpdb->get_row("SELECT table_rows FROM information_schema.tables WHERE table_name = '{$this->table_logs}' AND table_schema = DATABASE()");
                $total = $count_result ? $count_result->table_rows : 0;

                // Fallback to exact count if estimate not available
                if (!$total) {
                    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_logs}");
                }
            } else {
                // For filtered results or other pages, use exact count but limit it
                $count_query = "SELECT COUNT(*) FROM {$this->table_logs} {$where_clause}";
                if (!empty($where_conditions)) {
                    $count_values = array_slice($where_values, 0, -2);
                    $total = $wpdb->get_var($wpdb->prepare($count_query, ...$count_values));
                } else {
                    $total = $wpdb->get_var($count_query);
                }
            }

            wp_send_json_success(array(
                'logs' => $logs ? $logs : array(),
                'total' => intval($total),
                'page' => $page,
                'per_page' => $per_page
            ));
        } catch (Exception $e) {
            wp_send_json_error('Server error: ' . $e->getMessage());
        }
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
                Shabbir</a></p>
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
        // Get logs directly via PHP to bypass AJAX issues
        global $wpdb;

        // Handle pagination via URL parameters
        $current_page = isset($_GET['logs_page']) ? max(1, intval($_GET['logs_page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 25;
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $action_filter = isset($_GET['action_filter']) ? sanitize_text_field($_GET['action_filter']) : '';

        $offset = ($current_page - 1) * $per_page;

        // Build query conditions
        $where_conditions = array();
        $where_values = array();

        if (!empty($action_filter)) {
            $where_conditions[] = "action_type = %s";
            $where_values[] = $action_filter;
        }

        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = "product_sku LIKE %s";
            $where_values[] = $search_term;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        }

        // Get total count (with limit to prevent timeouts)
        try {
            if (empty($where_conditions)) {
                // Fast count for unfiltered results
                $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_logs} LIMIT 50000");
            } else {
                $count_query = "SELECT COUNT(*) FROM {$this->table_logs} {$where_clause}";
                $total_logs = $wpdb->get_var($wpdb->prepare($count_query, ...$where_values));
            }
        } catch (Exception $e) {
            $total_logs = 0;
        }

        // Get logs
        try {
            $query = "SELECT * FROM {$this->table_logs} {$where_clause} ORDER BY id DESC LIMIT %d OFFSET %d";
            $query_values = array_merge($where_values, array($per_page, $offset));

            if (!empty($query_values)) {
                $logs = $wpdb->get_results($wpdb->prepare($query, ...$query_values));
            } else {
                $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_logs} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset));
            }
        } catch (Exception $e) {
            $logs = array();
            $error_message = "Database error: " . $e->getMessage();
        }

        $total_pages = ceil($total_logs / $per_page);

    ?>
<div class="wrap ysm-admin-wrap">
    <div class="ysm-header">
        <div class="ysm-header-content">
            <h1><?php _e("Activity Logs", "yasir-sku-manager"); ?></h1>
            <div class="ysm-header-actions">
                <?php if ($total_logs > 0): ?>
                <a href="<?php echo wp_nonce_url(add_query_arg(array('clear_all_logs' => '1')), 'ysm_clear_logs'); ?>"
                    class="ysm-btn ysm-btn-danger"
                    onclick="return confirm('Are you sure you want to clear all logs? This cannot be undone.')">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e("Clear All Logs", "yasir-sku-manager"); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="ysm-content">
        <!-- Handle clear logs action -->
        <?php
                if (isset($_GET['clear_all_logs']) && wp_verify_nonce($_GET['_wpnonce'], 'ysm_clear_logs')) {
                    $deleted = $wpdb->query("DELETE FROM {$this->table_logs}");
                    echo '<div class="notice notice-success"><p>Cleared ' . number_format($deleted) . ' log entries.</p></div>';
                    $this->log_action('logs_cleared', 'All logs cleared by admin', 'admin');
                    // Refresh the page
                    echo '<script>window.location.href = "' . admin_url('admin.php?page=yasir-sku-manager-logs') . '";</script>';
                }
                ?>

        <div class="ysm-card">
            <div class="ysm-card-header">
                <div class="ysm-logs-controls">
                    <!-- Filter Form -->
                    <form method="get" class="ysm-logs-filters">
                        <input type="hidden" name="page" value="yasir-sku-manager-logs">

                        <select name="action_filter" class="ysm-input">
                            <option value=""><?php _e("All Actions", "yasir-sku-manager"); ?></option>
                            <option value="product_deleted" <?php selected($action_filter, 'product_deleted'); ?>>
                                <?php _e("Products Deleted", "yasir-sku-manager"); ?></option>
                            <option value="product_trashed" <?php selected($action_filter, 'product_trashed'); ?>>
                                <?php _e("Products Trashed", "yasir-sku-manager"); ?></option>
                            <option value="blocked_sku_added" <?php selected($action_filter, 'blocked_sku_added'); ?>>
                                <?php _e("SKUs Blocked", "yasir-sku-manager"); ?></option>
                            <option value="cleanup_completed" <?php selected($action_filter, 'cleanup_completed'); ?>>
                                <?php _e("Cleanups", "yasir-sku-manager"); ?></option>
                        </select>

                        <input type="text" name="search" class="ysm-input" value="<?php echo esc_attr($search); ?>"
                            placeholder="<?php _e("Search SKU...", "yasir-sku-manager"); ?>">

                        <select name="per_page" class="ysm-input">
                            <option value="25" <?php selected($per_page, 25); ?>>25 per page</option>
                            <option value="50" <?php selected($per_page, 50); ?>>50 per page</option>
                            <option value="100" <?php selected($per_page, 100); ?>>100 per page</option>
                        </select>

                        <button type="submit"
                            class="ysm-btn ysm-btn-secondary"><?php _e("Filter", "yasir-sku-manager"); ?></button>

                        <?php if (!empty($search) || !empty($action_filter)): ?>
                        <a href="<?php echo admin_url('admin.php?page=yasir-sku-manager-logs'); ?>"
                            class="ysm-btn ysm-btn-secondary">
                            <?php _e("Clear Filters", "yasir-sku-manager"); ?>
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="ysm-card-content">
                <?php if (isset($error_message)): ?>
                <div class="notice notice-error">
                    <p><strong>Error:</strong> <?php echo esc_html($error_message); ?></p>
                    <p>Try reducing the per-page count or clearing old logs.</p>
                </div>
                <?php endif; ?>

                <?php if ($total_logs > 0): ?>
                <div class="logs-info" style="margin-bottom: 1rem; color: #666;">
                    Showing <?php echo number_format(min($total_logs, $per_page)); ?> of
                    <?php echo number_format($total_logs); ?> logs
                    <?php if ($total_pages > 1): ?>
                    (Page <?php echo $current_page; ?> of <?php echo number_format($total_pages); ?>)
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="ysm-table-responsive">
                    <table class="ysm-table">
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
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 3rem; color: #666;">
                                    <?php if ($total_logs == 0): ?>
                                    No logs found. The plugin will start logging activity as products are processed.
                                    <?php else: ?>
                                    No logs match your current filters.
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <?php
                                                    $badge_class = 'ysm-badge-info';
                                                    $badge_text = ucfirst(str_replace('_', ' ', $log->action_type));

                                                    if (strpos($log->action_type, 'deleted') !== false) {
                                                        $badge_class = 'ysm-badge-danger';
                                                        $badge_text = 'Deleted';
                                                    } elseif (strpos($log->action_type, 'trashed') !== false) {
                                                        $badge_class = 'ysm-badge-warning';
                                                        $badge_text = 'Trashed';
                                                    } elseif (strpos($log->action_type, 'cleanup') !== false) {
                                                        $badge_class = 'ysm-badge-success';
                                                        $badge_text = 'Cleanup';
                                                    }
                                                    ?>
                                    <span
                                        class="ysm-badge <?php echo $badge_class; ?>"><?php echo esc_html($badge_text); ?></span>
                                </td>
                                <td><?php echo $log->product_id ? esc_html($log->product_id) : '-'; ?></td>
                                <td><?php echo $log->product_sku ? '<code>' . esc_html($log->product_sku) . '</code>' : '-'; ?>
                                </td>
                                <td title="<?php echo esc_attr($log->product_title); ?>">
                                    <?php
                                                    $title = $log->product_title ?: '-';
                                                    echo esc_html(strlen($title) > 30 ? substr($title, 0, 30) . '...' : $title);
                                                    ?>
                                </td>
                                <td title="<?php echo esc_attr($log->message); ?>">
                                    <?php echo esc_html(strlen($log->message) > 50 ? substr($log->message, 0, 50) . '...' : $log->message); ?>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Simple Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="margin-top: 2rem; text-align: center;">
                    <div class="pagination-info" style="margin-bottom: 1rem; color: #666;">
                        Page <?php echo $current_page; ?> of <?php echo number_format($total_pages); ?>
                    </div>

                    <div class="pagination-buttons"
                        style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                        <?php if ($current_page > 1): ?>
                        <a href="<?php echo add_query_arg(array_merge($_GET, array('logs_page' => 1))); ?>"
                            class="ysm-btn ysm-btn-secondary">First</a>
                        <a href="<?php echo add_query_arg(array_merge($_GET, array('logs_page' => $current_page - 1))); ?>"
                            class="ysm-btn ysm-btn-secondary">Previous</a>
                        <?php endif; ?>

                        <span class="ysm-btn ysm-btn-primary">Page <?php echo $current_page; ?></span>

                        <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo add_query_arg(array_merge($_GET, array('logs_page' => $current_page + 1))); ?>"
                            class="ysm-btn ysm-btn-secondary">Next</a>
                        <a href="<?php echo add_query_arg(array_merge($_GET, array('logs_page' => $total_pages))); ?>"
                            class="ysm-btn ysm-btn-secondary">Last</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($total_pages > 10): ?>
                    <div style="margin-top: 1rem;">
                        <form method="get" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                            <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'logs_page'): ?>
                            <input type="hidden" name="<?php echo esc_attr($key); ?>"
                                value="<?php echo esc_attr($value); ?>">
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <label>Jump to page:</label>
                            <input type="number" name="logs_page" min="1" max="<?php echo $total_pages; ?>"
                                placeholder="<?php echo $current_page; ?>" style="width: 80px; padding: 0.5rem;">
                            <button type="submit" class="ysm-btn ysm-btn-secondary">Go</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Debug Information -->
        <?php if (current_user_can('manage_options')): ?>
        <div class="ysm-card" style="margin-top: 2rem;">
            <div class="ysm-card-header">
                <h2>Debug Information</h2>
            </div>
            <div class="ysm-card-content">
                <p><strong>Database Status:</strong>
                    <?php
                                try {
                                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_logs}'");
                                    echo $table_exists ? 'Table exists' : 'Table missing';
                                } catch (Exception $e) {
                                    echo 'Error: ' . $e->getMessage();
                                }
                                ?>
                </p>

                <p><strong>Total Logs in Database:</strong> <?php echo number_format($total_logs); ?></p>

                <p><strong>Current Query:</strong>
                    <?php if (isset($wpdb->last_query)): ?>
                    <code><?php echo esc_html($wpdb->last_query); ?></code>
                    <?php else: ?>
                    No query executed
                    <?php endif; ?>
                </p>

                <?php if (!empty($search) || !empty($action_filter)): ?>
                <p><strong>Active Filters:</strong>
                    <?php if (!empty($action_filter)): ?>Action: <?php echo esc_html($action_filter); ?> |
                    <?php endif; ?>
                    <?php if (!empty($search)): ?>Search: <?php echo esc_html($search); ?><?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.ysm-logs-filters {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
    width: 100%;
}

.ysm-logs-filters .ysm-input {
    width: auto;
    min-width: 150px;
}

.logs-info {
    background: #f5f5f5;
    padding: 0.75rem;
    border-radius: 3px;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .ysm-logs-filters {
        flex-direction: column;
        align-items: stretch;
    }

    .ysm-logs-filters .ysm-input {
        width: 100%;
    }

    .pagination-buttons {
        flex-direction: column;
    }
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
                                        <?php _e("Every Hour", "yasir-sku-manager"); ?>
                                    </option>
                                    <option value="twicedaily"
                                        <?php selected($this->get_setting("cleanup_schedule"), "twicedaily"); ?>>
                                        <?php _e("Twice Daily", "yasir-sku-manager"); ?>
                                    </option>
                                    <option value="daily"
                                        <?php selected($this->get_setting("cleanup_schedule"), "daily"); ?>>
                                        <?php _e("Daily", "yasir-sku-manager"); ?>
                                    </option>
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

    // Simple admin CSS
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

        .ysm-badge-danger {
            background: var(--danger-color);
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

        .ysm-success-indicator {
            color: var(--success-color);
            font-size: 0.75rem;
            margin-left: 0.5rem;
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

?>