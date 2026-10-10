<?php
defined('ABSPATH') || exit;

function qbx_e($en, $ar) { return esc_html(qby_t($en, $ar)); }
function qbx_browse($slug = 'beauty', $extra = array()) {
    $args = array_merge(array('department' => $slug), $extra);
    return add_query_arg($args, qby_url('/beauty/')) . '#qby-shop';
}
function qbx_arrow() { return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.5"/></svg>'; }
function qbx_photo($name, $class = '', $eager = false) {
    $name = $name === 'hero' ? 'qimia-beauty-editorial' : $name;
    return '<img class="'.esc_attr($class).'" src="'.esc_url(QBY_URL.'assets/'.$name.'-960.webp').'" srcset="'.esc_url(QBY_URL.'assets/'.$name.'-640.webp').' 640w, '.esc_url(QBY_URL.'assets/'.$name.'-960.webp').' 960w, '.esc_url(QBY_URL.'assets/'.$name.'-1440.webp').' 1440w" sizes="(max-width:700px) 100vw, 55vw" width="1440" height="960" alt="" '.($eager?'fetchpriority="high"':'loading="lazy"').' decoding="async">';
}
function qbx_departments() {
    // The supplied lists inform emphasis, not stock or product facts. Native category slugs are preserved.
    return array(
        'skin-care' => array('Skin care', 'العناية بالبشرة', 'A moment for your skin.', 'لحظة عناية ببشرتك.', 'Cleanse. Layer. Moisturise.', 'نظّفي. اعتني. رطّبي.', 'face-cleansers', 'face-serums', 'face-moisturisers', 'toners-essences', 'face-masks', 'eye-care'),
        'makeup' => array('Makeup', 'المكياج', 'Make it your own.', 'جمال على طريقتك.', 'Your shade. Your finish.', 'درجتك. لمستك.', 'foundation', 'concealer', 'makeup-primer', 'face-powder', 'blush-bronzer', 'highlighter', 'eyeshadow', 'eyeliner', 'mascara', 'lip-makeup', 'brow-makeup', 'setting-spray'),
        'sun-care' => array('Sun care', 'الحماية من الشمس', 'Everyday, in the sun.', 'عناية لكل يوم مشمس.', 'Find your daily SPF.', 'اكتشفي واقي الشمس المناسب.', 'sun-care'),
        'hair-care' => array('Hair care', 'العناية بالشعر', 'Good hair. Your way.', 'شعرك. بطريقتك.', 'From scalp to ends.', 'من الجذور إلى الأطراف.', 'shampoo', 'conditioner', 'hair-mask', 'hair-oils', 'scalp-care'),
        'body-care' => array('Bath & body', 'العناية بالجسم', 'The softer side.', 'لحظات أكثر نعومة.', 'Make everyday care a ritual.', 'حوّلي العناية اليومية إلى روتين.', 'body-wash', 'body-moisturisers', 'deodorants', 'hand-foot-care'),
        'beauty-tools' => array('Beauty tools', 'أدوات الجمال', 'The finishing touch.', 'اللمسة الأخيرة.', 'Little tools. Lovely details.', 'أدوات صغيرة. تفاصيل جميلة.', 'hair-tools', 'makeup-brushes', 'makeup-sponges', 'beauty-organisers'),
    );
}
function qbx_category_tiles() {
    ob_start(); ?>
    <section class="qbx-section" id="qbx-world" aria-labelledby="qbx-world-title">
      <div class="qbx-section-head" data-qbx-reveal><div><span class="qbx-kicker"><?php echo qbx_e('CHOOSE YOUR WORLD','اختاري عالمك'); ?></span><h2 id="qbx-world-title"><?php echo qbx_e('A ritual for every side of you.','روتين لكل تفاصيلك.'); ?></h2></div><a class="qbx-text-link" href="#qbx-directory"><?php echo qbx_e('All beauty categories','كل فئات الجمال'); ?> <?php echo qbx_arrow(); ?></a></div>
      <div class="qbx-world-grid">
      <?php $i=0; foreach (qbx_departments() as $slug=>$d): $i++; ?>
        <a class="qbx-world qbx-world--<?php echo esc_attr($slug); ?>" href="<?php echo esc_url(qbx_browse($slug)); ?>" data-qbx-department="<?php echo esc_attr($slug); ?>" data-qbx-reveal>
          <span class="qbx-world-number">0<?php echo (int)$i; ?></span>
          <span class="qbx-world-art" aria-hidden="true"><?php if ($i===1 || $i===2) { echo qbx_photo($i===1?'skin':'makeup'); } else { echo qby_department_icon($slug==='sun-care'?'skin-care':$slug); } ?></span>
          <span class="qbx-world-copy"><small><?php echo qbx_e($d[4],$d[5]); ?></small><strong><?php echo qbx_e($d[0],$d[1]); ?></strong></span><span class="qbx-round-arrow"><?php echo qbx_arrow(); ?></span>
        </a>
      <?php endforeach; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}
function qbx_selection() {
    $keys = array('department','brand','stock','collection','search','orderby','qby_page','care','skin_type','hair_type','finish','coverage','shade','concentration');
    $input=array(); foreach ($keys as $key) { $input[$key]=sanitize_text_field(qby_query_value($key,'')); }
    if (!isset(qby_beauty_terms()[$input['department']])) { $input['department']='beauty'; }
    if (!in_array($input['collection'],array('all','new','bestsellers','offers'),true)) { $input['collection']='all'; }
    if (!in_array($input['orderby'],array('menu_order','date','popularity','price','price-desc'),true)) { $input['orderby']='menu_order'; }
    if (!in_array($input['stock'],array('','instock'),true)) { $input['stock']=''; }
    $input['search']=function_exists('mb_substr')?mb_substr($input['search'],0,100):substr($input['search'],0,100);
    $input['qby_page']=max(1,min(10000,(int)$input['qby_page']));
    return $input;
}
function qbx_catalogue($selection) {
    $result=qby_beauty_query(array_merge($selection,array('page'=>$selection['qby_page'],'per_page'=>12)));
    ob_start(); ?>
    <div class="qbx-results-head"><p id="qbx-count" role="status" aria-live="polite"><?php echo esc_html(number_format_i18n($result['total']).' '.qby_t($result['total']===1?'product':'products','منتج')); ?></p><span><?php echo qbx_e('Current prices & availability','الأسعار والتوفر الحاليان'); ?></span></div>
    <?php if ($result['products']) { echo qby_cards($result['products']); } else { ?>
      <div class="qbx-empty"><span class="qbx-empty-flower" aria-hidden="true">✳</span><h3><?php echo qbx_e('A new discovery is a click away.','اكتشاف جديد بانتظارك.'); ?></h3><p><?php echo qbx_e('No products match these filters right now. Try a different category or explore the full beauty collection.','لا توجد منتجات مطابقة لهذه الفلاتر حالياً. جرّبي فئة أخرى أو تصفحي مجموعة الجمال كاملة.'); ?></p><a class="qbx-button" href="<?php echo esc_url(qby_url('/beauty/').'#qby-shop'); ?>" data-qbx-reset><?php echo qbx_e('Explore all beauty','اكتشفي كل الجمال'); ?> <?php echo qbx_arrow(); ?></a></div>
    <?php }
    if ($result['pages']>1) { ?><nav class="qbx-pagination" aria-label="<?php echo esc_attr(qby_t('Product pages','صفحات المنتجات')); ?>"><?php
        foreach(array_unique(array_merge(array(1),range(max(1,$result['page']-2),min($result['pages'],$result['page']+2)),array($result['pages']))) as $page) {
            $args=$selection;$args['qby_page']=$page;
            echo '<a href="'.esc_url(add_query_arg($args,qby_url('/beauty/')).'#qby-shop').'" '.($page===$result['page']?'aria-current="page"':'').'>'.esc_html(number_format_i18n($page)).'</a>';
        } ?></nav><?php }
    return ob_get_clean();
}
function qbx_filter_option($value, $en, $ar, $selected) { return '<option value="'.esc_attr($value).'" '.selected($selected,$value,false).'>'.qbx_e($en,$ar).'</option>'; }
function qbx_shop($selection) {
    ob_start(); ?>
    <section class="qbx-section qbx-shop" id="qby-shop" aria-labelledby="qbx-shop-title">
      <div class="qbx-section-head" data-qbx-reveal><div><span class="qbx-kicker"><?php echo qbx_e('THE BEAUTY EDIT','مختارات الجمال'); ?></span><h2 id="qbx-shop-title"><?php echo qbx_e('Meet your next favourite.','اكتشفي مفضلتك القادمة.'); ?></h2></div><p><?php echo qbx_e('Explore. Compare. Make it yours.','اكتشفي. قارني. اختاري.'); ?></p></div>
      <form class="qbx-filters" data-qbx-filters action="<?php echo esc_url(qby_url('/beauty/')); ?>#qby-shop" method="get">
        <label class="qbx-search"><span class="qbx-sr"><?php echo qbx_e('Search beauty products','ابحثي عن منتجات الجمال'); ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg><input type="search" name="search" maxlength="100" placeholder="<?php echo esc_attr(qby_t('Search a product or brand…','ابحثي عن منتج أو علامة…')); ?>" value="<?php echo esc_attr($selection['search']); ?>" autocomplete="off"></label>
        <div class="qbx-filter-row">
        <label><?php echo qbx_e('Category','الفئة'); ?><select name="department"><?php echo qbx_filter_option('beauty','All beauty','كل الجمال',$selection['department']); foreach(qby_beauty_map() as $slug=>$d) { echo '<optgroup label="'.esc_attr(qby_t($d['en'],$d['ar'])).'">'.qbx_filter_option($slug,$d['en'],$d['ar'],$selection['department']);foreach($d['children'] as $child=>$label){echo qbx_filter_option($child,$label[0],$label[1],$selection['department']);}echo '</optgroup>'; } ?></select></label>
        <label><?php echo qbx_e('Brand','العلامة'); ?><select name="brand"><?php echo qbx_filter_option('','All brands','كل العلامات',$selection['brand']); foreach(qby_brand_directory() as $brand) { if(!empty($brand['departments'])){echo qbx_filter_option($brand['slug'],$brand['name'],$brand['name'],$selection['brand']);} } ?></select></label>
        <label><?php echo qbx_e('Sort by','الترتيب'); ?><select name="orderby"><?php foreach(array('menu_order'=>array('The Qimia edit','مختارات كيميا'),'date'=>array('Newest first','الأحدث أولاً'),'popularity'=>array('Most purchased','الأكثر شراءً'),'price'=>array('Price: low to high','السعر: من الأقل للأعلى'),'price-desc'=>array('Price: high to low','السعر: من الأعلى للأقل')) as $key=>$label){echo qbx_filter_option($key,$label[0],$label[1],$selection['orderby']);} ?></select></label>
        <label class="qbx-stock"><input type="checkbox" name="stock" value="instock" <?php checked($selection['stock'],'instock'); ?>><?php echo qbx_e('In stock only','المتوفر فقط'); ?></label>
        <button class="qbx-button qbx-filter-submit" type="submit"><?php echo qbx_e('Find products','ابحثي'); ?><?php echo qbx_arrow(); ?></button>
        </div>
        <?php $facet_labels=array('care'=>array('Care need','احتياج العناية'),'skin_type'=>array('Skin type','نوع البشرة'),'hair_type'=>array('Hair type','نوع الشعر'),'finish'=>array('Finish','اللمسة النهائية'),'coverage'=>array('Coverage','التغطية'),'shade'=>array('Shade','الدرجة'),'concentration'=>array('Concentration','التركيز'));$facet_html='';foreach(qby_beauty_facets() as $key=>$taxonomy){if(!taxonomy_exists($taxonomy)){continue;}$terms=get_terms(array('taxonomy'=>$taxonomy,'hide_empty'=>true));if(is_wp_error($terms)||!$terms){continue;}$label=$facet_labels[$key];$facet_html.='<label>'.qbx_e($label[0],$label[1]).'<select name="'.esc_attr($key).'">'.qbx_filter_option('','All','الكل',$selection[$key]);foreach($terms as $term){$facet_html.=qbx_filter_option($term->slug,$term->name,$term->name,$selection[$key]);}$facet_html.='</select></label>';}if($facet_html){echo '<div class="qbx-facets">'.$facet_html.'</div>';} ?>
        <input type="hidden" name="collection" value="<?php echo esc_attr($selection['collection']); ?>"><input type="hidden" name="qby_page" value="1">
      </form>
      <nav class="qbx-collections" aria-label="<?php echo esc_attr(qby_t('Beauty collections','مجموعات الجمال')); ?>"><?php foreach(array('all'=>array('The edit','المختارات'),'bestsellers'=>array('Best sellers','الأكثر مبيعاً'),'new'=>array('New arrivals','وصل حديثاً'),'offers'=>array('Offers','العروض')) as $key=>$label) { $args=$selection;$args['collection']=$key;$args['qby_page']=1; ?><a href="<?php echo esc_url(add_query_arg($args,qby_url('/beauty/')).'#qby-shop'); ?>" data-qbx-collection="<?php echo esc_attr($key); ?>" <?php if($selection['collection']===$key){echo 'aria-current="true"';} ?>><?php echo qbx_e($label[0],$label[1]); ?></a><?php } ?><a class="qbx-reset" data-qbx-reset href="<?php echo esc_url(qby_url('/beauty/').'#qby-shop'); ?>"><?php echo qbx_e('Reset filters','مسح الفلاتر'); ?></a></nav>
      <p class="qbx-network-status qbx-sr" data-qbx-status role="status" aria-live="polite"></p>
      <div id="qbx-results" data-qbx-results><?php echo qbx_catalogue($selection); ?></div>
    </section>
    <?php return ob_get_clean();
}
function qbx_finder() {
    $groups=array(
        'skin-care'=>array('Skin care','العناية بالبشرة',array('face-cleansers'=>array('Cleanse','التنظيف'),'face-serums'=>array('Serums','السيروم'),'face-moisturisers'=>array('Moisturise','الترطيب'),'sun-care'=>array('Sun protection','الحماية من الشمس'),'face-masks'=>array('A mask moment','لحظة مع ماسك'))),
        'makeup'=>array('Makeup','المكياج',array('foundation'=>array('Find a base','كريم الأساس'),'blush-bronzer'=>array('Cheeks','الخدود'),'mascara'=>array('Lashes','الرموش'),'lip-makeup'=>array('Lips','الشفاه'),'setting-spray'=>array('Set the look','تثبيت المكياج'))),
        'hair-care'=>array('Hair care','العناية بالشعر',array('shampoo'=>array('Wash day','غسل الشعر'),'hair-mask'=>array('Hair masks','ماسكات الشعر'),'hair-oils'=>array('Hair oils','زيوت الشعر'),'scalp-care'=>array('Scalp care','فروة الرأس'),'hair-tools'=>array('Styling tools','أدوات التصفيف'))),
    );
    ob_start(); ?>
    <section class="qbx-ritual" id="qbx-ritual" data-qbx-reveal>
      <div class="qbx-ritual-copy"><span class="qbx-kicker"><?php echo qbx_e('YOUR BEAUTY, YOUR PACE','جمالك. على راحتك.'); ?></span><h2><?php echo qby_t('A little less searching.<br><em>A little more you.</em>','بحث أقل.<br><em>وقت أكثر لنفسك.</em>'); ?></h2><p><?php echo qbx_e('Choose where you want to begin. We’ll take you straight to that part of your routine.','اختاري من أين تبدئين، وسنأخذك مباشرة إلى هذه الخطوة من روتينك.'); ?></p><span class="qbx-ritual-mark" aria-hidden="true">q.</span></div>
      <div class="qbx-finder"><h3><?php echo qbx_e('What’s your beauty mood?','ما خطوتك اليوم؟'); ?></h3><p><?php echo qbx_e('01 / Pick your world','٠١ / اختاري عالمك'); ?></p><div class="qbx-finder-tabs" role="group" aria-label="<?php echo esc_attr(qby_t('Choose your routine','اختاري روتينك')); ?>"><?php $i=0;foreach($groups as $key=>$g){?><button type="button" data-qbx-ritual-tab="<?php echo esc_attr($key); ?>" aria-controls="qbx-path-<?php echo esc_attr($key); ?>" aria-pressed="<?php echo $i++===0?'true':'false'; ?>"><?php echo qbx_e($g[0],$g[1]); ?></button><?php } ?></div><p><?php echo qbx_e('02 / Choose your next step','٠٢ / اختاري خطوتك التالية'); ?></p><?php $i=0;foreach($groups as $key=>$g){?><div id="qbx-path-<?php echo esc_attr($key); ?>" class="qbx-finder-path" data-qbx-ritual-path="<?php echo esc_attr($key); ?>" <?php if($i++>0){echo 'hidden';} ?>><?php foreach($g[2] as $slug=>$label){?><a href="<?php echo esc_url(qbx_browse($slug)); ?>" data-qbx-department="<?php echo esc_attr($slug); ?>"><?php echo qbx_e($label[0],$label[1]); ?><?php echo qbx_arrow(); ?></a><?php } ?></div><?php } ?><noscript><p><?php echo qbx_e('Explore every step in the category directory below.','تصفحي جميع الخطوات في دليل الفئات أدناه.'); ?></p></noscript></div>
    </section>
    <?php return ob_get_clean();
}
function qbx_directory() {
    ob_start(); ?><section class="qbx-section qbx-directory" id="qbx-directory"><div class="qbx-section-head"><div><span class="qbx-kicker"><?php echo qbx_e('EVERY DETAIL, WITHIN REACH','كل التفاصيل بين يديك'); ?></span><h2><?php echo qbx_e('Find exactly your thing.','اعثري على ما يناسبك.'); ?></h2></div></div><div class="qbx-directory-grid"><?php foreach(qbx_departments() as $slug=>$d){?><details><summary><?php echo qbx_e($d[0],$d[1]); ?><span aria-hidden="true">+</span></summary><div><a href="<?php echo esc_url(qbx_browse($slug)); ?>"><?php echo qbx_e('Explore all','تصفحي الكل'); ?><?php echo qbx_arrow(); ?></a><?php foreach(array_slice($d,6) as $child){?><a href="<?php echo esc_url(qbx_browse($child)); ?>"><?php echo esc_html(qby_label($child)); ?></a><?php } ?></div></details><?php } ?><details><summary><?php echo qbx_e('Fragrance','العطور'); ?><span aria-hidden="true">+</span></summary><div><?php foreach(array('fragrance','perfume','body-mists','fragrance-sets','travel-fragrance') as $slug){?><a href="<?php echo esc_url(qbx_browse($slug)); ?>"><?php echo esc_html(qby_label($slug)); ?></a><?php } ?></div></details><details><summary><?php echo qbx_e('Beauty from within','الجمال من الداخل'); ?><span aria-hidden="true">+</span></summary><div><?php foreach(array('hair-nail-skin-health','collagens') as $slug){echo qby_link(qby_term_url($slug),qby_label($slug));} ?></div></details></div></section><?php return ob_get_clean();
}
function qbx_page() {
    // Empty initial searches still need native compare/AI docks after an AJAX reset.
    if (function_exists('qby_qil_print_docks')) { $GLOBALS['qby_qil_docks_needed'] = true; }
    $selection=qbx_selection();
    ob_start(); ?>
    <div class="qbx qbx-experience" dir="<?php echo qby_ar()?'rtl':'ltr'; ?>" data-qbx-experience data-qbx-language="<?php echo qby_ar()?'ar':'en'; ?>">
      <nav class="qbx-subnav" aria-label="<?php echo esc_attr(qby_t('Beauty navigation','تصفح الجمال')); ?>"><a class="qbx-wordmark" href="<?php echo esc_url(qby_url('/beauty/')); ?>"><?php echo qbx_e('QIMIA','كيميا'); ?><span><?php echo qbx_e('BEAUTY','الجمال'); ?></span><i>✦</i></a><div><a href="#qbx-world"><?php echo qbx_e('Explore','اكتشفي'); ?></a><a href="#qby-shop"><?php echo qbx_e('The edit','المختارات'); ?></a><a href="#qbx-ritual"><?php echo qbx_e('Find your ritual','اكتشفي روتينك'); ?></a></div><button type="button" class="qbx-motion" data-qbx-motion aria-pressed="false"><?php echo qbx_e('Pause motion','إيقاف الحركة'); ?><span aria-hidden="true">Ⅱ</span></button></nav>
      <section class="qbx-hero" aria-labelledby="qbx-title">
        <div class="qbx-hero-visual" data-qbx-parallax><?php echo qbx_photo('hero','',true); ?><div class="qbx-hero-stamp" aria-hidden="true"><span><?php echo qbx_e('QIMIA','كيميا'); ?></span><em><?php echo qby_t('beauty<br>intelligence.','ذكاء<br>الجمال.'); ?></em></div><span class="qbx-photo-caption"><?php echo qbx_e('A MOMENT. JUST FOR YOU.','لحظة. لك وحدك.'); ?></span></div>

        <div class="qbx-hero-copy"><span class="qbx-kicker"><?php echo qbx_e('BEAUTY, WITH QIMIA INTELLIGENCE','الجمال بذكاء كيميا'); ?></span><h1 id="qbx-title"><?php echo qby_t('Every detail.<br><em>Beautifully you.</em>','جمالك.<br><em>بكل تفاصيله.</em>'); ?></h1><p><?php echo qbx_e('Skin, makeup and everyday care. Discover your next favourite with clear ingredients, thoughtful details and a little Qimia intelligence.','بشرة ومكياج وعناية يومية. اكتشفي مفضلتك القادمة مع مكونات واضحة وتفاصيل دقيقة وذكاء كيميا.'); ?></p><div class="qbx-actions"><a class="qbx-button" href="#qby-shop"><?php echo qbx_e('Explore the beauty edit','اكتشفي مختارات الجمال'); ?><?php echo qbx_arrow(); ?></a><a class="qbx-text-link" href="#qbx-ritual"><?php echo qbx_e('Help me choose','ساعديني أختار'); ?> ↗</a></div><div class="qbx-hero-foot"><span><?php echo qbx_e('SKIN / MAKEUP / SELF-CARE','بشرة / مكياج / عناية'); ?></span><a href="#qbx-world" aria-label="<?php echo esc_attr(qby_t('Explore beauty categories','اكتشفي فئات الجمال')); ?>">↓</a></div></div>
      </section>
      <div class="qbx-editorial-line"><span><?php echo qbx_e('A little care goes a long way.','قليل من العناية يصنع الفرق.'); ?></span><span aria-hidden="true">✳</span><span><?php echo qbx_e('Make room for your ritual.','امنحي روتينك مساحة.'); ?></span><span aria-hidden="true">✳</span></div>
      <?php echo qbx_category_tiles(); ?>
      <section class="qbx-story" data-qbx-reveal><div class="qbx-story-art"><?php echo qbx_photo('skin'); ?><span class="qbx-story-caption"><?php echo qbx_e('THE SKIN EDIT / 01','مختارات البشرة / ٠١'); ?></span></div><div class="qbx-story-copy"><span class="qbx-kicker"><?php echo qbx_e('START WITH SKIN','ابدئي بالبشرة'); ?></span><h2><?php echo qby_t('Less rush.<br><em>More ritual.</em>','تمهّلي.<br><em>واعتني بنفسك.</em>'); ?></h2><p><?php echo qbx_e('From the first cleanse to your daily SPF. Explore textures, compare formulas and find a place for every step.','من التنظيف إلى واقي الشمس اليومي. اكتشفي القوام وقارني التركيبات واختاري لكل خطوة مكانها.'); ?></p><div class="qbx-story-links"><?php foreach(array('face-cleansers'=>array('01 / Cleanse','٠١ / تنظيف'),'face-serums'=>array('02 / Serums','٠٢ / سيروم'),'face-moisturisers'=>array('03 / Moisturise','٠٣ / ترطيب'),'sun-care'=>array('04 / Sun care','٠٤ / حماية من الشمس')) as $slug=>$label){?><a href="<?php echo esc_url(qbx_browse($slug)); ?>" data-qbx-department="<?php echo esc_attr($slug); ?>"><?php echo qbx_e($label[0],$label[1]); ?><?php echo qbx_arrow(); ?></a><?php } ?></div></div></section>
      <?php echo qbx_shop($selection); echo qbx_finder(); ?>
      <section class="qbx-notes qbx-section" aria-labelledby="qbx-notes-title"><div class="qbx-section-head"><div><span class="qbx-kicker"><?php echo qbx_e('BEAUTY, MADE CLEAR','الجمال بوضوح'); ?></span><h2 id="qbx-notes-title"><?php echo qbx_e('The details worth knowing.','تفاصيل تستحق المعرفة.'); ?></h2></div></div><div class="qbx-notes-grid"><article data-qbx-reveal><span>01</span><h3><?php echo qbx_e('Your shade. Your finish.','درجتك. لمستك.'); ?></h3><p><?php echo qbx_e('Compare the exact shade, coverage and finish. Check each variant’s own details before adding it to your bag.','قارني الدرجة والتغطية واللمسة النهائية، وراجعي تفاصيل كل خيار قبل إضافته إلى سلتك.'); ?></p><a class="qbx-text-link" href="<?php echo esc_url(qbx_browse('foundation')); ?>"><?php echo qbx_e('Explore complexion','اكتشفي مكياج الوجه'); ?><?php echo qbx_arrow(); ?></a></article><article data-qbx-reveal><span>02</span><h3><?php echo qbx_e('Know what’s in it.','اعرفي مكوناته.'); ?></h3><p><?php echo qbx_e('Read the product’s own ingredient list and directions. Formulas can vary between sizes, shades and markets.','اقرئي قائمة مكونات المنتج وإرشاداته. قد تختلف التركيبة حسب الحجم أو الدرجة أو السوق.'); ?></p><a class="qbx-text-link" href="#qby-shop"><?php echo qbx_e('Compare the details','قارني التفاصيل'); ?><?php echo qbx_arrow(); ?></a></article><article data-qbx-reveal><span>03</span><h3><?php echo qbx_e('A guide by your side.','دليل بجانبك.'); ?></h3><p><?php echo qbx_e('Ask Qimia AI about the products you’re exploring, in English or Arabic. Start with a question, take your time.','اسألي ذكاء كيميا عن المنتجات التي تتصفحينها، بالعربية أو الإنجليزية. ابدئي بسؤال وخذي وقتك.'); ?></p><button class="qbx-text-link" type="button" data-qimia-ai-open><?php echo qbx_e('Ask Qimia AI','اسألي ذكاء كيميا'); ?><?php echo qbx_arrow(); ?></button></article></div></section>
      <?php echo qbx_directory(); ?>
      <section class="qbx-closing"><span class="qbx-kicker"><?php echo qbx_e('ONE QIMIA. ALL OF YOU.','كيميا واحدة. لكل تفاصيلك.'); ?></span><h2><?php echo qby_t('Beauty is personal.<br><em>Make yourself at home.</em>','الجمال على طريقتك.<br><em>أهلاً بك في عالمك.</em>'); ?></h2><div class="qbx-actions"><a class="qbx-button" href="<?php echo esc_url(qby_url('/brands/')); ?>"><?php echo qbx_e('Discover the brands','اكتشفي العلامات'); ?><?php echo qbx_arrow(); ?></a><a class="qbx-text-link" href="<?php echo esc_url(qby_url('/my-account/')); ?>"><?php echo qbx_e('Your Qimia account','حسابك في كيميا'); ?> ↗</a></div></section>
    </div>
    <?php return ob_get_clean();
}
function qbx_home() {
    // No extra product query: native product shelves remain owned by the existing Beauty integration.
    ?>
    <section class="qbx qbx-home" id="qbx-home" dir="<?php echo qby_ar()?'rtl':'ltr'; ?>" aria-labelledby="qbx-home-title" data-qbx-experience data-qbx-language="<?php echo qby_ar()?'ar':'en'; ?>">
      <div class="qbx-home-top"><span class="qbx-kicker"><?php echo qbx_e('THE QIMIA BEAUTY WORLD','عالم كيميا للجمال'); ?></span><a href="<?php echo esc_url(qby_url('/beauty/')); ?>" class="qbx-text-link"><?php echo qbx_e('Enter Qimia Beauty','ادخلي عالم كيميا للجمال'); ?><?php echo qbx_arrow(); ?></a></div>
      <div class="qbx-home-scene" data-qbx-reveal><div class="qbx-home-copy"><span class="qbx-wordmark"><?php echo qbx_e('QIMIA','كيميا'); ?><span><?php echo qbx_e('BEAUTY','الجمال'); ?></span><i>✦</i></span><h2 id="qbx-home-title"><?php echo qby_t('Your beauty.<br><em>Intelligently connected.</em>','جمالك.<br><em>بتجربة متكاملة.</em>'); ?></h2><p><?php echo qbx_e('Your everyday beauty ritual starts here. Skin, makeup and self-care, in one beautiful place.','روتين جمالك اليومي يبدأ هنا. بشرة ومكياج وعناية، في مكان واحد.'); ?></p><a class="qbx-button" href="<?php echo esc_url(qby_url('/beauty/')); ?>"><?php echo qbx_e('Discover your beauty ritual','اكتشفي روتين جمالك'); ?><?php echo qbx_arrow(); ?></a></div><div class="qbx-home-visual" data-qbx-parallax><?php echo qbx_photo('hero'); ?><span class="qbx-home-stamp" aria-hidden="true">q.</span></div></div>
      <nav class="qbx-home-categories" aria-label="<?php echo esc_attr(qby_t('Beauty categories','فئات الجمال')); ?>"><?php foreach(qbx_departments() as $slug=>$d){?><a href="<?php echo esc_url(qbx_browse($slug)); ?>"><?php echo qbx_e($d[0],$d[1]); ?><span aria-hidden="true">↗</span></a><?php } ?></nav>
    </section>
    <?php
}
