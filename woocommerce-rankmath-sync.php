<?php
/*
 * Plugin Name:             WordPress RankMath Sync
 * Plugin URI:              https://github.com/MrGKanev/wo-rank-math-automation/
 * Description:             Copies WooCommerce product, category, WordPress post, page, and media information to RankMath's meta information.
 * Version:                 0.0.5
 * Author:                  Gabriel Kanev
 * Author URI:              https://gkanev.com
 * License:                 GPL-2.0 License
 * Requires Plugins:        seo-by-rank-math
 * Requires at least:       6.0
 * Requires PHP:            7.4
 * Tested up to:            6.6.1
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WRMS_VERSION', '0.0.5');
define('WRMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WRMS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include other plugin files
require_once WRMS_PLUGIN_DIR . 'admin/admin-menu.php';
require_once WRMS_PLUGIN_DIR . 'includes/sync-functions.php';
require_once WRMS_PLUGIN_DIR . 'includes/sitemap-settings.php';
require_once WRMS_PLUGIN_DIR . 'admin/settings.php';
require_once WRMS_PLUGIN_DIR . 'includes/statistics.php';
require_once WRMS_PLUGIN_DIR . 'includes/statistics-display.php';
require_once WRMS_PLUGIN_DIR . 'public/rank-math-filters.php';
require_once WRMS_PLUGIN_DIR . 'includes/helpers.php';
require_once WRMS_PLUGIN_DIR . 'includes/url-functions.php';
require_once WRMS_PLUGIN_DIR . 'includes/cron-jobs.php';

// Activation hook
register_activation_hook(__FILE__, 'wrms_activate');
function wrms_activate()
{
    // Set default options
    add_option('wrms_auto_sync', '0');

    // Schedule cron job for daily sync
    if (!wp_next_scheduled('wrms_daily_sync')) {
        wp_schedule_event(time(), 'daily', 'wrms_daily_sync');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wrms_deactivate');
function wrms_deactivate()
{
    // Clear scheduled cron job
    wp_clear_scheduled_hook('wrms_daily_sync');
}

// Add daily sync action
add_action('wrms_daily_sync', 'wrms_perform_daily_sync');

// Ensure the function to check plugin is active is loaded
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Include AJAX handlers
require_once WRMS_PLUGIN_DIR . 'admin/ajax-handlers.php';

function wrms_reset_category_sync()
{
    delete_option('wrms_processed_categories');
}

// Enqueue admin scripts and styles
function wrms_enqueue_admin_scripts($hook)
{
    if ($hook != 'tools_page_woocommerce-rankmath-sync') {
        return;
    }
    wp_enqueue_script('wrms-script', WRMS_PLUGIN_URL . 'admin/js/wrms-script.js', array('jquery'), WRMS_VERSION, true);
    wp_enqueue_style('wrms-style', WRMS_PLUGIN_URL . 'assets/css/wrms-style.css', array(), WRMS_VERSION);
    wp_localize_script('wrms-script', 'wrms_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wrms_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'wrms_enqueue_admin_scripts');
