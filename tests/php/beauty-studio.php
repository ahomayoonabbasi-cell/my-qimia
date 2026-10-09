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
require __DIR__.'/../../qimia-beauty-studio/qimia-beauty-studio.php';
function assert_true($test,$message){if(!$test){throw new Exception($message);}}
if($_SERVER['HTTP_HOST']!=='qimialab.qimia.om'||$home!=='qimialab.qimia.om'){
  assert_true(!$hooks,'Non-staging host must register zero hooks');echo "PASS: no hooks on unapproved host/configuration\n";exit;
}
foreach($hooks['wp'] as $hook){$hook();}
assert_true($shortcodes['qimia_beauty']==='qbs_page','Shortcode ownership');
assert_true(!isset($hooks['init'])&&!isset($hooks['wp_ajax_qbs_catalogue']),'No migrations or public endpoint');
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
function qby_brand_directory(){return array();}
function qby_label($s){return ucwords(str_replace('-',' ',$s));}
function qby_link($u,$l){return '<a href="'.esc_url($u).'">'.esc_html($l).'</a>';}
function qby_term_url($s){return '/product-category/'.$s.'/';}
function qby_department_icon($s){return '<svg viewBox="0 0 96 88"><circle cx="48" cy="44" r="22" fill="none" stroke="currentColor"/></svg>';}
$_GET=array('department'=>array('makeup'),'search'=>array('bad'),'qby_page'=>-8,'orderby'=>'bad');
$s=qbs_selection();assert_true($s['department']==='beauty'&&$s['search']===''&&$s['qby_page']===1&&$s['orderby']==='menu_order','Malformed request sanitation');
$_GET=array('search'=>'<script>alert(1)</script>','collection'=>'unverified-trend');$s=qbs_selection();assert_true(strpos($s['search'],'<')===false&&$s['collection']==='all','HTML and collection sanitation');
foreach(array('en','ar') as $lang){
 $_GET=$lang==='ar'?array('ar'=>1):array();$html=qbs_page();
 assert_true(!empty($GLOBALS['qby_qil_docks_needed']),'Empty catalogue reserves native docks for async results');
 assert_true(substr_count($html,'<h1 ')===1,'Exactly one H1');
 assert_true(substr_count($html,'id="qby-shop"')===1,'Stable shop anchor');
 assert_true(str_contains($html,'data-qbs-results')&&str_contains($html,'data-qbs-motion'),'Catalogue and motion controls');
 assert_true(str_contains($html,'dir="'.($lang==='ar'?'rtl':'ltr').'"'),'Direction');
 assert_true(str_contains($html,'application/json')===false,'Empty catalogue has no fabricated products');
 if(getenv('QBS_PREVIEW_DIR')){file_put_contents(getenv('QBS_PREVIEW_DIR').'/'.$lang.'.html','<!doctype html><html lang="'.$lang.'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/qimia-beauty-studio/assets/studio.css"><style>body{margin:0;background:#f7f4ef}</style><body>'.$html.'<script src="/qimia-beauty-studio/assets/studio.js"></script></body></html>');}
}
echo "PASS: staging guard, input sanitation, no writes, English/Arabic rendering, single H1, empty states and controls\n";
