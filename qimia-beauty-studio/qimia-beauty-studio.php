<?php
/**
 * Plugin Name: Qimia Beauty Studio — Staging
 * Description: Editorial Beauty discovery and homepage portal. Strictly isolated to qimialab.qimia.om; no catalogue or commerce writes.
 * Version: 1.0.3
 * Author: Qimia
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 */
defined('ABSPATH') || exit;
define('QBS_VERSION', '1.0.3');
define('QBS_DIR', plugin_dir_path(__FILE__));
define('QBS_URL', plugin_dir_url(__FILE__));

function qbs_stage_allowed() {
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    return $host === 'qimialab.qimia.om'
        && wp_parse_url(get_option('home'), PHP_URL_HOST) === 'qimialab.qimia.om'
        && wp_parse_url(get_option('siteurl'), PHP_URL_HOST) === 'qimialab.qimia.om'
        && !(defined('QBS_DISABLE') && QBS_DISABLE);
}
if (!qbs_stage_allowed()) { return; }

add_action('wp', static function () {
    // Do not activate a partial experience when the existing Beauty owner is unavailable.
    if (!function_exists('qby_beauty_page') || !function_exists('qby_beauty_query') || !function_exists('qby_cards')) { return; }
    require_once QBS_DIR . 'includes/experience.php';
    add_shortcode('qimia_beauty', 'qbs_page');
    add_action('qil_home_before_flash', 'qbs_home', 4);
    add_filter('body_class', static function ($classes) {
        if (is_front_page() || is_page('beauty')) { $classes[] = 'qbs-active'; }
        if (is_page('beauty')) { $classes[] = 'qbs-beauty-page'; }
        return $classes;
    });
    add_action('wp_enqueue_scripts', static function () {
        if (is_admin() || isset($_GET['elementor-preview']) || (!is_front_page() && !is_page('beauty'))) { return; }
        wp_enqueue_style('qimia-beauty-studio', QBS_URL . 'assets/studio.min.css', array('qimia-beauty'), QBS_VERSION);
        wp_enqueue_script('qimia-beauty-studio', QBS_URL . 'assets/studio.min.js', array('qimia-beauty'), QBS_VERSION, true);
        wp_script_add_data('qimia-beauty-studio', 'strategy', 'defer');
    }, 130);
}, 100);
