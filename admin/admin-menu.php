<?php

declare(strict_types=1);

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Add Admin Menu
add_action('admin_menu', 'wrms_add_admin_menu');
function wrms_add_admin_menu(): void
{
    add_submenu_page(
        'tools.php',
        'WordPress RankMath Sync',
        'RankMath Sync',
        'manage_options',
        'woocommerce-rankmath-sync',
        'wrms_admin_page'
    );
}

// Admin Page Content
function wrms_admin_page(): void
{
    $auto_sync = get_option('wrms_auto_sync', '0');
    $stats = wrms_get_stats();
    ?>
    <div class="wrap wrms-admin-page">
        <h1>WordPress RankMath Sync</h1>

        <div class="wrms-tabs">
            <button class="wrms-tab-link active" data-tab="sync">Sync</button>
            <button class="wrms-tab-link" data-tab="seo-automation">SEO Automation</button>
            <button class="wrms-tab-link" data-tab="url-download">URL Download</button>
            <button class="wrms-tab-link" data-tab="sitemap-settings">Sitemap Settings</button>
            <button class="wrms-tab-link" data-tab="settings">Settings</button>
        </div>

        <div class="wrms-tab-content">
            <!-- Sync Tab -->
            <div id="sync" class="wrms-tab-pane active">
                <h2>Sync or Remove RankMath Meta</h2>
                <div class="wrms-button-grid">
                    <div class="wrms-button-group">
                        <h3>WooCommerce Products</h3>
                        <button id="sync-products" class="button button-primary">Sync Products</button>
                        <button id="remove-product-meta" class="button button-secondary">Remove Product Meta</button>
                    </div>
                    <div class="wrms-button-group">
                        <h3>WooCommerce Categories</h3>
                        <button id="sync-categories" class="button button-primary">Sync Categories</button>
                        <button id="remove-category-meta" class="button button-secondary">Remove Category Meta</button>
                    </div>
                    <div class="wrms-button-group">
                        <h3>Pages</h3>
                        <button id="sync-pages" class="button button-primary">Sync Pages</button>
                        <button id="remove-page-meta" class="button button-secondary">Remove Page Meta</button>
                    </div>
                    <div class="wrms-button-group">
                        <h3>Media</h3>
                        <button id="sync-media" class="button button-primary">Sync Media</button>
                        <button id="remove-media-meta" class="button button-secondary">Remove Media Meta</button>
                    </div>
                    <div class="wrms-button-group">
                        <h3>Posts</h3>
                        <button id="sync-posts" class="button button-primary">Sync Posts</button>
                        <button id="remove-post-meta" class="button button-secondary">Remove Post Meta</button>
                    </div>
                </div>
                <div id="sync-status" class="wrms-status-box">
                    <img id="sync-loader" src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" style="display:none;" alt="Loading" />
                    <p id="sync-count"></p>
                    <div id="sync-log" class="sync-log">
                        <?php esc_html_e('Sync logs will appear here once you start a sync process.', 'flavor'); ?>
                    </div>
                    <div id="progress-bar" class="progress-bar">
                        <div id="progress-bar-fill" class="progress-bar-fill"></div>
                    </div>
                </div>
            </div>

            <!-- SEO Automation Tab -->
            <div id="seo-automation" class="wrms-tab-pane">
                <h2>SEO Automation Features</h2>
                <p class="description">These features automatically optimize your content for search engines.</p>

                <div class="wrms-automation-grid">
                    <!-- Image ALT Text -->
                    <div class="wrms-automation-box">
                        <h3>Image ALT Text Automation</h3>
                        <p>Automatically generates missing ALT text for product images using product names and categories.</p>
                        <button id="bulk-update-alts" class="button button-primary">Update Missing ALT Texts</button>
                        <p id="alt-update-status" class="status-message"></p>
                    </div>

                    <!-- Open Graph -->
                    <div class="wrms-automation-box">
                        <h3>Open Graph & Twitter Cards</h3>
                        <p>Automatically generates social media meta tags from product/post data.</p>
                        <ul class="feature-list">
                            <li>OG Image from product/featured image</li>
                            <li>OG Description from short description</li>
                            <li>OG Title with price for products</li>
                            <li>Twitter Card support</li>
                        </ul>
                        <span class="badge badge-active">Active</span>
                    </div>

                    <!-- Secondary Keywords -->
                    <div class="wrms-automation-box">
                        <h3>Secondary Keywords</h3>
                        <p>Automatically extracts additional keywords from product data.</p>
                        <ul class="feature-list">
                            <li>Product categories</li>
                            <li>Product tags</li>
                            <li>Product attributes</li>
                            <li>Brand name</li>
                        </ul>
                        <span class="badge badge-active">Active</span>
                    </div>

                    <!-- Smart Robots Meta -->
                    <div class="wrms-automation-box">
                        <h3>Smart Robots Meta</h3>
                        <p>Intelligent noindex rules for low-value pages.</p>
                        <ul class="feature-list">
                            <li>Empty categories: <strong>noindex</strong></li>
                            <li>Search results: <strong>noindex</strong></li>
                            <li>Attachment pages: <strong>noindex</strong></li>
                            <li>Date archives: <strong>noindex</strong></li>
                            <li>Pagination > 5: <strong>noindex</strong></li>
                        </ul>
                        <span class="badge badge-active">Active</span>
                    </div>
                </div>

                <!-- Advanced Settings -->
                <div class="wrms-automation-settings">
                    <h3>Advanced Settings</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Out-of-Stock Products</th>
                            <td>
                                <label>
                                    <input type="checkbox" id="noindex-out-of-stock" name="wrms_noindex_out_of_stock" value="1"
                                        <?php checked(get_option('wrms_noindex_out_of_stock', '0'), '1'); ?> />
                                    Set <code>noindex</code> for out-of-stock products
                                </label>
                                <p class="description">Enable this if you want search engines to stop indexing products that are out of stock.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Date Archives</th>
                            <td>
                                <label>
                                    <input type="checkbox" id="noindex-date-archives" name="wrms_noindex_date_archives" value="1"
                                        <?php checked(get_option('wrms_noindex_date_archives', '1'), '1'); ?> />
                                    Set <code>noindex</code> for date-based archives
                                </label>
                                <p class="description">Date archives are generally considered low-value for SEO.</p>
                            </td>
                        </tr>
                    </table>
                    <button id="save-automation-settings" class="button button-primary">Save Settings</button>
                </div>
            </div>

            <!-- URL Download Tab -->
            <div id="url-download" class="wrms-tab-pane">
                <h2>Download WordPress URLs</h2>
                <div class="wrms-url-options">
                    <h3>Select URL Types</h3>
                    <p>Choose the types of URLs you want to download:</p>
                    <form id="url-download-form">
                        <label><input type="checkbox" name="url_types[]" value="product" checked> Products</label>
                        <label><input type="checkbox" name="url_types[]" value="page"> Pages</label>
                        <label><input type="checkbox" name="url_types[]" value="category"> Categories</label>
                        <label><input type="checkbox" name="url_types[]" value="tag"> Tags</label>
                        <label><input type="checkbox" name="url_types[]" value="post"> Posts</label>
                        <button id="download-urls" class="button button-primary">Download URLs</button>
                    </form>
                </div>
                <div id="download-status" class="wrms-status-box">
                    <img id="download-loader" src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" style="display:none;" alt="Loading" />
                    <p id="download-count"></p>
                    <div id="download-log" class="sync-log">
                        <?php esc_html_e('URL download logs will appear here once you start the download process.', 'flavor'); ?>
                    </div>
                    <div id="download-progress-bar" class="progress-bar">
                        <div id="download-progress-bar-fill" class="progress-bar-fill"></div>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings" class="wrms-tab-pane">
                <h2>Plugin Settings</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('wrms_options_group'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Auto Sync</th>
                            <td>
                                <label for="wrms_auto_sync">
                                    <input type="checkbox" id="wrms_auto_sync" name="wrms_auto_sync" value="1" <?php checked($auto_sync, '1'); ?> />
                                    Automatically sync all content (products, categories, pages, media, posts) to RankMath
                                </label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>

            <!-- Sitemap Settings Tab -->
            <div id="sitemap-settings" class="wrms-tab-pane">
                <h2>Sitemap Settings</h2>
                <form method="post" action="options.php" id="sitemap-settings-form">
                    <?php settings_fields('wrms_sitemap_options_group'); ?>
                    <h3>Additional Sitemaps</h3>
                    <div id="additional-sitemaps">
                        <?php
                        $additional_sitemaps = get_option('wrms_additional_sitemaps', []);
                        foreach ($additional_sitemaps as $sitemap) {
                            echo '<div class="sitemap-input">';
                            echo '<input type="text" name="wrms_additional_sitemaps[]" value="' . esc_url($sitemap) . '" />';
                            echo '<button type="button" class="remove-sitemap">Remove</button>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button type="button" id="add-sitemap" class="button button-secondary">Add Sitemap</button>

                    <h3>Additional URLs</h3>
                    <div id="additional-urls">
                        <?php
                        $additional_urls = get_option('wrms_additional_urls', []);
                        foreach ($additional_urls as $url) {
                            echo '<div class="url-input">';
                            echo '<input type="text" name="wrms_additional_urls[]" value="' . esc_url($url) . '" />';
                            echo '<button type="button" class="remove-url">Remove</button>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button type="button" id="add-url" class="button button-secondary">Add URL</button>

                    <?php submit_button('Save Sitemap Settings'); ?>
                </form>
                <button id="refresh-sitemap" class="button button-primary">Refresh Sitemap</button>
            </div>
        </div>

        <div class="wrms-sidebar">
            <?php wrms_display_statistics(); ?>
        </div>
    </div>
    <?php
}
