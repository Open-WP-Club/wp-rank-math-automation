<?php

declare(strict_types=1);

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Register sitemap settings
add_action('admin_init', 'wrms_register_sitemap_settings');
function wrms_register_sitemap_settings(): void
{
    register_setting('wrms_sitemap_options_group', 'wrms_additional_sitemaps', 'wrms_sanitize_sitemap_urls');
    register_setting('wrms_sitemap_options_group', 'wrms_additional_urls', 'wrms_sanitize_sitemap_urls');
}

// Sanitize sitemap and URL inputs
function wrms_sanitize_sitemap_urls(mixed $input): array
{
    $sanitized_input = [];
    if (is_array($input)) {
        foreach ($input as $url) {
            $sanitized_input[] = esc_url_raw($url);
        }
    }
    return $sanitized_input;
}

// Filter to modify RankMath sitemap
add_filter('rank_math/sitemap/index', 'wrms_modify_rankmath_sitemap');
function wrms_modify_rankmath_sitemap(string $sitemap_content): string
{
    $additional_sitemaps = get_option('wrms_additional_sitemaps', []);
    $additional_urls = get_option('wrms_additional_urls', []);

    if (empty($additional_sitemaps) && empty($additional_urls)) {
        return $sitemap_content;
    }

    $dom = new DOMDocument();
    if (!$dom->loadXML($sitemap_content)) {
        return $sitemap_content;
    }

    $sitemapindex = $dom->getElementsByTagName('sitemapindex')->item(0);
    if (!$sitemapindex) {
        return $sitemap_content;
    }

    // Add additional sitemaps
    foreach ($additional_sitemaps as $sitemap_url) {
        $sitemap = $dom->createElement('sitemap');
        $loc = $dom->createElement('loc', $sitemap_url);
        $sitemap->appendChild($loc);
        $sitemapindex->appendChild($sitemap);
    }

    // Add additional URLs as a separate urlset
    if (!empty($additional_urls)) {
        foreach ($additional_urls as $url) {
            $sitemap = $dom->createElement('sitemap');
            $loc = $dom->createElement('loc', $url);
            $sitemap->appendChild($loc);
            $sitemapindex->appendChild($sitemap);
        }
    }

    return $dom->saveXML() ?: $sitemap_content;
}
