<?php

declare(strict_types=1);

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security check helper - verifies nonce and capabilities.
 */
function wrms_verify_ajax_request(): void
{
    check_ajax_referer('wrms_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You do not have permission to perform this action.']);
    }
}

/**
 * Get batch size from POST request (clamped between 1-50).
 */
function wrms_get_batch_size(): int
{
    return min(50, max(1, (int) ($_POST['batch_size'] ?? 10)));
}

/**
 * Get next unsynced posts by type (supports batch processing).
 */
function wrms_get_next_unsynced_posts(string $post_type, string $post_status = 'publish', int $batch_size = 10): array
{
    return get_posts([
        'post_type' => $post_type,
        'post_status' => $post_status,
        'posts_per_page' => $batch_size,
        'meta_query' => [
            'relation' => 'OR',
            ['key' => '_wrms_synced', 'compare' => 'NOT EXISTS'],
            ['key' => '_wrms_synced', 'value' => '0', 'compare' => '='],
        ],
    ]);
}

/**
 * Remove RankMath meta from posts.
 */
function wrms_remove_post_type_meta(string $post_type, string $post_status = 'publish'): array
{
    $posts = get_posts([
        'post_type' => $post_type,
        'post_status' => $post_status,
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => '_wrms_synced', 'compare' => 'EXISTS'],
        ],
    ]);

    $removed = 0;
    foreach ($posts as $post_id) {
        delete_post_meta($post_id, 'rank_math_title');
        delete_post_meta($post_id, 'rank_math_description');
        delete_post_meta($post_id, 'rank_math_focus_keyword');
        delete_post_meta($post_id, '_wrms_synced');
        $removed++;
    }

    return ['total' => count($posts), 'removed' => $removed];
}

/**
 * Send batch sync response.
 */
function wrms_send_batch_response(array $processed_items, string $item_key): void
{
    if (!empty($processed_items)) {
        wp_send_json_success([
            'processed' => count($processed_items),
            $item_key => end($processed_items),
            'items' => $processed_items,
        ]);
    } else {
        wp_send_json_success(['processed' => 0]);
    }
}

// =====================================================
// AUTO SYNC & STATS HANDLERS
// =====================================================

add_action('wp_ajax_wrms_update_auto_sync', 'wrms_update_auto_sync_handler');
function wrms_update_auto_sync_handler(): void
{
    wrms_verify_ajax_request();
    $auto_sync = sanitize_text_field($_POST['auto_sync'] ?? '0');
    update_option('wrms_auto_sync', $auto_sync);
    wp_send_json_success();
}

add_action('wp_ajax_wrms_update_stats', 'wrms_update_stats_handler');
function wrms_update_stats_handler(): void
{
    wrms_verify_ajax_request();
    $stats = wrms_calculate_and_cache_stats();
    $stats['timestamp'] = time();
    wp_send_json_success($stats);
}

// =====================================================
// COUNT HANDLERS
// =====================================================

add_action('wp_ajax_wrms_get_product_count', 'wrms_get_product_count_handler');
function wrms_get_product_count_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(['count' => (int) wp_count_posts('product')->publish]);
}

add_action('wp_ajax_wrms_get_category_count', 'wrms_get_category_count_handler');
function wrms_get_category_count_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(['count' => (int) wp_count_terms('product_cat')]);
}

add_action('wp_ajax_wrms_get_page_count', 'wrms_get_page_count_handler');
function wrms_get_page_count_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(['count' => (int) wp_count_posts('page')->publish]);
}

add_action('wp_ajax_wrms_get_media_count', 'wrms_get_media_count_handler');
function wrms_get_media_count_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(['count' => (int) wp_count_posts('attachment')->inherit]);
}

add_action('wp_ajax_wrms_get_post_count', 'wrms_get_post_count_handler');
function wrms_get_post_count_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(['count' => (int) wp_count_posts('post')->publish]);
}

// =====================================================
// SYNC HANDLERS
// =====================================================

add_action('wp_ajax_wrms_sync_next_product', 'wrms_sync_next_product_handler');
function wrms_sync_next_product_handler(): void
{
    wrms_verify_ajax_request();

    $batch_size = wrms_get_batch_size();
    $posts = wrms_get_next_unsynced_posts('product', 'publish', $batch_size);
    $processed_items = [];

    foreach ($posts as $post) {
        $product = wc_get_product($post->ID);
        if ($product) {
            wrms_maybe_sync_product($post->ID, null, true);
            update_post_meta($post->ID, '_wrms_synced', '1');
            $processed_items[] = ['id' => $post->ID, 'title' => $product->get_name()];
        }
    }

    wrms_send_batch_response($processed_items, 'product');
}

add_action('wp_ajax_wrms_sync_next_category', 'wrms_sync_next_category_handler');
function wrms_sync_next_category_handler(): void
{
    wrms_verify_ajax_request();

    $batch_size = wrms_get_batch_size();
    $processed_ids = get_option('wrms_processed_categories', []);

    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'number' => $batch_size,
        'exclude' => $processed_ids,
        'orderby' => 'id',
        'order' => 'ASC',
    ]);

    $processed_items = [];

    if (!empty($categories) && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            wrms_maybe_sync_category($category->term_id, $category->term_taxonomy_id);
            $processed_ids[] = $category->term_id;
            $processed_items[] = ['id' => $category->term_id, 'name' => $category->name];
        }
        update_option('wrms_processed_categories', $processed_ids);

        wrms_send_batch_response($processed_items, 'category');
    } else {
        delete_option('wrms_processed_categories');
        wp_send_json_success(['processed' => 0]);
    }
}

add_action('wp_ajax_wrms_sync_next_page', 'wrms_sync_next_page_handler');
function wrms_sync_next_page_handler(): void
{
    wrms_verify_ajax_request();

    $batch_size = wrms_get_batch_size();
    $pages = wrms_get_next_unsynced_posts('page', 'publish', $batch_size);
    $processed_items = [];

    foreach ($pages as $page) {
        wrms_maybe_sync_page($page->ID, $page, true);
        update_post_meta($page->ID, '_wrms_synced', '1');
        $processed_items[] = ['id' => $page->ID, 'title' => $page->post_title];
    }

    wrms_send_batch_response($processed_items, 'page');
}

add_action('wp_ajax_wrms_sync_next_media', 'wrms_sync_next_media_handler');
function wrms_sync_next_media_handler(): void
{
    wrms_verify_ajax_request();

    $batch_size = wrms_get_batch_size();
    $media_items = wrms_get_next_unsynced_posts('attachment', 'inherit', $batch_size);
    $processed_items = [];

    foreach ($media_items as $media) {
        wrms_maybe_sync_media($media->ID);
        update_post_meta($media->ID, '_wrms_synced', '1');
        $processed_items[] = ['id' => $media->ID, 'title' => $media->post_title];
    }

    wrms_send_batch_response($processed_items, 'media');
}

add_action('wp_ajax_wrms_sync_next_post', 'wrms_sync_next_post_handler');
function wrms_sync_next_post_handler(): void
{
    wrms_verify_ajax_request();

    $batch_size = wrms_get_batch_size();
    $posts = wrms_get_next_unsynced_posts('post', 'publish', $batch_size);
    $processed_items = [];

    foreach ($posts as $post) {
        wrms_maybe_sync_post($post->ID, $post, true);
        update_post_meta($post->ID, '_wrms_synced', '1');
        $processed_items[] = ['id' => $post->ID, 'title' => $post->post_title];
    }

    wrms_send_batch_response($processed_items, 'post');
}

// =====================================================
// REMOVE META HANDLERS
// =====================================================

add_action('wp_ajax_wrms_remove_product_meta', 'wrms_remove_product_meta_handler');
function wrms_remove_product_meta_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(wrms_remove_post_type_meta('product'));
}

add_action('wp_ajax_wrms_remove_category_meta', 'wrms_remove_category_meta_handler');
function wrms_remove_category_meta_handler(): void
{
    wrms_verify_ajax_request();

    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'fields' => 'ids',
        'meta_query' => [
            ['key' => '_wrms_synced', 'compare' => 'EXISTS'],
        ],
    ]);

    $removed = 0;
    if (!is_wp_error($categories)) {
        foreach ($categories as $term_id) {
            delete_term_meta($term_id, 'rank_math_title');
            delete_term_meta($term_id, 'rank_math_description');
            delete_term_meta($term_id, 'rank_math_focus_keyword');
            delete_term_meta($term_id, '_wrms_synced');
            $removed++;
        }
    }

    wp_send_json_success(['total' => is_array($categories) ? count($categories) : 0, 'removed' => $removed]);
}

add_action('wp_ajax_wrms_remove_page_meta', 'wrms_remove_page_meta_handler');
function wrms_remove_page_meta_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(wrms_remove_post_type_meta('page'));
}

add_action('wp_ajax_wrms_remove_media_meta', 'wrms_remove_media_meta_handler');
function wrms_remove_media_meta_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(wrms_remove_post_type_meta('attachment', 'inherit'));
}

add_action('wp_ajax_wrms_remove_post_meta', 'wrms_remove_post_meta_handler');
function wrms_remove_post_meta_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(wrms_remove_post_type_meta('post'));
}

// =====================================================
// URL & UTILITY HANDLERS
// =====================================================

add_action('wp_ajax_wrms_get_urls', 'wrms_get_urls_handler');
function wrms_get_urls_handler(): void
{
    wrms_verify_ajax_request();

    $offset = (int) ($_POST['offset'] ?? 0);
    $chunk_size = (int) ($_POST['chunk_size'] ?? 100);
    $url_types = (array) ($_POST['url_types'] ?? []);

    $urls = [];
    $total = 0;

    $type_handlers = [
        'product' => 'wrms_get_product_urls',
        'page' => 'wrms_get_page_urls',
        'category' => 'wrms_get_category_urls',
        'tag' => 'wrms_get_tag_urls',
        'post' => 'wrms_get_post_urls',
    ];

    foreach ($url_types as $type) {
        if (isset($type_handlers[$type]) && function_exists($type_handlers[$type])) {
            $result = ($type_handlers[$type])($offset, $chunk_size);
            $urls = [...$urls, ...$result['urls']];
            $total += $result['total'];
        }
    }

    wp_send_json_success(['urls' => $urls, 'total' => $total]);
}

add_action('wp_ajax_wrms_get_last_sync_time', 'wrms_get_last_sync_time_handler');
function wrms_get_last_sync_time_handler(): void
{
    wrms_verify_ajax_request();
    wp_send_json_success(['last_sync_time' => wrms_get_last_sync_time()]);
}

add_action('wp_ajax_wrms_update_last_sync_time', 'wrms_update_last_sync_time_handler');
function wrms_update_last_sync_time_handler(): void
{
    wrms_verify_ajax_request();
    wrms_update_last_sync_time();
    wp_send_json_success(['message' => 'Last sync time updated.']);
}

// =====================================================
// SEO AUTOMATION HANDLERS
// =====================================================

add_action('wp_ajax_wrms_save_automation_settings', 'wrms_save_automation_settings_handler');
function wrms_save_automation_settings_handler(): void
{
    wrms_verify_ajax_request();

    $noindex_out_of_stock = sanitize_text_field($_POST['noindex_out_of_stock'] ?? '0');
    $noindex_date_archives = sanitize_text_field($_POST['noindex_date_archives'] ?? '1');

    update_option('wrms_noindex_out_of_stock', $noindex_out_of_stock);
    update_option('wrms_noindex_date_archives', $noindex_date_archives);

    wp_send_json_success(['message' => 'Automation settings saved.']);
}

add_action('wp_ajax_wrms_refresh_sitemap', 'wrms_refresh_sitemap_handler');
function wrms_refresh_sitemap_handler(): void
{
    wrms_verify_ajax_request();

    // Ping search engines to refresh sitemap
    $sitemap_url = get_home_url() . '/sitemap_index.xml';

    // Ping Google
    wp_remote_get('https://www.google.com/ping?sitemap=' . urlencode($sitemap_url), [
        'timeout' => 5,
        'blocking' => false,
    ]);

    // Ping Bing
    wp_remote_get('https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url), [
        'timeout' => 5,
        'blocking' => false,
    ]);

    // Clear RankMath sitemap cache if available
    if (class_exists('RankMath\\Sitemap\\Cache')) {
        \RankMath\Sitemap\Cache::invalidate_storage();
    }

    wp_send_json_success(['message' => 'Sitemap refresh triggered. Search engines have been notified.']);
}
