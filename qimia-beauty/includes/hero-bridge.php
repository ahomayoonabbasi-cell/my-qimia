<?php
/** A narrow, recorded QIL extension point. No page buffering or client image replacement. */
defined('ABSPATH') || exit;
function qby_install_hero_bridge(){
    if(!qby_stage_allowed() || !current_user_can('manage_options') || !defined('QIL_DIR')){throw new RuntimeException('Staging administrator and Qimia theme required.');}
    $file=QIL_DIR.'includes/sections.php';$source=file_get_contents($file);$saved=get_option('qby_hero_bridge_200');
    if($saved && hash('sha256',$source)===$saved['after_hash']){return;}
    if($saved){throw new RuntimeException('The theme source changed since the recorded artwork update. No theme file changed.');}
    if(strpos($source,"apply_filters( 'qil_hero_backdrop_url'")!==false){return;}
    $expected='5dec55576dda576b55dd4d0399b88b1fb089e18a9cf811c75163682b9b6981e1';
    if(hash('sha256',$source)!==$expected){throw new RuntimeException('The theme version differs from the reviewed source. No theme file changed.');}
    $count=0;$updated=preg_replace_callback("/QIL_URL \\. 'assets\\/qimia-digital-world-v14-(960|1672)\\.webp'/",static function($m){return "apply_filters( 'qil_hero_backdrop_url', ".$m[0].", ".(int)$m[1]." )";},$source,-1,$count);
    if($count!==3 || !$updated){throw new RuntimeException('Expected exactly three artwork references. No theme file changed.');}
    $record=array('time'=>gmdate('c'),'path'=>$file,'before'=>$source,'before_hash'=>hash('sha256',$source),'after_hash'=>hash('sha256',$updated));
    if(!add_option('qby_hero_bridge_200',$record,'',false)){throw new RuntimeException('Artwork bridge record already exists.');}
    $temporary=$file.'.qby-200.tmp';
    if(file_put_contents($temporary,$updated,LOCK_EX)!==strlen($updated)){@unlink($temporary);delete_option('qby_hero_bridge_200');throw new RuntimeException('Unable to prepare artwork bridge; theme unchanged.');}
    @chmod($temporary,fileperms($file)&0777);
    if(!rename($temporary,$file)){@unlink($temporary);delete_option('qby_hero_bridge_200');throw new RuntimeException('Unable to apply artwork bridge; theme unchanged.');}
    if(hash_file('sha256',$file)!==$record['after_hash']){throw new RuntimeException('Artwork bridge verification failed. Original source remains recorded.');}
    if(function_exists('opcache_invalidate')){opcache_invalidate($file,true);}
}
function qby_restore_hero_bridge(){
    $record=get_option('qby_hero_bridge_200');if(!$record || !defined('QIL_DIR')){return '';}$file=QIL_DIR.'includes/sections.php';
    if($file!==$record['path'] || hash_file('sha256',$file)!==$record['after_hash']){return 'Theme artwork bridge has later edits; preserved for manual review.';}
    $temporary=$file.'.qby-restore.tmp';if(file_put_contents($temporary,$record['before'],LOCK_EX)!==strlen($record['before'])){@unlink($temporary);return 'Theme artwork bridge restoration could not be prepared.';}@chmod($temporary,fileperms($file)&0777);
    if(!rename($temporary,$file)){@unlink($temporary);return 'Theme artwork bridge restoration could not be completed.';}
    if(function_exists('opcache_invalidate')){opcache_invalidate($file,true);}delete_option('qby_hero_bridge_200');return '';
}
// Home keeps the original QIL shaker artwork; Beauty owns its separate hero.
