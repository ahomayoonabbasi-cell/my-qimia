<?php
/** Two category entrances; no new initial product query or fabricated offer. */
defined('ABSPATH')||exit;
function qil_promotion_url($key,$ar=false){
 $slugs=(array)apply_filters('qil_promotion_category_slugs','flash'===$key?array('flash-sale','flash-sales'):array('offers','special-offers','deals'),$key);
 foreach($slugs as $slug){
  if(!is_scalar($slug))continue;
  $term=get_term_by('slug',sanitize_title((string)$slug),'product_cat');
  if(!$term||is_wp_error($term))continue;$url=get_term_link($term);
  if(!is_wp_error($url))return qil_localized_url($url,$ar);
 }
 $shop=function_exists('wc_get_page_permalink')?wc_get_page_permalink('shop'):home_url('/shop/');
 return add_query_arg('qil_on_sale','1',qil_localized_url($shop,$ar));
}
function qil_section_promotion_rail(){
 $context=qil_view_context();$ar=$context['isArabic'];$out='<div class="qil-container qil-promotion-rail" aria-label="'.esc_attr($ar?'عروض كيميا':'Qimia offers').'">';
 foreach(array('flash'=>array('Flash Sale','التخفيضات السريعة','Explore current flash-sale products','اكتشف منتجات التخفيضات السريعة','qil-i-energy'),'offers'=>array('Offers','العروض','Explore current store offers','اكتشف عروض المتجر الحالية','qil-i-spark')) as $key=>$v){
  $out.='<a class="qil-promotion-card" data-qil-promotion-entry="'.esc_attr($key).'" href="'.esc_url(qil_promotion_url($key,$ar)).'"><span class="qil-promotion-icon"><svg aria-hidden="true"><use href="#'.esc_attr($v[4]).'"/></svg></span><span><small>'.esc_html($ar?'اكتشف المزيد':'DISCOVER MORE').'</small><strong>'.esc_html($ar?$v[1]:$v[0]).'</strong><em>'.esc_html($ar?$v[3]:$v[2]).'</em></span><svg class="qil-promotion-arrow" aria-hidden="true"><use href="#qil-i-arrow"/></svg></a>';
 }
 return $out.'</div>';
}
/** The fallback filters the actual Woo query; it never manufactures sale prices. */
function qil_apply_on_sale_archive($query){
 if(!qil_experience_enabled()||is_admin()||!isset($_GET['qil_on_sale'])||!is_string($_GET['qil_on_sale'])||'1'!==$_GET['qil_on_sale']||!is_object($query))return;
 if(method_exists($query,'is_main_query')&&!$query->is_main_query())return;
 if(!function_exists('wc_get_product_ids_on_sale'))return;
 $ids=array_map('absint',wc_get_product_ids_on_sale());
 $existing=$query->get('post__in');
 if(is_array($existing)&&$existing)$ids=array_values(array_intersect($ids,array_map('absint',$existing)));
 $query->set('post__in',$ids?:array(0));
}
add_action('woocommerce_product_query','qil_apply_on_sale_archive',20);
