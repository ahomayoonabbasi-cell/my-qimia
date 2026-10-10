<?php
defined('ABSPATH') || exit;

// Data adapter only. The original mega-menu plugin owns rendering and all menu assets.
add_filter('acmm_qimia_navigation_provider', static function($provider) {
    if (!function_exists('qby_frontend_enabled') || !qby_frontend_enabled()
        || !function_exists('qil_experience_enabled') || !qil_experience_enabled()
        || isset($_GET['elementor-preview'])) { return $provider; }
    return array(
        'version'=>1,
        'beauty_enabled'=>true,
        'brand_taxonomy'=>'product_brand',
        'cache_key'=>QBY_VERSION.'_'.qby_cache_generation().'_'.(qby_ar() ? 'ar' : 'en'),
        'is_arabic'=>qby_ar(),
        'categories'=>'qby_acmm_categories',
        'label'=>'qby_label',
        'term_url'=>'qby_term_url',
        'term_link'=>'qby_acmm_term_link',
        'url'=>'qby_url',
        'beauty_map'=>'qby_beauty_map',
        'browse_url'=>'qby_browse_url',
        'brands'=>'qby_acmm_brands',
        'utilities'=>'qby_acmm_utilities',
    );
});

function qby_acmm_categories($settings) {
    if (($settings['hide_empty'] ?? 'yes') === 'yes') { return qby_category_tree(); }
    $terms = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'name'));
    return is_wp_error($terms) ? array() : $terms;
}
function qby_acmm_term_link($term) {
    $url = get_term_link($term);
    return is_wp_error($url) ? '' : (function_exists('qil_localized_url') ? qil_localized_url($url, qby_ar()) : $url);
}
function qby_acmm_brands() {
    $brands = qby_brand_directory();
    // One native taxonomy read, never a separate get_term_by slug query per brand.
    $terms = get_terms(array('taxonomy'=>'product_brand','hide_empty'=>false,'orderby'=>'name'));
    $by_id = array();
    if (!is_wp_error($terms)) { foreach ($terms as $term) { $by_id[(int)$term->term_id] = $term; } }
    foreach ($brands as &$brand) {
        if (isset($by_id[(int)$brand['id']])) { $brand['url'] = qby_acmm_term_link($by_id[(int)$brand['id']]); }
    }
    unset($brand);
    return $brands;
}
function qby_acmm_utilities() {
    $context = qil_view_context();
    $links = array(
        array('url'=>$context['homeUrl'], 'label'=>qby_t('Home','الرئيسية')),
        array('url'=>$context['shopUrl'], 'label'=>qby_t('Shop','المتجر')),
        array('url'=>$context['homeUrl'].'#qil-match', 'label'=>qby_t('Goals','الأهداف')),
    );
    if (function_exists('qmq_workspace_url')) { $links[] = array('url'=>qmq_workspace_url(), 'label'=>qby_t('My Qimia','ماي كيميا')); }
    $links[] = array('url'=>$context['accountUrl'], 'label'=>qby_t('My Account','حسابي'));
    $links[] = array('url'=>$context['aboutUrl'], 'label'=>qby_t('About us','من نحن'));
    $links[] = array('url'=>qby_url('/cashback/'), 'label'=>qby_t('Cashback','الاسترداد النقدي'));
    return $links;
}

// Beauty adds one destination; the shared header and its original links belong to QIL.
add_filter('qil_header_links', static function($links, $context) {
    if (!is_array($links) || !qby_frontend_enabled()) { return $links; }
    foreach ($links as $link) { if (($link['key'] ?? '') === 'beauty') { return $links; } }
    $beauty = array('key'=>'beauty', 'url'=>qby_url('/beauty/'), 'label'=>'Beauty', 'labelAr'=>'الجمال والعناية', 'i18n'=>'');
    $at = count($links);
    foreach ($links as $index => $link) {
        if (($link['key'] ?? '') === 'shop' || ($link['url'] ?? '') === ($context['shopUrl'] ?? null)) { $at = $index + 1; break; }
    }
    array_splice($links, $at, 0, array($beauty));
    return $links;
}, 100, 2);
