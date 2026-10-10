<?php
defined('ABSPATH') || exit;
function qby_source_names() {
    return array('Qimia Intelligence Lab','Qimia AI Product Box Structured (WooCommerce)',
        'Amir Smart Category + Brand Mega Menu','Qimia Smart Product Search',
        'Qimia AI Commerce Expert','Qimia Arabic Commerce Intelligence & SEO','My Qimia','Qimia Unlimited Geo Currency');
}
function qby_snapshot() {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $out = array('time'=>gmdate('c'),'home'=>home_url('/'),'siteurl'=>site_url('/'),
        'sandbox'=>function_exists('qil_is_staging_sandbox') && qil_is_staging_sandbox(),
        'plugins'=>array(),'sources'=>array(),'terms'=>array(),'products'=>array(),
        'permalinks'=>get_option('woocommerce_permalinks'),'post_permalink'=>get_option('permalink_structure'));
    foreach (get_plugins() as $file=>$plugin) {
        if (!is_plugin_active($file)) { continue; }
        $out['plugins'][$file] = array('name'=>$plugin['Name'],'version'=>$plugin['Version']);
        if (!in_array($plugin['Name'],qby_source_names(),true)) { continue; }
        $root=WP_PLUGIN_DIR . '/' . dirname($file);
        if (dirname($file)==='.' || !is_dir($root)) { continue; }
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach ($it as $item) {
            if (!$item->isFile() || $item->isLink() || $item->getSize()>2500000) { continue; }
            $rel=substr($item->getPathname(),strlen(WP_PLUGIN_DIR)+1);
            if (!preg_match('/\.(php|js|css|html|json|md|txt|svg)$/i',$rel) || preg_match('~(?:^|/)(?:node_modules|vendor|logs|cache)/~',$rel)) { continue; }
            $out['sources'][$rel]=file_get_contents($item->getPathname());
        }
    }
    foreach(array('product_cat','product_brand') as $tax) {
        $terms=get_terms(array('taxonomy'=>$tax,'hide_empty'=>false));
        if (is_wp_error($terms)) { continue; }
        foreach($terms as $t) { $out['terms'][$tax][]=array('id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug,'parent'=>$t->parent,'count'=>$t->count,'description'=>$t->description,'url'=>get_term_link($t),'image'=>wp_get_attachment_image_url((int)get_term_meta($t->term_id,'thumbnail_id',true),'medium')); }
    }
    $ids=get_posts(array('post_type'=>'product','post_status'=>'publish','numberposts'=>150,'fields'=>'ids','tax_query'=>array(array('taxonomy'=>'product_cat','field'=>'slug','terms'=>array('hair-nail-skin-health','collagens')))));
    foreach($ids as $id) {
        $p=wc_get_product($id); if (!$p) { continue; }
        $attrs=array(); foreach($p->get_attributes() as $name=>$attr) { $attrs[$name]=$p->get_attribute($name); }
        $out['products'][]=array('id'=>$id,'name'=>$p->get_name(),'slug'=>$p->get_slug(),'type'=>$p->get_type(),'sku'=>$p->get_sku(),'status'=>$p->get_status(),'stock'=>$p->get_stock_status(),'categories'=>$p->get_category_ids(),'brands'=>wp_get_object_terms($id,'product_brand',array('fields'=>'ids')),'description'=>$p->get_description(),'short_description'=>$p->get_short_description(),'attributes'=>$attrs,'url'=>get_permalink($id),'image'=>wp_get_attachment_image_url($p->get_image_id(),'large'),'published'=>$p->get_date_created() ? $p->get_date_created()->date('c') : '','sales'=>$p->get_total_sales(),'meta_keys'=>array_keys(get_post_meta($id)));
    }
    $out['theme']=array('active'=>wp_get_theme()->get('Name'),'version'=>wp_get_theme()->get('Version'),'parent'=>get_template());
    foreach(array('inc/integrations/woocommerce/modules/compare.php','woocommerce/content-product.php','woocommerce/loop/loop-start.php','woocommerce/content-product-standard.php') as $rel){$f=get_template_directory().'/'.$rel;if(is_file($f) && filesize($f)<250000){$out['sources']['theme-audit/'.$rel]=file_get_contents($f);}}
    return $out;
}
add_action('admin_menu',static function(){ add_management_page('Qimia Beauty','Qimia Beauty','manage_options','qimia-beauty','qby_admin_page'); });
function qby_admin_page() {
    if (!current_user_can('manage_options')) { wp_die('Administrator access required.'); }
    echo '<div class="wrap"><h1>Qimia Beauty</h1><p>Version '.esc_html(QBY_VERSION).'. The storefront switch controls Beauty presentation. Product data, orders and the shared header stay with their native owners.</p>';
    if(isset($_POST['qby_save_storefront'])){
        check_admin_referer('qby_manage');
        $enabled=isset($_POST['qby_storefront_enabled']) && $_POST['qby_storefront_enabled']==='1';
        if($enabled && !class_exists('WooCommerce')){echo '<div class="notice notice-error"><p>Activate WooCommerce before enabling Beauty.</p></div>';}
        else{update_option('qby_enabled',$enabled?1:0,false);if(!qby_stage_allowed()){update_option('qby_live_enabled',$enabled?1:0,false);}qby_invalidate_catalogue_cache();do_action('litespeed_purge_all');echo '<div class="notice notice-success"><p>Beauty storefront setting saved. Existing products and page contents are preserved.</p></div>';}
    }
    echo '<form method="post">';wp_nonce_field('qby_manage');
    echo '<p><label><input type="checkbox" name="qby_storefront_enabled" value="1" '.checked(qby_frontend_enabled(),true,false).'> Enable the Beauty storefront on this site</label></p><p>Use <code>[qimia_beauty]</code> on your Beauty page and <code>[qimia_brands]</code> on your Brands page. Add real products to the Beauty categories before launch. This switch does not create products or run the legacy migration.</p>';
    submit_button('Save storefront setting','primary','qby_save_storefront');echo '</form>';
    if(!qby_stage_allowed()){echo '<p>The legacy catalogue migration and rollback tools are restricted to the original staging site.</p></div>';return;}
    if(isset($_POST['qby_initialize']) || isset($_POST['qby_rollback']) || isset($_POST['qby_upgrade']) || isset($_POST['qby_upgrade_200'])) {
        check_admin_referer('qby_manage');
        try { if(isset($_POST['qby_upgrade_200'])){$message=qby_upgrade_200();}else{$message=isset($_POST['qby_upgrade']) ? qby_upgrade_110() : (isset($_POST['qby_initialize']) ? qby_initialize() : qby_rollback());} echo '<div class="notice notice-success"><p>'.esc_html($message).'</p></div>'; }
        catch(Throwable $error) { echo '<div class="notice notice-error"><p>'.esc_html($error->getMessage()).'</p></div>'; }
    }
    $ledger=get_option('qby_migration_1');
    echo '<p><strong>Status: '.esc_html(get_option('qby_enabled')?'Enabled':'Disabled').'</strong> · '.esc_html($ledger['status']??'not initialized').'</p>';
    echo '<form method="post">';wp_nonce_field('qby_manage');
    if(!$ledger){submit_button('Initialize Beauty on staging','primary','qby_initialize');}
    elseif(($ledger['status']??'')!=='rolled-back'){submit_button('Disable Beauty and restore recorded changes','secondary','qby_rollback');}
    if($ledger && get_option('qby_enabled') && !get_option('qby_upgrade_110')){submit_button('Apply 1.1 preservation update','primary','qby_upgrade');}
    if($ledger && get_option('qby_enabled') && ((get_option('qby_upgrade_200')['status']??'')!=='complete')){submit_button('Apply complete Beauty 2.0 redesign','primary','qby_upgrade_200');}
    echo '</form>';
    if($ledger){echo '<details><summary>Migration and rollback record</summary><pre id="qby-ledger" style="white-space:pre-wrap">'.esc_html(wp_json_encode($ledger,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre></details>';}
    echo '<form method="post">'; wp_nonce_field('qby_snapshot');
    submit_button('Prepare code and catalogue snapshot','secondary','qby_snapshot'); echo '</form>';
    if (isset($_POST['qby_snapshot'])) {
        check_admin_referer('qby_snapshot');
        echo '<label for="qby-snapshot">Code and catalogue snapshot</label><textarea id="qby-snapshot" readonly style="width:100%;height:480px">'.esc_textarea(wp_json_encode(qby_snapshot(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</textarea>';
    }
    echo '<details><summary>On-demand staging performance comparison</summary><p>Administrator requests only. Baseline skips this module for one request without changing saved settings. Query count and PHP time are measured near the end of rendering.</p>';
    foreach(array('/'=>'Home','/shop/'=>'Shop','/product/afterave-beauty-algorithm-60-tabs/'=>'Supplement') as $path=>$label){foreach(array('baseline','enabled') as $mode){$url=add_query_arg(array('qby_profile'=>$mode,'_qby_nonce'=>wp_create_nonce('qby_profile')),home_url($path));echo '<p><a href="'.esc_url($url).'">'.esc_html($label.' · '.$mode).'</a></p>';}}
    echo '</details>';
    echo '</div>';
}
add_action('add_meta_boxes_product',static function(){
    add_meta_box('qby-class','Qimia · Product classification & cosmetic details',static function($post){
        if(!current_user_can('edit_post',$post->ID)){return;}
        wp_nonce_field('qby_product_details','qby_product_nonce');
        $class=get_post_meta($post->ID,'_qby_product_class',true);if($class==='cosmetic'){$class='beauty';}
        $details=get_post_meta($post->ID,'_qby_cosmetic_details',true);$details=is_array($details)?$details:array();
        echo '<p>Separate from WooCommerce simple / variable. Oral supplements retain their existing facts. Enter cosmetic information from the exact product label; leave unverified details blank.</p><p><label for="qby-class-select">Product class</label> <select id="qby-class-select" name="qby_class">';
        foreach(array(''=>'Unclassified — existing behaviour','supplement'=>'Oral supplement','beauty'=>'Beauty / topical care','food'=>'Food','accessory'=>'Accessory') as $key=>$label){echo '<option value="'.esc_attr($key).'" '.selected($class,$key,false).'>'.esc_html($label).'</option>';}
        echo '</select></p>';
        foreach(array_map(static function($field){return $field['en'];},qby_beauty_field_schema()) as $key=>$label){echo '<p><label for="qby-'.esc_attr($key).'">'.esc_html($label).'</label><textarea style="display:block;width:100%" rows="'.($key==='inci'?4:2).'" id="qby-'.esc_attr($key).'" name="qby_details['.esc_attr($key).']"'.(str_ends_with($key,'_ar')?' dir="rtl"':'').'>'.esc_textarea($details[$key]??'').'</textarea></p>';}
    },'product','normal','default');
});
add_action('save_post_product',static function($id){
    if(!isset($_POST['qby_product_nonce']) || !is_scalar($_POST['qby_product_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['qby_product_nonce'])),'qby_product_details') || !current_user_can('edit_post',$id) || wp_is_post_revision($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)){return;}
    $class=isset($_POST['qby_class']) && is_scalar($_POST['qby_class'])?sanitize_key(wp_unslash((string)$_POST['qby_class'])):'';if(!in_array($class,array('','supplement','beauty','food','accessory'),true)){return;}
    if($class){update_post_meta($id,'_qby_product_class',$class);}else{delete_post_meta($id,'_qby_product_class');}
    if(!in_array($class,array('beauty','accessory'),true)){return;}
    $input=isset($_POST['qby_details']) && is_array($_POST['qby_details'])?wp_unslash($_POST['qby_details']):array();$out=array();
    $out=qby_sanitize_beauty_details($input);
    update_post_meta($id,'_qby_cosmetic_details',$out);
});
