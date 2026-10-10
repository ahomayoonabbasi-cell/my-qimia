<?php
defined('ABSPATH') || exit;
function qby_store_ledger($ledger) { update_option('qby_migration_1', $ledger, false); }
function qby_initialize() {
    if (!qby_stage_allowed() || !current_user_can('manage_options') || !class_exists('WooCommerce')) { throw new RuntimeException('Staging administrator access and WooCommerce required.'); }
    if (get_option('qby_migration_1')) { throw new RuntimeException('A migration record already exists. Use its status or rollback first.'); }
    if (!function_exists('qil_is_staging_sandbox') || !qil_is_staging_sandbox()) { throw new RuntimeException('The staging commerce sandbox must be active.'); }
    foreach(qby_seed() as $id=>$row) { $p=wc_get_product($id); if (!$p || $p->get_slug() !== $row['slug']) { throw new RuntimeException('Catalogue identity mismatch: '.$id); } }
    foreach(array('beauty','brands') as $slug) { if (get_page_by_path($slug)) { throw new RuntimeException('Existing page requires review: '.$slug); } }
    foreach(qby_terms() as $slug=>$row) { if (get_term_by('slug',$slug,'product_cat')) { throw new RuntimeException('Existing category requires review: '.$slug); } }
    $ledger=array('time'=>gmdate('c'),'status'=>'pending','terms'=>array(),'pages'=>array(),'products'=>array(),'renamed'=>array());
    if(!add_option('qby_migration_1',$ledger,'',false)){throw new RuntimeException('A migration is already running or recorded.');}
    try {
        $term_ids=array();
        foreach(qby_terms() as $slug=>$row) {
            $created=wp_insert_term($row[0],'product_cat',array('slug'=>$slug,'parent'=>$row[2] ? $term_ids[$row[2]] : 0));
            if (is_wp_error($created)) { throw new RuntimeException($created->get_error_message()); }
            $term_ids[$slug]=(int)$created['term_id']; $ledger['terms'][]=array('id'=>$term_ids[$slug],'taxonomy'=>'product_cat','slug'=>$slug); qby_store_ledger($ledger);
        }
        $sugar=get_term_by('slug','sugarbear','product_brand');
        if (!$sugar) { $created=wp_insert_term('SugarBear','product_brand',array('slug'=>'sugarbear')); if(is_wp_error($created)){throw new RuntimeException($created->get_error_message());} $sugar=get_term($created['term_id'],'product_brand'); $ledger['terms'][]=array('id'=>$sugar->term_id,'taxonomy'=>'product_brand','slug'=>'sugarbear'); qby_store_ledger($ledger); }
        $changes=array();
        foreach(qby_seed() as $id=>$row) {
            $cats=array_map('intval',wp_get_object_terms($id,'product_cat',array('fields'=>'ids')));
            $cats=array_values(array_unique(array_merge($cats,array($term_ids['beauty'],$term_ids['hair-care']),array_map(static function($slug)use($term_ids){return $term_ids[$slug];},$row['categories']))));
            $changes[$id]=array('categories'=>$cats,'class'=>'beauty');
        }
        foreach(array('sugarbearpro-hair-vitamins-62-gummies-2-month-supply','sugarbear-sleep-deep-vitamins-32gums') as $slug) {
            $post=get_page_by_path($slug,OBJECT,'product'); if(!$post){continue;}
            $brands=array_map('intval',wp_get_object_terms($post->ID,'product_brand',array('fields'=>'ids')));
            $hair=get_term_by('slug','hairburst','product_brand'); if($hair){$brands=array_values(array_diff($brands,array($hair->term_id)));}
            $changes[$post->ID]=array('brands'=>array_values(array_unique(array_merge($brands,array($sugar->term_id)))),'class'=>'supplement');
        }
        // Classify only the reviewed oral beauty catalogue; no inference for other products.
        foreach(get_posts(array('post_type'=>'product','post_status'=>'publish','numberposts'=>200,'fields'=>'ids','tax_query'=>array(array('taxonomy'=>'product_cat','field'=>'slug','terms'=>array('hair-nail-skin-health','collagens'))))) as $id) {
            if(!isset($changes[$id]) && !qby_class($id)){ $changes[$id]=array('class'=>'supplement'); }
        }
        foreach($changes as $id=>$change) {
            $before=array('class'=>get_post_meta($id,'_qby_product_class',true),'class_exists'=>metadata_exists('post',$id,'_qby_product_class'));
            foreach(array('categories'=>'product_cat','brands'=>'product_brand') as $key=>$tax){if(isset($change[$key])){$before[$key]=array_map('intval',wp_get_object_terms($id,$tax,array('fields'=>'ids')));}}
            $ledger['products'][$id]=array('before'=>$before,'after'=>$change); qby_store_ledger($ledger);
            foreach(array('categories'=>'product_cat','brands'=>'product_brand') as $key=>$tax){if(isset($change[$key])){$result=wp_set_object_terms($id,$change[$key],$tax);if(is_wp_error($result)){throw new RuntimeException($result->get_error_message());}}}
            update_post_meta($id,'_qby_product_class',$change['class']); clean_post_cache($id); wc_delete_product_transients($id);
        }
        foreach(array('beauty'=>array('Beauty','[qimia_beauty]'),'brands'=>array('Brands','[qimia_brands]')) as $slug=>$row){
            $id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$row[0],'post_content'=>$row[1]),true);
            if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}
            $ledger['pages'][]=array('id'=>$id,'slug'=>$slug,'content'=>$row[1]); qby_store_ledger($ledger);
        }
        $ledger['status']='complete'; qby_store_ledger($ledger); update_option('qby_enabled',1,false);
        delete_transient('qby_brand_groups'); do_action('litespeed_purge_all');
        return 'Beauty enabled. Existing product URLs, SKUs, prices, stock and order/account settings preserved.';
    } catch(Throwable $error) { $ledger['status']='partial';$ledger['error']=$error->getMessage();qby_store_ledger($ledger);throw $error; }
}
function qby_rollback() {
    if(!qby_stage_allowed() || !current_user_can('manage_options')){throw new RuntimeException('Staging administrator access required.');}
    $ledger=get_option('qby_migration_1'); if(!$ledger){return 'No migration to restore.';}
    update_option('qby_enabled',0,false); $conflicts=array();$bridge=qby_restore_hero_bridge();if($bridge){$conflicts[]=$bridge;}
    foreach($ledger['products'] as $id=>$row) {
        foreach(array('categories'=>'product_cat','brands'=>'product_brand') as $key=>$tax){if(!isset($row['after'][$key])){continue;}
            $current=array_map('intval',wp_get_object_terms($id,$tax,array('fields'=>'ids')));$after=$row['after'][$key];sort($current);sort($after);
            $before=$row['before'][$key];sort($before);
            if($current===$after){wp_set_object_terms($id,$row['before'][$key],$tax);}elseif($current!==$before){$conflicts[]=$id.':'.$key;}
        }
        $current=qby_class($id);
        if($current===$row['after']['class']){if($row['before']['class_exists']){update_post_meta($id,'_qby_product_class',$row['before']['class']);}else{delete_post_meta($id,'_qby_product_class');}}
        elseif($current!==$row['before']['class']){$conflicts[]=$id.':class';}
        clean_post_cache($id);wc_delete_product_transients($id);
    }
    $r=$ledger['renamed'];if($r){$t=get_term($r['id'],'product_cat');if($t && !is_wp_error($t) && $t->name===$r['after']){wp_update_term($r['id'],'product_cat',array('name'=>$r['before']));}elseif($t && $t->name!==$r['before']){$conflicts[]='category-name';}}
    foreach($ledger['pages'] as $row){$p=get_post($row['id']);if($p && $p->post_name===$row['slug'] && $p->post_content===$row['content']){wp_trash_post($p->ID);}elseif($p && $p->post_status!=='trash'){$conflicts[]='page:'.$row['id'];}}
    foreach(array_reverse($ledger['terms']) as $row){$t=get_term($row['id'],$row['taxonomy']);if(!$t || is_wp_error($t)){continue;}$objects=get_objects_in_term($t->term_id,$row['taxonomy']);$children=get_term_children($t->term_id,$row['taxonomy']);if(!$objects && !$children){wp_delete_term($t->term_id,$row['taxonomy']);}else{$conflicts[]='term:'.$t->term_id;}}
    $ledger['status']=$conflicts?'rollback-needs-review':'rolled-back';$ledger['conflicts']=$conflicts;qby_store_ledger($ledger);delete_transient('qby_brand_groups');do_action('litespeed_purge_all');
    return $conflicts?'Beauty disabled. Later changes preserved; review conflicts: '.implode(', ',$conflicts):'Beauty disabled and recorded catalogue changes restored. New pages moved to Trash. Snapshot retained.';
}
