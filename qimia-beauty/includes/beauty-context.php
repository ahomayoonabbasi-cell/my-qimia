<?php
/** Additive Beauty adapters. Native commerce remains the authority. */
defined('ABSPATH') || exit;
function qby_details($id) {
    $seed=qby_seed_record($id);$saved=get_post_meta($id,'_qby_cosmetic_details',true);
    return array_merge($seed,is_array($saved)?$saved:array());
}
// Reuse the existing single-value structured field, with no public class archive.
add_action('init',static function(){register_post_meta('product','_qby_product_class',array('type'=>'string','single'=>true,'show_in_rest'=>array('schema'=>array('type'=>'string','enum'=>array('','supplement','beauty','food','accessory'))),'sanitize_callback'=>static function($v){return in_array($v,array('supplement','beauty','food','accessory'),true)?$v:'';},'auth_callback'=>static function($allowed,$key,$id){return current_user_can('edit_post',$id);}));});
add_filter('body_class',static function($classes){if(qby_beauty_context()){$classes[]='qby-beauty-archive';}return $classes;});
// Only AI tool requests receive a read-only attribute projection; product storage is not rewritten.
add_filter('rest_request_before_callbacks',static function($response,$handler,$request){
    if(str_starts_with($request->get_route(),'/amir-ai/v1/')){$GLOBALS['qby_ai_request']=true;}return $response;
},10,3);
add_filter('woocommerce_product_get_attributes',static function($attrs,$product){
    if(empty($GLOBALS['qby_ai_request']) || !qby_uses_beauty_ui($product->get_id())){return $attrs;}
    $d=qby_details($product->get_id());$fields=array('Product Class'=>qby_resolved_class($product->get_id())==='accessory'?'Beauty tool / accessory; never an oral supplement':'Beauty — topical cosmetic; not an oral supplement','Product Type'=>qby_product_type_label($product->get_id()),'Pack size'=>$d['size']??'','INCI — Qimia catalogue source'=>$d['inci']??'','How to use'=>$d['directions_en']??'Check this exact product’s packaging and catalogue directions.','Warnings'=>$d['warnings_en']??'','PAO'=>$d['pao']??'Unconfirmed; check packaging.','Verified key ingredients'=>$d['key_ingredients']??'','Hair type'=>$d['hair_type']??'','Cosmetic certifications'=>'Sulfate-free, silicone-free, vegan and testing claims are unconfirmed unless explicitly verified in product data.');
    foreach(qby_beauty_field_schema(qby_product_department($product->get_id())) as $key=>$field){if(!empty($d[$key])){$fields[$field['en']]=$d[$key];}}
    $out=$attrs;foreach($out as $key=>$attribute){if(preg_match('/^(?:pa[-_])?(?:servings?|serving[-_ ]size|dosage|supplement[-_ ]facts|protein|carbohydrates?|calories)$/i',$key)){unset($out[$key]);}}foreach($fields as $name=>$value){if(!$value){continue;}$a=new WC_Product_Attribute();$a->set_name($name);$a->set_options(array($value));$a->set_visible(true);$a->set_variation(false);$out[sanitize_title($name)]=$a;}return $out;
},100,2);
add_action('woocommerce_after_shop_loop_item_title',static function(){global $product;if(!$product || !qby_uses_beauty_ui($product->get_id())){return;}$d=qby_details($product->get_id());$concern=qby_concerns()[$d['concerns'][0]??'']??null;echo '<p class="qby-product-caption"><bdi>'.esc_html($d['size']??'').'</bdi> · '.esc_html($concern?qby_t($concern[0],$concern[1]):qby_product_type_label($product->get_id())).'</p>';},9);
add_filter('wpseo_title',static function($title){return is_page('beauty')?qby_t('Beauty, Makeup, Skin Care & Fragrance in Oman | Qimia','الجمال والعناية الشخصية في عُمان | كيميا'):$title;},30);
add_filter('wpseo_metadesc',static function($description){return is_page('beauty')?qby_t('Explore makeup, skin care, hair care, fragrance, body care and beauty tools with Qimia Oman. Clear product details, one account and one shared basket.','اكتشف المكياج والعناية بالبشرة والشعر والعطور والجسم وأدوات الجمال لدى كيميا عُمان. تفاصيل واضحة وحساب واحد وسلة مشتركة.'):$description;},30);
