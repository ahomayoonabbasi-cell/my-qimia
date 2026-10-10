<?php
defined('ABSPATH') || exit;
add_filter('wp_robots',static function($robots){if(!qby_stage_allowed()){return $robots;}unset($robots['index'],$robots['follow']);$robots['noindex']=true;$robots['nofollow']=true;$robots['noarchive']=true;return $robots;},PHP_INT_MAX);
add_filter('qil_product_intelligence_enabled',static function($enabled){return is_product() && qby_uses_beauty_ui() ? false : $enabled;});
add_filter('qil_personal_product_eligible',static function($eligible,$p){return $p && qby_uses_beauty_ui($p->get_id()) ? false : $eligible;},50,2);
add_filter('qil_product_knowledge',static function($k,$id,$ar){
    if(!qby_uses_beauty_ui($id)){return $k;}
    foreach(array('bodyEffects','timeline','whoFor','notes','faq','rows') as $key){$k[$key]=array();}
    foreach(array('serving','servingSize','servings','directions','allergens') as $key){$k[$key]='';}
    $d=qby_details($id);$k['hasFacts']=false;$k['hasBox']=false;$k['title']=$ar?'العناية الموضعية':'Topical care';$k['netContent']=$d['size']??'';$k['ingredients']=$d['inci']??'';return $k;
},100,3);
add_filter('pre_do_shortcode_tag',static function($return,$tag,$attr){
    $id=(int)($attr['product_id']??0);$id=$id?:get_queried_object_id();
    if(!is_product() || !qby_uses_beauty_ui($id)){return $return;}
    if($tag==='qimia_supplement_facts'){return '';}
    if($tag==='qimia_ai_box'){return qby_product_details($id);}
    return $return;
},100,3);
add_action('wp',static function(){
    if(!is_product() || !qby_uses_beauty_ui()){return;}
    // Suppress only the oral-supplement FAQ schema owned by the existing box.
    global $wp_filter;
    if(isset($wp_filter['wp_head'])){foreach($wp_filter['wp_head']->callbacks as $priority=>$callbacks){foreach($callbacks as $callback){$fn=$callback['function'];if(is_array($fn) && is_object($fn[0]) && get_class($fn[0])==='Qimia_AI_Product_Box_Structured_Plugin' && $fn[1]==='output_faq_schema'){remove_action('wp_head',$fn,$priority);}}}}
});
function qby_product_details($id=0) {
    static $done=array();$id=$id?:get_queried_object_id();if(isset($done[$id]) || !qby_uses_beauty_ui($id)){return '';}$done[$id]=true;
    if(qby_stage_allowed() && function_exists('qby_knowledge_render')){return qby_knowledge_render($id);}
    $d=qby_details($id);$ar=qby_ar();$department=qby_product_department($id);$schema=qby_beauty_field_schema($department);$inci=$d['inci']??'';$use=$d[$ar?'directions_ar':'directions_en']??$d['directions_en']??'';$warnings=$d[$ar?'warnings_ar':'warnings_en']??$d['warnings_en']??'';
    $facts=array(qby_t('Product type','نوع المنتج')=>qby_product_type_label($id));if(!empty($d['size'])){$facts[qby_t('Size','الحجم')]=$d['size'];}
    foreach(array('skin_type','hair_type','skin_concern','hair_concern','body_concern','finish','coverage','undertone','texture','shade_notes','spf','concentration','fragrance_family','top_notes','heart_notes','base_notes','material','dimensions','tool_care') as $key){$value=$d[$ar?$key.'_ar':$key]??$d[$key]??'';if($value && isset($schema[$key])){$facts[qby_t($schema[$key]['en'],$schema[$key]['ar'])]=$value;}}
    $choice_notes=array(
        'makeup'=>array('Choose your exact shade and size using the product options above. Compare the recorded undertone, finish and coverage; screen colours can differ from the product.','اختر الدرجة والحجم من خيارات المنتج أعلاه. قارن الأندرتون واللمسة النهائية والتغطية المدرجة؛ قد تختلف ألوان الشاشة عن المنتج.'),
        'skin-care'=>array('Start with the listed skin type, texture and full ingredient list. Follow the exact label for frequency, precautions and sun-care instructions.','ابدأ بنوع البشرة والقوام وقائمة المكونات المدرجة. اتبع ملصق المنتج لمعرفة عدد مرات الاستخدام والاحتياطات وإرشادات الوقاية من الشمس.'),
        'hair-care'=>array('Check the product type and the hair or scalp need stated on the label. Shampoo, conditioner, masks and leave-in products have different directions.','راجع نوع المنتج واحتياج الشعر أو فروة الرأس المذكور على العبوة. تختلف إرشادات الشامبو والبلسم والماسكات والعناية بدون شطف.'),
        'fragrance'=>array('Compare the listed concentration, fragrance notes and bottle size. Scent experience and wear time can vary; choose the exact option before adding to your bag.','قارن التركيز والنوتات العطرية وحجم العبوة المدرج. قد تختلف تجربة الرائحة ومدة ثباتها؛ اختر الخيار المحدد قبل إضافته إلى السلة.'),
        'body-care'=>array('Check the intended area, texture and product directions. Review the full ingredient list and precautions on your exact package.','راجع المنطقة المخصصة للاستخدام والقوام وإرشادات المنتج. اطلع على قائمة المكونات والاحتياطات على العبوة المحددة.'),
        'beauty-tools'=>array('Check the recorded material, size, compatibility and care instructions. Choose the exact tool or accessory option before adding it to your bag.','راجع الخامة والحجم والتوافق وإرشادات العناية المدرجة. اختر الأداة أو الإكسسوار المحدد قبل إضافته إلى السلة.'),
    );
    ob_start();?><section class="qby qby-product-details" id="qby-product-details" aria-labelledby="qby-details-heading" dir="<?php echo $ar?'rtl':'ltr'; ?>"><div class="qby-detail-header"><div><span class="qby-eyebrow"><?php echo esc_html(qby_t('QIMIA / BEAUTY','كيميا / الجمال')); ?></span><h2 id="qby-details-heading"><?php echo esc_html(qby_t('Every detail, made clear.','كل التفاصيل بوضوح.')); ?></h2><p><?php echo esc_html(qby_t('Get to know your product, from the details to your daily routine.','تعرّف على منتجك، من تفاصيله إلى روتينك اليومي.')); ?></p></div><span class="qby-detail-category"><?php echo esc_html(qby_label($department?:'beauty')); ?></span></div><div class="qby-detail-layout"><div class="qby-detail-overview"><div class="qby-product-facts"><?php foreach($facts as $label=>$value){echo '<div><span>'.esc_html($label).'</span><strong>'.esc_html($value).'</strong></div>';} ?></div>
    <?php $benefits=$d[$ar?'benefits_ar':'benefits_en']??$d['benefits_en']??'';if($benefits){echo '<div class="qby-verified-benefits"><h3>'.esc_html(qby_t('Why you’ll like it','لماذا ستفضّله')).'</h3><ul>';foreach(array_slice(preg_split('/\R/u',$benefits,-1,PREG_SPLIT_NO_EMPTY),0,4) as $benefit){echo '<li>'.esc_html($benefit).'</li>';}echo '</ul></div>';}$key_ingredients=$d[$ar?'key_ingredients_ar':'key_ingredients']??$d['key_ingredients']??'';if($key_ingredients){echo '<p class="qby-key-ingredients"><strong>'.esc_html(qby_t('Key ingredients: ','المكونات الرئيسية: ')).'</strong>'.esc_html($key_ingredients).'</p>';} ?>
    <?php if(isset($choice_notes[$department])): ?><div class="qby-choice-notes"><span class="qby-eyebrow"><?php echo esc_html(qby_t('BEFORE YOU CHOOSE','قبل أن تختار')); ?></span><p><?php echo esc_html(qby_t($choice_notes[$department][0],$choice_notes[$department][1])); ?></p></div><?php endif; ?></div><div class="qby-detail-accordions">
    <details open><summary><?php echo esc_html(qby_t('How to use','طريقة الاستخدام')); ?></summary><p><?php echo esc_html($use?:qby_t('Follow the directions on this product’s packaging. See the product description for the catalogue instructions.','اتبع الإرشادات على عبوة هذا المنتج. راجع وصف المنتج لتفاصيل الاستخدام المدرجة.')); ?></p></details>
    <?php if($department!=='beauty-tools'): ?><details><summary><?php echo esc_html(qby_t('Full ingredients · INCI','المكونات الكاملة · INCI')); ?></summary><?php if($inci): ?><p lang="en" dir="ltr" class="qby-inci"><?php echo esc_html($inci); ?></p><p class="qby-muted"><?php echo esc_html(qby_t('As listed in the Qimia catalogue. Formulas can vary by batch and market; compare with your package label.','كما هو مدرج في كتالوج كيميا. قد تختلف التركيبة حسب الدفعة والسوق؛ راجع ملصق عبوتك.')); ?></p><?php else: ?><p><?php echo esc_html(qby_t('The complete ingredient list is not recorded for this item. Check the label of the exact product and shade; sets can contain different formulas.','قائمة المكونات الكاملة غير مسجلة لهذا المنتج. راجع ملصق المنتج والدرجة المحددة؛ قد تحتوي المجموعات على تركيبات مختلفة.')); ?></p><?php endif; ?></details><?php endif; ?>
    <details><summary><?php echo esc_html(qby_t('Care, storage & label notes','العناية والتخزين وملاحظات العبوة')); ?></summary><p><?php echo esc_html($warnings?:qby_t('Follow the precautions and storage instructions on the exact product label.','اتبع احتياطات وإرشادات التخزين على ملصق المنتج المحدد.')); ?></p><?php if($department!=='beauty-tools'){echo '<p>'.esc_html(!empty($d['pao'])?qby_t('Period after opening: ','مدة الاستخدام بعد الفتح: ').$d['pao']:qby_t('Period after opening (PAO): check the open-jar symbol on your package. It is separate from a printed expiry date.','مدة الاستخدام بعد الفتح (PAO): راجع رمز العبوة المفتوحة. تختلف عن تاريخ انتهاء الصلاحية المطبوع.')).'</p>';} ?></details>
    </div></div><div class="qby-detail-footer"><div><strong><?php echo esc_html(qby_t('A question about this product?','هل لديك سؤال عن هذا المنتج؟')); ?></strong><p><?php echo esc_html(qby_t('Ask about the listed formula, product options or how it fits your routine.','اسأل عن التركيبة المدرجة أو خيارات المنتج أو ملاءمته لروتينك.')); ?></p></div><div class="qby-actions"><button type="button" class="qby-button" data-qimia-ai-open data-qby-product-ask="<?php echo (int)$id; ?>"><?php echo esc_html(qby_t('Ask Qimia AI','اسأل ذكاء كيميا')); ?></button><?php echo qby_link(qby_browse_url($department?:'beauty'),qby_t('Explore this department ↗','اكتشف هذا القسم ↗'),'qby-text-link');echo qby_link(qby_url('/refund_policy/'),qby_t('Returns & refunds','الإرجاع والاسترداد'),'qby-text-link'); ?></div></div><?php if(function_exists('qby_qil_records')){echo '<script type="application/json" class="qby-qil-records">'.wp_json_encode(qby_qil_records(array($id)),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';} ?></section><?php return ob_get_clean();
}
add_filter('elementor/frontend/the_content',static function($html){
    if(is_product() && qby_uses_beauty_ui() && preg_match('/(?:wd-single-title|product_title|woocommerce-product-gallery)/',$html) && strpos($html,'qby-product-details')===false){$html.=qby_product_details();}return $html;
},25);
add_action('woocommerce_after_single_product_summary',static function(){if(is_product() && qby_uses_beauty_ui()){echo qby_product_details();}},8);

function qby_search_scope($q) {
    $q=function_exists('mb_strtolower')?mb_strtolower(trim($q),'UTF-8'):strtolower(trim($q));$map=array(
        '/dry\s+shampoo|شامبو\s+جاف|شامپوی?\s+خشک/u'=>'dry-shampoo',
        '/shampoo|شامبو|شامپو/u'=>'shampoo', '/conditioner|بلسم/u'=>'conditioner',
        '/hair\s+mask|ماسك(?:\s+الشعر)?|ماسک(?:\s+مو)?/u'=>'hair-mask',
        '/scalp\s+serum|hair\s+serum|سيروم\s+(?:الشعر|فروة)|سرم\s+مو/u'=>'hair-serum',
        '/face\s+serum|skin\s+serum|سيروم\s+(?:الوجه|البشرة)/u'=>'face-serums',
        '/lipstick|أحمر\s+الشفاه/u'=>'lipstick','/mascara|ماسكارا/u'=>'mascara','/foundation|كريم\s+الأساس/u'=>'foundation','/perfume|عطر|عطور/u'=>'perfume','/sunscreen|واقي\s+الشمس/u'=>'sun-care','/face\s+cleanser|غسول\s+الوجه/u'=>'face-cleansers',
        '/leave[ -]?in|elixir|إكسير|بدون شطف/u'=>'leave-in-treatment',
    );
    foreach($map as $pattern=>$slug){if(preg_match($pattern,$q)){return array('category'=>$slug,'remainder'=>trim(preg_replace($pattern,' ',$q)),'exclusive'=>true);}}
    if(preg_match('/^(hair|hair care|hairburst|hair burst|hair loss|شعر|الشعر|العناية بالشعر|هيربرست|هير برست|تساقط الشعر)$/u',$q)){return array('category'=>'hair-care','remainder'=>'','exclusive'=>false,'ambiguous'=>(bool)preg_match('/^(hair|hair loss|شعر|الشعر|تساقط الشعر)$/u',$q));}
    return null;
}
function qby_search_ids($scope) {
    $rest=trim(preg_replace('/\b(?:hair|care|for|and|the)\b|للشعر/u','',$scope['remainder']));
    $result=qby_beauty_query(array('department'=>$scope['category'],'search'=>$rest,'per_page'=>8));
    return $result['ids'];
}
/** Two contiguous groups fit the caller's limit, so neither department crowds out the other. */
function qby_group_search_results($beauty,$oral,$limit) {
    $limit=max(4,min(8,(int)$limit));$take=$oral?intdiv($limit,2):$limit;
    $out=array_merge(array_slice($beauty,0,$take),array_slice($oral,0,$limit-min($take,count($beauty))));
    $seen=array();return array_values(array_filter($out,static function($p)use(&$seen){$id=(int)($p['id']??0);if(!$id || isset($seen[$id])){return false;}$seen[$id]=true;return true;}));
}
// Existing permission and locale checks run before this response adapter.
add_filter('rest_request_after_callbacks',static function($response,$handler,$request){
    if($request->get_route()!=='/qimia-lab/v1/search' || is_wp_error($response) || !function_exists('qil_get_catalogue')){return $response;}
    $locale=$request->get_param('qil_locale');if(!in_array($locale,array('en','ar'),true)){return $response;}
    $query=$request->get_param('q');if(!is_scalar($query)){return $response;}$scope=qby_search_scope((string)$query);if(!$scope){return $response;}
    $response=rest_ensure_response($response);if($response->get_status()!==200){return $response;}$data=$response->get_data();if(!is_array($data) || !isset($data['products'])){return $response;}
    $extra=array();$ids=qby_search_ids($scope);
    $records=$ids?qil_get_catalogue(array('include'=>$ids,'limit'=>count($ids),'orderby'=>'include','_qil_include_unavailable'=>true,'_qil_locale'=>$locale)):array();
    foreach($records as $r){$r['productClass']=qby_resolved_class((int)$r['id']);if(empty($r['stock']['inStock'])){$r['name'].=$locale==='ar'?' · غير متوفر':' · Out of stock';}$extra[]=$r;}
    if($scope['exclusive']){$data['products']=$extra;}
    elseif(!empty($scope['ambiguous'])){
        $oral=array_values(array_filter($data['products'],static function($p){return !qby_is_cosmetic((int)($p['id']??0));}));
        if(!$oral && function_exists('qil_search_product_ids')){$ids=qil_search_product_ids('hair supplement',4);$oral=$ids?qil_get_catalogue(array('include'=>$ids,'limit'=>4,'orderby'=>'include','_qil_locale'=>$locale)):array();}
        $data['products']=qby_group_search_results($extra,$oral,max(4,min(8,(int)$request->get_param('limit'))));
    }else{$combined=array_merge(array_slice($extra,0,4),$data['products']);$seen=array();$data['products']=array_values(array_filter($combined,static function($p)use(&$seen){if(isset($seen[$p['id']])){return false;}$seen[$p['id']]=true;return true;}));}
    $data['products']=array_slice($data['products'],0,max(4,min(8,(int)$request->get_param('limit'))));$response->set_data($data);return $response;
},100,3);
add_action('pre_get_posts',static function($query){
    if(is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || !$query->is_main_query()){return;}
    $existing_tax=(array)$query->get('tax_query');$tax=array('relation'=>'AND');if($existing_tax){$tax[]=$existing_tax;}
    if($query->is_search() && $query->get('s')){
        $scope=qby_search_scope((string)$query->get('s'));
        if($scope && $scope['exclusive']){$query->set('qby_original_search',(string)$query->get('s'));$tax[]=array('taxonomy'=>'product_cat','field'=>'slug','terms'=>array($scope['category']));$query->set('post_type','product');$query->set('s',trim(preg_replace('/\b(?:hair|care|for|and|the)\b|للشعر/u','',$scope['remainder'])));}
    }
    if($query->is_post_type_archive('product') && sanitize_key(qby_query_value('qby_department'))==='supplements'){
        $slugs=array_merge(qby_groups()['sports'][2],qby_groups()['wellness'][2],qby_groups()['inside'][2]);$tax[]=array('taxonomy'=>'product_cat','field'=>'slug','terms'=>$slugs);$tax[]=array('taxonomy'=>'product_cat','field'=>'slug','terms'=>array('beauty'),'operator'=>'NOT IN');
    }
    $kind=sanitize_key(qby_query_value('qby_kind'));
    if($query->is_tax('product_brand') && in_array($kind,array_merge(array_keys(qby_beauty_map()),array('supplements')),true)){
        $terms=$kind==='supplements'?array_merge(qby_groups()['sports'][2],qby_groups()['wellness'][2],qby_groups()['inside'][2]):array($kind);$tax[]=array('taxonomy'=>'product_cat','field'=>'slug','terms'=>$terms);
        if($kind==='supplements'){$tax[]=array('taxonomy'=>'product_cat','field'=>'slug','terms'=>array('beauty'),'operator'=>'NOT IN');}
    }
    if(count($tax)>1){$query->set('tax_query',$tax);}
},1000);
function qby_brand_nav() {
    static $done=false;if($done || !is_tax('product_brand')){return '';}$term=get_queried_object();$base=get_term_link($term);if(is_wp_error($base)){return '';}$base=function_exists('qil_localized_url')?qil_localized_url($base,qby_ar()):$base;
    $options=array(''=>qby_t('All products','كل المنتجات'));
    $brand=null;foreach(qby_brand_directory() as $row){if((int)$row['id']===(int)$term->term_id){$brand=$row;break;}}
    foreach((array)($brand['departments']??array()) as $slug){$options[$slug]=qby_label($slug);}
    if(!empty($brand['has_supplements'])){$options['supplements']=qby_t('Supplements','المكملات');}
    if(count($options)<2){return '';}$done=true;ob_start();$current=sanitize_key(qby_query_value('qby_kind'));echo '<nav class="qby qby-chips qby-brand-kinds" aria-label="'.esc_attr(qby_t('Brand product categories','فئات منتجات العلامة')).'">';foreach($options as $key=>$label){echo '<a href="'.esc_url($key?add_query_arg('qby_kind',$key,$base):$base).'"'.($current===$key?' aria-current="true"':'').'>'.esc_html($label).'</a>';}echo '</nav>';return ob_get_clean();
}
add_action('woocommerce_before_shop_loop',static function(){echo qby_brand_nav();},5);
add_filter('elementor/widget/render_content',static function($html){return is_tax('product_brand') && strpos($html,'wd-products')!==false && strpos($html,'product-grid-item')!==false ? qby_brand_nav().$html : $html;},100);
add_filter('woocommerce_product_loop_start',static function($html){return is_tax('product_brand')?qby_brand_nav().$html:$html;},20);
add_filter('woocommerce_rest_prepare_product_object',static function($response,$product){$data=$response->get_data();$class=qby_resolved_class($product->get_id());if($class){$data['qimia_product_class']=$class;$response->set_data($data);}return $response;},20,2);
add_filter('qimia_ai_instruction_parts_v1',static function($parts){$parts['stable'].="\nQimia Beauty includes makeup, skin care, hair care, fragrance, bath/body and beauty tools. Respect the explicit product class and exact product type: cosmetics are topical; tools are accessories. Never ingest either. Do not infer oral dosing from legacy supplement categories. Do not give oral dosing, servings, Supplement Facts, nutrient totals or supplement-plan actions for these items. Collagen and hair/skin/nails vitamins remain supplements. Use only the exact product label for ingredients and directions; do not infer certifications, PAO, availability or treatment claims.";return $parts;},30);
