<?php
defined('ABSPATH') || exit;
add_action('wp_enqueue_scripts', static function(){
    if(is_admin() || isset($_GET['elementor-preview'])){return;}
    wp_enqueue_style('qimia-beauty',QBY_URL.'assets/beauty.min.css',array(),QBY_VERSION);
    wp_enqueue_script('qimia-beauty',QBY_URL.'assets/beauty.min.js',array(),QBY_VERSION,true);
    wp_script_add_data('qimia-beauty','strategy','defer');
    // Only attach to an active native card owner; Woo fallback stays styled and functional without Lab.
    if(wp_script_is('qimia-intelligence-lab','enqueued')){
        $styles=array('qimia-beauty');if(wp_style_is('qimia-intelligence-lab','enqueued')){$styles[]='qimia-intelligence-lab';}
        wp_enqueue_style('qimia-beauty-native-cards',QBY_URL.'assets/beauty-native-cards.min.css',$styles,QBY_VERSION);
        wp_enqueue_script('qimia-beauty-native-cards',QBY_URL.'assets/beauty-native-cards.min.js',array('qimia-intelligence-lab','qimia-beauty'),QBY_VERSION,true);
        wp_script_add_data('qimia-beauty-native-cards','strategy','defer');
    }
    if (qby_stage_allowed() && (is_front_page() || is_page('beauty') || (is_product() && qby_uses_beauty_ui()))) {
        wp_enqueue_style('qimia-beauty-experience',QBY_URL.'assets/experience.min.css',array('qimia-beauty'),QBY_VERSION);
        wp_enqueue_script('qimia-beauty-experience',QBY_URL.'assets/experience.min.js',array('qimia-beauty'),QBY_VERSION,true);
        wp_script_add_data('qimia-beauty-experience','strategy','defer');
    }
    wp_localize_script('qimia-beauty','QBYNavigation',array('brandCountLabel'=>qby_t('brands','علامات'),'categoryLabel'=>qby_t('Explore categories','اكتشف الفئات'),'searchLinks'=>array(array('url'=>qby_browse_url('hair-care'),'label'=>qby_t('Hair care','العناية بالشعر')),array('url'=>qby_term_url('hair-nail-skin-health'),'label'=>qby_t('Hair supplements','مكملات الشعر')))));
},110);
add_filter('body_class',static function($classes){$classes[]='qby-enabled';if(is_page(array('beauty','brands','all-categories'))){$classes[]='qby-hub';}if(is_page('beauty')){$classes[]='qby-beauty-page';}if(is_product() && qby_uses_beauty_ui()){$classes[]='qby-cosmetic';}return $classes;});
add_shortcode('qimia_beauty','qby_beauty_page');
add_shortcode('qimia_brands','qby_brands_page');
add_filter('the_content',static function($content){return is_page('all-categories') && in_the_loop() && is_main_query() ? qby_categories_page() : $content;},30);
/** Native QIL cards with functional, server-rendered Woo commerce fallback. */
function qby_cards($products){
    if(!$products){return '';}
    $ids=array_map(static function($p){return $p->get_id();},$products);
    $previous=$GLOBALS['qby_qil_manual_shelf']??false;
    $GLOBALS['qby_qil_manual_shelf']=true;
    try{$fallback=do_shortcode('[products ids="'.implode(',',$ids).'" columns="4" orderby="post__in" limit="'.count($ids).'"]');}
    finally{$GLOBALS['qby_qil_manual_shelf']=$previous;}
    return function_exists('qby_qil_shelf')?qby_qil_shelf($products,$fallback):$fallback;
}
function qby_product_type_label($id){
    $d=qby_details($id);$slugs=$d['categories']??array();
    if(!$slugs){$terms=wp_get_object_terms($id,'product_cat');if(!is_wp_error($terms)){foreach($terms as $t){if(isset(qby_terms()[$t->slug])){$slugs[]=$t->slug;}}}}
    foreach($slugs as $slug){if(str_ends_with($slug,'-sets')){return qby_label($slug);}}
    foreach($slugs as $slug){if(isset(qby_terms()[$slug]) && qby_terms()[$slug][2]!=='' && qby_terms()[$slug][2]!=='beauty'){return qby_label($slug);}}
    return $slugs?qby_label(reset($slugs)):qby_t('Beauty & personal care','الجمال والعناية الشخصية');
}
/** Small original interface illustrations; no icon font or image request. */
function qby_department_icon($slug){
    $paths=array(
        'sun-care'=>'<circle cx="48" cy="44" r="17"/><path d="M48 15v7M48 66v7M19 44h7M70 44h7M27 23l5 5M64 60l5 5M27 65l5-5M64 28l5-5"/>',
        'makeup'=>'<path d="M23 40h18v31H23zM26 40V24l12-7v23M21 71h22M56 42h17v29H56zM60 42V29h9v13M58 22h13"/><path d="M27 49h10M60 51h9"/>',
        'skin-care'=>'<path d="M29 34h38v40H29zM37 34V23h22v11M44 23V12h8v11M35 46h26M38 56h20"/><path d="M71 17c9 11 9 16 0 16s-9-5 0-16Z"/>',
        'hair-care'=>'<path d="M20 32h25v43H20zM25 32V20h15v12M29 20v-8h17M25 45h15M57 32h19l-3 43H60zM59 24h15v8M63 44h7"/>',
        'fragrance'=>'<path d="M23 35h50v37H23zM34 35V25h28v10M37 25V13h22v12M34 46h28v17H34zM27 40h42"/><path d="m72 13 4 4-4 4-4-4z"/>',
        'body-care'=>'<path d="M24 38h29v37H24zM31 38V26h15v12M36 26V14h21v6H42M30 51h17M62 44h15l-3 31h-9zM64 37h11v7"/>',
        'beauty-tools'=>'<path d="M30 35v40h8V35M27 35h14l5-20c-7-5-16-5-23 0l4 20ZM61 32v43h7V32M56 32h17l-2-18H58z"/><path d="M27 26h14M60 25h9"/>');
    return '<svg viewBox="0 0 96 88" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">'.($paths[$slug]??$paths['skin-care']).'</svg>';
}
function qby_department_cards($compact=false){
    $html='<div class="qby-department-grid'.($compact?' qby-department-grid--compact':'').'">';$n=0;
    foreach(qby_beauty_map() as $slug=>$d){$n++;$html.='<article class="qby-department-card qby-department--'.esc_attr($slug).'"><a class="qby-department-main" href="'.esc_url(qby_browse_url($slug)).'"><span class="qby-category-art">'.qby_department_icon($slug).'</span><span class="qby-department-text"><small>0'.$n.'</small><strong>'.esc_html(qby_t($d['en'],$d['ar'])).'</strong></span><span class="qby-arrow" aria-hidden="true">'.(function_exists('qbx_icon')?qbx_icon('outward'):'↗').'</span></a>';
        if(!$compact){$html.='<div class="qby-department-links">';foreach(array_slice($d['children'],0,3,true) as $child=>$label){$html.=qby_link(qby_browse_url($child),qby_t($label[0],$label[1]));}$html.='</div>';}$html.='</article>';
    }return $html.'</div>';
}
function qby_collection_url($changes=array()){
    $allowed=array('department','collection','care','brand','stock','qby_page');$query=array();foreach($allowed as $key){if(isset($_GET[$key]) && is_scalar($_GET[$key])){$query[$key]=$key==='brand'?sanitize_title(wp_unslash($_GET[$key])):sanitize_key(wp_unslash($_GET[$key]));}}
    $query=array_merge($query,$changes);return add_query_arg($query,qby_url('/beauty/')).'#qby-shop';
}
function qby_beauty_page(){
    if (function_exists('qbx_page')) { return qbx_page(); }
    $department=sanitize_key(qby_query_value('department','all'));if($department!=='all' && !isset(qby_terms()[$department])){$department='all';}
    $collection=sanitize_key(qby_query_value('collection','all'));if(!in_array($collection,array('all','new','bestsellers','offers'),true)){$collection='all';}
    $care=sanitize_key(qby_query_value('care','all'));if(!isset(qby_concerns()[$care])){$care='all';}
    $brand=sanitize_title(qby_query_value('brand',''));$stock=sanitize_key(qby_query_value('stock',''));
    $result=qby_beauty_query(array('department'=>$department,'collection'=>$collection,'care'=>$care,'brand'=>$brand,'stock'=>$stock,'page'=>max(1,(int)qby_query_value('qby_page','1')),'per_page'=>12));
    $products=$result['products'];
    ob_start(); ?>
    <div class="qby qby-beauty" dir="<?php echo qby_ar()?'rtl':'ltr'; ?>">
    <div class="qby-breadcrumb"><?php echo qby_link(qby_url('/'),qby_t('Qimia','كيميا')); ?><span>/</span><?php echo esc_html(qby_t('Beauty','الجمال والعناية')); ?></div>
    <section class="qby-hero" aria-labelledby="qby-title"><div class="qby-hero-copy"><span class="qby-eyebrow"><?php echo esc_html(qby_t('QIMIA / BEAUTY','كيميا / الجمال')); ?></span><h1 id="qby-title"><?php echo qby_t('Every side<br>of <em>you.</em>','جمالك.<br><em>بكل تفاصيله.</em>'); ?></h1><p><?php echo esc_html(qby_t('Makeup. Skin. Hair. Fragrance. Explore your beauty world, with clear product details and Qimia intelligence at every step.','مكياج وبشرة وشعر وعطور. اكتشف عالم جمالك، مع تفاصيل واضحة وذكاء كيميا في كل خطوة.')); ?></p><div class="qby-actions"><?php echo qby_link('#qby-departments',qby_t('Explore Beauty','اكتشف الجمال'),'qby-button');echo qby_link('#qby-shop',qby_t('Shop the collection ↗','تسوق المجموعة ↗'),'qby-text-link'); ?></div><div class="qby-hero-note"><span class="qby-dot"></span><?php echo esc_html(qby_t('One Qimia. Your complete routine.','كيميا واحدة. روتينك المتكامل.')); ?></div></div><div class="qby-hero-art" data-qby-hero-art><img src="<?php echo esc_url(QBY_URL.'assets/qimia-beauty-editorial-960.webp'); ?>" srcset="<?php echo esc_url(QBY_URL.'assets/qimia-beauty-editorial-640.webp'); ?> 640w, <?php echo esc_url(QBY_URL.'assets/qimia-beauty-editorial-960.webp'); ?> 960w, <?php echo esc_url(QBY_URL.'assets/qimia-beauty-editorial-1440.webp'); ?> 1440w" sizes="(max-width:700px) 100vw, 55vw" alt="" width="960" height="640" fetchpriority="high" decoding="async"><div class="qby-art-caption"><span><?php echo esc_html(qby_t('BEAUTY × WELLNESS','الجمال × العافية')); ?></span><strong><?php echo esc_html(qby_t('Made for your everyday.','لعنايتك كل يوم.')); ?></strong></div></div></section>
    <div class="qby-benefit-strip"><span><?php echo esc_html(qby_t('Ingredients, made clear','مكونات واضحة')); ?></span><span><?php echo esc_html(qby_t('Compare with Qimia AI','قارن مع ذكاء كيميا')); ?></span><span><?php echo esc_html(qby_t('Your account. Your basket.','حسابك وسلتك في مكان واحد')); ?></span></div>
    <section class="qby-section" id="qby-departments"><div class="qby-section-head"><div><span class="qby-eyebrow"><?php echo esc_html(qby_t('A WORLD OF BEAUTY','عالم من الجمال')); ?></span><h2><?php echo esc_html(qby_t('Find your next ritual.','اكتشف روتينك القادم.')); ?></h2></div><p class="qby-section-description"><?php echo esc_html(qby_t('Six departments. One connected experience.','ستة أقسام. تجربة واحدة متكاملة.')); ?></p></div><?php echo qby_department_cards(); ?></section>
    <section class="qby-section" id="qby-shop" aria-labelledby="qby-shop-title"><div class="qby-section-head"><div><span class="qby-eyebrow"><?php echo esc_html(qby_t('THE QIMIA EDIT','مختارات كيميا')); ?></span><h2 id="qby-shop-title"><?php echo esc_html($department==='all'?qby_t('Your beauty shelf.','اختياراتك للجمال.'):qby_label($department)); ?></h2></div><span class="qby-result-count"><?php echo esc_html($result['total'].' '.qby_t((int)$result['total']===1?'product':'products',(int)$result['total']===1?'منتج':'منتجات')); ?></span></div>
    <form class="qby-collection-filters" method="get" action="<?php echo esc_url(qby_url('/beauty/')); ?>#qby-shop"><label><?php echo esc_html(qby_t('Department','القسم')); ?><select name="department"><option value="all"><?php echo esc_html(qby_t('All Beauty','كل الجمال')); ?></option><?php foreach(qby_beauty_map() as $slug=>$d){echo '<optgroup label="'.esc_attr(qby_t($d['en'],$d['ar'])).'"><option value="'.esc_attr($slug).'" '.selected($department,$slug,false).'>'.esc_html(qby_t('All ','كل ').qby_t($d['en'],$d['ar'])).'</option>';foreach($d['children'] as $child=>$label){echo '<option value="'.esc_attr($child).'" '.selected($department,$child,false).'>'.esc_html(qby_t($label[0],$label[1])).'</option>';}echo '</optgroup>';} ?></select></label><label><?php echo esc_html(qby_t('Brand','العلامة')); ?><select name="brand"><option value=""><?php echo esc_html(qby_t('All brands','كل العلامات')); ?></option><?php foreach(qby_brand_directory() as $b){if(empty($b['departments'])){continue;}echo '<option value="'.esc_attr($b['slug']).'" '.selected($brand,$b['slug'],false).'>'.esc_html($b['name']).'</option>';} ?></select></label><label><?php echo esc_html(qby_t('Availability','التوفر')); ?><select name="stock"><option value=""><?php echo esc_html(qby_t('All availability','كل الحالات')); ?></option><option value="instock" <?php selected($stock,'instock'); ?>><?php echo esc_html(qby_t('In stock','متوفر')); ?></option></select></label><input type="hidden" name="collection" value="<?php echo esc_attr($collection); ?>"><input type="hidden" name="care" value="<?php echo esc_attr($care); ?>"><button type="submit" class="qby-button"><?php echo esc_html(qby_t('Find products','اعثر على المنتجات')); ?></button><?php echo qby_link(qby_url('/beauty/').'#qby-shop',qby_t('Reset','إعادة تعيين'),'qby-text-link'); ?></form>
    <div class="qby-collection-row"><nav class="qby-shelves" aria-label="<?php echo esc_attr(qby_t('Collections','المجموعات')); ?>"><?php foreach(array('all'=>array('All products','كل المنتجات'),'new'=>array('New arrivals','وصل حديثاً'),'bestsellers'=>array('Best sellers','الأكثر مبيعاً'),'offers'=>array('Offers','العروض')) as $key=>$label){echo '<a href="'.esc_url(qby_collection_url(array('collection'=>$key,'qby_page'=>1))).'"'.($collection===$key?' aria-current="true"':'').'>'.esc_html(qby_t($label[0],$label[1])).'</a>';} ?></nav></div>
    <?php if($products){echo qby_cards($products);}else{echo '<div class="qby-empty"><span class="qby-eyebrow">'.esc_html(qby_t('QIMIA / BEAUTY','كيميا / الجمال')).'</span><h3>'.esc_html(qby_t('Your next discovery starts here.','اكتشافك القادم يبدأ هنا.')).'</h3><p>'.esc_html(qby_t('No products are published for this selection yet. Explore the available catalogue or choose another department.','لا توجد منتجات منشورة لهذا الاختيار بعد. تصفح الكتالوج الحالي أو اختر قسماً آخر.')).'</p>'.qby_link(qby_url('/beauty/').'#qby-shop',qby_t('Explore all Beauty','اكتشف كل الجمال'),'qby-button').'</div>';}
    if($result['pages']>1){echo '<nav class="qby-pagination" aria-label="'.esc_attr(qby_t('Product pages','صفحات المنتجات')).'">';for($page=max(1,$result['page']-2);$page<=min($result['pages'],$result['page']+2);$page++){echo '<a href="'.esc_url(qby_collection_url(array('qby_page'=>$page))).'"'.($page===$result['page']?' aria-current="page"':'').'>'.$page.'</a>';}echo '</nav>';}
    ?></section>
    <?php echo qby_beauty_guides(); ?>
    <section class="qby-inside qby-section"><div><span class="qby-eyebrow"><?php echo esc_html(qby_t('BEAUTY + WELLNESS','الجمال + العافية')); ?></span><h2><?php echo qby_t('Your routine.<br>Inside & outside.','روتينك.<br>من الداخل والخارج.'); ?></h2><p><?php echo esc_html(qby_t('Explore personal care and beauty supplements in one place. Each product keeps its own ingredients, purpose and directions.','اكتشف العناية الشخصية ومكملات الجمال في مكان واحد. لكل منتج مكوناته واستخدامه وإرشاداته.')); ?></p></div><div class="qby-routine-links"><?php echo qby_link(qby_browse_url('skin-care'),qby_t('01 / Skin care →','٠١ / العناية بالبشرة ←'));echo qby_link(qby_term_url('collagens'),qby_t('02 / Collagen supplements →','٠٢ / مكملات الكولاجين ←'));echo qby_link(qby_browse_url('hair-care'),qby_t('03 / Hair care →','٠٣ / العناية بالشعر ←'));echo qby_link(qby_term_url('hair-nail-skin-health'),qby_t('04 / Hair, skin & nails supplements →','٠٤ / مكملات الشعر والبشرة والأظافر ←')); ?></div></section>
    <section class="qby-brand-feature"><div><span class="qby-eyebrow"><?php echo esc_html(qby_t('THE BRANDS YOU KNOW','علامات تعرفها')); ?></span><h2><?php echo esc_html(qby_t('Find your favourites.','اعثر على علاماتك المفضلة.')); ?></h2><p><?php echo esc_html(qby_t('Discover every Qimia brand in one searchable directory.','اكتشف كل علامات كيميا في دليل واحد يسهل البحث فيه.')); ?></p></div><?php echo qby_link(qby_url('/brands/'),qby_t('Explore all brands ↗','اكتشف كل العلامات ↗'),'qby-button'); ?></section>
    <section class="qby-ai-callout qby-section"><div><span class="qby-eyebrow"><?php echo esc_html(qby_t('QIMIA INTELLIGENCE','ذكاء كيميا')); ?></span><h2><?php echo esc_html(qby_t('A little clarity. A better choice.','تفاصيل أوضح. اختيار أفضل.')); ?></h2><p><?php echo esc_html(qby_t('Compare ingredients, understand a formula or ask about a shade. Your Qimia assistant uses the details available for each product.','قارن المكونات وافهم التركيبة أو اسأل عن درجة اللون. يستخدم مساعد كيميا التفاصيل المتوفرة لكل منتج.')); ?></p></div><button type="button" class="qby-button" data-qimia-ai-open><?php echo esc_html(qby_t('Ask Qimia AI','اسأل ذكاء كيميا')); ?></button></section></div>
    <?php return ob_get_clean();
}
function qby_beauty_guides(){
    $guides=array(
    array('01','Skin, understood.','افهم بشرتك.','Compare product type, skin type, texture and the complete ingredient list. Follow the exact label’s usage and sun-care directions.','قارن نوع المنتج ونوع البشرة والقوام وقائمة المكونات الكاملة. اتبع إرشادات الاستخدام والحماية من الشمس على العبوة.','skin-care','Ingredients & routine','المكونات والروتين'),
    array('02','Your shade. Your finish.','درجتك. لمستك.','Check the shade name, undertone, coverage and finish on the exact variant. Select a shade on the product page to see its own price and availability.','راجع اسم الدرجة والأندرتون والتغطية واللمسة النهائية للخيار المحدد. اختر الدرجة في صفحة المنتج لمعرفة سعرها وتوفرها.','makeup','Shade & coverage','الدرجة والتغطية'),
    array('03','Find your fragrance.','اكتشف عطرك.','Explore the listed notes, concentration and bottle size. Compare the product’s own description; scent experience and wear time can vary.','اكتشف النوتات المدرجة والتركيز وحجم العبوة. قارن وصف كل منتج؛ فقد تختلف تجربة الرائحة ومدة ثباتها.','fragrance','Notes & concentration','النوتات والتركيز'));
    $html='<section class="qby-section qby-guides"><div class="qby-section-head"><div><span class="qby-eyebrow">'.esc_html(qby_t('THE BEAUTY NOTES','دليل الجمال')).'</span><h2>'.esc_html(qby_t('Details that make a difference.','تفاصيل تصنع الفرق.')).'</h2></div></div><div class="qby-guide-grid">';
    foreach($guides as $g){$html.='<article><span class="qby-guide-number">'.$g[0].'</span><h3>'.esc_html(qby_t($g[1],$g[2])).'</h3><p>'.esc_html(qby_t($g[3],$g[4])).'</p>'.qby_link(qby_browse_url($g[5]),qby_t($g[6],$g[7]).' ↗','qby-text-link').'</article>';}
    return $html.'</div></section>';
}
/** Bounded native sales order; never invent a best-seller claim for unsold products. */
function qby_home_beauty_edit(){
    $selection=qby_beauty_query(array('department'=>'beauty','orderby'=>'popularity','per_page'=>3));
    $products=$selection['products'];$bestsellers=count($products)===3;
    foreach($products as $product){if((int)$product->get_total_sales()<=0){$bestsellers=false;}}
    return array('products'=>$products,'bestsellers'=>$bestsellers);
}
// WooCommerce generates escaped attachment markup. Preserve its responsive image attributes.
add_action('qil_home_before_flash',static function(){
    if (function_exists('qbx_home')) { qbx_home(); return; }
    $edit=qby_home_beauty_edit();
    ?><section id="qby-home-beauty" class="qby qby-home qil-section" dir="<?php echo qby_ar()?'rtl':'ltr'; ?>"><div class="qil-container"><div class="qby-section-head"><div><span class="qby-eyebrow"><?php echo esc_html(qby_t('QIMIA / BEAUTY','كيميا / الجمال')); ?></span><h2><?php echo esc_html(qby_t('Beauty belongs here.','للجمال مكان هنا.')); ?></h2></div><?php echo qby_link(qby_url('/beauty/'),qby_t('Explore all Beauty ↗','اكتشف كل الجمال ↗'),'qby-text-link'); ?></div><div class="qby-home-shell"><div class="qby-home-copy"><span class="qby-eyebrow"><?php echo esc_html(qby_t('BEAUTY MEETS WELLNESS','يلتقي الجمال بالعافية')); ?></span><h2><?php echo qby_t('More ways<br>to be <em>you.</em>','جمالك.<br>على <em>طريقتك.</em>'); ?></h2><p><?php echo esc_html(qby_t('Makeup, skin, hair, fragrance and everyday care. Your beauty world, connected to the Qimia you know.','المكياج والبشرة والشعر والعطور والعناية اليومية. عالم جمالك، ضمن تجربة كيميا التي تعرفها.')); ?></p><?php echo qby_link(qby_url('/beauty/'),qby_t('Discover Qimia Beauty','اكتشف جمال كيميا'),'qby-button'); ?></div><div class="qby-home-art" data-qby-scroll-scene><span class="qby-home-edit-title"><?php echo esc_html($edit['bestsellers']?qby_t('THE BEAUTY BEST SELLERS','الأكثر مبيعاً في الجمال'):qby_t('THE BEAUTY EDIT','مختارات الجمال')); ?></span><div class="qby-home-products"><?php foreach($edit['products'] as $product): $stock=$product->get_stock_status();if(!in_array($stock,array('instock','outofstock','onbackorder'),true)){$stock='unknown';}if($stock==='instock' && (!$product->is_in_stock() || !$product->has_enough_stock(1))){$stock='outofstock';} ?><a class="qby-home-product" href="<?php echo esc_url(function_exists('qil_localized_url')?qil_localized_url(get_permalink($product->get_id()),qby_ar()):get_permalink($product->get_id())); ?>"><span class="qby-home-product-visual"><?php echo $product->get_image('woocommerce_single',array('loading'=>'lazy','decoding'=>'async','sizes'=>'(max-width:700px) 30vw, 220px','alt'=>'')); ?></span><span class="qby-home-product-info"><small><?php echo esc_html(qby_product_type_label($product->get_id())); ?></small><strong><?php echo esc_html($product->get_name()); ?></strong><span class="qby-home-stock" data-qil-beauty-stock-product="<?php echo (int)$product->get_id(); ?>" data-qil-beauty-stock-checked-at="<?php echo (int)time(); ?>" data-stock="<?php echo esc_attr($stock); ?>"><?php echo esc_html($stock==='outofstock'?qby_t('Out of stock','غير متوفر'):($stock==='onbackorder'?qby_t('On backorder','متاح بالطلب المسبق'):($stock==='instock'?qby_t('In stock','متوفر'):qby_t('Check availability','تحقق من التوفر')))); ?></span></span></a><?php endforeach; ?></div></div></div><?php echo qby_department_cards(true); ?><div class="qby-home-wellness"><?php echo qby_link(qby_term_url('hair-nail-skin-health'),qby_t('Complete your routine · Beauty supplements ↗','أكمل روتينك · مكملات الجمال ↗'));echo qby_link(qby_url('/brands/'),qby_t('Meet the brands ↗','اكتشف العلامات ↗')); ?></div></div></section><?php
},5);
function qby_brand_directory() {
    $cached=get_transient('qby_brand_groups');if(is_array($cached) && (!$cached || array_key_exists('departments',reset($cached)))){return $cached;}
    $brands=get_terms(array('taxonomy'=>'product_brand','hide_empty'=>false,'orderby'=>'name'));if(is_wp_error($brands)){return array();}
    $categories=get_terms(array('taxonomy'=>'product_cat','hide_empty'=>false));if(is_wp_error($categories)){return array();}
    // Resolve all category ancestry from one primed taxonomy read, instead of querying each group slug.
    $by_id=array();$by_slug=array();foreach($categories as $term){$by_id[(int)$term->term_id]=$term;$by_slug[$term->slug]=(int)$term->term_id;}
    $roots=array();foreach(qby_groups() as $key=>$group){$roots[$key]=$group[2];}
    foreach(qby_beauty_map() as $slug=>$department){$roots['dept_'.str_replace('-','_',$slug)]=array($slug);}
    $roots['supplements']=array_merge(qby_groups()['sports'][2],qby_groups()['wellness'][2],qby_groups()['inside'][2]);
    $maps=array();foreach($roots as $key=>$slugs){
        $root_ids=array_values(array_intersect_key($by_slug,array_flip($slugs)));$maps[$key]=array();
        foreach($categories as $term){$id=(int)$term->term_id;$seen=array();while($id && isset($by_id[$id]) && !isset($seen[$id])){if(in_array($id,$root_ids,true)){$maps[$key][]=(int)$term->term_id;break;}$seen[$id]=true;$id=(int)$by_id[$id]->parent;}}
    }
    global $wpdb;$select=array();foreach($maps as $key=>$ids){$select[]='MAX(ct.term_id IN ('.implode(',',array_merge(array(0),$ids)).')) AS qby_'.sanitize_key($key);}
    $visibility=wc_get_product_visibility_term_ids();$excluded=array_filter(array((int)($visibility['exclude-from-catalog']??0)));
    if(get_option('woocommerce_hide_out_of_stock_items')==='yes' && !empty($visibility['outofstock'])){$excluded[]=(int)$visibility['outofstock'];}
    $visible=$excluded?" AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} qby_bv WHERE qby_bv.object_id=p.ID AND qby_bv.term_taxonomy_id IN (".implode(',',array_map('intval',$excluded))."))":'';
    $rows=$wpdb->get_results("SELECT bt.term_id AS brand_id, ".implode(',',$select)." FROM {$wpdb->term_relationships} br INNER JOIN {$wpdb->term_taxonomy} bt ON bt.term_taxonomy_id=br.term_taxonomy_id AND bt.taxonomy='product_brand' INNER JOIN {$wpdb->posts} p ON p.ID=br.object_id AND p.post_type='product' AND p.post_status='publish' AND p.post_password='' INNER JOIN {$wpdb->term_relationships} cr ON cr.object_id=p.ID INNER JOIN {$wpdb->term_taxonomy} ct ON ct.term_taxonomy_id=cr.term_taxonomy_id AND ct.taxonomy='product_cat' WHERE 1=1 {$visible} GROUP BY bt.term_id");
    $memberships=array();foreach((array)$rows as $row){foreach($maps as $key=>$ids){if(!empty($row->{'qby_'.$key})){$memberships[(int)$row->brand_id][$key]=true;}}}
    $out=array();foreach($brands as $brand){
        $membership=$memberships[(int)$brand->term_id]??array();$groups=array();foreach(array_keys(qby_groups()) as $group){if(!empty($membership[$group])){$groups[]=$group==='inside'?'beauty':$group;}}
        $departments=array();foreach(qby_beauty_map() as $slug=>$department){if(!empty($membership['dept_'.str_replace('-','_',$slug)])){$departments[]=$slug;}}
        $out[]=array('id'=>$brand->term_id,'name'=>html_entity_decode($brand->name,ENT_QUOTES,'UTF-8'),'slug'=>$brand->slug,'groups'=>array_values(array_unique($groups)),'departments'=>$departments,'has_supplements'=>!empty($membership['supplements']),'count'=>$brand->count,'image'=>(int)get_term_meta($brand->term_id,'thumbnail_id',true));
    }
    set_transient('qby_brand_groups',$out,6*HOUR_IN_SECONDS);return $out;
}
function qby_featured_brands() {
    $slugs=array('optimum-nutrition','muscletech','allmax','swanson','hairburst','daruc');$all=qby_brand_directory();$chosen=array_filter($all,static function($b)use($slugs){return in_array($b['slug'],$slugs,true);});
    if(!$chosen){return '';}$html='<section class="qby-featured-brands"><h2 class="qby-directory-subtitle">'.esc_html(qby_t('Featured brands','علامات مختارة')).'</h2><div class="qby-featured-grid">';
    foreach(array_slice($chosen,0,6) as $b){$html.='<a href="'.esc_url(qby_term_url($b['slug'],'product_brand')).'">';if($b['image']){$html.=wp_get_attachment_image($b['image'],'full',false,array('loading'=>'lazy','decoding'=>'async','sizes'=>'(max-width:700px) 160px, 240px','alt'=>''));}$html.='<strong>'.esc_html($b['name']).'</strong></a>';}return $html.'</div></section>';
}
function qby_brands_page() {
    ob_start();?><div class="qby qby-directory" dir="<?php echo qby_ar()?'rtl':'ltr'; ?>"><span class="qby-eyebrow"><?php echo esc_html(qby_t('QIMIA / BRANDS','كيميا / العلامات')); ?></span><h1><?php echo esc_html(qby_t('Find your favourites.','اعثر على علاماتك المفضلة.')); ?></h1><p class="qby-lead"><?php echo esc_html(qby_t('Sports nutrition, everyday wellness and beauty. Explore every brand in one place.','التغذية الرياضية والعافية والجمال. اكتشف جميع العلامات في مكان واحد.')); ?></p><label class="qby-search-label" for="qby-brand-search"><?php echo esc_html(qby_t('Search brands','ابحث عن علامة')); ?></label><input type="search" id="qby-brand-search" class="qby-brand-search" placeholder="<?php echo esc_attr(qby_t('Start typing a brand name…','اكتب اسم العلامة…')); ?>" autocomplete="off"><div class="qby-chips" role="group" aria-label="<?php echo esc_attr(qby_t('Brand departments','أقسام العلامات')); ?>"><?php foreach(array('all'=>array('All','الكل'),'sports'=>array('Sports Nutrition','التغذية الرياضية'),'wellness'=>array('Health & Wellness','الصحة والعافية'),'beauty'=>array('Beauty','الجمال')) as $key=>$label){echo '<button type="button" data-qby-brand-filter="'.esc_attr($key).'" aria-pressed="'.($key==='all'?'true':'false').'">'.esc_html(qby_t($label[0],$label[1])).'</button>';}?></div><?php echo qby_featured_brands(); ?><h2 class="qby-directory-subtitle"><?php echo esc_html(qby_t('All brands · A–Z','كل العلامات · أ–ي')); ?></h2><p id="qby-brand-status" role="status" aria-live="polite"></p><div class="qby-brand-grid"><?php foreach(qby_brand_directory() as $b):?><a class="qby-brand-card" href="<?php echo esc_url(qby_term_url($b['slug'],'product_brand')); ?>" data-qby-brand-name="<?php echo esc_attr($b['name']); ?>" data-qby-brand-groups="<?php echo esc_attr(implode(' ',$b['groups'])); ?>"><?php if($b['image']){echo wp_kses_post(wp_get_attachment_image($b['image'],'full',false,array('loading'=>'lazy','decoding'=>'async','sizes'=>'(max-width:700px) 160px, 240px','alt'=>'')));}else{echo '<span class="qby-brand-monogram" aria-hidden="true">'.esc_html(function_exists('mb_substr')?mb_substr($b['name'],0,1,'UTF-8'):substr($b['name'],0,1)).'</span>';}?><strong><?php echo esc_html($b['name']); ?></strong><span><?php echo esc_html($b['count'].' '.qby_t((int)$b['count']===1?'product':'products',(int)$b['count']===1?'منتج':'منتجات')); ?> ↗</span></a><?php endforeach;?></div><p class="qby-empty" data-qby-brand-empty hidden><?php echo esc_html(qby_t('No matching brands. Try another name or department.','لا توجد علامة مطابقة. جرّب اسماً أو قسماً آخر.')); ?></p></div><?php return ob_get_clean();
}
function qby_categories_page() {
    $out='<div class="qby qby-directory" dir="'.(qby_ar()?'rtl':'ltr').'"><span class="qby-eyebrow">'.esc_html(qby_t('QIMIA / DEPARTMENTS','كيميا / الأقسام')).'</span><h1>'.esc_html(qby_t('Explore Qimia.','اكتشف كيميا.')).'</h1><div class="qby-category-groups">';
    foreach(qby_groups() as $key=>$g){$out.='<section><h2>'.esc_html(qby_t($g[0],$g[1])).'</h2>';foreach($g[2] as $slug){$t=get_term_by('slug',$slug,'product_cat');if(!$t){continue;}$out.=$t->count?qby_link(qby_term_url($slug),qby_label($slug). ' ↗'):'<p class="qby-muted">'.esc_html(qby_label($slug).' · '.qby_t('No products listed yet','لا توجد منتجات مدرجة بعد')).'</p>';}$out.='</section>';}
    // Keep every legacy category reachable, including children and uncategorized products.
    $out.='<section><h2>'.esc_html(qby_t('All categories · A–Z','كل الفئات')).'</h2>';$terms=get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'orderby'=>'name'));foreach(is_wp_error($terms)?array():$terms as $t){$out.=qby_link(qby_term_url($t->slug),qby_label($t->slug));}$out.='</section></div></div>';return $out;
}
