<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

// Register sitemap settings
add_action('admin_init', 'wrms_register_sitemap_settings');
function wrms_register_sitemap_settings()
{
  register_setting('wrms_sitemap_options_group', 'wrms_additional_sitemaps', 'wrms_sanitize_sitemap_urls');
  register_setting('wrms_sitemap_options_group', 'wrms_additional_urls', 'wrms_sanitize_sitemap_urls');
}

// Sanitize sitemap and URL inputs
function wrms_sanitize_sitemap_urls($input)
{
  $sanitized_input = array();
  if (is_array($input)) {
    foreach ($input as $url) {
      $sanitized_input[] = esc_url_raw($url);
    }
  }
  return $sanitized_input;
}

// Filter to modify RankMath sitemap
add_filter('rank_math/sitemap/index', 'wrms_modify_rankmath_sitemap');
function wrms_modify_rankmath_sitemap($sitemap_content)
{
  $additional_sitemaps = get_option('wrms_additional_sitemaps', array());
  $additional_urls = get_option('wrms_additional_urls', array());

  $dom = new DOMDocument();
  $dom->loadXML($sitemap_content);
  $sitemapindex = $dom->getElementsByTagName('sitemapindex')->item(0);

  // Add additional sitemaps
  foreach ($additional_sitemaps as $sitemap_url) {
    $sitemap = $dom->createElement('sitemap');
    $loc = $dom->createElement('loc', $sitemap_url);
    $sitemap->appendChild($loc);
    $sitemapindex->appendChild($sitemap);
  }

  // Add additional URLs
  if (!empty($additional_urls)) {
    $urlset = $dom->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    foreach ($additional_urls as $url) {
      $url_element = $dom->createElement('url');
      $loc = $dom->createElement('loc', $url);
      $url_element->appendChild($loc);
      $urlset->appendChild($url_element);
    }

    $sitemapindex->appendChild($urlset);
  }

  return $dom->saveXML();
}
