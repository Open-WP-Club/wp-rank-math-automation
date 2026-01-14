<?php

declare(strict_types=1);

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Ensure the function to check if plugin is active is loaded
if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// =============================================================================
// CONFIGURABLE OPTION FILTERS
// =============================================================================

/**
 * Filter: Read noindex_out_of_stock option value.
 */
add_filter('wrms_noindex_out_of_stock', function (): bool {
    return get_option('wrms_noindex_out_of_stock', '0') === '1';
});

/**
 * Filter: Read noindex_date_archives option value.
 */
add_filter('wrms_noindex_date_archives', function (): bool {
    return get_option('wrms_noindex_date_archives', '1') === '1';
});

// =============================================================================
// CORE RANKMATH FILTERS
// =============================================================================

add_filter('rank_math/frontend/title', 'wrms_modify_rankmath_title', 10, 2);
add_filter('rank_math/frontend/description', 'wrms_modify_rankmath_description', 10, 2);
add_filter('rank_math/frontend/canonical', 'wrms_modify_rankmath_canonical', 10, 2);
add_filter('rank_math/focus_keyword', 'wrms_modify_rankmath_focus_keyword', 10, 2);
add_filter('rank_math/schema/product', 'wrms_add_product_schema', 10, 2);

/**
 * Modify RankMath title for products.
 */
function wrms_modify_rankmath_title(string $title, mixed $object = null): string
{
    if (is_product() && $object instanceof WP_Post) {
        $product = wc_get_product($object->ID);
        if ($product) {
            return $product->get_name();
        }
    }
    return $title;
}

/**
 * Modify RankMath description for products.
 */
function wrms_modify_rankmath_description(string $description, mixed $object = null): string
{
    if (is_product() && $object instanceof WP_Post) {
        $product = wc_get_product($object->ID);
        if ($product) {
            $short_description = $product->get_short_description();
            return wp_trim_words(
                $short_description ?: $product->get_description(),
                30,
                '...'
            );
        }
    }
    return $description;
}

/**
 * Modify RankMath canonical URL for products.
 */
function wrms_modify_rankmath_canonical(string $canonical, mixed $object = null): string
{
    if (is_product() && $object instanceof WP_Post) {
        $product = wc_get_product($object->ID);
        if ($product) {
            return get_permalink($product->get_id());
        }
    }
    return $canonical;
}

/**
 * Modify RankMath focus keyword for products.
 */
function wrms_modify_rankmath_focus_keyword(string $focus_keyword, int $post_id): string
{
    if (get_post_type($post_id) === 'product') {
        $product = wc_get_product($post_id);
        if ($product) {
            return $product->get_name();
        }
    }
    return $focus_keyword;
}

/**
 * Add product data to RankMath's schema.
 */
function wrms_add_product_schema(array $schema, mixed $object): array
{
    if (!is_product() || !$object instanceof WP_Post) {
        return $schema;
    }

    $product = wc_get_product($object->ID);
    if (!$product) {
        return $schema;
    }

    $schema['product'] = [
        '@type' => 'Product',
        'name' => $product->get_name(),
        'description' => $product->get_short_description(),
        'sku' => $product->get_sku(),
        'price' => $product->get_price(),
        'priceCurrency' => get_woocommerce_currency(),
        'availability' => $product->is_in_stock() ? 'InStock' : 'OutOfStock',
    ];

    // Add product image
    $image_id = $product->get_image_id();
    if ($image_id) {
        $schema['product']['image'] = wp_get_attachment_url($image_id);
    }

    // Add product brand
    $brands = wp_get_post_terms($product->get_id(), 'product_brand');
    if (!empty($brands) && !is_wp_error($brands)) {
        $schema['product']['brand'] = [
            '@type' => 'Brand',
            'name' => $brands[0]->name,
        ];
    }

    return $schema;
}

// =============================================================================
// OPEN GRAPH & TWITTER CARDS AUTOMATION
// =============================================================================

add_filter('rank_math/opengraph/facebook/og_image', 'wrms_auto_og_image', 10, 2);
add_filter('rank_math/opengraph/facebook/og_image_secure_url', 'wrms_auto_og_image', 10, 2);
add_filter('rank_math/opengraph/twitter/twitter_image', 'wrms_auto_og_image', 10, 2);
add_filter('rank_math/opengraph/facebook/og_description', 'wrms_auto_og_description', 10);
add_filter('rank_math/opengraph/twitter/twitter_description', 'wrms_auto_og_description', 10);
add_filter('rank_math/opengraph/facebook/og_title', 'wrms_auto_og_title', 10);
add_filter('rank_math/opengraph/twitter/twitter_title', 'wrms_auto_og_title', 10);

/**
 * Auto-generate Open Graph image from product/post featured image.
 */
function wrms_auto_og_image(string $image, mixed $object = null): string
{
    // If image already exists, don't override
    if (!empty($image)) {
        return $image;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return $image;
    }

    // For products, use product image
    if (is_product()) {
        $product = wc_get_product($post_id);
        if ($product) {
            $image_id = $product->get_image_id();
            if ($image_id) {
                return wp_get_attachment_image_url($image_id, 'large') ?: $image;
            }
            // Try gallery images
            $gallery_ids = $product->get_gallery_image_ids();
            if (!empty($gallery_ids)) {
                return wp_get_attachment_image_url($gallery_ids[0], 'large') ?: $image;
            }
        }
    }

    // For product categories, use category thumbnail
    if (is_product_category()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            if ($thumbnail_id) {
                return wp_get_attachment_image_url($thumbnail_id, 'large') ?: $image;
            }
        }
    }

    // Fallback to featured image
    if (has_post_thumbnail($post_id)) {
        return get_the_post_thumbnail_url($post_id, 'large') ?: $image;
    }

    return $image;
}

/**
 * Auto-generate Open Graph description.
 */
function wrms_auto_og_description(string $description): string
{
    if (!empty($description)) {
        return $description;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return $description;
    }

    // For products
    if (is_product()) {
        $product = wc_get_product($post_id);
        if ($product) {
            $short_desc = $product->get_short_description();
            $text = $short_desc ?: $product->get_description();
            return wp_trim_words(wp_strip_all_tags($text), 25, '...');
        }
    }

    // For product categories
    if (is_product_category()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term && !empty($term->description)) {
            return wp_trim_words(wp_strip_all_tags($term->description), 25, '...');
        }
    }

    // Fallback to excerpt or content
    if (has_excerpt($post_id)) {
        return wp_trim_words(get_the_excerpt($post_id), 25, '...');
    }

    $content = get_post_field('post_content', $post_id);
    return wp_trim_words(wp_strip_all_tags($content), 25, '...');
}

/**
 * Auto-generate Open Graph title.
 */
function wrms_auto_og_title(string $title): string
{
    if (!empty($title)) {
        return $title;
    }

    $post_id = get_queried_object_id();

    // For products
    if (is_product()) {
        $product = wc_get_product($post_id);
        if ($product) {
            $price = $product->get_price();
            $currency = get_woocommerce_currency_symbol();
            return $product->get_name() . ($price ? " - {$currency}{$price}" : '');
        }
    }

    // For product categories
    if (is_product_category()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            return $term->name . ' - ' . get_bloginfo('name');
        }
    }

    return get_the_title($post_id) ?: $title;
}

// =============================================================================
// SECONDARY KEYWORDS AUTOMATION
// =============================================================================

add_filter('rank_math/focus_keyword', 'wrms_add_secondary_keywords', 20, 2);

/**
 * Auto-generate secondary focus keywords from product attributes, categories, tags.
 */
function wrms_add_secondary_keywords(string $focus_keyword, int $post_id): string
{
    if (get_post_type($post_id) !== 'product') {
        return $focus_keyword;
    }

    $product = wc_get_product($post_id);
    if (!$product) {
        return $focus_keyword;
    }

    $keywords = [$focus_keyword];

    // Add keywords from product categories
    $categories = wp_get_post_terms($post_id, 'product_cat', ['fields' => 'names']);
    if (!is_wp_error($categories) && !empty($categories)) {
        $keywords = [...$keywords, ...array_slice($categories, 0, 2)];
    }

    // Add keywords from product tags
    $tags = wp_get_post_terms($post_id, 'product_tag', ['fields' => 'names']);
    if (!is_wp_error($tags) && !empty($tags)) {
        $keywords = [...$keywords, ...array_slice($tags, 0, 2)];
    }

    // Add keywords from product attributes
    $attributes = $product->get_attributes();
    foreach ($attributes as $attribute) {
        if ($attribute instanceof WC_Product_Attribute) {
            $values = $attribute->get_options();
            if (!empty($values) && count($keywords) < 6) {
                // Get term names for taxonomy attributes
                if ($attribute->is_taxonomy()) {
                    $terms = wc_get_product_terms($post_id, $attribute->get_name(), ['fields' => 'names']);
                    if (!empty($terms)) {
                        $keywords[] = $terms[0];
                    }
                } else {
                    $keywords[] = $values[0];
                }
            }
        }
    }

    // Add brand if available
    $brands = wp_get_post_terms($post_id, 'product_brand', ['fields' => 'names']);
    if (!is_wp_error($brands) && !empty($brands)) {
        $keywords[] = $brands[0];
    }

    // Remove duplicates and limit to 5 keywords
    $keywords = array_unique(array_filter($keywords));
    $keywords = array_slice($keywords, 0, 5);

    return implode(', ', $keywords);
}

/**
 * Store secondary keywords in meta for RankMath.
 */
add_action('save_post_product', 'wrms_save_secondary_keywords', 20, 1);
function wrms_save_secondary_keywords(int $post_id): void
{
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    $keywords = wrms_add_secondary_keywords('', $post_id);
    if (!empty($keywords)) {
        update_post_meta($post_id, 'rank_math_focus_keyword', $keywords);
    }
}

// =============================================================================
// SMART ROBOTS META AUTOMATION
// =============================================================================

add_filter('rank_math/frontend/robots', 'wrms_smart_robots_meta', 10, 1);

/**
 * Automatically set robots meta based on content rules.
 */
function wrms_smart_robots_meta(array $robots): array
{
    // Noindex empty product categories
    if (is_product_category()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $product_count = $term->count;
            if ($product_count === 0) {
                $robots['index'] = 'noindex';
                $robots['follow'] = 'nofollow';
            }
        }
    }

    // Noindex out-of-stock products (optional - configurable)
    if (is_product() && apply_filters('wrms_noindex_out_of_stock', false)) {
        $product = wc_get_product(get_queried_object_id());
        if ($product && !$product->is_in_stock()) {
            $robots['index'] = 'noindex';
        }
    }

    // Noindex paginated pages beyond page 5
    if (is_paged()) {
        $paged = get_query_var('paged', 1);
        if ($paged > 5) {
            $robots['index'] = 'noindex';
            $robots['follow'] = 'follow'; // Still follow links
        }
    }

    // Noindex search results
    if (is_search()) {
        $robots['index'] = 'noindex';
        $robots['follow'] = 'nofollow';
    }

    // Noindex attachment pages
    if (is_attachment()) {
        $robots['index'] = 'noindex';
        $robots['follow'] = 'follow';
    }

    // Noindex author archives with no posts
    if (is_author()) {
        global $wp_query;
        if ($wp_query->post_count === 0) {
            $robots['index'] = 'noindex';
            $robots['follow'] = 'nofollow';
        }
    }

    // Noindex date archives (optional - often considered low value)
    if (is_date() && apply_filters('wrms_noindex_date_archives', true)) {
        $robots['index'] = 'noindex';
        $robots['follow'] = 'follow';
    }

    return $robots;
}

// =============================================================================
// IMAGE ALT TEXT AUTOMATION
// =============================================================================

add_filter('wp_get_attachment_image_attributes', 'wrms_auto_image_alt', 10, 3);

/**
 * Automatically generate ALT text for images without one.
 */
function wrms_auto_image_alt(array $attr, WP_Post $attachment, mixed $size): array
{
    // If ALT already exists and is not empty, don't override
    if (!empty($attr['alt'])) {
        return $attr;
    }

    $alt_text = '';

    // Try to get existing alt from meta
    $existing_alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
    if (!empty($existing_alt)) {
        $attr['alt'] = $existing_alt;
        return $attr;
    }

    // Check if this image is attached to a product
    $parent_id = $attachment->post_parent;
    if ($parent_id && get_post_type($parent_id) === 'product') {
        $product = wc_get_product($parent_id);
        if ($product) {
            // Check if it's the main product image
            if ($product->get_image_id() == $attachment->ID) {
                $alt_text = $product->get_name();
            } else {
                // It's a gallery image - add context
                $alt_text = $product->get_name() . ' - ' . __('Product Image', 'flavor');
            }

            // Add category for more context
            $categories = wp_get_post_terms($parent_id, 'product_cat', ['fields' => 'names']);
            if (!is_wp_error($categories) && !empty($categories)) {
                $alt_text .= ' | ' . $categories[0];
            }
        }
    }

    // Fallback to attachment title
    if (empty($alt_text)) {
        $alt_text = $attachment->post_title;

        // Clean up filename-based titles
        $alt_text = str_replace(['-', '_'], ' ', $alt_text);
        $alt_text = preg_replace('/\d{2,}/', '', $alt_text); // Remove numbers
        $alt_text = ucwords(trim($alt_text));
    }

    $attr['alt'] = $alt_text;

    return $attr;
}

/**
 * Bulk update missing ALT texts for product images.
 */
function wrms_bulk_update_image_alts(): int
{
    global $wpdb;

    // Find images without ALT text that are attached to products
    $images = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_parent
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
        WHERE p.post_type = 'attachment'
        AND p.post_mime_type LIKE 'image/%'
        AND p.post_parent > 0
        AND (pm.meta_value IS NULL OR pm.meta_value = '')
        LIMIT 100
    ");

    $updated = 0;

    foreach ($images as $image) {
        $parent_type = get_post_type($image->post_parent);

        if ($parent_type === 'product') {
            $product = wc_get_product($image->post_parent);
            if ($product) {
                $alt_text = $product->get_name();

                // Add category context
                $categories = wp_get_post_terms($image->post_parent, 'product_cat', ['fields' => 'names']);
                if (!is_wp_error($categories) && !empty($categories)) {
                    $alt_text .= ' | ' . $categories[0];
                }

                update_post_meta($image->ID, '_wp_attachment_image_alt', $alt_text);
                $updated++;
            }
        } else {
            // For non-products, use parent post title
            $parent_title = get_the_title($image->post_parent);
            if (!empty($parent_title)) {
                update_post_meta($image->ID, '_wp_attachment_image_alt', $parent_title);
                $updated++;
            }
        }
    }

    return $updated;
}

// Add AJAX handler for bulk ALT update
add_action('wp_ajax_wrms_bulk_update_alts', 'wrms_bulk_update_alts_handler');
function wrms_bulk_update_alts_handler(): void
{
    check_ajax_referer('wrms_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $updated = wrms_bulk_update_image_alts();
    wp_send_json_success([
        'updated' => $updated,
        'message' => sprintf(__('Updated ALT text for %d images.', 'flavor'), $updated),
    ]);
}
