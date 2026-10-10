<?php
/** On-demand administrator measurements. No job, polling, logging or customer data. */
defined('ABSPATH') || exit;
function qby_profile_mode() {
    static $mode=null;if($mode!==null){return $mode;}$mode='';
    if(!isset($_GET['qby_profile'],$_GET['_qby_nonce']) || !is_scalar($_GET['qby_profile']) || !is_scalar($_GET['_qby_nonce'])){return '';}
    if(!current_user_can('manage_options') || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_qby_nonce'])),'qby_profile')){return '';}
    $v=sanitize_key(wp_unslash($_GET['qby_profile']));if(in_array($v,array('baseline','enabled'),true)){$mode=$v;}return $mode;
}
add_action('wp_footer',static function(){
    $mode=qby_profile_mode();if(!$mode){return;}
    $metrics=array('mode'=>$mode,'queries'=>get_num_queries(),'php_ms'=>round((microtime(true)-(float)($_SERVER['REQUEST_TIME_FLOAT']??microtime(true)))*1000,1),'peak_mb'=>round(memory_get_peak_usage(true)/1048576,1),'measured_at'=>gmdate('c'));
    echo '<aside style="padding:20px;background:#fff;color:#153e4b;border:1px solid #ccc"><strong>Qimia staging · administrator measurement</strong><pre id="qby-page-metrics">'.esc_html(wp_json_encode($metrics)).'</pre></aside>';
},PHP_INT_MAX);
