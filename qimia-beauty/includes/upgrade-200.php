<?php
/** Administrator-triggered additive Beauty topology upgrade. No product/SEO rewrites. */
defined('ABSPATH') || exit;
function qby_upgrade_200() {
    if(!qby_stage_allowed() || !current_user_can('manage_options') || !class_exists('WooCommerce')){throw new RuntimeException('Staging administrator access and WooCommerce required.');}
    if(!function_exists('qil_is_staging_sandbox') || !qil_is_staging_sandbox()){throw new RuntimeException('The staging commerce sandbox must remain active.');}
    $ledger=get_option('qby_migration_1');
    if(!is_array($ledger) || ($ledger['status']??'')!=='complete'){throw new RuntimeException('A complete Beauty migration is required before the department update.');}
    $prior=get_option('qby_upgrade_200');
    if(is_array($prior) && ($prior['status']??'')==='complete'){return 'Beauty department update already applied.';}
    if(!add_option('qby_upgrade_200_lock',gmdate('c'),'',false)){throw new RuntimeException('A Beauty department update is already running. Review its saved record before retrying.');}
    $record=is_array($prior)?$prior:array('time'=>gmdate('c'),'status'=>'pending','created'=>array(),'reused'=>array());
    try {
        $plan=qby_beauty_terms();$ids=array();
        // Validate the entire existing topology before creating anything. Never reparent legacy terms.
        foreach($plan as $slug=>$row){
            $term=get_term_by('slug',$slug,'product_cat');
            if(!$term || is_wp_error($term)){continue;}
            if(!$row[2]){if((int)$term->parent!==0){throw new RuntimeException('Existing Beauty root requires review: '.$slug);}}
            else{$parent=get_term_by('slug',$row[2],'product_cat');if(!$parent || is_wp_error($parent) || (int)$term->parent!==(int)$parent->term_id){throw new RuntimeException('Existing category parent differs; no categories moved: '.$slug);}}
            $ids[$slug]=(int)$term->term_id;
        }
        $record['status']='pending';unset($record['error']);update_option('qby_upgrade_200',$record,false);
        foreach($plan as $slug=>$row){
            if(isset($ids[$slug])){$record['reused'][$slug]=$ids[$slug];continue;}
            $parent=$row[2]?($ids[$row[2]]??0):0;
            if($row[2] && !$parent){throw new RuntimeException('Missing planned parent: '.$row[2]);}
            $created=wp_insert_term($row[0],'product_cat',array('slug'=>$slug,'parent'=>$parent));
            if(is_wp_error($created)){throw new RuntimeException($created->get_error_message());}
            $id=(int)$created['term_id'];$ids[$slug]=$id;
            $entry=array('id'=>$id,'taxonomy'=>'product_cat','slug'=>$slug);
            $ledger['terms'][]=$entry;qby_store_ledger($ledger);
            $record['created'][$slug]=$id;update_option('qby_upgrade_200',$record,false);
        }
        $record['status']='complete';$record['completed']=gmdate('c');update_option('qby_upgrade_200',$record,false);
        delete_transient('qby_brand_groups');delete_transient('qby_beauty_presence_v2');foreach(array('en','ar') as $language){delete_transient('qby_navigation_'.QBY_VERSION.'_'.$language);}
        do_action('litespeed_purge_all');
        return 'Beauty departments prepared. Products, existing category assignments, brands, prices, stock, URLs and SEO metadata preserved. New empty categories contain no invented products.';
    } catch(Throwable $error){$record['status']='partial';$record['error']=$error->getMessage();update_option('qby_upgrade_200',$record,false);throw $error;}
    finally{delete_option('qby_upgrade_200_lock');}
}
