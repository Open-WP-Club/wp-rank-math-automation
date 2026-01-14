<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Count synced posts by post type using optimized database query.
 * Much faster than get_posts() with meta_query for large datasets.
 */
function wrms_count_synced_posts($post_type, $post_status = 'publish')
{
  global $wpdb;

  return (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT p.ID)
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
     WHERE p.post_type = %s
     AND p.post_status = %s
     AND pm.meta_key = '_wrms_synced'
     AND pm.meta_value = '1'",
    $post_type,
    $post_status
  ));
}

/**
 * Count synced terms by taxonomy using optimized database query.
 */
function wrms_count_synced_terms($taxonomy)
{
  global $wpdb;

  return (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT t.term_id)
     FROM {$wpdb->terms} t
     INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
     INNER JOIN {$wpdb->termmeta} tm ON t.term_id = tm.term_id
     WHERE tt.taxonomy = %s
     AND tm.meta_key = '_wrms_synced'
     AND tm.meta_value = '1'",
    $taxonomy
  ));
}

/**
 * Calculate and cache statistics using optimized queries.
 */
function wrms_calculate_and_cache_stats()
{
  // Get totals (these are already optimized by WordPress)
  $total_products = (int) wp_count_posts('product')->publish;
  $total_pages = (int) wp_count_posts('page')->publish;
  $total_media = (int) wp_count_posts('attachment')->inherit;
  $total_categories = (int) wp_count_terms('product_cat');
  $total_posts = (int) wp_count_posts('post')->publish;

  // Get synced counts using optimized queries
  $synced_products = wrms_count_synced_posts('product', 'publish');
  $synced_pages = wrms_count_synced_posts('page', 'publish');
  $synced_media = wrms_count_synced_posts('attachment', 'inherit');
  $synced_categories = wrms_count_synced_terms('product_cat');
  $synced_posts = wrms_count_synced_posts('post', 'publish');

  $total_items = $total_products + $total_pages + $total_media + $total_categories + $total_posts;
  $total_synced = $synced_products + $synced_pages + $synced_media + $synced_categories + $synced_posts;
  $sync_percentage = $total_items > 0 ? round(($total_synced / $total_items) * 100, 2) : 0;

  $stats = array(
    'total_products' => $total_products,
    'total_pages' => $total_pages,
    'total_media' => $total_media,
    'total_categories' => $total_categories,
    'total_posts' => $total_posts,
    'synced_products' => $synced_products,
    'synced_pages' => $synced_pages,
    'synced_media' => $synced_media,
    'synced_categories' => $synced_categories,
    'synced_posts' => $synced_posts,
    'total_items' => $total_items,
    'total_synced' => $total_synced,
    'sync_percentage' => $sync_percentage,
    'last_updated' => current_time('mysql')
  );

  update_option('wrms_stats_cache', $stats);
  return $stats;
}

/**
 * Get cached stats or calculate if not available.
 */
function wrms_get_stats()
{
  $stats = get_option('wrms_stats_cache');
  if (!$stats) {
    $stats = wrms_calculate_and_cache_stats();
  }
  return $stats;
}
