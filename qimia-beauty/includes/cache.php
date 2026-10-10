<?php
/** Invalidate derived navigation/facets when Woo data changes, including REST imports. */
defined('ABSPATH') || exit;
function qby_cache_generation() { return (string)get_option('qby_cache_generation','1'); }
function qby_flush_catalogue_cache() {
    delete_transient('qby_brand_groups');
    delete_transient('qby_beauty_presence_v2');
    foreach(array('en','ar') as $language){delete_transient('qby_navigation_'.QBY_VERSION.'_'.$language);}
    // Old facet keys expire after five minutes; generation changes avoid wildcard SQL deletes.
    update_option('qby_cache_generation',str_replace('.','',sprintf('%.6f',microtime(true))),false);
}
function qby_invalidate_catalogue_cache() {
    static $queued=false;if($queued){return;}$queued=true;
    qby_flush_catalogue_cache();
    // A long import may repopulate a cache between product saves. Clear it once after the last save.
    add_action('shutdown','qby_flush_catalogue_cache',50);
}
foreach(array('save_post_product','woocommerce_new_product','woocommerce_update_product','woocommerce_delete_product','woocommerce_product_set_stock_status','woocommerce_variation_set_stock_status','update_option_woocommerce_hide_out_of_stock_items') as $hook){add_action($hook,'qby_invalidate_catalogue_cache');}
add_action('set_object_terms',static function($object_id,$terms,$tt_ids,$taxonomy){
    if(in_array($taxonomy,array('product_cat','product_brand','product_visibility'),true) || str_starts_with((string)$taxonomy,'pa_')){qby_invalidate_catalogue_cache();}
},30,4);
foreach(array('created_term','edited_term','delete_term') as $hook){add_action($hook,static function($term_id,$tt_id,$taxonomy){
    if(in_array($taxonomy,array('product_cat','product_brand','product_visibility'),true) || str_starts_with((string)$taxonomy,'pa_')){qby_invalidate_catalogue_cache();}
},30,3);}
add_action('deleted_post',static function($id,$post=null){if($post && in_array($post->post_type,array('product','product_variation'),true)){qby_invalidate_catalogue_cache();}},30,2);

foreach(array('added_term_meta','updated_term_meta','deleted_term_meta') as $hook){add_action($hook,static function($meta_id,$term_id,$key){
    if(in_array($key,array('thumbnail_id','_thumbnail_id'),true)){qby_invalidate_catalogue_cache();}
},30,3);}
foreach(array('added_post_meta','updated_post_meta','deleted_post_meta') as $hook){add_action($hook,static function($meta_id,$id,$key){
    if(in_array($key,array('_qby_product_class','_qby_cosmetic_details'),true)){qby_invalidate_catalogue_cache();}
},30,3);}
