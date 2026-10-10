<?php
/**
 * Plugin Name: Qimia Beauty
 * Description: Beauty department, product facts and native shopping integration for Qimia.
 * Version: 2.3.2
 * Author: Qimia
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 */
defined('ABSPATH') || exit;
define('QBY_VERSION', '2.3.2');
define('QBY_DIR', plugin_dir_path(__FILE__));
define('QBY_URL', plugin_dir_url(__FILE__));
function qby_stage_allowed() {
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    return $host === 'qimialab.qimia.om'
        && wp_parse_url(get_option('home'), PHP_URL_HOST) === 'qimialab.qimia.om'
        && wp_parse_url(get_option('siteurl'), PHP_URL_HOST) === 'qimialab.qimia.om';
}
/** Existing staging settings stay valid. Production requires an explicit storefront switch. */
function qby_frontend_enabled() {
    return (bool) get_option('qby_enabled')
        && (qby_stage_allowed() || (bool) get_option('qby_live_enabled', false));
}
require_once QBY_DIR . 'includes/admin.php';
require_once QBY_DIR . 'includes/catalogue.php';
require_once QBY_DIR . 'includes/department-data.php';
require_once QBY_DIR . 'includes/cache.php';
require_once QBY_DIR . 'includes/migration.php';
require_once QBY_DIR . 'includes/upgrade.php';
require_once QBY_DIR . 'includes/upgrade-200.php';
require_once QBY_DIR . 'includes/hero-bridge.php';
require_once QBY_DIR . 'includes/diagnostics.php';
add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce') || !qby_frontend_enabled() || qby_profile_mode()==='baseline') { return; }
    require_once QBY_DIR . 'includes/storefront.php';
    require_once QBY_DIR . 'includes/navigation.php';
    if (qby_stage_allowed()) { require_once QBY_DIR . 'includes/experience.php'; }
    require_once QBY_DIR . 'includes/product-knowledge.php';
    require_once QBY_DIR . 'includes/integration.php';
    require_once QBY_DIR . 'includes/beauty-context.php';
    require_once QBY_DIR . 'includes/beauty-native-cards.php';
    require_once QBY_DIR . 'includes/data-integration.php';
    require_once QBY_DIR . 'includes/archive-filters.php';
    require_once QBY_DIR . 'includes/compare-beauty.php';
}, 100);
