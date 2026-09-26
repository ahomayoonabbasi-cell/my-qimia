<?php
/** Independent header navigation, with the installed currency engine as sole owner. */
defined( 'ABSPATH' ) || exit;
function qil_navigation_config(){
 $home=(string)get_option('home',home_url('/'));
 $path=untrailingslashit((string)wp_parse_url($home,PHP_URL_PATH));
 return array('basePath'=>$path,'languageUrls'=>qil_language_route_urls(),'currencyCodes'=>qil_storefront_currency_codes(),
  'currencySwitchEnabled'=>class_exists('Qimia_Unlimited_Geo_Currency')&&count(qil_storefront_currency_codes())>1,
  'varyCookieKey'=>class_exists('Qimia_Unlimited_Geo_Currency')?Qimia_Unlimited_Geo_Currency::LSCACHE_VARY_COOKIE:'',
  'currency'=>function_exists('get_woocommerce_currency')?get_woocommerce_currency():'','locale'=>qil_language_context()['locale']);
}
function qil_enqueue_navigation(){
 if('none'===qil_render_mode() || qil_is_elementor_context() || (function_exists('qil_perf_noninteractive_bot')&&qil_perf_noninteractive_bot()) || (function_exists('qil_perf_crawler_family')&&''!==qil_perf_crawler_family()))return;
 $suffix=defined('WP_DEBUG')&&WP_DEBUG?'':'.min';
 // No dependency on WooCommerce, Qimia AI, or the homepage's large bundle.
 wp_enqueue_script('qimia-lab-navigation',QIL_URL.'assets/qil-navigation'.$suffix.'.js',array(),QIL_VERSION,false);
 wp_add_inline_script('qimia-lab-navigation','window.QIMIA_NAV='.wp_json_encode(qil_navigation_config(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
}
add_action('wp_enqueue_scripts','qil_enqueue_navigation',12);
/** Keep navigation on the current page without repeating state-changing arguments. */
function qil_navigation_current_url(){
 $home=(string)get_option('home',home_url('/'));$parts=wp_parse_url($home);
 $origin=($parts['scheme']??'https').'://'.($parts['host']??'').(isset($parts['port'])?':'.(int)$parts['port']:'');
 $request=isset($_SERVER['REQUEST_URI'])&&is_string($_SERVER['REQUEST_URI'])?wp_unslash($_SERVER['REQUEST_URI']):'/';
 if(substr($request,0,1)!=='/')$request='/';
 return remove_query_arg(array('add-to-cart','remove_item','undo_item','_wpnonce','wc-ajax','qimia_currency','qimiac_ts','qcur','qaatm_preview','qaatm_prepare','qaatm_lang','_qaatm_compiler','_qaatm_variant','_qaatm_bust'),$origin.$request);
}
function qil_currency_route_url($code){
 $url=qil_navigation_current_url();
 if(!class_exists('Qimia_Unlimited_Geo_Currency')||!in_array($code,qil_storefront_currency_codes(),true))return $url;
 return add_query_arg(array('qimia_currency'=>$code),$url);
}
/** A GET form works without JS but exposes no currency crawl links or timestamps. */
function qil_currency_form_fields(){
 $query=(string)wp_parse_url(qil_navigation_current_url(),PHP_URL_QUERY);
 $html='';
 foreach(explode('&',$query) as $part){
  if(''===$part)continue;
  $pair=explode('=',$part,2);$name=urldecode($pair[0]);
  if(''===$name)continue;
  $html.='<input type="hidden" name="'.esc_attr($name).'" value="'.esc_attr(urldecode($pair[1]??'')).'">';
 }
 return $html;
}
/** Currency choices are actions, not additional indexable catalogue pages. */
function qil_currency_robots($robots){
 if(qil_requested_currency()){$robots['noindex']=true;$robots['nofollow']=true;unset($robots['index'],$robots['follow']);}
 return $robots;
}
add_filter('wp_robots','qil_currency_robots');
function qil_currency_robots_txt($output,$public){
 if(!$public||!qil_experience_enabled())return $output;
 return rtrim($output)."\n\n# Currency actions are not crawlable pages.\nUser-agent: *\nDisallow: /*?*qimia_currency=\nDisallow: /*?*qimiac_ts=\n";
}
add_filter('robots_txt','qil_currency_robots_txt',10,2);
function qil_navigation_delay_exclusions($items){
 if(!qil_experience_enabled())return $items;
 return array_values(array_unique(array_merge((array)$items,array('qil-navigation','QIMIA_NAV'))));
}
add_filter('litespeed_optm_js_defer_exc','qil_navigation_delay_exclusions');
add_filter('litespeed_optm_js_exc','qil_navigation_delay_exclusions');
function qil_navigation_script_tag($tag,$handle){
 if('qimia-lab-navigation'!==$handle)return $tag;
 return str_replace('<script ','<script data-no-defer="1" data-no-optimize="1" data-cfasync="false" ',$tag);
}
add_filter('script_loader_tag','qil_navigation_script_tag',10,2);
function qil_requested_currency(){
 if(!qil_experience_enabled() || is_admin() || wp_doing_ajax() || !class_exists('Qimia_Unlimited_Geo_Currency'))return '';
 $settings=get_option(Qimia_Unlimited_Geo_Currency::OPTION_KEY,array());if(!is_array($settings)||empty($settings['enabled']))return '';
 $code=$_GET['qimia_currency']??''; if(!is_string($code))return '';
 $code=strtoupper(wp_unslash($code));
 if(!preg_match('/^[A-Z]{3}$/',$code))return '';
 return in_array($code,qil_storefront_currency_codes(),true)?$code:'';
}
function qil_currency_switch_no_cache(){
 if(!qil_requested_currency())return;
 if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
 if(!headers_sent()){nocache_headers();header('X-Robots-Tag: noindex, nofollow',false);}
 do_action('litespeed_control_set_nocache','Explicit Qimia currency change');
}
add_action('init','qil_currency_switch_no_cache',-1);
/** The owner's init handler can run before Woo's cart/session exists. Finish only then. */
function qil_finalize_currency_session(){
 static $done=false;if($done)return;
 $code=qil_requested_currency();if(!$code||!function_exists('WC')||!function_exists('get_woocommerce_currency'))return;
 // Never replace the owning engine's selection or attempt a local conversion.
 if(strtoupper((string)get_woocommerce_currency())!==$code)return;
 $wc=WC();if(!$wc||empty($wc->session)||empty($wc->cart))return;
 $owner=Qimia_Unlimited_Geo_Currency::instance();
 if(!is_callable(array($owner,'maybe_recalc_cart_after_currency_switch')))return;
 $owner->maybe_recalc_cart_after_currency_switch($code);$done=true;
}
add_action('wp_loaded','qil_finalize_currency_session',99);
add_action('template_redirect','qil_finalize_currency_session',2);
