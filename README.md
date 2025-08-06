# WooCommerce SKU Manager

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)

An advanced SKU management system with automated filtering, logging, and cleanup capabilities for WooCommerce products. This plugin helps maintain clean product catalogs by automatically managing products with empty or blocked SKUs.

## 🚀 Features

### Core Functionality
- **Automated SKU Filtering**: Automatically detect and handle products with empty or blocked SKUs
- **Bulk Product Cleanup**: Remove unwanted products in bulk operations
- **Scheduled Cleanup**: Automated background cleanup with configurable schedules
- **Real-time Monitoring**: Monitor product changes and SKU modifications in real-time

### SKU Management
- **Blocked SKU Lists**: Maintain lists of SKUs that should be automatically removed
- **Regex Pattern Support**: Use regular expressions for advanced SKU pattern matching
- **Exact Match Filtering**: Block specific SKUs with exact string matching
- **Dynamic SKU Validation**: Real-time validation during product creation/update

### Logging & Monitoring
- **Comprehensive Activity Logs**: Track all plugin activities with detailed logging
- **Filterable Log Views**: Filter logs by action type, date, and product information
- **Log Retention Management**: Automatic cleanup of old logs with configurable retention periods
- **Real-time Dashboard**: Live statistics and recent activity monitoring

### Admin Interface
- **Modern Dashboard**: Clean, responsive admin interface with real-time statistics
- **Quick Actions Panel**: Easy access to common tasks and settings
- **Bulk Operations**: Perform bulk actions on products and SKUs
- **AJAX-powered Interface**: Smooth user experience with asynchronous operations

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher

## 🔧 Installation

### Method 1: WordPress Admin (Recommended)
1. Download the plugin ZIP file
2. Go to **Plugins > Add New** in your WordPress admin
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

### Method 2: Manual Installation
1. Extract the plugin files to `/wp-content/plugins/ys-sku-manager/`
2. Go to **Plugins** in your WordPress admin
3. Find "WooCommerce SKU Manager" and click **Activate**

### Method 3: WP-CLI
```bash
wp plugin install ys-sku-manager.zip --activate
```

## ⚙️ Configuration

### Initial Setup
1. After activation, go to **WooCommerce > SKU Manager**
2. Configure your settings in the **Settings** tab
3. Add blocked SKUs in the **Blocked SKUs** tab
4. Monitor activity in the **Logs** tab

### Settings Options

#### Automation Settings
- **Auto Delete Empty SKU**: Automatically delete products without SKUs
- **Auto Delete Blocked SKU**: Automatically delete products with blocked SKUs
- **Delete Immediately**: Delete products immediately or move to trash first
- **Cleanup Schedule**: Set automated cleanup frequency (hourly, daily, weekly)

#### Log Management
- **Log Retention Days**: Number of days to keep activity logs (default: 30)
- **Log Level**: Control the verbosity of logging

## 📖 Usage

### Dashboard Overview
The main dashboard provides:
- **Product Statistics**: Total products, products without SKUs, blocked SKUs count
- **Recent Activity**: Latest plugin actions and changes
- **Quick Actions**: Direct links to common tasks
- **System Status**: Current plugin status and health

### Managing Blocked SKUs

#### Adding Blocked SKUs
1. Go to **SKU Manager > Blocked SKUs**
2. Enter the SKU or pattern to block
3. Optionally add a description
4. Choose between exact match or regex pattern
5. Click **Add Blocked SKU**

#### SKU Pattern Examples
- **Exact Match**: `TEMP-001` (blocks exactly this SKU)
- **Regex Pattern**: `^TEMP-` (blocks all SKUs starting with "TEMP-")
- **Regex Pattern**: `.*-TEST$` (blocks all SKUs ending with "-TEST")

### Bulk Operations

#### Manual Cleanup
1. Go to **SKU Manager > Dashboard**
2. Click **Run Cleanup** to manually trigger cleanup
3. Review the results in the activity logs

#### Scheduled Cleanup
- Configure automatic cleanup in **Settings**
- Choose frequency: hourly, daily, or weekly
- Monitor results in the **Logs** section

### Activity Monitoring

#### Viewing Logs
1. Go to **SKU Manager > Logs**
2. Use filters to view specific action types
3. Navigate through pages for historical data
4. Clear old logs when needed

#### Log Types
- **Product Deleted**: Products removed by the plugin
- **Product Trashed**: Products moved to trash
- **Blocked SKU Added**: New SKUs added to blocklist
- **Cleanup Completed**: Automated cleanup operations
- **Settings Changed**: Configuration modifications

## 🔌 Hooks & Filters

### Action Hooks
```php
// Triggered when a product is filtered
do_action('ysm_product_filtered', $product_id, $reason, $sku);

// Triggered after cleanup completion
do_action('ysm_cleanup_completed', $deleted_count);

// Triggered when a blocked SKU is added
do_action('ysm_blocked_sku_added', $sku, $pattern, $is_regex);
```

### Filter Hooks
```php
// Modify cleanup query limits
apply_filters('ysm_cleanup_limit', 100);

// Customize log retention period
apply_filters('ysm_log_retention_days', 30);

// Modify blocked SKU check
apply_filters('ysm_is_sku_blocked', $is_blocked, $sku);
```

## 🛠️ Developer Guide

### Database Tables

The plugin creates three custom tables:

#### `wp_ysm_logs`
Stores all plugin activity logs with detailed information.

#### `wp_ysm_blocked_skus`
Maintains the list of blocked SKUs and patterns.

#### `wp_ysm_settings`
Stores plugin configuration settings.

### Custom Functions

#### Check if SKU is blocked
```php
$ysm = new YasirSKUManager();
$is_blocked = $ysm->is_sku_blocked('TEMP-001');
```

#### Add custom blocked SKU
```php
$ysm = new YasirSKUManager();
$ysm->add_blocked_sku('CUSTOM-SKU', 'Custom description', false);
```

#### Log custom action
```php
$ysm = new YasirSKUManager();
$ysm->log_action('custom_action', 'Custom message', 'admin', $product_id);
```

## 🔒 Security Features

- **Nonce Verification**: All AJAX requests are protected with WordPress nonces
- **Capability Checks**: Admin functions require proper user capabilities
- **Data Sanitization**: All user inputs are properly sanitized
- **SQL Injection Prevention**: Prepared statements for all database queries
- **XSS Protection**: Output escaping for all displayed data

## 🚀 Performance Optimization

- **Efficient Queries**: Optimized database queries with proper indexing
- **Batch Processing**: Bulk operations process items in batches
- **Caching**: Strategic caching of frequently accessed data
- **Background Processing**: Heavy operations run in background
- **Resource Management**: Automatic cleanup of temporary data

## 🐛 Troubleshooting

### Common Issues

#### Plugin not working after activation
- Ensure WooCommerce is installed and activated
- Check PHP error logs for any fatal errors
- Verify database permissions

#### Cleanup not running automatically
- Check if WordPress cron is working properly
- Verify cleanup schedule settings
- Review activity logs for error messages

#### Products not being deleted
- Check if "Auto Delete" settings are enabled
- Verify blocked SKU patterns are correct
- Review product status and permissions

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📝 Changelog

### Version 1.0.0
- Initial release
- Core SKU management functionality
- Automated cleanup system
- Activity logging
- Admin dashboard interface
- Blocked SKU management
- Regex pattern support

## 🤝 Contributing

We welcome contributions! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch
3. Follow WordPress coding standards
4. Add appropriate tests
5. Submit a pull request

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## 👨‍💻 Author

**Yasir Shabbir**
- Website: [https://yasirshabbir.com](https://yasirshabbir.com)
- Plugin URI: [https://yasirshabbir.com](https://yasirshabbir.com)

## 🆘 Support

For support and questions:
- Create an issue on GitHub
- Visit the plugin support forum
- Contact the developer directly

## 🔮 Roadmap

### Upcoming Features
- **Export/Import**: Backup and restore blocked SKU lists
- **Advanced Filters**: More sophisticated filtering options
- **Email Notifications**: Alerts for cleanup activities
- **API Integration**: REST API endpoints for external integrations
- **Multi-site Support**: Enhanced multisite compatibility
- **Performance Analytics**: Detailed performance metrics

---

**Made with ❤️ for the WordPress community**