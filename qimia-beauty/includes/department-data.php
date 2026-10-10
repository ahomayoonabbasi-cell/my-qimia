<?php
/** Shared Beauty topology and bounded catalogue reads. No writes on storefront requests. */
defined('ABSPATH') || exit;

function qby_beauty_map() {
    return array(
        'makeup'=>array('en'=>'Makeup','ar'=>'المكياج','children'=>array(
            'face-makeup'=>array('Face makeup','مكياج الوجه'),
            'foundation'=>array('Foundation','كريم الأساس'),
            'concealer'=>array('Concealer','خافي العيوب'),
            'makeup-primer'=>array('Primer','برايمر'),
            'face-powder'=>array('Face powder','بودرة الوجه'),
            'blush-bronzer'=>array('Blush & bronzer','أحمر الخدود والبرونزر'),
            'highlighter'=>array('Highlighter','الهايلايتر'),
            'eye-makeup'=>array('Eye makeup','مكياج العيون'),
            'mascara'=>array('Mascara','الماسكارا'),
            'eyeliner'=>array('Eyeliner','محدد العيون'),
            'eyeshadow'=>array('Eyeshadow','ظلال العيون'),
            'brow-makeup'=>array('Brows','مكياج الحواجب'),
            'lip-makeup'=>array('Lips','مكياج الشفاه'),
            'lipstick'=>array('Lipstick','أحمر الشفاه'),
            'lip-gloss'=>array('Lip gloss & tint','ملمع الشفاه والتنت'),
            'lip-liner'=>array('Lip liner','محدد الشفاه'),
            'setting-spray'=>array('Setting spray','مثبت المكياج'),
            'makeup-sets'=>array('Makeup sets','مجموعات المكياج'),
        )),
        'skin-care'=>array('en'=>'Skin care','ar'=>'العناية بالبشرة','children'=>array(
            'face-cleansers'=>array('Cleansers & makeup removers','غسول الوجه ومزيلات المكياج'),
            'toners-essences'=>array('Toners & essences','التونر والإيسنس'),
            'face-serums'=>array('Serums & treatments','السيروم والعلاجات الموضعية'),
            'face-moisturisers'=>array('Moisturisers','مرطبات الوجه'),
            'sun-care'=>array('Sun care','العناية والوقاية من الشمس'),
            'eye-care'=>array('Eye care','العناية بمحيط العين'),
            'face-masks'=>array('Face masks','ماسكات الوجه'),
            'face-exfoliators'=>array('Exfoliators','مقشرات الوجه'),
            'lip-care'=>array('Lip care','العناية بالشفاه'),
            'skin-care-sets'=>array('Skin care sets','مجموعات العناية بالبشرة'),
        )),
        'hair-care'=>array('en'=>'Hair care','ar'=>'العناية بالشعر','children'=>array(
            'shampoo'=>array('Shampoo','الشامبو'),
            'conditioner'=>array('Conditioner','البلسم'),
            'hair-mask'=>array('Hair masks','ماسكات الشعر'),
            'hair-serum'=>array('Scalp & hair serums','سيروم الشعر وفروة الرأس'),
            'leave-in-treatment'=>array('Leave-in care','العناية بدون شطف'),
            'dry-shampoo'=>array('Dry shampoo','الشامبو الجاف'),
            'hair-care-sets'=>array('Hair care sets','مجموعات العناية بالشعر'),
            'hair-oils'=>array('Hair oils','زيوت الشعر'),
            'hair-styling'=>array('Styling & heat protection','تصفيف الشعر والحماية من الحرارة'),
            'scalp-care'=>array('Scalp care','العناية بفروة الرأس'),
        )),
        'fragrance'=>array('en'=>'Fragrance','ar'=>'العطور','children'=>array(
            'perfume'=>array('Perfume','العطور'),
            'body-mists'=>array('Body & hair mists','معطرات الجسم والشعر'),
            'fragrance-sets'=>array('Fragrance gift sets','مجموعات هدايا العطور'),
            'travel-fragrance'=>array('Travel & discovery sizes','أحجام السفر وتجربة العطور'),
        )),
        'body-care'=>array('en'=>'Bath & body','ar'=>'الاستحمام والعناية بالجسم','children'=>array(
            'body-wash'=>array('Body wash & soap','غسول الجسم والصابون'),
            'body-moisturisers'=>array('Body moisturisers','مرطبات الجسم'),
            'body-scrubs'=>array('Body scrubs','مقشرات الجسم'),
            'body-treatments'=>array('Body treatments','العناية الموضعية بالجسم'),
            'hand-foot-care'=>array('Hand & foot care','العناية باليدين والقدمين'),
            'deodorants'=>array('Deodorants','مزيلات العرق'),
            'bath-body-sets'=>array('Bath & body sets','مجموعات الاستحمام والعناية بالجسم'),
        )),
        'beauty-tools'=>array('en'=>'Tools & accessories','ar'=>'أدوات وإكسسوارات الجمال','children'=>array(
            'makeup-brushes'=>array('Makeup brushes','فرش المكياج'),
            'makeup-sponges'=>array('Makeup sponges','إسفنج المكياج'),
            'makeup-tools'=>array('Makeup tools','أدوات المكياج'),
            'hair-tools'=>array('Hair tools & accessories','أدوات وإكسسوارات الشعر'),
            'beauty-organisers'=>array('Beauty bags & organisers','حقائب ومنظمات مستحضرات الجمال'),
        )),
    );
}

/** Original migration's tuple format; parents always precede their children. */
function qby_beauty_terms() {
    $out=array('beauty'=>array('Beauty & Personal Care','الجمال والعناية',''));
    foreach(qby_beauty_map() as $slug=>$department){
        $out[$slug]=array($department['en'],$department['ar'],'beauty');
        foreach($department['children'] as $child=>$labels){$out[$child]=array($labels[0],$labels[1],$slug);}
    }
    return $out;
}
function qby_department_slug($slug) {
    $slug=sanitize_key($slug);
    foreach(qby_beauty_map() as $key=>$department){if($key===$slug || isset($department['children'][$slug])){return $key;}}
    return '';
}
function qby_beauty_label($slug) {
    $terms=qby_beauty_terms();
    return isset($terms[$slug]) ? qby_t($terms[$slug][0],$terms[$slug][1]) : '';
}
function qby_browse_url($slug) {
    $slug=sanitize_key($slug);$known=qby_beauty_terms();
    if(!isset($known[$slug])){return qby_url('/beauty/');}
    $term=get_term_by('slug',$slug,'product_cat');
    if($term && !is_wp_error($term) && qby_category_has_catalogue($slug)){
        $url=get_term_link($term);
        if(!is_wp_error($url)){return function_exists('qil_localized_url')?qil_localized_url($url,qby_ar()):$url;}
    }
    return $slug==='beauty'?qby_url('/beauty/'):add_query_arg('department',$slug,qby_url('/beauty/')).'#qby-shop';
}

/** Tool taxonomy IDs are resolved once per request; product class itself is never rewritten. */
function qby_beauty_tool_term_ids() {
    static $ids=null;if($ids!==null){return $ids;}
    $root=get_term_by('slug','beauty-tools','product_cat');$ids=array();
    if(!$root || is_wp_error($root)){return $ids;}
    $children=get_term_children($root->term_id,'product_cat');
    $ids=array_values(array_unique(array_merge(array((int)$root->term_id),is_wp_error($children)?array():array_map('intval',$children))));
    return $ids;
}
function qby_is_beauty_tool($id) {
    $id=absint($id);if(!$id){return false;}
    if(get_post_type($id)==='product_variation'){$id=(int)wp_get_post_parent_id($id);}
    $terms=get_the_terms($id,'product_cat');if(!$terms || is_wp_error($terms)){return false;}
    $ids=qby_beauty_tool_term_ids();
    foreach($terms as $term){if(in_array((int)$term->term_id,$ids,true)){return true;}}
    return false;
}
/** SQL counterpart of the UI rule: oral/food never enter; accessories require Beauty tools. */
function qby_beauty_class_sql($product_id_sql) {
    global $wpdb;
    if(!in_array($product_id_sql,array('p.ID',$wpdb->posts.'.ID'),true)){throw new InvalidArgumentException('Internal product SQL identifier required.');}
    $ids=qby_beauty_tool_term_ids();
    $tool=$ids?"EXISTS (SELECT 1 FROM {$wpdb->term_relationships} qby_tr INNER JOIN {$wpdb->term_taxonomy} qby_tt ON qby_tt.term_taxonomy_id=qby_tr.term_taxonomy_id AND qby_tt.taxonomy='product_cat' WHERE qby_tr.object_id={$product_id_sql} AND qby_tt.term_id IN (".implode(',',$ids)."))":'1=0';
    return "NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} qby_cm WHERE qby_cm.post_id={$product_id_sql} AND qby_cm.meta_key='_qby_product_class' AND qby_cm.meta_value IN ('supplement','food')) AND (NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} qby_am WHERE qby_am.post_id={$product_id_sql} AND qby_am.meta_key='_qby_product_class' AND qby_am.meta_value='accessory') OR {$tool})";
}

/** One cached aggregate answers presence, including children; never sums duplicate products. */
function qby_beauty_presence() {
    $cached=get_transient('qby_beauty_presence_v2');if(is_array($cached)){return $cached;}
    global $wpdb;
    $visibility=wc_get_product_visibility_term_ids();$excluded=array_filter(array((int)($visibility['exclude-from-catalog']??0)));
    if(get_option('woocommerce_hide_out_of_stock_items')==='yes' && !empty($visibility['outofstock'])){$excluded[]=(int)$visibility['outofstock'];}
    $visible=$excluded?" AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} qby_vr WHERE qby_vr.object_id=p.ID AND qby_vr.term_taxonomy_id IN (".implode(',',array_map('intval',$excluded))."))":'';
    $root=get_term_by('slug','beauty','product_cat');if(!$root || is_wp_error($root)){return array();}
    $children=get_term_children($root->term_id,'product_cat');$category_ids=array_merge(array((int)$root->term_id),is_wp_error($children)?array():array_map('intval',$children));
    $category_scope=implode(',',$category_ids);
    $class_sql=qby_beauty_class_sql('p.ID');
    $rows=$wpdb->get_col("SELECT DISTINCT tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='product_cat' INNER JOIN {$wpdb->posts} p ON p.ID=tr.object_id AND p.post_type='product' AND p.post_status='publish' AND p.post_parent=0 AND p.post_password='' WHERE tt.term_id IN ({$category_scope}) AND {$class_sql} {$visible} LIMIT 1000");
    $present=array();foreach((array)$rows as $id){$present[(int)$id]=true;foreach(get_ancestors((int)$id,'product_cat') as $ancestor){$present[(int)$ancestor]=true;}}
    set_transient('qby_beauty_presence_v2',$present,5*MINUTE_IN_SECONDS);return $present;
}
function qby_category_has_catalogue($slug) {
    $term=get_term_by('slug',sanitize_key($slug),'product_cat');
    return $term && !is_wp_error($term) && !empty(qby_beauty_presence()[(int)$term->term_id]);
}


/** Explicit primary class always wins; fallback uses real category ancestry only. */
function qby_resolved_class($id) {
    $id=absint($id);if(!$id){return '';}
    if(get_post_type($id)==='product_variation'){$id=(int)wp_get_post_parent_id($id);}
    $class=qby_class($id);
    if($class==='cosmetic'){return 'beauty';}
    if(in_array($class,array('beauty','supplement','food','accessory'),true)){return $class;}
    $root=get_term_by('slug','beauty','product_cat');
    if(!$root || is_wp_error($root)){return '';}
    $terms=get_the_terms($id,'product_cat');
    if(!$terms || is_wp_error($terms)){return '';}
    foreach($terms as $term){
        if((int)$term->term_id===(int)$root->term_id || in_array((int)$root->term_id,array_map('intval',get_ancestors($term->term_id,'product_cat')),true)){return 'beauty';}
    }
    return '';
}
// Replace the older catalogue.php implementation with this shared wrapper when integrating.
function qby_is_cosmetic($id=0) {return qby_resolved_class($id?:get_queried_object_id())==='beauty';}
function qby_uses_beauty_ui($id=0) {
    $id=$id?:get_queried_object_id();$class=qby_resolved_class($id);
    return $class==='beauty' || ($class==='accessory' && qby_is_beauty_tool($id));
}
function qby_product_department($id) {
    if(!qby_uses_beauty_ui($id)){return '';}
    if(qby_resolved_class($id)==='accessory' && qby_is_beauty_tool($id)){return 'beauty-tools';}
    if(get_post_type($id)==='product_variation'){$id=(int)wp_get_post_parent_id($id);}
    $terms=get_the_terms($id,'product_cat');if(!$terms || is_wp_error($terms)){return '';}
    foreach($terms as $term){$department=qby_department_slug($term->slug);if($department){return $department;}}
    // Custom leaves below a recognised department also resolve without name inference.
    foreach($terms as $term){foreach(get_ancestors($term->term_id,'product_cat') as $ancestor){$parent=get_term($ancestor,'product_cat');if($parent && !is_wp_error($parent)){$department=qby_department_slug($parent->slug);if($department){return $department;}}}}
    return '';
}

/** Display schema reuses _qby_cosmetic_details. Unknown values stay blank. */
function qby_beauty_field_schema($department='') {
    $common=array(
        'size'=>array('en'=>'Pack size','ar'=>'حجم العبوة','type'=>'text'),
        'inci'=>array('en'=>'Full ingredients (INCI)','ar'=>'المكونات الكاملة (INCI)','type'=>'textarea'),
        'directions_en'=>array('en'=>'Directions — English','ar'=>'طريقة الاستخدام — الإنجليزية','type'=>'textarea'),
        'directions_ar'=>array('en'=>'Directions — Arabic','ar'=>'طريقة الاستخدام — العربية','type'=>'textarea'),
        'warnings_en'=>array('en'=>'Warnings — English','ar'=>'تنبيهات — الإنجليزية','type'=>'textarea'),
        'warnings_ar'=>array('en'=>'Warnings — Arabic','ar'=>'تنبيهات — العربية','type'=>'textarea'),
        'benefits_en'=>array('en'=>'Verified benefits — English','ar'=>'فوائد موثقة — الإنجليزية','type'=>'textarea'),
        'benefits_ar'=>array('en'=>'Verified benefits — Arabic','ar'=>'فوائد موثقة — العربية','type'=>'textarea'),
        'key_ingredients'=>array('en'=>'Verified key ingredients — English','ar'=>'المكونات الرئيسية الموثقة — الإنجليزية','type'=>'text'),
        'key_ingredients_ar'=>array('en'=>'Verified key ingredients — Arabic','ar'=>'المكونات الرئيسية الموثقة — العربية','type'=>'text'),
        'pao'=>array('en'=>'Period after opening, as labelled','ar'=>'مدة الاستخدام بعد الفتح كما على العبوة','type'=>'text'),
        'source_url'=>array('en'=>'Exact product / manufacturer source','ar'=>'مصدر المنتج أو الشركة المصنعة','type'=>'url'),
    );
    $extra=array(
        'skin-care'=>array('skin_type'=>array('Verified skin type','نوع البشرة الموثق'),'skin_concern'=>array('Labelled skin concern','احتياج البشرة المذكور'),'texture'=>array('Texture','القوام'),'finish'=>array('Finish','اللمسة النهائية'),'spf'=>array('SPF, only as labelled','عامل الحماية كما على العبوة')),
        'makeup'=>array('finish'=>array('Finish','اللمسة النهائية'),'coverage'=>array('Coverage','التغطية'),'undertone'=>array('Verified undertone','درجة البشرة التحتية الموثقة'),'texture'=>array('Texture','القوام'),'shade_notes'=>array('Verified shade notes; native variations control purchase','تفاصيل الدرجات الموثقة؛ الاختيار من خيارات المنتج')),
        'fragrance'=>array('concentration'=>array('Fragrance concentration','تركيز العطر'),'fragrance_family'=>array('Verified fragrance family','العائلة العطرية الموثقة'),'top_notes'=>array('Top notes','المكونات العليا'),'heart_notes'=>array('Heart notes','المكونات الوسطى'),'base_notes'=>array('Base notes','المكونات الأساسية')),
        'hair-care'=>array('hair_type'=>array('Verified hair type','نوع الشعر الموثق'),'hair_concern'=>array('Labelled hair concern','احتياج الشعر المذكور'),'texture'=>array('Texture','القوام')),
        'body-care'=>array('skin_type'=>array('Verified skin type','نوع البشرة الموثق'),'body_concern'=>array('Labelled body-care concern','احتياج العناية بالجسم المذكور'),'texture'=>array('Texture','القوام')),
        'beauty-tools'=>array('material'=>array('Material, as supplied','الخامة المذكورة'),'dimensions'=>array('Dimensions','الأبعاد'),'tool_care'=>array('Cleaning & care instructions','إرشادات التنظيف والعناية')),
    );
    $selected=$department!==''?array($extra[qby_department_slug($department)]??array()):array_values($extra);
    foreach($selected as $fields){foreach($fields as $key=>$labels){
        $common[$key]=array('en'=>$labels[0],'ar'=>$labels[1],'type'=>'text');
        $common[$key.'_ar']=array('en'=>$labels[0].' — Arabic','ar'=>$labels[1].' — العربية','type'=>'text');
    }}
    return $common;
}
function qby_sanitize_beauty_details($input) {
    $out=array();if(!is_array($input)){return $out;}
    foreach(qby_beauty_field_schema() as $key=>$field){
        if(!isset($input[$key]) || !is_scalar($input[$key])){continue;}
        $raw=(string)$input[$key];$max=$key==='inci'?16000:($field['type']==='textarea'?4000:1000);
        $raw=function_exists('mb_substr')?mb_substr($raw,0,$max):substr($raw,0,$max);
        $value=$field['type']==='url'?esc_url_raw($raw):sanitize_textarea_field($raw);
        if(str_starts_with($key,'benefits_')){$value=implode("\n",array_slice(array_values(array_filter(array_map('trim',preg_split('/\R/u',$value)))),0,4));}
        // A deliberate blank must also override bundled seed data after an editor save.
        $out[$key]=$value;
    }
    return $out;
}

/** Stable Woo attributes, when present; never create attributes or guess values here. */
function qby_beauty_facets() {
    return array('care'=>'pa_beauty-concern','skin_type'=>'pa_skin-type','hair_type'=>'pa_hair-type','finish'=>'pa_finish','coverage'=>'pa_coverage','shade'=>'pa_shade','concentration'=>'pa_concentration');
}

/**
 * SQL scopes apply BEFORE pagination and total counting. No PHP post-filter of a first page.
 * Inputs: department/type (registered category slugs), brand, care/other existing Woo
 * attribute facet slugs, collection=all|new|bestsellers|offers, page, per_page, orderby.
 * Returns bounded IDs + Woo product objects, exact matched total, pages, page, per_page.
 * Native Woo lookup prices are used only to decide shelf eligibility, never rendered as prices.
 */
function qby_beauty_query($input=array()) {
    $input=is_array($input)?$input:array();
    // Malformed array query parameters are ignored rather than passed to string APIs.
    $input=array_filter($input,'is_scalar');$known=qby_beauty_terms();
    $department=sanitize_key($input['department']??'beauty');if(!isset($known[$department])){$department='beauty';}
    $type=sanitize_key($input['type']??'');if($type && isset($known[$type]) && ($department==='beauty' || qby_department_slug($type)===qby_department_slug($department))){$department=$type;}
    $per_page=max(1,min(48,absint($input['per_page']??24)));$page=max(1,min(10000,absint($input['page']??1)));
    $collection=sanitize_key($input['collection']??'all');if(!in_array($collection,array('all','new','bestsellers','offers'),true)){$collection='all';}
    $tax=array('relation'=>'AND',array('taxonomy'=>'product_cat','field'=>'slug','terms'=>array($department),'include_children'=>true));
    $visibility=wc_get_product_visibility_term_ids();$excluded=array_filter(array((int)($visibility['exclude-from-catalog']??0)));
    if(get_option('woocommerce_hide_out_of_stock_items')==='yes' && !empty($visibility['outofstock'])){$excluded[]=(int)$visibility['outofstock'];}
    if($excluded){$tax[]=array('taxonomy'=>'product_visibility','field'=>'term_taxonomy_id','terms'=>$excluded,'operator'=>'NOT IN');}
    $brand=sanitize_title($input['brand']??'');if($brand){$tax[]=array('taxonomy'=>'product_brand','field'=>'slug','terms'=>array($brand));}
    $facet_terms=array();$care=sanitize_key($input['care']??'');
    foreach(qby_beauty_facets() as $field=>$taxonomy){
        if($field==='care'){continue;}$value=sanitize_title($input[$field]??'');if(!$value){continue;}
        // An unsupported chosen facet yields an empty result, never silently broadens it.
        if(!taxonomy_exists($taxonomy)){$facet_terms[]=false;continue;}
        $tax[]=array('taxonomy'=>$taxonomy,'field'=>'slug','terms'=>array($value));
    }
    $args=array('post_type'=>'product','post_status'=>'publish','has_password'=>false,'posts_per_page'=>$per_page,'paged'=>$page,'fields'=>'ids','ignore_sticky_posts'=>true,'no_found_rows'=>false,'tax_query'=>$tax,'orderby'=>array('menu_order'=>'ASC','title'=>'ASC'),'qby_collection_scope'=>true);
    if(in_array(false,$facet_terms,true)){$args['post__in']=array(0);}
    $orderby=sanitize_key($input['orderby']??'menu_order');
    if($orderby==='date'){$args['orderby']=array('date'=>'DESC','ID'=>'DESC');}
    if(in_array($orderby,array('popularity','rating','price','price-desc'),true)){$args['qby_lookup_order']=$orderby;}
    if($collection==='bestsellers'){$args['qby_lookup_order']='popularity';}
    $args['qby_collection']=$collection;
    $stock=sanitize_key($input['stock']??'');$args['qby_stock']=in_array($stock,array('instock','outofstock','onbackorder'),true)?$stock:'';
    if(!empty($input['search']) && is_scalar($input['search'])){$args['s']=sanitize_text_field($input['search']);}
    $scope=static function($clauses,$query)use($care){
        if(!$query->get('qby_collection_scope')){return $clauses;}
        global $wpdb;$posts=$wpdb->posts;$lookup=$wpdb->prefix.'wc_product_meta_lookup';
        $clauses['where'].=" AND {$posts}.post_parent = 0";
        // Same department/class rule as navigation presence; Beauty tools remain accessories.
        $clauses['where'].=' AND ('.qby_beauty_class_sql($posts.'.ID').')';
        $collection=$query->get('qby_collection');$order=(string)$query->get('qby_lookup_order');$stock=$query->get('qby_stock');
        if($collection!=='all' || $order || $stock){$clauses['join'].=" LEFT JOIN {$lookup} qby_lookup ON qby_lookup.product_id={$posts}.ID";}
        if($stock){$clauses['where'].=$wpdb->prepare(' AND qby_lookup.stock_status=%s',$stock);}
        if($collection==='offers'){$clauses['where'].=' AND qby_lookup.onsale = 1';}
        if($collection==='bestsellers'){$clauses['where'].=' AND qby_lookup.total_sales > 0';}
        if($collection==='new'){
            $window=function_exists('qil_inventory_window')?(int)qil_inventory_window('new'):30*DAY_IN_SECONDS;$now=time();
            if($window<=0){$clauses['where'].=' AND 1=0';}
            else {
                $first="COALESCE(NULLIF(CAST((SELECT qby_pub.meta_value FROM {$wpdb->postmeta} qby_pub WHERE qby_pub.post_id={$posts}.ID AND qby_pub.meta_key='_qil_first_published_at' ORDER BY qby_pub.meta_id ASC LIMIT 1) AS UNSIGNED),0), TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', {$posts}.post_date_gmt))";
                $clauses['where'].=$wpdb->prepare(" AND {$first} > %d AND {$first} <= %d AND qby_lookup.stock_status='instock' AND qby_lookup.min_price > 0 AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} qby_image WHERE qby_image.post_id={$posts}.ID AND qby_image.meta_key='_thumbnail_id' AND CAST(qby_image.meta_value AS UNSIGNED)>0)",$now-$window,$now);
            }
        }
        if($care && $care!=='all'){
            $ids=array();foreach(qby_seed() as $id=>$record){if(qby_seed_record($id) && in_array($care,$record['concerns']??array(),true)){$ids[]=(int)$id;}}
            $parts=array($ids?"{$posts}.ID IN (".implode(',',$ids).')':'1=0');
            $facet=qby_beauty_facets()['care'];
            if(taxonomy_exists($facet)){$parts[]=$wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->term_relationships} qby_fr INNER JOIN {$wpdb->term_taxonomy} qby_ft ON qby_ft.term_taxonomy_id=qby_fr.term_taxonomy_id INNER JOIN {$wpdb->terms} qby_term ON qby_term.term_id=qby_ft.term_id WHERE qby_fr.object_id={$posts}.ID AND qby_ft.taxonomy=%s AND qby_term.slug=%s)",$facet,$care);}
            $clauses['where'].=' AND ('.implode(' OR ',$parts).')';
        }
        $orders=array('popularity'=>'qby_lookup.total_sales DESC','rating'=>'qby_lookup.average_rating DESC','price'=>'qby_lookup.min_price ASC','price-desc'=>'qby_lookup.max_price DESC');
        if(isset($orders[$order])){$clauses['orderby']=$orders[$order].", {$posts}.ID DESC";}
        return $clauses;
    };
    add_filter('posts_clauses',$scope,30,2);
    try{$query=new WP_Query($args);}finally{remove_filter('posts_clauses',$scope,30);}
    $ids=array_map('intval',$query->posts);
    // Prime only this bounded page so native cards avoid one post/meta query per product.
    if($ids && function_exists('_prime_post_caches')){_prime_post_caches($ids,true,true);}
    $products=array_values(array_filter(array_map('wc_get_product',$ids)));
    return array('ids'=>$ids,'products'=>$products,'total'=>(int)$query->found_posts,'pages'=>(int)$query->max_num_pages,'page'=>$page,'per_page'=>$per_page,'department'=>$department);
}
