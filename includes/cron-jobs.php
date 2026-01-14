<?php

declare(strict_types=1);

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron job to perform daily sync of all content types.
 */
function wrms_perform_daily_sync(): void
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

/**
 * Manually trigger sync (runs immediately).
 */
function wrms_manual_sync(): void
{
    wrms_perform_daily_sync();
}

/**
 * Get the timestamp of the last sync.
 */
function wrms_get_last_sync_time(): int
{
    return (int) get_option('wrms_last_sync_time', 0);
}

/**
 * Update the last sync timestamp to current time.
 */
function wrms_update_last_sync_time(): void
{
    update_option('wrms_last_sync_time', time());
}
