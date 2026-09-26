<?php
/** Small global navigation refinement. No catalog, pricing or translation-plugin rewrites. */
defined('ABSPATH') || exit;
add_filter('qil_header_links',static function($links,$context){
    $home=function_exists('qil_should_render')&&qil_should_render();
    if(!$home)$links=array_values(array_filter($links,static function($link){return !in_array(wp_parse_url($link['url']??'',PHP_URL_FRAGMENT),array('qil-match','qil-ai','qil-compare'),true);}));
    if(!empty($context['isArabic'])&&function_exists('qil_localized_url'))foreach($links as &$link)$link['url']=qil_localized_url($link['url']??'',true);unset($link);
    return $links;
},50,2);
add_action('wp_enqueue_scripts',static function(){
    if(is_admin()||qil_is_elementor_context()||(function_exists('qil_perf_noninteractive_bot')&&qil_perf_noninteractive_bot())||(function_exists('qil_perf_crawler_family')&&''!==qil_perf_crawler_family()))return;
    wp_enqueue_style('qh-site-chrome',QIL_URL.'assets/qh-chrome.css',array(),QIL_VERSION);
    wp_enqueue_script('qh-site-chrome',QIL_URL.'assets/qh-chrome.js',array(),QIL_VERSION,true);
},45);
