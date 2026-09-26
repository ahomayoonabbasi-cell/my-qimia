<?php
/** Hero Studio: local media and bounded positioning; no frontend editor library. */
defined( 'ABSPATH' ) || exit;
function qil_hero_defaults() {
 return array('desktop_id'=>0,'mobile_id'=>0,'width'=>72,'height'=>96,'left'=>-3,'bottom'=>-2,'mobile_width'=>76,'mobile_height'=>88,'mobile_left'=>-5,'mobile_bottom'=>0);
}
function qil_hero_sanitize( $input ) {
 $defaults=qil_hero_defaults(); if(!is_array($input))return $defaults;
 if (!empty($input['reset'])) return $defaults;
 $out=$defaults;
 foreach(array('desktop_id','mobile_id') as $key){
  $raw=$input[$key]??0; $id=is_scalar($raw)&&is_numeric($raw)?absint($raw):0;
  $out[$key]=$id && wp_attachment_is_image($id)?$id:0;
 }
 foreach(array('width','height','left','bottom','mobile_width','mobile_height','mobile_left','mobile_bottom') as $key){
  $v=$input[$key]??$defaults[$key]; if(!is_scalar($v)||!is_numeric($v)||!is_finite((float)$v))continue;
  $size=false!==strpos($key,'width')||false!==strpos($key,'height');
  $out[$key]=round(max($size?20:-45,min($size?130:45,(float)$v)),1);
 }
 return $out;
}
function qil_hero_settings() {
 static $settings=null;
 if(null===$settings){
  $raw=get_option('qil_hero_studio',array());
  $settings=qil_hero_sanitize(is_array($raw)?$raw:array());
 }
 return $settings;
}
function qil_hero_register_setting(){register_setting('qil_hero_settings','qil_hero_studio',array('type'=>'array','sanitize_callback'=>'qil_hero_sanitize','default'=>qil_hero_defaults()));}
add_action('admin_init','qil_hero_register_setting');
function qil_hero_image_data($id,$mobile=false){
 if($id){$image=wp_get_attachment_image_src($id,'full');if($image)return array('url'=>$image[0],'width'=>(int)$image[1],'height'=>(int)$image[2],'srcset'=>(string)wp_get_attachment_image_srcset($id,'full'));}
 return array('url'=>QIL_URL.'assets/'.($mobile?'qimia-couple-ai-v14-640.webp':'qimia-couple-ai-v14-1448.webp').'?v='.QIL_VERSION,'width'=>$mobile?640:1448,'height'=>$mobile?480:1086,'srcset'=>QIL_URL.'assets/qimia-couple-ai-v14-640.webp?v='.QIL_VERSION.' 640w, '.QIL_URL.'assets/qimia-couple-ai-v14-960.webp?v='.QIL_VERSION.' 960w, '.QIL_URL.'assets/qimia-couple-ai-v14-1448.webp?v='.QIL_VERSION.' 1448w');
}
function qil_hero_primary_image_alt(){
 $language=function_exists('qil_language_context')?qil_language_context():array('isArabic'=>false);
 return !empty($language['isArabic'])
  ? 'رجل وامرأة مع مكملات كيميا في عُمان'
  : 'Man and woman with Qimia supplements in Oman';
}
function qil_hero_primary_image_data(){
 $s=qil_hero_settings();
 $image=qil_hero_image_data((int)$s['desktop_id']);
 // Keep the SEO image URL stable across plugin version bumps. The storefront
 // may cache-bust the same bundled file with ?v=, but search engines should
 // see one persistent image URL. Uploaded Media Library images are unchanged.
 if(empty($s['desktop_id']) && !empty($image['url']))$image['url']=remove_query_arg('v',$image['url']);
 return $image;
}
function qil_hero_avatar_markup(){
 $s=qil_hero_settings(); $desktop=qil_hero_image_data($s['desktop_id']);
 // A custom desktop image is also the mobile default, never an unrelated built-in.
 $mobile=qil_hero_image_data($s['mobile_id']?:$s['desktop_id'],!$s['desktop_id']);
 $style='';foreach(array('width','height','left','bottom') as $key){$style.='--qil-avatar-'.$key.':'.$s[$key].'%;--qil-avatar-mobile-'.$key.':'.$s['mobile_'.$key].'%;';}
 $srcset=$desktop['srcset']?:($desktop['url'].' '.$desktop['width'].'w');
 $mobile_set=$mobile['srcset']?:($mobile['url'].' '.$mobile['width'].'w');
 // The positioned element stays the IMG so its percentage geometry matches the old slot.
 return '<picture class="qil-hero-avatar-picture" style="'.esc_attr($style).'"><source media="(max-width:680px)" srcset="'.esc_attr($mobile_set).'" sizes="76vw"><img class="qil-hero-depth-avatar" data-qil-hero-avatar src="'.esc_url($desktop['url']).'" srcset="'.esc_attr($srcset).'" sizes="(max-width:980px) 76vw, 540px" width="'.esc_attr($desktop['width']).'" height="'.esc_attr($desktop['height']).'" alt="'.esc_attr(qil_hero_primary_image_alt()).'" loading="eager" decoding="async"></picture>';
}

/**
 * Search/social primary-image hints for the two Qimia home routes only.
 * Titles, descriptions, canonicals, hreflang, sitemap and indexability are not
 * touched. Rank Math remains the metadata authority; these filters only replace
 * the homepage image candidate with the visible couple already in the hero.
 */
function qil_hero_is_public_search_home(){
 if(is_admin()||wp_doing_ajax()||(defined('REST_REQUEST')&&REST_REQUEST))return false;
 if(function_exists('qil_is_staging_sandbox')&&qil_is_staging_sandbox())return false;
 return function_exists('qil_should_render')&&qil_should_render();
}
function qil_hero_rank_math_social_image($url){
 if(!qil_hero_is_public_search_home())return $url;
 $image=qil_hero_primary_image_data();
 return !empty($image['url'])?esc_url_raw($image['url']):$url;
}
add_filter('rank_math/opengraph/facebook/image','qil_hero_rank_math_social_image',99);
add_filter('rank_math/opengraph/twitter/image','qil_hero_rank_math_social_image',99);

function qil_hero_rank_math_robots($robots){
 if(!qil_hero_is_public_search_home()||!is_array($robots))return $robots;
 $robots['max-image-preview']='max-image-preview:large';
 return $robots;
}
add_filter('rank_math/frontend/robots','qil_hero_rank_math_robots',99);

function qil_hero_schema_has_webpage_type($type){
 foreach((array)$type as $candidate){
  if(in_array((string)$candidate,array('WebPage','CollectionPage','ProfilePage'),true))return true;
 }
 return false;
}
function qil_hero_rank_math_json_ld($data,$jsonld=null){
 if(!qil_hero_is_public_search_home()||!is_array($data))return $data;
 $image=qil_hero_primary_image_data();
 if(empty($image['url']))return $data;
 $context=function_exists('qil_view_context')?qil_view_context():array();
 $home=!empty($context['homeUrl'])?(string)$context['homeUrl']:home_url('/');
 $image_id=untrailingslashit($home).'#qimia-home-hero-image';
 $image_object=array(
  '@type'=>'ImageObject',
  '@id'=>$image_id,
  'url'=>esc_url_raw($image['url']),
  'contentUrl'=>esc_url_raw($image['url']),
  'width'=>(int)($image['width']??0),
  'height'=>(int)($image['height']??0),
  'caption'=>qil_hero_primary_image_alt(),
 );
 $matched=false;
 foreach($data as $key=>&$entity){
  if(!is_array($entity)||empty($entity['@type'])||!qil_hero_schema_has_webpage_type($entity['@type']))continue;
  $entity['primaryImageOfPage']=array('@id'=>$image_id);
  $entity['image']=array('@id'=>$image_id);
  $matched=true;
 }
 unset($entity);
 if($matched)$data['QimiaHomeHeroImage']=$image_object;
 return $data;
}
add_filter('rank_math/json_ld','qil_hero_rank_math_json_ld',99,2);

function qil_hero_search_image_hint(){
 if(!qil_hero_is_public_search_home())return;
 $image=qil_hero_primary_image_data();
 if(empty($image['url']))return;
 printf('<link rel="image_src" href="%s">'."\n",esc_url($image['url']));
}
add_action('wp_head','qil_hero_search_image_hint',3);
function qil_hero_admin_assets($hook){
 if('settings_page_qimia-intelligence-lab'!==$hook || !current_user_can('manage_options'))return;
 wp_enqueue_media();
 wp_enqueue_script('qimia-lab-hero-admin',QIL_URL.'assets/qil-hero-admin.js',array('jquery','media-editor'),QIL_VERSION,true);
 wp_add_inline_script('qimia-lab-hero-admin','window.QIL_HERO_STUDIO='.wp_json_encode(array('defaults'=>qil_hero_defaults(),'desktop'=>qil_hero_image_data(0),'mobile'=>qil_hero_image_data(0,true)),JSON_HEX_TAG|JSON_HEX_AMP).';','before');
 wp_enqueue_style('qimia-lab-hero-admin',QIL_URL.'assets/qil-hero-admin.css',array(),QIL_VERSION);
}
add_action('admin_enqueue_scripts','qil_hero_admin_assets');
function qil_hero_studio_settings_form(){
 if(!current_user_can('manage_options'))return;
 $s=qil_hero_settings();$image=qil_hero_image_data($s['desktop_id']);
 ?>
 <section class="qil-hero-studio" id="qil-hero-studio">
 <h2>Hero Studio · استوديو الهيرو</h2>
 <p>Keep the original hero slot. Change the image or fine-tune its position without changing the homepage layout. Transparent WebP is recommended. No automatic upscaling or image processing runs on page views.</p>
 <form method="post" action="options.php">
 <?php settings_fields('qil_hero_settings'); ?>
 <div class="qil-hero-admin-grid"><div>
 <?php foreach(array('desktop_id'=>'Desktop image · صورة الكمبيوتر','mobile_id'=>'Mobile image · صورة الجوال') as $key=>$label):$im=qil_hero_image_data($s[$key],$key==='mobile_id'); ?>
 <div class="qil-hero-media-row"><strong><?php echo esc_html($label); ?></strong>
 <input type="hidden" id="qil-hero-<?php echo esc_attr($key); ?>" name="qil_hero_studio[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($s[$key]); ?>">
 <button type="button" class="button" data-qil-hero-media="<?php echo esc_attr($key); ?>">Choose / Replace</button>
 <button type="button" class="button" data-qil-hero-clear="<?php echo esc_attr($key); ?>">Use default</button>
 <span data-qil-hero-name="<?php echo esc_attr($key); ?>"><?php echo $s[$key]?esc_html(get_the_title($s[$key])):'Built-in / default'; ?></span></div>
 <?php endforeach; ?>
 <p class="description">Mobile empty = selected desktop image; with both empty, the bundled desktop/mobile WebP files are used.</p>
 <?php foreach(array('desktop'=>'Desktop · الكمبيوتر','mobile'=>'Mobile / tablet · الجوال') as $mode=>$label): ?>
 <fieldset><legend><strong><?php echo esc_html($label); ?></strong></legend>
 <?php foreach(array('width'=>'Width / العرض','height'=>'Height / الارتفاع','left'=>'Left offset / الإزاحة من اليسار','bottom'=>'Bottom offset / الإزاحة من الأسفل') as $field=>$text):$key=('mobile'===$mode?'mobile_':'').$field;$size=in_array($field,array('width','height'),true); ?>
 <label class="qil-hero-field" for="qil-hero-<?php echo esc_attr($key); ?>"><span><?php echo esc_html($text); ?> (%)</span><input id="qil-hero-<?php echo esc_attr($key); ?>" data-qil-hero-number="<?php echo esc_attr($key); ?>" name="qil_hero_studio[<?php echo esc_attr($key); ?>]" type="number" min="<?php echo $size?'20':'-45'; ?>" max="<?php echo $size?'130':'45'; ?>" step="0.1" value="<?php echo esc_attr($s[$key]); ?>"><input data-qil-hero-range="<?php echo esc_attr($key); ?>" aria-label="<?php echo esc_attr($label.' '.$text); ?>" type="range" min="<?php echo $size?'20':'-45'; ?>" max="<?php echo $size?'130':'45'; ?>" step="0.1" value="<?php echo esc_attr($s[$key]); ?>"></label>
 <?php endforeach; ?></fieldset><?php endforeach; ?>
 </div><div>
 <label for="qil-hero-preview-mode">Preview / معاينة</label> <select id="qil-hero-preview-mode"><option value="desktop">Desktop</option><option value="mobile">Mobile / tablet</option></select>
 <div class="qil-hero-preview" data-qil-hero-preview style="background-image:url('<?php echo esc_url(QIL_URL.'assets/qimia-digital-world-v14-1672.webp'); ?>')"><img data-qil-hero-preview-image src="<?php echo esc_url($image['url']); ?>" alt="Hero layout preview"></div>
 <p class="description">Positive left offset moves right, including Arabic. Preview is illustrative; cached storefront pages may need a targeted cache purge after saving.</p>
 </div></div>
 <?php submit_button('Save hero · حفظ الهيرو'); ?>
 <button class="button" type="button" data-qil-hero-reset>Restore original size &amp; position · استعادة القياسات</button>
 </form></section>
 <?php
}
