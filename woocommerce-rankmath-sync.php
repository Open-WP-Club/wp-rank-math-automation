<?php
/*
 * Plugin Name:             WooCommerce RankMath Sync
 * Plugin URI:              https://github.com/MrGKanev/StageGuard/
 * Description:             Copies WooCommerce product information to RankMath's meta information.
 * Version:                 0.0.1
 * Author:                  Gabriel Kanev
 * Author URI:              https://gkanev.com
 * License:                 MIT
 * Requires at least:       6.0
 * Requires PHP:            7.4
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include AJAX handlers
require_once plugin_dir_path(__FILE__) . 'includes/wrms-ajax-handlers.php';

// Add Admin Menu
add_action('admin_menu', 'wrms_add_admin_menu');
function wrms_add_admin_menu()
{
    add_submenu_page(
        'tools.php', // Parent slug
        'WooCommerce RankMath Sync', // Page title
        'RankMath Sync', // Menu title
        'manage_options', // Capability
        'woocommerce-rankmath-sync', // Menu slug
        'wrms_admin_page' // Callback function
    );
}

// Admin Page Content
function wrms_admin_page()
{
?>
    <div class="wrap">
        <h1>WooCommerce RankMath Sync</h1>
        <button id="sync-products" class="button button-primary" style="margin-right: 10px; margin-bottom: 20px;">Sync Products</button>
        <button id="remove-rankmath-meta" class="button button-secondary" style="margin-bottom: 20px;">Remove RankMath Meta</button>
        <div id="sync-status" style="margin-top: 20px;">
            <img id="sync-loader" src="<?php echo admin_url('images/spinner.gif'); ?>" style="display:none; margin-right: 10px;" />
            <p id="sync-count"></p>
            <div id="sync-log" style="margin-top: 20px; height: 200px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px;"></div>
        </div>
    </div>
    <script src="<?php echo plugin_dir_url(__FILE__); ?>js/wrms-script.js"></script>
<?php
}
?>