<?php
defined('ABSPATH') || exit;
function qby_ar() {
    if (function_exists('qil_view_context')) { return (bool) qil_view_context()['isArabic']; }
    return (bool) preg_match('~^/ar(?:/|$)~', (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
}
function qby_t($en, $ar) { return qby_ar() ? $ar : $en; }
function qby_query_value($key, $default='') {
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? wp_unslash((string)$_GET[$key]) : $default;
}
function qby_url($path = '/') {
    $url = home_url($path);
    return function_exists('qil_localized_url') ? qil_localized_url($url, qby_ar()) : $url;
}
function qby_term_url($slug, $taxonomy = 'product_cat') {
    $term = get_term_by('slug', $slug, $taxonomy);
    if (!$term || is_wp_error($term)) { return ''; }
    $url = get_term_link($term);
    return is_wp_error($url) ? '' : (function_exists('qil_localized_url') ? qil_localized_url($url, qby_ar()) : $url);
}
function qby_class($id) { return (string) get_post_meta((int) $id, '_qby_product_class', true); }
function qby_seed() { static $data; if ($data === null) { $data = json_decode(file_get_contents(QBY_DIR . 'includes/products.json'), true) ?: array(); } return $data; }
/** Bundled legacy facts can only match the reviewed product, never an unrelated reused ID. */
function qby_seed_record($id) {
    $row=qby_seed()[(int)$id]??array();
    return $row && get_post_field('post_name',(int)$id)===(string)($row['slug']??'') ? $row : array();
}
function qby_terms() {return qby_beauty_terms();}
function qby_concerns() {
    return array('all'=>array('All needs','كل الاحتياجات'), 'dry'=>array('Dry & damaged hair','الشعر الجاف والتالف'), 'oily'=>array('Oily roots','الجذور الدهنية'), 'curly'=>array('Curls & waves','الشعر المجعد والمموج'), 'volume'=>array('Volume & texture','الكثافة والملمس'), 'scalp'=>array('Scalp care','العناية بفروة الرأس'));
}
function qby_groups() {
    return array(
        'sports'=>array('Sports Nutrition','التغذية الرياضية',array('proteins','creatines-oman','amino-acids-oman','pre-workout','weight-gainers','fat-burners','carb','hydrations')),
        'wellness'=>array('Vitamins & Wellness','الفيتامينات والعافية',array('vitamin-herbals-minerals','vitamin-c','multivitamins-oman','magnesium-oman','omega-3','vitamin-d3','zinc','gut-health','melatonin-oman','herbal-extracts','joint-support','greens','men-vitamins','vitamins-women','sex-products','testosterone-boosters')),
        'beauty'=>array('Beauty & Personal Care','الجمال والعناية',array_keys(qby_beauty_map())),
        'inside'=>array('Beauty Supplements','مكملات الجمال',array('collagens','hair-nail-skin-health')),
        'food'=>array('Food & Snacks','الأطعمة والوجبات الخفيفة',array('bars')),
        'accessories'=>array('Accessories','الإكسسوارات',array('accessories')),
    );
}
function qby_label($slug) {
    $labels = qby_terms();
    $labels += array('hair-nail-skin-health'=>array('Hair, Skin & Nails Supplements','مكملات الشعر والبشرة والأظافر'), 'collagens'=>array('Collagen','الكولاجين'), 'protein-whey'=>array('Whey Protein','بروتين مصل اللبن'), 'isolate-whey-protein'=>array('Isolate Protein','البروتين المعزول'), 'creatines-oman'=>array('Creatine','الكرياتين'), 'weight-gainers'=>array('Mass Gainers','زيادة الوزن'), 'pre-workout'=>array('Pre-Workout','قبل التمرين'), 'amino-acids-oman'=>array('Amino Acids','الأحماض الأمينية'), 'multivitamins-oman'=>array('Multivitamins','الفيتامينات المتعددة'), 'magnesium-oman'=>array('Magnesium','المغنيسيوم'), 'omega-3'=>array('Omega-3','أوميغا ٣'), 'vitamin-d3'=>array('Vitamin D','فيتامين د'), 'zinc'=>array('Zinc','الزنك'), 'gut-health'=>array('Gut Health','صحة الأمعاء'), 'herbal-extracts'=>array('Herbal Supplements','المكملات العشبية'), 'flash-sale'=>array('Flash Sale','العروض السريعة'), 'staks-offer'=>array('Bundles','الباقات'), 'offers'=>array('Offers','العروض'));
    $labels+=array('proteins'=>array('Proteins','البروتينات'),'vitamin-herbals-minerals'=>array('Vitamins, Minerals & Herbs','الفيتامينات والمعادن والأعشاب'),'vitamin-c'=>array('Vitamin C','فيتامين ج'),'greens'=>array('Greens','الخضروات الخضراء'),'carb'=>array('Carbohydrates','الكربوهيدرات'),'hydrations'=>array('Hydration & Electrolytes','الترطيب والإلكتروليتات'),'fat-burners'=>array('Weight Management','إدارة الوزن'),'melatonin-oman'=>array('Melatonin','الميلاتونين'),'sex-products'=>array('Sexual Wellness','الصحة الجنسية'),'testosterone-boosters'=>array('Testosterone Support','دعم التستوستيرون'),'accessories'=>array('Accessories','الإكسسوارات'),'others'=>array('More Essentials','أساسيات أخرى'),'beef-protein'=>array('Beef Protein','بروتين اللحم'),'casein'=>array('Casein','الكازين'),'protein-blend'=>array('Protein Blends','خلطات البروتين'),'bcaa-oman'=>array('BCAA','الأحماض الأمينية متفرعة السلسلة'),'eaa'=>array('EAA','الأحماض الأمينية الأساسية'),'amino-powder-oman'=>array('Amino Powders','مساحيق الأحماض الأمينية'),'amino-tablet'=>array('Amino Tablets','أقراص الأحماض الأمينية'),'arginine-oman'=>array('Arginine','الأرجينين'),'glutamine-oman'=>array('Glutamine','الجلوتامين'),'citrulline'=>array('Citrulline','السيترولين'),'ashwagandha'=>array('Ashwagandha','الأشواغاندا'),'joint-support'=>array('Joint Support','دعم المفاصل'),'men-vitamins'=>array('Men’s Vitamins','فيتامينات الرجال'),'vitamins-women'=>array('Women’s Vitamins','فيتامينات النساء'),'caffeine-pro-preworkouts'=>array('Caffeine Pre-Workout','قبل التمرين مع الكافيين'),'free-stim-pre-workout'=>array('Stimulant-Free Pre-Workout','قبل التمرين بدون منبهات'),'beginners-pre-workout'=>array('Beginner Pre-Workout','قبل التمرين للمبتدئين'),'pump-nitric-oxide'=>array('Pump & Nitric Oxide','الضخ وأكسيد النيتريك'));
    if (isset($labels[$slug])) { return qby_t($labels[$slug][0], $labels[$slug][1]); }
    $term = get_term_by('slug', $slug, 'product_cat'); return $term ? html_entity_decode($term->name, ENT_QUOTES, 'UTF-8') : $slug;
}
function qby_products($args = array()) {
    $defaults = array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>48,'fields'=>'ids','no_found_rows'=>true,'orderby'=>'menu_order title','order'=>'ASC', 'tax_query'=>array(array('taxonomy'=>'product_cat','field'=>'slug','terms'=>array('beauty'))));
    $query = new WP_Query(array_merge($defaults, $args));
    return array_values(array_filter(array_map('wc_get_product', $query->posts), static function($p) { return $p && !$p->get_parent_id() && !post_password_required($p->get_id()) && in_array($p->get_catalog_visibility(),array('visible','catalog'),true); }));
}
function qby_product_link($id) { $url=get_permalink($id); return function_exists('qil_localized_url') ? qil_localized_url($url,qby_ar()) : $url; }
function qby_link($url, $label, $class = '') { return $url ? '<a class="'.esc_attr($class).'" href="'.esc_url($url).'">'.esc_html($label).'</a>' : ''; }

function qby_category_tree() {
    static $terms=null;if($terms!==null){return $terms;}
    $terms=get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'orderby'=>'name'));
    if(is_wp_error($terms)){$terms=array();}return $terms;
}
function qby_tree_branch($term,$depth=0) {
    if($depth>5){return '';}$children=array_filter(qby_category_tree(),static function($t)use($term){return (int)$t->parent===(int)$term->term_id;});
    $link=qby_link(qby_term_url($term->slug),qby_label($term->slug));
    if(!$children){return '<div class="qby-tree-leaf">'.$link.'</div>';}
    $out='<details class="qby-tree"><summary>'.esc_html(qby_label($term->slug)).'<span aria-hidden="true">+</span></summary><div>'.$link;
    foreach($children as $child){$out.=qby_tree_branch($child,$depth+1);}return $out.'</div></details>';
}
function qby_beauty_context() {
    if(!is_product_category()){return false;}$term=get_queried_object();$root=get_term_by('slug','beauty','product_cat');
    return $root && !is_wp_error($root) && $term && !is_wp_error($term) && ((int)$term->term_id===(int)$root->term_id || in_array((int)$root->term_id,array_map('intval',get_ancestors($term->term_id,'product_cat')),true));
}
