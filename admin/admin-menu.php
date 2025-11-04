<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

// Add Admin Menu
add_action('admin_menu', 'wrms_add_admin_menu');
function wrms_add_admin_menu()
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
function wrms_admin_page()
{
  $auto_sync = get_option('wrms_auto_sync', '0');
  $stats = wrms_get_stats();
?>
  <div class="wrap wrms-admin-page">
    <h1>WordPress RankMath Sync</h1>

    <div class="wrms-tabs">
      <button class="wrms-tab-link active" data-tab="sync">Sync</button>
      <button class="wrms-tab-link" data-tab="url-download">URL Download</button>
      <button class="wrms-tab-link" data-tab="sitemap-settings">Sitemap Settings</button>
      <button class="wrms-tab-link" data-tab="settings">Settings</button>
    </div>

    <div class="wrms-tab-content">
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
          <img id="sync-loader" src="<?php echo admin_url('images/spinner.gif'); ?>" style="display:none;" />
          <p id="sync-count"></p>
          <div id="sync-log" class="sync-log">
            <?php echo esc_html__('Sync logs will appear here once you start a sync process.', 'woocommerce-rankmath-sync'); ?>
          </div>
          <div id="progress-bar" class="progress-bar">
            <div id="progress-bar-fill" class="progress-bar-fill"></div>
          </div>
        </div>
      </div>

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
          <img id="download-loader" src="<?php echo admin_url('images/spinner.gif'); ?>" style="display:none;" />
          <p id="download-count"></p>
          <div id="download-log" class="sync-log">
            <?php echo esc_html__('URL download logs will appear here once you start the download process.', 'woocommerce-rankmath-sync'); ?>
          </div>
          <div id="download-progress-bar" class="progress-bar">
            <div id="download-progress-bar-fill" class="progress-bar-fill"></div>
          </div>
        </div>
      </div>

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

      <div id="sitemap-settings" class="wrms-tab-pane">
        <h2>Sitemap Settings</h2>
        <form method="post" action="options.php" id="sitemap-settings-form">
          <?php settings_fields('wrms_sitemap_options_group'); ?>
          <h3>Additional Sitemaps</h3>
          <div id="additional-sitemaps">
            <?php
            $additional_sitemaps = get_option('wrms_additional_sitemaps', array());
            foreach ($additional_sitemaps as $index => $sitemap) {
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
            $additional_urls = get_option('wrms_additional_urls', array());
            foreach ($additional_urls as $index => $url) {
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
