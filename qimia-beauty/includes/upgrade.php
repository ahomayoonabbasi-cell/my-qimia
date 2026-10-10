<?php
/** Versioned, administrator-triggered preservation update. */
defined('ABSPATH') || exit;
function qby_upgrade_110() {
    if(!qby_stage_allowed() || !current_user_can('manage_options')){throw new RuntimeException('Staging administrator access required.');}
    if(get_option('qby_upgrade_110')){return 'Preservation update already applied.';}
    $ledger=get_option('qby_migration_1');if(!$ledger || $ledger['status']!=='complete'){throw new RuntimeException('A complete Beauty migration is required.');}
    $record=array('time'=>gmdate('c'),'products'=>array());
    foreach(qby_seed() as $id=>$data){
        $p=wc_get_product($id);if(!$p || $p->get_slug()!==$data['slug']){throw new RuntimeException('Product identity changed: '.$id);}
        $row=$ledger['products'][$id]??null;if(!$row){continue;}
        $current=array_map('intval',wp_get_object_terms($id,'product_cat',array('fields'=>'ids')));
        $wanted=array_values(array_unique(array_merge($current,$row['before']['categories']??array())));
        $record['products'][$id]=array('before'=>$current,'after'=>$wanted);
        $result=wp_set_object_terms($id,$wanted,'product_cat');if(is_wp_error($result)){throw new RuntimeException($result->get_error_message());}
        $ledger['products'][$id]['after']['categories']=$wanted;
        if(get_post_meta($id,'_qby_product_class',true)==='cosmetic'){update_post_meta($id,'_qby_product_class','beauty');$ledger['products'][$id]['after']['class']='beauty';}
        clean_post_cache($id);
    }
    $renamed=$ledger['renamed']??array();
    if($renamed){$t=get_term($renamed['id'],'product_cat');if($t && !is_wp_error($t) && $t->name===$renamed['after']){wp_update_term($t->term_id,'product_cat',array('name'=>$renamed['before']));}$ledger['renamed']=array();$record['restored_category_name']=$renamed;}
    qby_store_ledger($ledger);update_option('qby_upgrade_110',$record,false);delete_transient('qby_brand_groups');
    foreach(array('en','ar') as $lang){delete_transient('qby_navigation_'.$lang);}
    do_action('litespeed_purge_all');
    return 'Updated: legacy category memberships and display name preserved; reviewed topical products classified Beauty. Existing URLs and SEO metadata unchanged.';
}
