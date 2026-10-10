<?php
/** Authenticated Woo product enrichment. No custom endpoint, product creation, cron or commerce writes. */
defined('ABSPATH') || exit;

function qby_data_field_properties() {
    $properties=array();
    foreach(qby_beauty_field_schema() as $key=>$field){
        $properties[$key]=array('type'=>'string','description'=>$field['en'].' / '.$field['ar'],'maxLength'=>$key==='inci'?16000:($field['type']==='textarea'?4000:1000));
    }
    return $properties;
}
function qby_data_prepared_id($object) {
    if(is_object($object) && method_exists($object,'get_id')){return (int)$object->get_id();}
    if(is_array($object)){return (int)($object['id']??$object['ID']??0);}
    return is_object($object)?(int)($object->ID??0):0;
}
function qby_data_error($code,$message,$status=400) {return new WP_Error('qby_'.$code,$message,array('status'=>$status));}
function qby_data_clean_fields($fields) {
    $out=array();$schema=qby_beauty_field_schema();
    foreach((array)$fields as $key=>$value){
        if(!isset($schema[$key]) || !is_string($value)){continue;}
        $max=$key==='inci'?16000:($schema[$key]['type']==='textarea'?4000:1000);
        $value=function_exists('mb_substr')?mb_substr($value,0,$max):substr($value,0,$max);
        // Explicit empty strings are retained to suppress a seed fallback, while omitted keys are unchanged.
        $out[$key]=$schema[$key]['type']==='url'?esc_url_raw($value,array('http','https')):sanitize_textarea_field($value);
        if(str_starts_with($key,'benefits_')){$out[$key]=implode("\n",array_slice(array_values(array_filter(array_map('trim',preg_split('/\R/u',$out[$key])))),0,4));}
    }
    return $out;
}
function qby_data_read_fields($id) {
    if(!$id || !qby_uses_beauty_ui($id)){return (object)array();}
    return (object)qby_data_clean_fields(qby_details($id));
}
add_action('rest_api_init',static function(){
    register_rest_field('product','qimia_product_class',array(
        'get_callback'=>static function($object){return qby_resolved_class(qby_data_prepared_id($object));},
        'schema'=>array('description'=>'Qimia primary class. Writes require one explicit non-empty enum; native Woo product type is separate.','type'=>'string','enum'=>array('','supplement','beauty','food','accessory'),'context'=>array('view','edit')),
    ));
    register_rest_field('product','qimia_beauty_details',array(
        'get_callback'=>static function($object){return qby_data_read_fields(qby_data_prepared_id($object));},
        'schema'=>array('description'=>'Verified Beauty fields. Patch semantics: omit to preserve; empty string clears that field. Requires explicit Beauty/tool class.','type'=>'object','properties'=>qby_data_field_properties(),'additionalProperties'=>false,'context'=>array('view','edit')),
    ));
});
// Private persistence is not a second public WordPress metadata write surface.
add_action('init',static function(){
    register_post_meta('product','_qby_cosmetic_details',array('type'=>'object','single'=>true,'show_in_rest'=>false,'sanitize_callback'=>'qby_data_clean_fields','auth_callback'=>static function($allowed,$key,$id){return current_user_can('edit_post',$id);}));
},30);

function qby_data_has_raw_meta($request,$product_id=0) {
    foreach((array)$request->get_param('meta_data') as $entry){
        if(!is_array($entry)){continue;}$key=$entry['key']??'';$stored=false;
        if(!empty($entry['id'])){$stored=get_metadata_by_mid('post',(int)$entry['id']);if($stored && in_array($stored->meta_key,array('_qby_product_class','_qby_cosmetic_details'),true)){$key=$stored->meta_key;if((int)$stored->post_id!==$product_id){return true;}}}
        if(!in_array($key,array('_qby_product_class','_qby_cosmetic_details'),true)){continue;}
        // Preserve generic GET-to-PUT clients that echo unchanged supplement metadata.
        $current=$product_id?get_post_meta($product_id,$key,true):null;
        if(!$product_id || !array_key_exists('value',$entry) || $entry['value']!==$current){return true;}
    }
    return false;
}
function qby_data_has_top_level($request) {return $request->has_param('qimia_product_class') || $request->has_param('qimia_beauty_details');}
function qby_data_category_context($category_ids) {
    $context=array('beauty'=>false,'tools'=>false,'departments'=>array());
    $root=get_term_by('slug','beauty','product_cat');$tool=get_term_by('slug','beauty-tools','product_cat');$map=qby_beauty_map();
    foreach(array_map('intval',(array)$category_ids) as $id){
        $chain=array_merge(array($id),array_map('intval',get_ancestors($id,'product_cat')));
        if($root && !is_wp_error($root) && in_array((int)$root->term_id,$chain,true)){$context['beauty']=true;}
        if($tool && !is_wp_error($tool) && in_array((int)$tool->term_id,$chain,true)){$context['tools']=true;}
        foreach($chain as $term_id){$term=get_term($term_id,'product_cat');if($term && !is_wp_error($term) && isset($map[$term->slug])){$context['departments'][$term->slug]=true;}}
    }
    return $context;
}
function qby_data_validate_fields($fields,$class,$context,$saved) {
    if(is_object($fields)){$fields=(array)$fields;}
    if(!is_array($fields) || ($fields && array_is_list($fields))){return qby_data_error('details_shape','qimia_beauty_details must be an object of named string fields.');}
    if(!in_array($class,array('beauty','accessory'),true) || ($class==='accessory' && !$context['tools'])){return qby_data_error('oral_details','Beauty details are only accepted for Beauty products or accessories assigned to Beauty tools. Supplement facts must use the existing supplement fields.');}
    $all=qby_data_field_properties();$allowed=array();
    foreach(array_keys($context['departments']) as $department){$allowed+=qby_beauty_field_schema($department);}
    if(!$allowed){$allowed=qby_beauty_field_schema('__common__');}
    if($class==='accessory'){$allowed=qby_beauty_field_schema('beauty-tools');foreach(array('inci','key_ingredients','key_ingredients_ar','pao') as $key){unset($allowed[$key]);}}
    foreach($fields as $key=>$value){
        if(!isset($all[$key]) || !isset($allowed[$key])){return qby_data_error('unknown_field','Unsupported field for this product department: '.sanitize_key($key));}
        if(!is_string($value)){return qby_data_error('field_type','Beauty field must be a string: '.$key);}
        $length=function_exists('mb_strlen')?mb_strlen($value):strlen($value);
        if($length>$all[$key]['maxLength']){return qby_data_error('field_length','Beauty field exceeds its documented length: '.$key);}
        if(str_starts_with($key,'benefits_') && count(preg_split('/\R/u',trim($value),-1,PREG_SPLIT_NO_EMPTY))>4){return qby_data_error('benefit_count','Use at most four source-supported benefits per language.');}
        if($key==='pao' && $value!=='' && !preg_match('/^(?:[1-9]|[1-9][0-9])M$/',trim($value))){return qby_data_error('pao_format','PAO must match the labelled open-jar value, e.g. 12M, or be blank.');}
        if($key==='spf' && $value!=='' && !preg_match('/^(?:[1-9][0-9]?|100)\+?$/',trim($value))){return qby_data_error('spf_format','SPF must be the labelled numeric value, optionally followed by +, or blank.');}
    }
    $clean=qby_data_clean_fields($fields);$candidate=array_merge(qby_data_clean_fields($saved),$clean);
    $nonempty=array_filter(array_diff_key($candidate,array('source_url'=>true)),static function($value){return trim($value)!=='';});
    $source=$candidate['source_url']??'';
    if($clean && $nonempty){$url=wp_parse_url($source);if(!$source || !is_array($url) || !in_array(strtolower($url['scheme']??''),array('http','https'),true) || empty($url['host']) || isset($url['user']) || isset($url['pass'])){return qby_data_error('source_required','Provide a public HTTP(S) source_url for this exact product or label before importing non-empty Beauty facts. No credentials in source URLs.');}}
    return $clean;
}
function qby_data_prepare_product($product,$request,$creating=false) {
    if(is_wp_error($product)){return $product;}
    if(qby_data_has_raw_meta($request,is_object($product) && method_exists($product,'get_id')?(int)$product->get_id():0)){return qby_data_error('raw_meta','Use qimia_product_class and qimia_beauty_details; direct writes of their private meta_data keys are not accepted.');}
    if(!qby_data_has_top_level($request)){return $product;}
    if(!qby_frontend_enabled()){return qby_data_error('disabled','Enable the Qimia Beauty storefront before enrichment.',403);}
    if(!is_object($product) || !method_exists($product,'get_id')){return qby_data_error('product','A native WooCommerce product is required.');}
    $id=(int)$product->get_id();
    if(($id && !current_user_can('edit_post',$id)) || (!$id && !current_user_can('edit_products'))){return qby_data_error('permission','Product edit permission required.',403);}
    if($product->is_type('variation')){return qby_data_error('variation_scope','This contract enriches parent product records. Keep shade/size inventory and options in native variations; do not treat parent ingredients as all shade formulas.');}
    $class=$request->get_param('qimia_product_class');
    if(!is_string($class) || !in_array($class,array('supplement','beauty','food','accessory'),true)){return qby_data_error('class_required','Provide qimia_product_class explicitly: supplement, beauty, food or accessory.');}
    $existing=$id?(string)get_post_meta($id,'_qby_product_class',true):'';if($existing==='cosmetic'){$existing='beauty';}
    if($existing!=='' && $existing!==$class){return qby_data_error('class_conflict','Stored product class differs. Review its classification in the product editor before automated enrichment.',409);}
    $context=qby_data_category_context($product->get_category_ids());
    if($class==='beauty' && !$context['beauty']){return qby_data_error('beauty_category','Beauty classification requires a category inside the existing Beauty tree. Assign the intended native category explicitly; this adapter never moves categories.');}
    if(in_array($class,array('supplement','food'),true) && $context['beauty']){return qby_data_error('category_conflict','An oral/food product is currently assigned to the topical Beauty tree. Review the category/class conflict before enrichment.',409);}
    $details=null;
    $requested_details=$request->get_param('qimia_beauty_details');
    $empty_oral_details=in_array($class,array('supplement','food'),true) && (is_array($requested_details) || is_object($requested_details)) && count((array)$requested_details)===0;
    if($request->has_param('qimia_beauty_details') && !$empty_oral_details){
        $saved=$id?get_post_meta($id,'_qby_cosmetic_details',true):array();$saved=is_array($saved)?$saved:array();
        $details=qby_data_validate_fields($request->get_param('qimia_beauty_details'),$class,$context,$saved);
        if(is_wp_error($details)){return $details;}
        // Preserve omitted data and intentional empty overrides. Native save owns persistence.
        $details=array_merge(qby_data_clean_fields($saved),$details);
    }
    $product->update_meta_data('_qby_product_class',$class);
    if($details!==null){$product->update_meta_data('_qby_cosmetic_details',$details);}
    return $product;
}
add_filter('woocommerce_rest_pre_insert_product_object','qby_data_prepare_product',90,3);
add_filter('woocommerce_rest_pre_insert_product_variation_object','qby_data_prepare_product',90,3);
// Reject a second write path via the WordPress post controller; Woo permissions/save flow are the owner.
add_filter('rest_pre_insert_product',static function($prepared,$request){
    $meta=(array)$request->get_param('meta');
    if(qby_data_has_top_level($request) || array_key_exists('_qby_product_class',$meta) || array_key_exists('_qby_cosmetic_details',$meta)){return qby_data_error('woo_route_required','Use the authenticated WooCommerce wc/v3/products endpoint for Qimia enrichment.');}
    return $prepared;
},90,2);

/** Existing cache/snapshot owners, invoked only after a real metadata change. */
function qby_data_meta_changed($meta_id,$object_id,$key,$value=null) {
    if(!in_array($key,array('_qby_product_class','_qby_cosmetic_details'),true)){return;}
    $type=get_post_type($object_id);if(!in_array($type,array('product','product_variation'),true)){return;}
    $parent=$type==='product_variation'?(int)wp_get_post_parent_id($object_id):(int)$object_id;
    static $invalidated=array();if(!isset($invalidated[$parent])){$invalidated[$parent]=true;if(function_exists('wc_delete_product_transients')){wc_delete_product_transients($parent);}}
    if(is_callable(array('AAICE_Edge_Sync','dirty')) && has_action('shutdown',array('AAICE_Edge_Sync','flush'))!==false){AAICE_Edge_Sync::dirty((int)$object_id);}
    qby_invalidate_catalogue_cache();
}
foreach(array('added_post_meta','updated_post_meta','deleted_post_meta') as $qby_meta_hook){add_action($qby_meta_hook,'qby_data_meta_changed',30,4);}
