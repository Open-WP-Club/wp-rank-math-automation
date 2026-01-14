<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

// Cron job to perform daily sync
function wrms_perform_daily_sync()
{
  if (get_option('wrms_auto_sync', '0') === '1') {
    wrms_sync_all_products();
    wrms_sync_all_categories();
    wrms_sync_all_pages();
    wrms_sync_all_media();
    wrms_sync_all_posts();
    wrms_update_last_sync_time();
  }
}

// Function to manually trigger sync (runs immediately)
function wrms_manual_sync()
{
  wrms_perform_daily_sync();
}

// Function to check last sync time
function wrms_get_last_sync_time()
{
  return get_option('wrms_last_sync_time', 0);
}

// Function to update last sync time
function wrms_update_last_sync_time()
{
  update_option('wrms_last_sync_time', current_time('timestamp'));
}
