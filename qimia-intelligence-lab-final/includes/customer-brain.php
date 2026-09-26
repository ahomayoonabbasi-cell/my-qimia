<?php
/** Intelligence Lab adapter. Existing account ledger/consent owns collection and retention. */
defined('ABSPATH') || exit;
add_filter('qimia_customer_source_projection_v2',static function(array $context):array {
    $context['intelligence_lab']=['version'=>QIL_VERSION,'contract'=>'qimia-intelligence-signals/2','source'=>'existing_event_ledger','enabled'=>true];
    if(empty($context['permissions']['activity_history']))return $context;
    foreach($context['interests'] as &$interest){
        $id=(int)($interest['id']??0);if(!$id)continue;
        $interest['categories']=[];$interest['brands']=[];
        foreach(['product_cat'=>'categories','product_brand'=>'brands','pa_brand'=>'brands','pwb-brand'=>'brands'] as $taxonomy=>$key){
            if(!taxonomy_exists($taxonomy))continue;$terms=get_the_terms($id,$taxonomy);if(!$terms||is_wp_error($terms))continue;
            foreach(array_slice($terms,0,3) as $term)$interest[$key][]=['id'=>(int)$term->term_id,'label'=>sanitize_text_field($term->name)];
        }
    }unset($interest);return $context;
});
add_action('wp_enqueue_scripts',static function():void {
    if(is_admin()||!is_tax()||(function_exists('qil_perf_noninteractive_bot')&&qil_perf_noninteractive_bot())||(function_exists('qil_perf_crawler_family')&&''!==qil_perf_crawler_family()))return;
    $term=get_queried_object();if(!($term instanceof WP_Term)||!in_array($term->taxonomy,['product_cat','product_brand','pa_brand','pwb-brand','yith_product_brand'],true))return;
    wp_enqueue_script('qil-brain-signals',QIL_URL.'assets/qil-brain-signals.js',['qh-account'],QIL_VERSION,true);
    wp_add_inline_script('qil-brain-signals','window.QIL_BRAIN_VIEW='.wp_json_encode(['type'=>$term->taxonomy==='product_cat'?'category_view':'brand_view','term_id'=>(int)$term->term_id]).';','before');
},70);
