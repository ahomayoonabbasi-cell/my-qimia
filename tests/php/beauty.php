<?php
// Isolated staging guard and rendering contract tests; no database or remote writes.
define('ABSPATH',__DIR__.'/');
$_SERVER['HTTP_HOST']=$argv[1]??'qimialab.qimia.om';
$_SERVER['REQUEST_URI']='/beauty/';
$home=$argv[2]??'qimialab.qimia.om';$hooks=array();$shortcodes=array();
function get_option($key){global $home;return 'https://'.$home;}
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function plugin_dir_path($path){return dirname($path).'/';}
function plugin_dir_url($path){return '/qimia-beauty-studio/';}
function add_action($tag,$callback,$priority=10){global $hooks;$hooks[$tag][]=$callback;}
function add_filter($tag,$callback,$priority=10){add_action($tag,$callback,$priority);}
function add_shortcode($tag,$callback){global $shortcodes;$shortcodes[$tag]=$callback;}
function qby_beauty_page(){}
function qby_beauty_query($input){return array('products'=>array(),'total'=>0,'pages'=>0,'page'=>1);}
function qby_cards($products){return '';}
function qby_qil_print_docks(){}
define('QBY_URL','/qimia-beauty/');
function assert_true($test,$message){if(!$test){throw new Exception($message);}}
require __DIR__.'/../../qimia-beauty/includes/experience.php';
function qby_ar(){return isset($_GET['ar']);}
function qby_t($en,$ar){return qby_ar()?$ar:$en;}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function qby_url($path){return (qby_ar()?'/ar':'').$path;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function selected($a,$b,$echo=true){$s=(string)$a===(string)$b?'selected':'';if($echo){echo $s;}return $s;}
function checked($a,$b){if($a===$b){echo 'checked';}}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function qby_query_value($key,$default=''){return isset($_GET[$key])&&is_scalar($_GET[$key])?(string)$_GET[$key]:$default;}
function number_format_i18n($n){return (string)$n;}
function qby_beauty_map(){return array('skin-care'=>array('en'=>'Skin care','ar'=>'العناية بالبشرة','children'=>array('face-serums'=>array('Serums','السيروم'),'sun-care'=>array('Sun care','الحماية من الشمس'))),'makeup'=>array('en'=>'Makeup','ar'=>'المكياج','children'=>array('foundation'=>array('Foundation','كريم الأساس'))),'hair-care'=>array('en'=>'Hair care','ar'=>'العناية بالشعر','children'=>array('shampoo'=>array('Shampoo','الشامبو'))));}
function qby_beauty_terms(){return array_fill_keys(array('beauty','skin-care','face-serums','sun-care','makeup','foundation','hair-care','shampoo'),true);}
function qby_beauty_facets(){return array();}
function qby_brand_directory(){return array();}
function qby_label($s){return ucwords(str_replace('-',' ',$s));}
function qby_link($u,$l){return '<a href="'.esc_url($u).'">'.esc_html($l).'</a>';}
function qby_term_url($s){return '/product-category/'.$s.'/';}
function qby_department_icon($s){return '<svg viewBox="0 0 96 88"><circle cx="48" cy="44" r="22" fill="none" stroke="currentColor"/></svg>';}
$_GET=array('department'=>array('makeup'),'search'=>array('bad'),'qby_page'=>-8,'orderby'=>'bad');
$s=qbx_selection();assert_true($s['department']==='beauty'&&$s['search']===''&&$s['qby_page']===1&&$s['orderby']==='menu_order','Malformed request sanitation');
$_GET=array('search'=>'<script>alert(1)</script>','collection'=>'unverified-trend');$s=qbx_selection();assert_true(strpos($s['search'],'<')===false&&$s['collection']==='all','HTML and collection sanitation');
foreach(array('en','ar') as $lang){
 $_GET=$lang==='ar'?array('ar'=>1):array();$html=qbx_page();
 assert_true(!empty($GLOBALS['qby_qil_docks_needed']),'Empty catalogue reserves native docks for async results');
 assert_true(strpos($html,'qbx-hero-visual')<strpos($html,'qbx-hero-copy'),'Image-first document order');
 assert_true(substr_count($html,'<h1 ')===1,'Exactly one H1');
 assert_true(substr_count($html,'id="qby-shop"')===1,'Stable shop anchor');
 assert_true(str_contains($html,'data-qbx-results')&&str_contains($html,'data-qbx-motion'),'Catalogue and motion controls');
 assert_true(str_contains($html,'dir="'.($lang==='ar'?'rtl':'ltr').'"'),'Direction');
 assert_true(str_contains($html,'application/json')===false,'Empty catalogue has no fabricated products');
 if(getenv('QBY_PREVIEW_DIR')){file_put_contents(getenv('QBY_PREVIEW_DIR').'/'.$lang.'.html','<!doctype html><html lang="'.$lang.'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/qimia-beauty/assets/experience.css"><style>body{margin:0;background:#f7fcfb}</style><body>'.$html.'<script src="/qimia-beauty/assets/experience.js"></script></body></html>');}
}
echo "PASS: input sanitation, no writes, image-first hero, English/Arabic rendering, single H1, empty states and controls\n";

// The knowledge component never renders on supplements and never invents a translation.
function qby_uses_beauty_ui($id){return $id===10;}
function qby_details($id){return array('size'=>'50 ml','inci'=>'Aqua, Glycerin','directions_en'=>'Apply to damp hair.','hair_type'=>'Dry hair','source_url'=>'https://example.com/label');}
function qby_product_department($id){return 'hair-care';}
function qby_product_type_label($id){return qby_t('Hair care','العناية بالشعر');}
function qby_beauty_field_schema($d=''){return array('hair_type'=>array('en'=>'Hair type','ar'=>'نوع الشعر'));}
function wc_get_product($id){return new class {function is_type($t){return $t==='variable';}};}
function qby_browse_url($d){return '/beauty/?department='.$d;}
require __DIR__.'/../../qimia-beauty/includes/product-knowledge.php';
assert_true(qby_knowledge_render(11)==='','Supplement does not receive Beauty boxes');
$_GET=array();$html=qby_knowledge_render(10);assert_true(str_contains($html,'Beauty facts')&&str_contains($html,'Your Qimia guide')&&str_contains($html,'Apply to damp hair.'),'English facts and guide');
assert_true(str_contains($html,'individual shades and sets may have different formulas'),'Variant scope notice');
$_GET=array('ar'=>1);$html=qby_knowledge_render(10);assert_true(str_contains($html,'مواصفات الجمال')&&str_contains($html,'دليلك من كيميا'),'Arabic headings');
assert_true(!str_contains($html,'Apply to damp hair.')&&!str_contains($html,'Dry hair'),'Missing Arabic never silently falls back to English');
assert_true(str_contains($html,'lang="en" dir="ltr"'),'Canonical INCI direction on Arabic');
echo "PASS: supplement isolation, bilingual knowledge, missing-translation fallback, variant notice, INCI direction\n";
