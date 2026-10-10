<?php
/** Beauty facts on QIL's native card, with native Woo commerce kept intact. */
defined('ABSPATH') || exit;
function qby_qil_text($value,$limit=180) {
    if(!is_scalar($value) || is_bool($value)){return '';}
    $value=trim(wp_strip_all_tags((string)$value));
    return function_exists('mb_substr')?mb_substr($value,0,$limit,'UTF-8'):substr($value,0,$limit);
}
function qby_qil_category_labels($id) {
    $known=qby_terms();$terms=get_the_terms($id,'product_cat');$candidates=array();
    foreach(array_slice(is_array($terms)?$terms:array(),0,64) as $term){if(isset($known[$term->slug])){$candidates[]=array('labels'=>$known[$term->slug],'depth'=>count(get_ancestors($term->term_id,'product_cat')),'set'=>str_ends_with($term->slug,'-sets'));}}
    usort($candidates,static function($a,$b){return ($b['set']<=>$a['set'])?:($b['depth']<=>$a['depth']);});
    $row=$candidates?$candidates[0]['labels']:array('Beauty & care','الجمال والعناية');
    return array('en'=>qby_qil_text($row[0],100),'ar'=>qby_qil_text($row[1],100));
}
function qby_qil_beauty_record($record,$product,$language=array()) {
    if(!is_array($record) || !$product || !qby_uses_beauty_ui($product->get_id())){return $record;}
    $id=$product->get_id();$details=qby_details($id);$department=qby_product_department($id);$category=qby_qil_category_labels($id);
    $class=qby_resolved_class($id)==='accessory'?'accessory':'beauty';
    $preferred=array('skin-care'=>array('skin_type','texture','spf'),'makeup'=>array('finish','coverage','undertone'),'hair-care'=>array('hair_type','texture'),'fragrance'=>array('concentration','fragrance_family'),'body-care'=>array('skin_type','texture'),'beauty-tools'=>array('material','dimensions'));
    $schema=qby_beauty_field_schema($department);$facts=array();
    foreach(array('en','ar') as $locale){
        $rows=array();$size=qby_qil_text($details['size']??'',100);
        if($class!=='accessory'){$rows[]=array('label'=>$locale==='ar'?'الحجم':'Size','value'=>$size?:($locale==='ar'?'غير مدرج':'Not listed'));}
        foreach($preferred[$department]??array() as $key){
            $value=qby_qil_text($details[$locale==='ar'?$key.'_ar':$key]??'',120);
            if($value && isset($schema[$key])){$rows[]=array('label'=>$schema[$key][$locale],'value'=>$value);}
            if(count($rows)>=2){break;}
        }
        if(count($rows)<2){$rows[]=array('label'=>$locale==='ar'?'النوع':'Type','value'=>$category[$locale]);}
        if(count($rows)<2){$rows[]=array('label'=>$locale==='ar'?'تفاصيل المنتج':'Product detail','value'=>$locale==='ar'?'غير مدرج':'Not listed');}
        $facts[$locale]=array_slice($rows,0,2);
    }
    $record['productClass']=$class;$record['beauty']=array('schema'=>1,'department'=>$department,'category'=>$category,'facts'=>$facts);
    // Cosmetic and tool cards never inherit oral-only derived facts or dietary claims.
    $record['facts']=array();$record['dietary']=array();
    foreach(array_keys((array)($record['price']??array())) as $key){if(str_starts_with($key,'perServing')){unset($record['price'][$key]);}}
    $record['match']['purposeKey']='beauty';$record['match']['reasons']=array();
    return $record;
}
add_filter('qil_catalogue_product_record','qby_qil_beauty_record',100,3);
// Baseline/disabled and Beauty-enabled projections never share a native payload cache.
add_filter('qil_cache_market_identity',static function($identity){$identity['beautyRecordSchema']='qby-'.QBY_VERSION.'-1';return $identity;});

/** One bounded native query; return in the caller's already-authorized listing order. */
function qby_qil_records($items) {
    if(!function_exists('qil_get_catalogue') || !function_exists('qil_experience_enabled') || !qil_experience_enabled() || !defined('QIL_VERSION') || version_compare(QIL_VERSION,'1.18.12','<')){return array();}
    $ids=array();foreach(array_slice((array)$items,0,48) as $item){$id=is_object($item)&&is_callable(array($item,'get_id'))?$item->get_id():(is_object($item)&&isset($item->ID)?absint($item->ID):absint($item));if($id && qby_uses_beauty_ui($id)){$ids[$id]=$id;}}
    if(!$ids){return array();}
    $records=qil_get_catalogue(array('include'=>array_values($ids),'limit'=>count($ids),'orderby'=>'include','_qil_catalogue_selection'=>true,'_qil_locale'=>qby_ar()?'ar':'en'));
    $indexed=array();foreach((array)$records as $record){if(is_array($record) && isset($ids[(int)($record['id']??0)])){$indexed[(int)$record['id']]=$record;}}
    $ordered=array();foreach($ids as $id){if(isset($indexed[$id])){$ordered[]=$indexed[$id];}}
    return $ordered;
}
function qby_qil_json($records) {
    return '<script type="application/json" class="qby-qil-records">'.wp_json_encode(array_values($records),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
}
/** Caller supplies existing server-rendered Woo fallback; this function never calls qby_cards. */
function qby_qil_shelf($products,$fallback_html) {
    $records=qby_qil_records($products);if(!$records){return $fallback_html;}
    $GLOBALS['qby_qil_docks_needed']=true;
    $locale=qby_ar()?'ar':'en';
    return '<div class="qby-native-products qby-qil-shelf qil-shell" data-qby-qil-shelf data-qil-locale="'.$locale.'" lang="'.$locale.'" dir="'.($locale==='ar'?'rtl':'ltr').'">'.$fallback_html.qby_qil_json($records).'</div>';
}

/** Inner catalogue pages reuse the native dock before deferred QIL initializes. */
function qby_qil_print_docks() {
    static $printed=false;
    if($printed || empty($GLOBALS['qby_qil_docks_needed']) || !empty($GLOBALS['qby_qil_existing_docks']) || !function_exists('qil_section_docks') || !function_exists('qil_render_mode') || qil_render_mode()!=='chrome'){return;}
    if(is_product() || (function_exists('qil_has_shortcode') && qil_has_shortcode())){return;}
    $printed=true;$locale=qby_ar()?'ar':'en';
    echo '<div class="qby-qil-docks qil-shell qaatm-no-translate notranslate" data-qil-locale="'.$locale.'" lang="'.$locale.'" dir="'.($locale==='ar'?'rtl':'ltr').'" translate="no" data-qaatm-no-rewrite data-no-translation>'.qil_section_docks().'</div>';
}
add_action('wp_footer','qby_qil_print_docks',15);
// Also recognize an explicitly rendered shortcode outside the post content.
add_filter('do_shortcode_tag',static function($output,$tag){
    if($tag==='qimia_intelligence_lab' && is_string($output) && strpos($output,'data-qil-compare-dock')!==false){$GLOBALS['qby_qil_existing_docks']=true;}
    return $output;
},10,2);

/** Native archive/brand loops; one batch from the current query, never one query per card. */
function qby_qil_archive_loop_enabled() {
    return empty($GLOBALS['qby_qil_manual_shelf']) && !is_product() && (is_tax('product_brand') || (function_exists('qby_archive_context') && qby_archive_context()));
}
add_filter('woocommerce_product_loop_start',static function($html){
    if(!qby_qil_archive_loop_enabled()){return $html;}
    global $wp_query;
    $products=is_object($wp_query)&&is_array($wp_query->posts??null)?array_slice($wp_query->posts,0,48):array();
    $records=qby_qil_records($products);$indexed=array();foreach($records as $record){$indexed[(int)$record['id']]=$record;}
    if($records){$GLOBALS['qby_qil_docks_needed']=true;}
    $GLOBALS['qby_qil_loop_stack'][]=$indexed;
    $locale=qby_ar()?'ar':'en';
    // A mixed brand loop does not put its supplement cards inside a QIL shell.
    $shell=is_tax('product_brand')?'':' qil-shell';
    return '<div class="qby-qil-shelf'.$shell.'" data-qby-qil-shelf data-qil-locale="'.$locale.'" lang="'.$locale.'" dir="'.($locale==='ar'?'rtl':'ltr').'">'.$html;
},30);
add_action('woocommerce_after_shop_loop_item_title',static function(){
    if(!empty($GLOBALS['qby_qil_manual_shelf']) || empty($GLOBALS['qby_qil_loop_stack'])){return;}global $product;
    if(!$product || !qby_uses_beauty_ui($product->get_id())){return;}
    $records=end($GLOBALS['qby_qil_loop_stack']);$record=$records[$product->get_id()]??null;
    if($record){echo '<script type="application/json" class="qby-qil-card-record">'.wp_json_encode($record,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';}
},9);
add_filter('woocommerce_product_loop_end',static function($html){
    if(empty($GLOBALS['qby_qil_loop_stack']) || !qby_qil_archive_loop_enabled()){return $html;}
    $records=array_pop($GLOBALS['qby_qil_loop_stack']);
    return $html.qby_qil_json(array_values($records)).'</div>';
},30);
