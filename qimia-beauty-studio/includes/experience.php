<?php
defined('ABSPATH') || exit;

function qbs_e($en, $ar) { return esc_html(qby_t($en, $ar)); }
function qbs_browse($slug = 'beauty', $extra = array()) {
    $args = array_merge(array('department' => $slug), $extra);
    return add_query_arg($args, qby_url('/beauty/')) . '#qby-shop';
}
function qbs_arrow() { return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.5"/></svg>'; }
function qbs_photo($name, $class = '', $eager = false) {
    return '<img class="'.esc_attr($class).'" src="'.esc_url(QBS_URL.'assets/'.$name.'-960.webp').'" srcset="'.esc_url(QBS_URL.'assets/'.$name.'-640.webp').' 640w, '.esc_url(QBS_URL.'assets/'.$name.'-960.webp').' 960w, '.esc_url(QBS_URL.'assets/'.$name.'-1440.webp').' 1440w" sizes="(max-width:700px) 100vw, 55vw" width="1440" height="960" alt="" '.($eager?'fetchpriority="high"':'loading="lazy"').' decoding="async">';
}
function qbs_departments() {
    // The supplied lists inform emphasis, not stock or product facts. Native category slugs are preserved.
    return array(
        'skin-care' => array('Skin care', 'العناية بالبشرة', 'A moment for your skin.', 'لحظة عناية ببشرتك.', 'Cleanse. Layer. Moisturise.', 'نظّفي. اعتني. رطّبي.', 'face-cleansers', 'face-serums', 'face-moisturisers', 'toners-essences', 'face-masks', 'eye-care'),
        'makeup' => array('Makeup', 'المكياج', 'Make it your own.', 'جمال على طريقتك.', 'Your shade. Your finish.', 'درجتك. لمستك.', 'foundation', 'makeup-primer', 'face-powder', 'blush-bronzer', 'mascara', 'lip-makeup', 'brow-makeup', 'setting-spray'),
        'sun-care' => array('Sun care', 'الحماية من الشمس', 'Everyday, in the sun.', 'عناية لكل يوم مشمس.', 'Find your daily SPF.', 'اكتشفي واقي الشمس المناسب.', 'sun-care'),
        'hair-care' => array('Hair care', 'العناية بالشعر', 'Good hair. Your way.', 'شعرك. بطريقتك.', 'From scalp to ends.', 'من الجذور إلى الأطراف.', 'shampoo', 'conditioner', 'hair-mask', 'hair-oils', 'scalp-care'),
        'body-care' => array('Bath & body', 'العناية بالجسم', 'The softer side.', 'لحظات أكثر نعومة.', 'Make everyday care a ritual.', 'حوّلي العناية اليومية إلى روتين.', 'body-wash', 'body-moisturisers', 'deodorants', 'hand-foot-care'),
        'beauty-tools' => array('Beauty tools', 'أدوات الجمال', 'The finishing touch.', 'اللمسة الأخيرة.', 'Little tools. Lovely details.', 'أدوات صغيرة. تفاصيل جميلة.', 'hair-tools', 'makeup-brushes', 'makeup-sponges', 'beauty-organisers'),
    );
}
function qbs_category_tiles() {
    ob_start(); ?>
    <section class="qbs-section" id="qbs-world" aria-labelledby="qbs-world-title">
      <div class="qbs-section-head" data-qbs-reveal><div><span class="qbs-kicker"><?php echo qbs_e('CHOOSE YOUR WORLD','اختاري عالمك'); ?></span><h2 id="qbs-world-title"><?php echo qbs_e('A ritual for every side of you.','روتين لكل تفاصيلك.'); ?></h2></div><a class="qbs-text-link" href="#qbs-directory"><?php echo qbs_e('All beauty categories','كل فئات الجمال'); ?> <?php echo qbs_arrow(); ?></a></div>
      <div class="qbs-world-grid">
      <?php $i=0; foreach (qbs_departments() as $slug=>$d): $i++; ?>
        <a class="qbs-world qbs-world--<?php echo esc_attr($slug); ?>" href="<?php echo esc_url(qbs_browse($slug)); ?>" data-qbs-department="<?php echo esc_attr($slug); ?>" data-qbs-reveal>
          <span class="qbs-world-number">0<?php echo (int)$i; ?></span>
          <span class="qbs-world-art" aria-hidden="true"><?php if ($i===1 || $i===2) { echo qbs_photo($i===1?'skin':'makeup'); } else { echo qby_department_icon($slug==='sun-care'?'skin-care':$slug); } ?></span>
          <span class="qbs-world-copy"><small><?php echo qbs_e($d[4],$d[5]); ?></small><strong><?php echo qbs_e($d[0],$d[1]); ?></strong></span><span class="qbs-round-arrow"><?php echo qbs_arrow(); ?></span>
        </a>
      <?php endforeach; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}
function qbs_selection() {
    $keys = array('department','brand','stock','collection','search','orderby','qby_page');
    $input=array(); foreach ($keys as $key) { $input[$key]=sanitize_text_field(qby_query_value($key,'')); }
    if (!isset(qby_beauty_terms()[$input['department']])) { $input['department']='beauty'; }
    if (!in_array($input['collection'],array('all','new','bestsellers','offers'),true)) { $input['collection']='all'; }
    if (!in_array($input['orderby'],array('menu_order','date','popularity','price','price-desc'),true)) { $input['orderby']='menu_order'; }
    if (!in_array($input['stock'],array('','instock'),true)) { $input['stock']=''; }
    $input['search']=mb_substr($input['search'],0,100);
    $input['qby_page']=max(1,min(10000,(int)$input['qby_page']));
    return $input;
}
function qbs_catalogue($selection) {
    $result=qby_beauty_query(array_merge($selection,array('page'=>$selection['qby_page'],'per_page'=>12)));
    ob_start(); ?>
    <div class="qbs-results-head"><p id="qbs-count" role="status" aria-live="polite"><?php echo esc_html(number_format_i18n($result['total']).' '.qby_t($result['total']===1?'product':'products','منتج')); ?></p><span><?php echo qbs_e('Current prices & availability','الأسعار والتوفر الحاليان'); ?></span></div>
    <?php if ($result['products']) { echo qby_cards($result['products']); } else { ?>
      <div class="qbs-empty"><span class="qbs-empty-flower" aria-hidden="true">✳</span><h3><?php echo qbs_e('A new discovery is a click away.','اكتشاف جديد بانتظارك.'); ?></h3><p><?php echo qbs_e('No products match these filters right now. Try a different category or explore the full beauty collection.','لا توجد منتجات مطابقة لهذه الفلاتر حالياً. جرّبي فئة أخرى أو تصفحي مجموعة الجمال كاملة.'); ?></p><a class="qbs-button" href="<?php echo esc_url(qby_url('/beauty/').'#qby-shop'); ?>" data-qbs-reset><?php echo qbs_e('Explore all beauty','اكتشفي كل الجمال'); ?> <?php echo qbs_arrow(); ?></a></div>
    <?php }
    if ($result['pages']>1) { ?><nav class="qbs-pagination" aria-label="<?php echo esc_attr(qby_t('Product pages','صفحات المنتجات')); ?>"><?php
        foreach(array_unique(array_merge(array(1),range(max(1,$result['page']-2),min($result['pages'],$result['page']+2)),array($result['pages']))) as $page) {
            $args=$selection;$args['qby_page']=$page;
            echo '<a href="'.esc_url(add_query_arg($args,qby_url('/beauty/')).'#qby-shop').'" '.($page===$result['page']?'aria-current="page"':'').'>'.esc_html(number_format_i18n($page)).'</a>';
        } ?></nav><?php }
    return ob_get_clean();
}
function qbs_filter_option($value, $en, $ar, $selected) { return '<option value="'.esc_attr($value).'" '.selected($selected,$value,false).'>'.qbs_e($en,$ar).'</option>'; }
function qbs_shop($selection) {
    ob_start(); ?>
    <section class="qbs-section qbs-shop" id="qby-shop" aria-labelledby="qbs-shop-title">
      <div class="qbs-section-head" data-qbs-reveal><div><span class="qbs-kicker"><?php echo qbs_e('THE BEAUTY EDIT','مختارات الجمال'); ?></span><h2 id="qbs-shop-title"><?php echo qbs_e('Meet your next favourite.','اكتشفي مفضلتك القادمة.'); ?></h2></div><p><?php echo qbs_e('Explore. Compare. Make it yours.','اكتشفي. قارني. اختاري.'); ?></p></div>
      <form class="qbs-filters" data-qbs-filters action="<?php echo esc_url(qby_url('/beauty/')); ?>#qby-shop" method="get">
        <label class="qbs-search"><span class="qbs-sr"><?php echo qbs_e('Search beauty products','ابحثي عن منتجات الجمال'); ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg><input type="search" name="search" maxlength="100" placeholder="<?php echo esc_attr(qby_t('Search a product or brand…','ابحثي عن منتج أو علامة…')); ?>" value="<?php echo esc_attr($selection['search']); ?>" autocomplete="off"></label>
        <div class="qbs-filter-row">
        <label><?php echo qbs_e('Category','الفئة'); ?><select name="department"><?php echo qbs_filter_option('beauty','All beauty','كل الجمال',$selection['department']); foreach(qby_beauty_map() as $slug=>$d) { echo '<optgroup label="'.esc_attr(qby_t($d['en'],$d['ar'])).'">'.qbs_filter_option($slug,$d['en'],$d['ar'],$selection['department']);foreach($d['children'] as $child=>$label){echo qbs_filter_option($child,$label[0],$label[1],$selection['department']);}echo '</optgroup>'; } ?></select></label>
        <label><?php echo qbs_e('Brand','العلامة'); ?><select name="brand"><?php echo qbs_filter_option('','All brands','كل العلامات',$selection['brand']); foreach(qby_brand_directory() as $brand) { if(in_array('beauty',$brand['groups'],true)){echo qbs_filter_option($brand['slug'],$brand['name'],$brand['name'],$selection['brand']);} } ?></select></label>
        <label><?php echo qbs_e('Sort by','الترتيب'); ?><select name="orderby"><?php foreach(array('menu_order'=>array('The Qimia edit','مختارات كيميا'),'date'=>array('Newest first','الأحدث أولاً'),'popularity'=>array('Most purchased','الأكثر شراءً'),'price'=>array('Price: low to high','السعر: من الأقل للأعلى'),'price-desc'=>array('Price: high to low','السعر: من الأعلى للأقل')) as $key=>$label){echo qbs_filter_option($key,$label[0],$label[1],$selection['orderby']);} ?></select></label>
        <label class="qbs-stock"><input type="checkbox" name="stock" value="instock" <?php checked($selection['stock'],'instock'); ?>><?php echo qbs_e('In stock only','المتوفر فقط'); ?></label>
        <button class="qbs-button qbs-filter-submit" type="submit"><?php echo qbs_e('Find products','ابحثي'); ?><?php echo qbs_arrow(); ?></button>
        </div>
        <input type="hidden" name="collection" value="<?php echo esc_attr($selection['collection']); ?>"><input type="hidden" name="qby_page" value="1">
      </form>
      <nav class="qbs-collections" aria-label="<?php echo esc_attr(qby_t('Beauty collections','مجموعات الجمال')); ?>"><?php foreach(array('all'=>array('The edit','المختارات'),'bestsellers'=>array('Best sellers','الأكثر مبيعاً'),'new'=>array('New arrivals','وصل حديثاً'),'offers'=>array('Offers','العروض')) as $key=>$label) { $args=$selection;$args['collection']=$key;$args['qby_page']=1; ?><a href="<?php echo esc_url(add_query_arg($args,qby_url('/beauty/')).'#qby-shop'); ?>" data-qbs-collection="<?php echo esc_attr($key); ?>" <?php if($selection['collection']===$key){echo 'aria-current="true"';} ?>><?php echo qbs_e($label[0],$label[1]); ?></a><?php } ?><a class="qbs-reset" data-qbs-reset href="<?php echo esc_url(qby_url('/beauty/').'#qby-shop'); ?>"><?php echo qbs_e('Reset filters','مسح الفلاتر'); ?></a></nav>
      <p class="qbs-network-status qbs-sr" data-qbs-status role="status" aria-live="polite"></p>
      <div id="qbs-results" data-qbs-results><?php echo qbs_catalogue($selection); ?></div>
    </section>
    <?php return ob_get_clean();
}
function qbs_finder() {
    $groups=array(
        'skin-care'=>array('Skin care','العناية بالبشرة',array('face-cleansers'=>array('Cleanse','التنظيف'),'face-serums'=>array('Serums','السيروم'),'face-moisturisers'=>array('Moisturise','الترطيب'),'sun-care'=>array('Sun protection','الحماية من الشمس'),'face-masks'=>array('A mask moment','لحظة مع ماسك'))),
        'makeup'=>array('Makeup','المكياج',array('foundation'=>array('Find a base','كريم الأساس'),'blush-bronzer'=>array('Cheeks','الخدود'),'mascara'=>array('Lashes','الرموش'),'lip-makeup'=>array('Lips','الشفاه'),'setting-spray'=>array('Set the look','تثبيت المكياج'))),
        'hair-care'=>array('Hair care','العناية بالشعر',array('shampoo'=>array('Wash day','غسل الشعر'),'hair-mask'=>array('Hair masks','ماسكات الشعر'),'hair-oils'=>array('Hair oils','زيوت الشعر'),'scalp-care'=>array('Scalp care','فروة الرأس'),'hair-tools'=>array('Styling tools','أدوات التصفيف'))),
    );
    ob_start(); ?>
    <section class="qbs-ritual" id="qbs-ritual" data-qbs-reveal>
      <div class="qbs-ritual-copy"><span class="qbs-kicker"><?php echo qbs_e('YOUR BEAUTY, YOUR PACE','جمالك. على راحتك.'); ?></span><h2><?php echo qby_t('A little less searching.<br><em>A little more you.</em>','بحث أقل.<br><em>وقت أكثر لنفسك.</em>'); ?></h2><p><?php echo qbs_e('Choose where you want to begin. We’ll take you straight to that part of your routine.','اختاري من أين تبدئين، وسنأخذك مباشرة إلى هذه الخطوة من روتينك.'); ?></p><span class="qbs-ritual-mark" aria-hidden="true">q.</span></div>
      <div class="qbs-finder"><h3><?php echo qbs_e('What’s your beauty mood?','ما خطوتك اليوم؟'); ?></h3><p><?php echo qbs_e('01 / Pick your world','٠١ / اختاري عالمك'); ?></p><div class="qbs-finder-tabs" role="group" aria-label="<?php echo esc_attr(qby_t('Choose your routine','اختاري روتينك')); ?>"><?php $i=0;foreach($groups as $key=>$g){?><button type="button" data-qbs-ritual-tab="<?php echo esc_attr($key); ?>" aria-controls="qbs-path-<?php echo esc_attr($key); ?>" aria-pressed="<?php echo $i++===0?'true':'false'; ?>"><?php echo qbs_e($g[0],$g[1]); ?></button><?php } ?></div><p><?php echo qbs_e('02 / Choose your next step','٠٢ / اختاري خطوتك التالية'); ?></p><?php $i=0;foreach($groups as $key=>$g){?><div id="qbs-path-<?php echo esc_attr($key); ?>" class="qbs-finder-path" data-qbs-ritual-path="<?php echo esc_attr($key); ?>" <?php if($i++>0){echo 'hidden';} ?>><?php foreach($g[2] as $slug=>$label){?><a href="<?php echo esc_url(qbs_browse($slug)); ?>" data-qbs-department="<?php echo esc_attr($slug); ?>"><?php echo qbs_e($label[0],$label[1]); ?><?php echo qbs_arrow(); ?></a><?php } ?></div><?php } ?><noscript><p><?php echo qbs_e('Explore every step in the category directory below.','تصفحي جميع الخطوات في دليل الفئات أدناه.'); ?></p></noscript></div>
    </section>
    <?php return ob_get_clean();
}
function qbs_directory() {
    ob_start(); ?><section class="qbs-section qbs-directory" id="qbs-directory"><div class="qbs-section-head"><div><span class="qbs-kicker"><?php echo qbs_e('EVERY DETAIL, WITHIN REACH','كل التفاصيل بين يديك'); ?></span><h2><?php echo qbs_e('Find exactly your thing.','اعثري على ما يناسبك.'); ?></h2></div></div><div class="qbs-directory-grid"><?php foreach(qbs_departments() as $slug=>$d){?><details><summary><?php echo qbs_e($d[0],$d[1]); ?><span aria-hidden="true">+</span></summary><div><a href="<?php echo esc_url(qbs_browse($slug)); ?>"><?php echo qbs_e('Explore all','تصفحي الكل'); ?><?php echo qbs_arrow(); ?></a><?php foreach(array_slice($d,6) as $child){?><a href="<?php echo esc_url(qbs_browse($child)); ?>"><?php echo esc_html(qby_label($child)); ?></a><?php } ?></div></details><?php } ?><details><summary><?php echo qbs_e('Fragrance','العطور'); ?><span aria-hidden="true">+</span></summary><div><?php foreach(array('fragrance','perfume','body-mists','fragrance-sets','travel-fragrance') as $slug){?><a href="<?php echo esc_url(qbs_browse($slug)); ?>"><?php echo esc_html(qby_label($slug)); ?></a><?php } ?></div></details><details><summary><?php echo qbs_e('Beauty from within','الجمال من الداخل'); ?><span aria-hidden="true">+</span></summary><div><?php foreach(array('hair-nail-skin-health','collagens') as $slug){echo qby_link(qby_term_url($slug),qby_label($slug));} ?></div></details></div></section><?php return ob_get_clean();
}
function qbs_page() {
    // Empty initial searches still need native compare/AI docks after an AJAX reset.
    if (function_exists('qby_qil_print_docks')) { $GLOBALS['qby_qil_docks_needed'] = true; }
    $selection=qbs_selection();
    ob_start(); ?>
    <div class="qbs qbs-experience" dir="<?php echo qby_ar()?'rtl':'ltr'; ?>" data-qbs-experience data-qbs-language="<?php echo qby_ar()?'ar':'en'; ?>">
      <nav class="qbs-subnav" aria-label="<?php echo esc_attr(qby_t('Beauty navigation','تصفح الجمال')); ?>"><a class="qbs-wordmark" href="<?php echo esc_url(qby_url('/beauty/')); ?>">qimia<span>beauty</span><i>✳</i></a><div><a href="#qbs-world"><?php echo qbs_e('Explore','اكتشفي'); ?></a><a href="#qby-shop"><?php echo qbs_e('The edit','المختارات'); ?></a><a href="#qbs-ritual"><?php echo qbs_e('Find your ritual','اكتشفي روتينك'); ?></a></div><button type="button" class="qbs-motion" data-qbs-motion aria-pressed="false"><?php echo qbs_e('Pause motion','إيقاف الحركة'); ?><span aria-hidden="true">Ⅱ</span></button></nav>
      <section class="qbs-hero" aria-labelledby="qbs-title">
        <div class="qbs-hero-copy"><span class="qbs-kicker"><?php echo qbs_e('THE ART OF EVERYDAY BEAUTY','فن الجمال اليومي'); ?></span><h1 id="qbs-title"><?php echo qby_t('Your beauty.<br><em>Your ritual.</em>','جمالك.<br><em>روتينك.</em>'); ?></h1><p><?php echo qbs_e('Skin you care for. A look you love. Discover the little things that make you feel like you.','بشرة تعتنين بها. إطلالة تحبينها. اكتشفي التفاصيل الصغيرة التي تعبّر عنك.'); ?></p><div class="qbs-actions"><a class="qbs-button" href="#qby-shop"><?php echo qbs_e('Explore the beauty edit','اكتشفي مختارات الجمال'); ?><?php echo qbs_arrow(); ?></a><a class="qbs-text-link" href="#qbs-ritual"><?php echo qbs_e('Help me choose','ساعديني أختار'); ?> ↗</a></div><div class="qbs-hero-foot"><span><?php echo qbs_e('SKIN / MAKEUP / SELF-CARE','بشرة / مكياج / عناية'); ?></span><a href="#qbs-world" aria-label="<?php echo esc_attr(qby_t('Explore beauty categories','اكتشفي فئات الجمال')); ?>">↓</a></div></div>
        <div class="qbs-hero-visual" data-qbs-parallax><?php echo qbs_photo('hero','',true); ?><div class="qbs-hero-stamp" aria-hidden="true"><span>beauty in</span><em>every<br>detail.</em></div><span class="qbs-photo-caption"><?php echo qbs_e('A MOMENT. JUST FOR YOU.','لحظة. لك وحدك.'); ?></span></div>
      </section>
      <div class="qbs-editorial-line"><span><?php echo qbs_e('A little care goes a long way.','قليل من العناية يصنع الفرق.'); ?></span><span aria-hidden="true">✳</span><span><?php echo qbs_e('Make room for your ritual.','امنحي روتينك مساحة.'); ?></span><span aria-hidden="true">✳</span></div>
      <?php echo qbs_category_tiles(); ?>
      <section class="qbs-story" data-qbs-reveal><div class="qbs-story-art"><?php echo qbs_photo('skin'); ?><span class="qbs-story-caption">THE SKIN EDIT / 01</span></div><div class="qbs-story-copy"><span class="qbs-kicker"><?php echo qbs_e('START WITH SKIN','ابدئي بالبشرة'); ?></span><h2><?php echo qby_t('Less rush.<br><em>More ritual.</em>','تمهّلي.<br><em>واعتني بنفسك.</em>'); ?></h2><p><?php echo qbs_e('From the first cleanse to your daily SPF. Explore textures, compare formulas and find a place for every step.','من التنظيف إلى واقي الشمس اليومي. اكتشفي القوام وقارني التركيبات واختاري لكل خطوة مكانها.'); ?></p><div class="qbs-story-links"><?php foreach(array('face-cleansers'=>array('01 / Cleanse','٠١ / تنظيف'),'face-serums'=>array('02 / Serums','٠٢ / سيروم'),'face-moisturisers'=>array('03 / Moisturise','٠٣ / ترطيب'),'sun-care'=>array('04 / Sun care','٠٤ / حماية من الشمس')) as $slug=>$label){?><a href="<?php echo esc_url(qbs_browse($slug)); ?>" data-qbs-department="<?php echo esc_attr($slug); ?>"><?php echo qbs_e($label[0],$label[1]); ?><?php echo qbs_arrow(); ?></a><?php } ?></div></div></section>
      <?php echo qbs_shop($selection); echo qbs_finder(); ?>
      <section class="qbs-notes qbs-section" aria-labelledby="qbs-notes-title"><div class="qbs-section-head"><div><span class="qbs-kicker"><?php echo qbs_e('BEAUTY, MADE CLEAR','الجمال بوضوح'); ?></span><h2 id="qbs-notes-title"><?php echo qbs_e('The details worth knowing.','تفاصيل تستحق المعرفة.'); ?></h2></div></div><div class="qbs-notes-grid"><article data-qbs-reveal><span>01</span><h3><?php echo qbs_e('Your shade. Your finish.','درجتك. لمستك.'); ?></h3><p><?php echo qbs_e('Compare the exact shade, coverage and finish. Check each variant’s own details before adding it to your bag.','قارني الدرجة والتغطية واللمسة النهائية، وراجعي تفاصيل كل خيار قبل إضافته إلى سلتك.'); ?></p><a class="qbs-text-link" href="<?php echo esc_url(qbs_browse('foundation')); ?>"><?php echo qbs_e('Explore complexion','اكتشفي مكياج الوجه'); ?><?php echo qbs_arrow(); ?></a></article><article data-qbs-reveal><span>02</span><h3><?php echo qbs_e('Know what’s in it.','اعرفي مكوناته.'); ?></h3><p><?php echo qbs_e('Read the product’s own ingredient list and directions. Formulas can vary between sizes, shades and markets.','اقرئي قائمة مكونات المنتج وإرشاداته. قد تختلف التركيبة حسب الحجم أو الدرجة أو السوق.'); ?></p><a class="qbs-text-link" href="#qby-shop"><?php echo qbs_e('Compare the details','قارني التفاصيل'); ?><?php echo qbs_arrow(); ?></a></article><article data-qbs-reveal><span>03</span><h3><?php echo qbs_e('A guide by your side.','دليل بجانبك.'); ?></h3><p><?php echo qbs_e('Ask Qimia AI about the products you’re exploring, in English or Arabic. Start with a question, take your time.','اسألي ذكاء كيميا عن المنتجات التي تتصفحينها، بالعربية أو الإنجليزية. ابدئي بسؤال وخذي وقتك.'); ?></p><button class="qbs-text-link" type="button" data-qimia-ai-open><?php echo qbs_e('Ask Qimia AI','اسألي ذكاء كيميا'); ?><?php echo qbs_arrow(); ?></button></article></div></section>
      <?php echo qbs_directory(); ?>
      <section class="qbs-closing"><span class="qbs-kicker"><?php echo qbs_e('ONE QIMIA. ALL OF YOU.','كيميا واحدة. لكل تفاصيلك.'); ?></span><h2><?php echo qby_t('Beauty is personal.<br><em>Make yourself at home.</em>','الجمال على طريقتك.<br><em>أهلاً بك في عالمك.</em>'); ?></h2><div class="qbs-actions"><a class="qbs-button" href="<?php echo esc_url(qby_url('/brands/')); ?>"><?php echo qbs_e('Discover the brands','اكتشفي العلامات'); ?><?php echo qbs_arrow(); ?></a><a class="qbs-text-link" href="<?php echo esc_url(qby_url('/my-account/')); ?>"><?php echo qbs_e('Your Qimia account','حسابك في كيميا'); ?> ↗</a></div></section>
    </div>
    <?php return ob_get_clean();
}
function qbs_home() {
    // No extra product query: native product shelves remain owned by the existing Beauty integration.
    ?>
    <section class="qbs qbs-home" id="qbs-home" dir="<?php echo qby_ar()?'rtl':'ltr'; ?>" aria-labelledby="qbs-home-title" data-qbs-experience data-qbs-language="<?php echo qby_ar()?'ar':'en'; ?>">
      <div class="qbs-home-top"><span class="qbs-kicker"><?php echo qbs_e('A NEW SIDE OF QIMIA','جانب جديد من كيميا'); ?></span><a href="<?php echo esc_url(qby_url('/beauty/')); ?>" class="qbs-text-link"><?php echo qbs_e('Enter Qimia Beauty','ادخلي عالم كيميا للجمال'); ?><?php echo qbs_arrow(); ?></a></div>
      <div class="qbs-home-scene" data-qbs-reveal><div class="qbs-home-copy"><span class="qbs-wordmark">qimia<span>beauty</span><i>✳</i></span><h2 id="qbs-home-title"><?php echo qby_t('A little care.<br><em>A whole new feeling.</em>','قليل من العناية.<br><em>شعور جديد بالكامل.</em>'); ?></h2><p><?php echo qbs_e('Your everyday beauty ritual starts here. Skin, makeup and self-care, in one beautiful place.','روتين جمالك اليومي يبدأ هنا. بشرة ومكياج وعناية، في مكان واحد.'); ?></p><a class="qbs-button" href="<?php echo esc_url(qby_url('/beauty/')); ?>"><?php echo qbs_e('Discover your beauty ritual','اكتشفي روتين جمالك'); ?><?php echo qbs_arrow(); ?></a></div><div class="qbs-home-visual" data-qbs-parallax><?php echo qbs_photo('hero'); ?><span class="qbs-home-stamp" aria-hidden="true">q.</span></div></div>
      <nav class="qbs-home-categories" aria-label="<?php echo esc_attr(qby_t('Beauty categories','فئات الجمال')); ?>"><?php foreach(qbs_departments() as $slug=>$d){?><a href="<?php echo esc_url(qbs_browse($slug)); ?>"><?php echo qbs_e($d[0],$d[1]); ?><span aria-hidden="true">↗</span></a><?php } ?></nav>
    </section>
    <?php
}
