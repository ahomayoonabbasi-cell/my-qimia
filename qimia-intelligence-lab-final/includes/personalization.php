<?php
/**
 * One bounded storefront recommendation adapter. WooCommerce owns commerce;
 * QH_Account owns optional activity, permissions, decisions and retention.
 * No AI calls, third-party requests, new tables, global locks or scheduled scans.
 */
defined( 'ABSPATH' ) || exit;

final class QIL_Personalization {
    const SCHEMA = 'qil-shopping/1';
    const MAX_CANDIDATES = 48;
    const MAX_RECORDS = 24;
    const MAX_ROWS = 12;
    const MAX_ORDER_ITEMS = 240;
    const BUDGET_MS = 1100;
    private static $cart_printed = false;
    private static $products = array();
    private static $profiles = array();
    private static $started = 0;

    public static function boot() {
        add_action( 'init', array( __CLASS__, 'private_request' ), -9997 );
        add_action( 'wc_ajax_qil_personalize', array( __CLASS__, 'ajax' ) );
        add_action( 'wc_ajax_qil_personal_events', array( __CLASS__, 'events' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 95 );
        add_action( 'woocommerce_after_cart', array( __CLASS__, 'cart' ), 30 );
        add_filter( 'render_block_woocommerce/cart', array( __CLASS__, 'cart_block' ), 20, 2 );
        add_action( 'woocommerce_after_order_object_save', array( __CLASS__, 'invalidate_order' ), 20, 1 );
        add_action( 'woocommerce_order_refunded', array( __CLASS__, 'invalidate_order' ), 20, 1 );
        add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'invalidate_order' ), 20, 1 );
        add_action( 'woocommerce_before_trash_order', array( __CLASS__, 'invalidate_order' ), 20, 1 );
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'attribution' ), 30, 4 );
        add_action( 'admin_init', array( __CLASS__, 'settings' ) );
    }
    public static function enabled() {
        return ! ( defined( 'QIL_PERSONALIZATION_DISABLE' ) && QIL_PERSONALIZATION_DISABLE )
            && function_exists( 'qil_experience_enabled' ) && qil_experience_enabled()
            && '0' !== (string) get_option( 'qil_personalization_enabled', '1' );
    }
    public static function settings() {
        register_setting( 'qil_personal_settings', 'qil_personalization_enabled', array(
            'type'=>'string', 'default'=>'1', 'sanitize_callback'=>static function($v){return '1' === (string)$v ? '1' : '0';}
        ) );
        register_setting( 'qil_personal_settings', 'qil_personal_rollout', array(
            'type'=>'integer', 'default'=>100, 'sanitize_callback'=>static function($v){return max(0,min(100,(int)$v));}
        ) );
    }
    public static function private_request() {
        $endpoint = isset($_GET['wc-ajax']) && is_string($_GET['wc-ajax']) ? sanitize_key(wp_unslash($_GET['wc-ajax'])) : '';
        if ( in_array($endpoint,array('qil_personalize','qil_personal_events'),true) ) qil_continuity_private_headers();
    }
    public static function same_origin() {
        if ( 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? '') || 'shopping/1' !== ($_SERVER['HTTP_X_QIMIA_REQUEST'] ?? '') ) return false;
        $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
        if ( $site && !in_array($site,array('same-origin','none'),true) ) return false;
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ( !$origin ) return !$site || in_array($site,array('same-origin','none'),true);
        $a = wp_parse_url($origin); $b = wp_parse_url(home_url('/'));
        if ( !is_array($a) || !is_array($b) || !empty($a['user']) || !empty($a['pass']) ) return false;
        foreach ( array('host','scheme') as $key ) if (strtolower($a[$key]??'')!==strtolower($b[$key]??'')) return false;
        return (int)($a['port']??(($a['scheme']??'')==='https'?443:80)) === (int)($b['port']??(($b['scheme']??'')==='https'?443:80));
    }
    public static function ids( $values, $limit = 48 ) {
        if ( !is_array($values) ) return array();
        $out = array();
        foreach ( array_slice($values,0,max(1,$limit)*2) as $value ) {
            if ( !is_scalar($value) || is_bool($value) || !preg_match('/^[1-9][0-9]{0,9}$/D',(string)$value) ) continue;
            $id = (int)$value; $out[$id] = $id;
            if ( count($out) >= $limit ) break;
        }
        return array_values($out);
    }
    public static function context( $raw ) {
        $raw = is_array($raw) ? $raw : array();
        $input_filters = is_array($raw['filters']??null) ? array_filter(array_slice($raw['filters'],0,12),'is_string') : array();
        $filters = array_values(array_intersect(array('stimfree','vegan','value'),$input_filters)); sort($filters);
        return array(
            'surface'=>in_array($raw['surface']??'',array('home','product','cart'),true)?$raw['surface']:'home',
            'locale'=>($raw['locale']??'')==='ar'?'ar':'en',
            'current'=>self::ids(array($raw['current']??0),1)[0]??0,
            'selected'=>self::ids(array($raw['selected']??0),1)[0]??0,
            'compare'=>self::ids($raw['compare']??array(),3),
            'results'=>self::ids($raw['results']??array(),8),
            // Browser-local WooCommerce recently-viewed IDs are hints only. They
            // are revalidated as live public products and are never trusted for
            // ownership, price, stock or purchase attribution.
            'recent'=>self::ids($raw['recent']??array(),12),
            'exclude'=>self::ids($raw['exclude']??array(),64),
            'dismissed'=>self::ids($raw['dismissed']??array(),24),
            'filters'=>$filters,
            'task'=>is_string($raw['task']??null)&&preg_match('/^[a-f0-9-]{36}$/D',$raw['task'])?$raw['task']:'',
        );
    }
    private static function get_product( $id ) {
        $id = (int)$id;
        if ( !array_key_exists($id,self::$products) ) self::$products[$id] = $id>0 ? wc_get_product($id) : false;
        return self::$products[$id];
    }
    private static function parent( $id ) {
        $p = self::get_product($id);
        return $p && $p->is_type('variation') ? self::get_product($p->get_parent_id()) : $p;
    }
    public static function eligible( $p, $surface = 'home' ) {
        if ( !is_a($p,'WC_Product') || 'publish'!==$p->get_status() || !$p->is_visible() || post_password_required($p->get_id()) ) return false;
        if ( !$p->is_type(array('simple','variable')) || 'instock'!==$p->get_stock_status() || !$p->is_in_stock() || !$p->is_purchasable()
            || !$p->has_enough_stock(1) || ''===(string)$p->get_price() || (float)$p->get_price()<=0 || !$p->get_image_id() ) return false;
        // Installed destination/restriction plugins can veto recommendations here.
        // Commerce/cart validation still owns the definitive purchase decision.
        return (bool) apply_filters('qil_personal_product_eligible',true,$p,$surface);
    }
    public static function allowed() {
        if(!is_user_logged_in() || !class_exists('QH_Account'))return false;
        foreach(array('ready','allowed','uuid','session','table','with_account_lock','insert','valid_decision') as $method)if(!is_callable(array('QH_Account',$method)))return false;
        try { return QH_Account::ready() && QH_Account::allowed(get_current_user_id()) && (!function_exists('qh_environment_allowed') || qh_environment_allowed()); }
        catch(Throwable $error){return false;} // Optional owner failure cannot break Buy Again.
    }
    /** Public product taxonomy only, request-local; never a second customer profile. */
    private static function profile( $p ) {
        $id = (int)$p->get_id();
        if ( isset(self::$profiles[$id]) ) return self::$profiles[$id];
        $terms = get_the_terms($id,'product_cat'); $leaf = array(); $ancestors = array();
        foreach ( is_array($terms)?$terms:array() as $term ) {
            $leaf[(int)$term->term_id]=(string)$term->slug;
            foreach ( (array)get_ancestors($term->term_id,'product_cat','taxonomy') as $ancestor ) $ancestors[(int)$ancestor]=true;
        }
        foreach ( array_keys($ancestors) as $ancestor ) unset($leaf[$ancestor]);
        $brand = $p->get_attribute('pa_brand') ?: $p->get_attribute('brand');
        if ( taxonomy_exists('product_brand') ) {
            $brands=get_the_terms($id,'product_brand');
            if(is_array($brands)&&isset($brands[0]))$brand=$brands[0]->slug;
        }
        return self::$profiles[$id]=array('categories'=>array_values($leaf),'brand'=>strtolower((string)$brand),'price'=>(float)$p->get_price());
    }
    private static function within_budget() { return !self::$started || (microtime(true)-self::$started)*1000 < self::BUDGET_MS; }
    public static function invalidate_order( $order ) {
        if ( !is_a($order,'WC_Order') && function_exists('wc_get_order') ) $order=wc_get_order($order);
        if ( is_a($order,'WC_Order') && $order->get_customer_id() ) {
            $uid=(int)$order->get_customer_id();
            delete_transient('qil_purchase_refs_v1_'.$uid);
            // A build begun before this write must not republish a stale snapshot.
            update_user_meta($uid,'_qil_purchase_refs_revision',wp_generate_uuid4());
        }
    }
    /** Compact source references only. All ownership, attributes, stock and prices rechecked live. */
    public static function purchase_refs( $uid ) {
        if ( $uid<1 || $uid !== (int)get_current_user_id() ) return array();
        $key='qil_purchase_refs_v1_'.$uid;
        $revision=(string)get_user_meta($uid,'_qil_purchase_refs_revision',true);
        $cached=get_transient($key);
        if(is_array($cached)&&($cached['revision']??'')===$revision&&is_array($cached['rows']??null))return array_slice($cached['rows'],0,24);
        $lock='qil_purchase_build_'.$uid; $owner=array('at'=>time(),'id'=>wp_generate_uuid4());
        $existing=get_option($lock);
        if(is_array($existing)&&($existing['at']??0)<time()-30)delete_option($lock);
        // Per account, non-waiting; never serialize all shoppers on a global lock.
        if(!add_option($lock,$owner,'',false))return array();
        $rows=array(); $seen=array(); $visited=0;
        try {
            $orders=wc_get_orders(array('type'=>'shop_order','customer_id'=>$uid,'status'=>array('wc-processing','wc-completed'),'limit'=>30,'orderby'=>'date','order'=>'DESC','return'=>'objects','paginate'=>false));
            foreach(array_slice(is_array($orders)?$orders:array(),0,30) as $order){
                if(!qil_repeat_order_owned($order,$uid)||$order->get_meta('_qcb2_synthetic_order')==='yes')continue;
                $date=$order->get_date_paid()?:$order->get_date_created(); $at=$date?(int)$date->getTimestamp():0;
                foreach($order->get_items('line_item') as $item){
                    if(++$visited>self::MAX_ORDER_ITEMS)break 2;
                    if(!is_a($item,'WC_Order_Item_Product'))continue;
                    $qty=(float)$item->get_quantity(); $refund=abs((float)$order->get_qty_refunded_for_item($item->get_id()));
                    $total=(float)$item->get_total(); $refunded=abs((float)$order->get_total_refunded_for_item($item->get_id()));
                    if($qty<=0||$refund>=$qty||($total>0&&$refunded>=$total))continue;
                    $identity=$item->get_product_id().':'.$item->get_variation_id();
                    if($item->get_variation_id()) {
                        $variation=wc_get_product($item->get_variation_id());$chosen=array();
                        if($variation && $variation->is_type('variation')) foreach(array_keys($variation->get_variation_attributes()) as $attribute) { $attribute_key=preg_replace('/^attribute_/','',$attribute);$chosen[$attribute_key]=(string)$item->get_meta($attribute_key,true); }
                        $identity.=':'.md5(wp_json_encode($chosen));
                    }
                    if(isset($seen[$identity]))continue;
                    $seen[$identity]=true;
                    $rows[]=array('orderId'=>(int)$order->get_id(),'itemId'=>(int)$item->get_id(),'productId'=>(int)$item->get_product_id(),'variationId'=>(int)$item->get_variation_id(),'at'=>$at);
                    if(count($rows)>=24)break 2;
                }
            }
            // TTL bounds retained references even after unusual third-party database writes.
            wp_cache_delete($uid,'user_meta');
            if((string)get_user_meta($uid,'_qil_purchase_refs_revision',true)===$revision)set_transient($key,array('revision'=>$revision,'rows'=>$rows),10*MINUTE_IN_SECONDS);
        } finally { if(get_option($lock)===$owner)delete_option($lock); }
        return $rows;
    }
    private static function activity() {
        if(!self::allowed()||!is_callable(array('QH_Account','activity')))return array();
        try {
            $result=QH_Account::activity(new WP_REST_Request('GET'));
            if(is_wp_error($result)||!is_object($result)||!method_exists($result,'get_data'))return array();
            return array_slice((array)($result->get_data()['events']??array()),0,100);
        } catch(Throwable $error){return array();}
    }
    /** My Qimia 3.1+ is the canonical owner of exact persistent product views. */
    private static function my_qimia_recent_views() {
        if(!self::allowed()||!function_exists('apply_filters'))return array();
        try { $rows=apply_filters('qimia_my_qimia_recent_views_v1',array()); }
        catch(Throwable $error){ return array(); }
        if(!is_array($rows))return array();
        $out=array();
        foreach(array_slice($rows,0,12) as $row){
            if(!is_array($row))continue;
            $id=self::ids(array($row['product_id']??0),1)[0]??0;$at=(int)($row['viewed_at']??0);
            if($id<1||$at<1||$at>time()+5)continue;
            $out[$id]=max($out[$id]??0,$at);
        }
        arsort($out,SORT_NUMERIC);
        return array_slice($out,0,12,true);
    }
    /** Deterministic decayed interest; impressions never become positive preference. */
    public static function interests( array $events, $now ) {
        $weights=array('product_view'=>8,'search_click'=>18,'compare_select'=>24,'recommendation_click'=>8,'cart_confirmed'=>24);
        $scores=array();$recent=array();$seen=array();$fatigue=array();
        foreach(array_slice($events,0,100) as $row){
            $id=(int)($row['product_id']??0);$type=(string)($row['event_name']??'');$at=(int)($row['received_at']??0);
            if($id<1||$at>$now||$at<$now-30*DAY_IN_SECONDS)continue;
            if($type==='recommendation_view'){$fatigue[$id]=min(12,($fatigue[$id]??0)+2);continue;}
            if(!isset($weights[$type]))continue;
            $key=$id.'|'.$type.'|'.gmdate('Ymd',$at);if(isset($seen[$key]))continue;$seen[$key]=true;
            $scores[$id]=min(36,($scores[$id]??0)+$weights[$type]*pow(.5,($now-$at)/(7*DAY_IN_SECONDS)));
            // A card impression is NOT a viewed product page.
            if($type==='product_view')$recent[$id]=max($recent[$id]??0,$at);
        }
        arsort($scores,SORT_NUMERIC);arsort($recent,SORT_NUMERIC);
        return array('scores'=>array_slice($scores,0,12,true),'recent'=>array_slice($recent,0,12,true),'fatigue'=>$fatigue);
    }
    private static function category_candidates( array $categories ) {
        $categories=array_slice(array_values(array_unique($categories)),0,3);sort($categories);
        if(!$categories)return array();
        $version=class_exists('WC_Cache_Helper')?WC_Cache_Helper::get_transient_version('product'):'0';
        $key='qil_personal_pool_v1_'.md5(wp_json_encode($categories).'|'.$version);
        $cached=get_transient($key);if(is_array($cached))return self::ids($cached,36);
        // A public shortlist, not an identity-specific recommendation/price cache.
        $ids=wc_get_products(array('status'=>'publish','stock_status'=>'instock','visibility'=>'visible','category'=>$categories,'limit'=>36,'orderby'=>'date','order'=>'DESC','return'=>'ids'));
        $ids=self::ids($ids,36);set_transient($key,$ids,5*MINUTE_IN_SECONDS);return $ids;
    }
    public static function matches( array $record, array $filters ) {
        if(in_array('stimfree',$filters,true)&&($record['dietary']['stimulantFree']??null)!==true)return false;
        if(in_array('vegan',$filters,true)&&($record['dietary']['vegan']??null)!==true)return false;
        if(in_array('value',$filters,true)&&(($record['price']['perServingVerified']??false)!==true||!is_numeric($record['price']['perServing']??null)||(float)$record['price']['perServing']<=0))return false;
        return true;
    }
    private static function repeat_row( array $ref, $locale ) {
        $order=wc_get_order((int)$ref['orderId']);
        if(!qil_repeat_order_owned($order,(int)get_current_user_id()))return null;
        $item=$order->get_item((int)$ref['itemId']);
        if(!is_a($item,'WC_Order_Item_Product')||(int)$item->get_order_id()!==(int)$order->get_id())return null;
        $total=(float)$item->get_total();
        if($total>0&&abs((float)$order->get_total_refunded_for_item($item->get_id()))>=$total)return null;
        $selection=qil_repeat_selection($order,$item);
        if(!$selection||!$selection['canAdd']||!self::eligible($selection['parent']))return null;
        $product=$selection['product'];$label=$selection['label'];
        if('ar'===$locale&&$label){$map=qil_translate_batch(array($label),'general',false);$label=qil_translated($label,$map);}
        return array('productId'=>(int)$selection['parent']->get_id(),'variationId'=>(int)$selection['variationId'],
            'orderId'=>(int)$order->get_id(),'itemId'=>(int)$item->get_id(),'canAdd'=>true,'selection'=>$label,
            'url'=>esc_url_raw(qil_localized_url($selection['parent']->get_permalink(),'ar'===$locale)),
            'price'=>qil_product_price_schema($product,strtoupper(get_woocommerce_currency()),0),
            'inventory'=>qil_inventory_state($product),
            'image'=>$selection['variationId']?qil_image_data($product->get_image_id(),qil_clean_text($product->get_name())):null);
    }
    private static function parent_ids( array $ids ) {
        $out=array();foreach($ids as $id){$p=self::parent($id);if($p)$out[(int)$p->get_id()]=(int)$p->get_id();}return array_values($out);
    }
    /**
     * Shared/private response cache. Guests with the same page context share
     * one public result for a few minutes; a signed-in shopper reuses only
     * their own result, bound to their login session, for one minute. Nonces,
     * the account binding and signed presentation tokens are never cached:
     * they are issued fresh on every response.
     */
    private static function cart_signature() {
        $lines=array();$removed=array();
        if(function_exists('WC')&&WC()->cart){
            foreach(array_slice(WC()->cart->get_cart(),0,32) as $line)$lines[]=(int)($line['product_id']??0);
            foreach(array_slice(WC()->cart->get_removed_cart_contents(),0,24) as $line)$removed[]=(int)($line['product_id']??0);
        }
        return array($lines,$removed);
    }
    private static function cache_key( array $context ) {
        if(!function_exists('qil_perf_cache_get'))return '';
        $uid=(int)get_current_user_id();$key_context=$context;unset($key_context['task']);
        $identity=array('market'=>qil_perf_market_identity());
        if($uid){
            $identity['login']=hash('sha256',(string)wp_get_session_token());
            $identity['allowed']=self::allowed();
            $identity['orders']=(string)get_user_meta($uid,'_qil_purchase_refs_revision',true);
        }
        return 'qil_personal_v2_'.md5(wp_json_encode(array(self::SCHEMA,QIL_VERSION,$identity,$key_context,self::cart_signature(),
            (int)get_option('qil_personal_rollout',100),qil_perf_product_version())));
    }
    private static function cache_ttl( $uid, $partial ) {
        $ttl=$uid?MINUTE_IN_SECONDS:5*MINUTE_IN_SECONDS;
        if($partial)$ttl=min($ttl,30);
        return max(1,(int)apply_filters('qil_personalization_cache_ttl',$ttl,$uid,$partial));
    }
    public static function payload( array $context ) {
        $started=microtime(true);$uid=(int)get_current_user_id();
        $key=self::cache_key($context);
        // Only an anonymous visitor without a Woo session may share a result
        // through the database; account/session results stay in memory cache.
        $public=$key&&qil_perf_identity_public(qil_perf_market_identity());
        $core=$key?qil_perf_cache_get($key,false,$public):false;
        $hit=is_array($core)&&($core['schema']??'')===self::SCHEMA&&isset($core['groups'],$core['products']);
        $lock='';
        if(!$hit&&$public){
            // Many ad visitors land on the same page at once; let one build it.
            // Followers wait for the shared answer instead of immediately doing
            // the same catalogue/ranking work in another PHP worker.
            $lock=qil_perf_lock('personal|'.$key,20);
            if(''===$lock){
                $validator=static function($value){return is_array($value)&&($value['schema']??'')===self::SCHEMA&&isset($value['groups'],$value['products']);};
                $core=qil_perf_wait_for_cache($key,2.5,true,$validator);
                $hit=$validator($core);
                if(!$hit){
                    // If the original owner died, take over only after its lock is
                    // actually free. Otherwise allow it a second bounded window.
                    $lock=qil_perf_lock('personal|'.$key,20);
                    if(''===$lock){
                        $core=qil_perf_wait_for_cache($key,2.5,true,$validator);$hit=$validator($core);
                        if(!$hit){
                            $lock=qil_perf_lock('personal|'.$key,20);
                            if(''===$lock){$core=qil_perf_wait_for_cache($key,2.5,true,$validator);$hit=$validator($core);}
                        }
                    }
                }
            }
        }
        if(!$hit){
            try{
                $core=self::build($context);
                if($key)qil_perf_cache_set($key,$core,self::cache_ttl($uid,!empty($core['diagnostics']['budgetReached'])),$public);
            } finally { qil_perf_unlock($lock); }
        }
        return self::finalize($core,$context,$hit,$started);
    }
    /** Fresh per-response tokens around a cached or newly built result. */
    private static function finalize( array $core, array $context, $hit, $started ) {
        $uid=(int)get_current_user_id();$allowed=!empty($core['personal']);
        $variant=(string)($core['diagnostics']['variant']??'personalized');
        foreach($core['groups'] as &$group){
            unset($group['presentation']);
            if($allowed)$group['presentation']=self::sign(array('id'=>wp_generate_uuid4(),'ids'=>array_column($group['items'],'id'),'surface'=>$core['surface'],'group'=>$group['key'],'variant'=>$variant,'task'=>$context['task'],'exp'=>time()+900));
        }
        unset($group);
        $core['binding']=self::binding();
        $core['nonce']=$uid?wp_create_nonce('qil_buy_again'):'';
        $core['eventNonce']=$allowed?wp_create_nonce('qil_personal_events'):'';
        $core['serverTime']=time();
        $core['diagnostics']['cache']=$hit?'hit':'miss';
        $core['diagnostics']['servedMs']=(int)round((microtime(true)-$started)*1000);
        return $core;
    }
    private static function build( array $context ) {
        self::$started=microtime(true);self::$products=array();self::$profiles=array();
        $uid=(int)get_current_user_id();$allowed=self::allowed();$surface=$context['surface'];$locale=$context['locale'];
        $by_value=in_array('value',$context['filters'],true);
        $cart=array();$removed=array();$cross=array();
        if(function_exists('WC')&&WC()->cart){
            foreach(array_slice(WC()->cart->get_cart(),0,32) as $line){$pid=(int)($line['product_id']??0);if(!$pid)continue;$cart[]=$pid;$p=self::parent($pid);if($p)$cross=array_merge($cross,(array)$p->get_cross_sell_ids());}
            foreach(array_slice(WC()->cart->get_removed_cart_contents(),0,24) as $line)if(!empty($line['product_id']))$removed[]=(int)$line['product_id'];
        }
        $excluded=array_fill_keys(self::parent_ids(self::ids(array_merge($context['exclude'],$context['dismissed'],$cart,$removed,array($context['current'])),96)),true);
        $now=time();
        $interest=self::interests($allowed?self::activity():array(),$now);
        $canonical_recent=$allowed?self::my_qimia_recent_views():array();
        if($canonical_recent)$interest['recent']=$canonical_recent;
        $sources=$uid?self::purchase_refs($uid):array();$purchase_ids=array();$purchased_at=array();
        foreach($sources as $ref){$pid=(int)$ref['productId'];$purchase_ids[]=$pid;$purchased_at[$pid]=max($purchased_at[$pid]??0,(int)$ref['at']);}

        // Current task / permitted account activity forms the precise-intent layer.
        // Purchase history is kept separate so "more from your categories" can
        // work as an ordinary account-shopping feature without pretending that a
        // purchase proves a health goal or a current need.
        $scores=array();
        $add_seed=static function($id,$score)use(&$scores){if($id>0)$scores[$id]=max($scores[$id]??0,$score);};
        if($context['selected'])$add_seed($context['selected'],70);
        foreach($context['compare'] as $id)$add_seed($id,60);
        foreach($context['results'] as $rank=>$id)$add_seed($id,max(22,44-$rank*3));
        if($context['current'])$add_seed($context['current'],55);
        foreach($cart as $id)$add_seed($id,65);
        foreach($interest['scores'] as $id=>$score)$add_seed($id,$score);
        arsort($scores,SORT_NUMERIC);$scores=array_slice($scores,0,12,true);

        $categories=array();$seed_profiles=array();
        foreach($scores as $id=>$weight){
            $p=self::parent($id);if(!$p||'publish'!==$p->get_status()||!$p->is_visible())continue;
            $profile=self::profile($p);$seed_profiles[]=array('id'=>(int)$p->get_id(),'weight'=>$weight,'profile'=>$profile);
            foreach($profile['categories'] as $slug)$categories[$slug]=($categories[$slug]??0)+$weight;
        }

        // Exact persistent recent views come through My Qimia's read-only agent
        // contract when available; the permitted ledger remains a compatibility
        // fallback. Browser hints are revalidated and never imply ownership.
        $recent_at=array();
        foreach($interest['recent'] as $view_id=>$at){
            $view_parent=self::parent($view_id);if(!$view_parent)continue;
            $view_parent_id=(int)$view_parent->get_id();
            $recent_at[$view_parent_id]=max($recent_at[$view_parent_id]??0,(int)$at);
        }
        foreach($context['recent'] as $rank=>$view_id){
            $view_parent=self::parent($view_id);if(!$view_parent)continue;
            $view_parent_id=(int)$view_parent->get_id();
            // Rank-only timestamp keeps browser order deterministic without
            // accepting a client-supplied clock value.
            $recent_at[$view_parent_id]=max($recent_at[$view_parent_id]??0,$now-min(3600,$rank));
        }
        arsort($recent_at,SORT_NUMERIC);$recent_ids=array_keys($recent_at);

        // Broader category continuity uses only public taxonomy attached to
        // verified viewed / purchased products. It does not copy raw searches,
        // chat text, health data or customer attributes into a second profile.
        $category_profiles=array();
        foreach(array_slice($recent_ids,0,6) as $rank=>$id){
            $p=self::parent($id);if(!$p||'publish'!==$p->get_status()||!$p->is_visible())continue;
            $profile=self::profile($p);$weight=max(12,30-$rank*3);
            $category_profiles[]=array('id'=>(int)$p->get_id(),'weight'=>$weight,'profile'=>$profile,'source'=>'viewed');
            foreach($profile['categories'] as $slug)$categories[$slug]=($categories[$slug]??0)+$weight;
        }
        foreach(array_slice($purchase_ids,0,6) as $rank=>$id){
            $p=self::parent($id);if(!$p||'publish'!==$p->get_status()||!$p->is_visible())continue;
            $profile=self::profile($p);$weight=max(10,24-$rank*2);
            $category_profiles[]=array('id'=>(int)$p->get_id(),'weight'=>$weight,'profile'=>$profile,'source'=>'purchase');
            foreach($profile['categories'] as $slug)$categories[$slug]=($categories[$slug]??0)+$weight;
        }
        arsort($categories,SORT_NUMERIC);

        $cross=self::ids($cross,12);$pool=array();
        // One bounded public catalogue pool feeds every non-cart tab. We do not
        // run one catalogue query per tab, so adding useful tabs does not create
        // N+1 queries or a per-customer product cache.
        if($surface!=='cart'&&self::within_budget())$pool=self::category_candidates(array_keys($categories));
        $candidates=self::ids(array_merge($purchase_ids,$recent_ids,$context['compare'],$context['results'],$cross,$pool),self::MAX_CANDIDATES);
        $ranking=array();$cart_categories=array();
        foreach($cart as $id){$p=self::parent($id);if($p)$cart_categories=array_merge($cart_categories,self::profile($p)['categories']);}
        foreach($candidates as $id){
            if(!self::within_budget()&&count($ranking)>0)break;
            $p=self::parent($id);if(!$p)continue;$id=(int)$p->get_id();
            if(isset($ranking[$id])||isset($excluded[$id])||!self::eligible($p,$surface))continue;
            $profile=self::profile($p);$score=0;$category_score=0;
            foreach($seed_profiles as $seed){
                $shared=count(array_intersect($profile['categories'],$seed['profile']['categories']));if(!$shared)continue;
                $value=$seed['weight']*min(1.5,$shared);
                if($profile['brand']&&$profile['brand']===$seed['profile']['brand'])$value+=4;
                if($profile['price']>0&&$seed['profile']['price']>0)$value+=max(0,4-abs(log($profile['price']/$seed['profile']['price']))*3);
                $score=max($score,$value);
            }
            foreach($category_profiles as $seed){
                $shared=count(array_intersect($profile['categories'],$seed['profile']['categories']));if(!$shared)continue;
                $category_score=max($category_score,$seed['weight']*min(1.35,$shared));
            }
            $is_repeat=in_array($id,$purchase_ids,true);$is_recent=in_array($id,$recent_ids,true);$is_cross=in_array($id,$cross,true);
            if($surface==='cart'&&!$is_cross){if(!$is_repeat||array_intersect($profile['categories'],$cart_categories))continue;}
            if(!$is_repeat&&!$is_recent&&!$is_cross&&$score<=0&&$category_score<=0)continue;
            $ranking[$id]=array('id'=>$id,'score'=>$score-($interest['fatigue'][$id]??0),'categoryScore'=>$category_score,'repeat'=>$is_repeat,'recent'=>$is_recent,'cross'=>$is_cross);
        }
        uasort($ranking,static function($a,$b){
            $bp=($b['repeat']?1000:($b['recent']?500:0))+max($b['score'],$b['categoryScore']);
            $ap=($a['repeat']?1000:($a['recent']?500:0))+max($a['score'],$a['categoryScore']);
            return ($bp<=>$ap)?:($a['id']<=>$b['id']);
        });

        // Expensive shared card construction stays bounded. Reserve enough room
        // for five visible desktop cards per useful tab, while one catalogue read
        // and a hard 24-record hydration cap protect the request path.
        $repeat_short=array();$recent_short=array();$related_short=array();$category_short=array();
        foreach($ranking as $id=>$candidate){
            if($candidate['repeat'])$repeat_short[]=$id;
            elseif($candidate['recent'])$recent_short[]=$id;
            else{
                if($candidate['cross']||$candidate['score']>0)$related_short[]=$id;
                if($candidate['categoryScore']>0)$category_short[]=$id;
            }
        }
        $related_reserve=array_slice($related_short,0,6);
        $related_reserved=array_fill_keys($related_reserve,true);
        $category_reserve=array();
        foreach($category_short as $candidate_id){
            if(isset($related_reserved[$candidate_id]))continue;
            $category_reserve[]=$candidate_id;
            if(count($category_reserve)>=6)break;
        }
        $hydrate=self::ids(array_merge(
            array_slice($repeat_short,0,6),array_slice($recent_short,0,6),
            $related_reserve,$category_reserve,array_keys($ranking)
        ),self::MAX_RECORDS);
        $records=$hydrate?qil_get_catalogue(array('include'=>$hydrate,'limit'=>count($hydrate),'_qil_locale'=>$locale)):array();
        $by_id=array();$variation_work=0;
        foreach($records as $record){
            if(in_array('value',$context['filters'],true)){
                $p=self::get_product($record['id']);$count=$p&&$p->is_type('variable')?count($p->get_visible_children()):1;
                if($count>60||$variation_work+$count>60)continue;$variation_work+=$count;$record=qil_goal_value_record($record);
            }
            if(self::matches($record,$context['filters']))$by_id[(int)$record['id']]=$record;
        }
        $groups=array('again'=>array(),'recent'=>array(),'categories'=>array(),'related'=>array());$taken=array();$purchases=array();$checked=0;$repeat_unit=array();
        foreach($sources as $ref){
            $id=(int)$ref['productId'];if(isset($taken[$id])||!isset($by_id[$id])||!isset($ranking[$id]))continue;
            if(++$checked>12)break;
            $row=self::repeat_row($ref,$locale);if(!$row)continue;
            if($context['filters']){
                // Per-serving proof for repeat cards must refer to the exact old option.
                $effective=$by_id[$id];$effective['price']=$row['price'];
                if($row['variationId']){
                    $variant_product=self::get_product($row['variationId']);
                    $variant_profile=qil_goal_taxonomy_profile($row['variationId']);
                    $variant_flags=qil_goal_verified_dietary($variant_product,$variant_profile['dietary']);
                    foreach(array('vegan','stimulantFree') as $flag)if(false===($variant_flags[$flag]??null))$effective['dietary'][$flag]=false;
                }
                if(!self::matches($effective,array_values(array_diff($context['filters'],array('value')))))continue;
                if(in_array('value',$context['filters'],true)){
                    $p=self::get_product($row['variationId']?:$id);$count=qil_filter_product_servings($p);
                    if(null===$count&&$row['variationId']){
                        $parent=self::get_product($id);$flavour_only=true;
                        foreach(array_keys((array)$parent->get_variation_attributes()) as $name)if(!in_array(qil_goal_token(preg_replace('/^pa_/i','',$name)),array('flavor','flavour','flavors','flavours','taste','نكهة','النكهة'),true))$flavour_only=false;
                        if($flavour_only)$count=qil_filter_product_servings($parent,$by_id[$id]['facts']['servings']??'');
                    }
                    if(!$count||$count<1)continue;$unit=(float)wc_get_price_to_display($p)/$count;if($unit<=0)continue;
                    $display=qil_price_display($unit,$unit,$row['price']['currency']);
                    $row['price']['perServingVerified']=true;$row['price']['perServing']=$unit;$row['price']['perServingHtml']=$display['formattedHtml'];$row['price']['perServingFormatted']=$display['formatted'];
                }
            }
            $purchases[]=$row;$taken[$id]=true;$repeat_unit[$id]=$row['price']['perServing']??PHP_INT_MAX;
            $groups['again'][]=array('id'=>$id,'key'=>$row['orderId'].':'.$row['itemId'],'reason'=>'previous_purchase');
            if(!$by_value&&count($groups['again'])>=self::MAX_ROWS)break;
        }
        foreach($recent_ids as $id){
            if(isset($taken[$id])||!isset($by_id[$id]))continue;
            // After a purchase it belongs to Buy again, not unfinished browsing.
            $last_view=$recent_at[$id]??0;if(isset($purchased_at[$id])&&$purchased_at[$id]>=$last_view)continue;
            $groups['recent'][]=array('id'=>$id,'reason'=>'viewed');$taken[$id]=true;if(!$by_value&&count($groups['recent'])>=self::MAX_ROWS)break;
        }
        $rollout=max(0,min(100,(int)get_option('qil_personal_rollout',100)));
        $variant= !$uid || hexdec(substr(hash_hmac('sha256',(string)$uid,wp_salt('auth')),0,6))%100 < $rollout ? 'personalized':'control';

        // Precise current/permitted intent gets first choice of new products.
        // The broader categories tab then fills from the same already-ranked,
        // already-hydrated pool without repeating cards between tabs.
        $suggestions=array_values($ranking);usort($suggestions,static function($a,$b){return ($b['cross']<=>$a['cross'])?:($b['score']<=>$a['score'])?:($b['categoryScore']<=>$a['categoryScore'])?:($a['id']<=>$b['id']);});
        if($variant==='personalized')foreach($suggestions as $candidate){
            $id=$candidate['id'];if(isset($taken[$id])||!isset($by_id[$id]))continue;
            if(isset($purchased_at[$id])||(!$candidate['cross']&&$candidate['score']<=0))continue;
            $groups['related'][]=array('id'=>$id,'reason'=>$candidate['cross']?'merchant_cross_sell':'interest');$taken[$id]=true;if(!$by_value&&count($groups['related'])>=self::MAX_ROWS)break;
        }
        $category_suggestions=array_values($ranking);usort($category_suggestions,static function($a,$b){return ($b['categoryScore']<=>$a['categoryScore'])?:($b['score']<=>$a['score'])?:($a['id']<=>$b['id']);});
        foreach($category_suggestions as $candidate){
            $id=$candidate['id'];if(isset($taken[$id])||!isset($by_id[$id])||$candidate['categoryScore']<=0)continue;
            if(isset($purchased_at[$id]))continue; // Exact owned products stay in Buy Again only.
            $groups['categories'][]=array('id'=>$id,'reason'=>'category_continuity');$taken[$id]=true;if(!$by_value&&count($groups['categories'])>=self::MAX_ROWS)break;
        }
        // Sort the already bounded, hydrated/validated shortlist BEFORE the
        // twelve-card carousel cutoff. Sorting after slicing can discard the best value.
        // The catalogue query stays single and bounded; hydration is capped at 24.
        $sort_value=static function($a,$b)use($by_id,$repeat_unit){return (isset($a['key'])?($repeat_unit[$a['id']]??PHP_INT_MAX):($by_id[$a['id']]['price']['perServing']??PHP_INT_MAX))<=>(isset($b['key'])?($repeat_unit[$b['id']]??PHP_INT_MAX):($by_id[$b['id']]['price']['perServing']??PHP_INT_MAX));};
        foreach($groups as &$rows){if($by_value)usort($rows,$sort_value);$rows=array_slice($rows,0,self::MAX_ROWS);}unset($rows);
        if($surface==='cart'){
            $cart_rows=array_merge($groups['related'],$groups['again']);
            if($by_value)usort($cart_rows,$sort_value);
            $groups=array('cart'=>array_slice($cart_rows,0,4));
        }
        $result_groups=array();$used=array();
        foreach($groups as $key=>$rows){if(!$rows)continue;foreach($rows as $row)$used[$row['id']]=true;
            // Signed presentation tokens are added per response in finalize().
            $result_groups[]=array('key'=>$key,'items'=>$rows);
        }
        $out=array();foreach(array_keys($used) as $id)$out[]=$by_id[$id];
        // Account payloads are cached only for their own login session (see
        // payload()); nonces and signed tokens are added fresh in finalize().
        return array('schema'=>self::SCHEMA,'surface'=>$surface,'locale'=>$locale,'filters'=>$context['filters'],'currency'=>strtoupper(get_woocommerce_currency()),
            'country'=>function_exists('qil_market_context')?(qil_market_context()['country']??''):'',
            'authenticated'=>$uid>0,'personal'=>$allowed,'binding'=>'','nonce'=>'',
            'eventNonce'=>'','groups'=>$result_groups,'products'=>$out,
            'purchases'=>array_values(array_filter($purchases,static function($row)use($used){return isset($used[$row['productId']]);})),
            'serverTime'=>time(),'diagnostics'=>array('candidateLimit'=>self::MAX_CANDIDATES,'candidates'=>count($candidates),'hydrated'=>count($hydrate),'elapsedMs'=>(int)round((microtime(true)-self::$started)*1000),'budgetReached'=>!self::within_budget(),'variant'=>$variant));
    }
    public static function binding() {
        return hash_hmac('sha256',get_current_user_id().'|'.wp_get_session_token(),wp_salt('auth'));
    }
    private static function sign( array $data ) {
        $data['binding']=self::binding();$json=rtrim(strtr(base64_encode(wp_json_encode($data)),'+/','-_'),'=');
        return $json.'.'.hash_hmac('sha256',$json,wp_salt('nonce'));
    }
    public static function verify( $token ) {
        if(!is_string($token)||strlen($token)>2300||!self::allowed())return null;
        $parts=explode('.',$token);if(count($parts)!==2||!hash_equals(hash_hmac('sha256',$parts[0],wp_salt('nonce')),$parts[1]))return null;
        $json=base64_decode(strtr($parts[0],'-_','+/'),true);$data=$json?json_decode($json,true):null;
        if(!is_array($data)||!hash_equals(self::binding(),(string)($data['binding']??''))||($data['exp']??0)<time()||($data['exp']??0)>time()+901)return null;
        if(!is_callable(array('QH_Account','uuid'))||!QH_Account::uuid($data['id']??''))return null;
        $data['ids']=self::ids($data['ids']??array(),self::MAX_ROWS);return $data['ids']?$data:null;
    }
    public static function ajax() {
        qil_continuity_private_headers();
        if(function_exists('qil_perf_noninteractive_bot')&&qil_perf_noninteractive_bot())wp_send_json(array('schema'=>self::SCHEMA,'groups'=>array(),'products'=>array(),'bot'=>true));
        if(!self::enabled()||!self::same_origin()||!function_exists('wc_get_products'))wp_send_json(array('error'=>true),403);
        $raw=isset($_POST['context'])&&is_string($_POST['context'])?wp_unslash($_POST['context']):'';
        if(strlen($raw)>6000)wp_send_json(array('error'=>true),413);
        $input=json_decode($raw,true);if(!is_array($input))wp_send_json(array('error'=>true),400);
        try { $response=self::payload(self::context($input)); }
        catch(Throwable $error){wp_send_json(array('schema'=>self::SCHEMA,'error'=>true,'groups'=>array()),503);return;}
        wp_send_json($response);
    }
    /** Reuse the existing ledger, behind its account privacy lock, off the rendering path. */
    public static function events() {
        qil_continuity_private_headers();
        if(!self::enabled()||!self::same_origin()||!self::allowed()||!check_ajax_referer('qil_personal_events','nonce',false))wp_send_json(array('error'=>true),403);
        $raw=isset($_POST['events'])&&is_string($_POST['events'])?wp_unslash($_POST['events']):'';
        if(strlen($raw)>24000)wp_send_json(array('error'=>true),413);
        $events=json_decode($raw,true);if(!is_array($events)||count($events)>10)wp_send_json(array('error'=>true),400);
        $accepted=array();$decisions=array();
        try {
        $result=QH_Account::with_account_lock(get_current_user_id(),static function()use($events,&$accepted,&$decisions){
            if(!self::allowed())return false;
            global $wpdb;
            foreach($events as $event){
                if(!is_array($event)||!QH_Account::uuid($event['id']??''))continue;
                $type=$event['type']??'';if(!in_array($type,array('recommendation_view','recommendation_click','reorder_click'),true))continue;
                $ref=self::verify($event['presentation']??'');$pid=self::ids(array($event['product']??0),1)[0]??0;
                if(!$ref||!in_array($pid,$ref['ids'],true))continue;
                $p=self::parent($pid);if(!$p||'publish'!==$p->get_status())continue;
                $context=array('schema_version'=>2,'surface'=>$ref['surface'],'placement'=>$ref['group'],'variant'=>$ref['variant'],'engine'=>self::SCHEMA,'task_id'=>$ref['task'],'trusted_source'=>'signed_storefront_presentation','session_ref'=>substr(QH_Account::session(),0,32));
                if(!isset($decisions[$ref['id']])){
                    $decisions[$ref['id']]=false!==$wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.QH_Account::table('decisions').' (id,user_id,session_hash,products,reason,context,created_at,expires_at) VALUES (%s,%d,%s,%s,%s,%s,%d,%d)',
                        $ref['id'],get_current_user_id(),QH_Account::session(),wp_json_encode($ref['ids']),'recommendation',wp_json_encode($context),time(),time()+7*DAY_IN_SECONDS));
                }
                if(!$decisions[$ref['id']])continue;
                $context['trusted_source']='browser_intent';
                // A visibility report is not proof of cart success, payment or need.
                if(QH_Account::insert('qil:'.get_current_user_id().':'.$event['id'],get_current_user_id(),$type,$pid,$ref['id'],$context))$accepted[]=$event['id'];
            }
            return true;
        });
        } catch(Throwable $error) {
            wp_send_json(array('accepted'=>$accepted,'coverage'=>'best_effort_opt_in','error'=>true),503);return;
        }
        wp_send_json(array('accepted'=>$accepted,'coverage'=>'best_effort_opt_in','error'=>is_wp_error($result)||$result!==true));
    }
    /** No ledger writes / network / waiting on analytics in the purchase path. */
    public static function attribution( $data, $pid, $vid = 0, $quantity = 1 ) {
        if(!self::enabled()||!self::allowed())return $data;
        $raw=isset($_POST['qil_presentation'])&&is_string($_POST['qil_presentation'])?wp_unslash($_POST['qil_presentation']):'';
        if(!$raw)return $data;
        try {
            $ref=self::verify($raw);if(!$ref)return $data;
            $parent=self::parent($vid?:$pid);if(!$parent||!in_array((int)$parent->get_id(),$ref['ids'],true))return $data;
            $decision=QH_Account::valid_decision($ref['id'],(int)($vid?:$pid));
            if($decision)$data['qh_decision_id']=$decision->id;
        } catch(Throwable $error){/* Optional attribution cannot block the cart. */}
        return $data;
    }
    public static function admin() {
        if(!current_user_can('manage_options'))return;
        echo '<section class="qimia-admin-panel"><h2>Unified shopping experience</h2><p>One card renderer across Home, Product and Cart. Prices, stock and exact previous options are checked on each private response. Ordinary shopping never waits for activity delivery.</p>';
        echo '<form method="post" action="options.php">';settings_fields('qil_personal_settings');
        echo '<input type="hidden" name="qil_personalization_enabled" value="0"><p><label><input type="checkbox" name="qil_personalization_enabled" value="1" '.checked('1',(string)get_option('qil_personalization_enabled','1'),false).'> Enable the unified shelves</label></p>';
        echo '<p><label>Related-selection rollout for signed-in accounts (%) <input type="number" min="0" max="100" step="1" name="qil_personal_rollout" value="'.esc_attr((int)get_option('qil_personal_rollout',100)).'"></label></p><p class="description">The stable control group keeps Buy Again, Recently Viewed and category continuity, but hides the current-intent related-selection tab. Guest contextual selections are not part of this account experiment.</p>';
        submit_button('Save shopping experience');echo '</form><h3>Runtime boundaries</h3><table class="widefat striped"><tbody>';
        $rows=array('Ranking candidates'=>'At most 48','Full product cards'=>'At most 24 per response','Displayed items'=>'Up to 12 per carousel; 4 in Cart','Order reference refresh'=>'30 orders / 240 lines; 10-minute private reference cache','Optional history'=>'My Qimia owns exact recent views and permitted Event Ledger history; this Lab keeps no second session profile','Product pool'=>'Public IDs only, 5-minute cache; no customer HTML','Response cache'=>'Keyed by currency, country, tax context, exchange-rate settings and cart. Anonymous guests: one shared result per page context for 5 minutes. Signed-in or Woo-session shoppers: private, memory object cache only, 1 minute. Nonces and signed tokens are issued fresh on every response','Activity queue'=>'At most 50 in-memory events, batches of 10; best effort','External calls'=>'None in the recommendation adapter','Privacy owner'=>class_exists('QH_Account')?'Existing My Qimia runtime detected; each account permission is checked separately':'Not detected: no retained browsing or activity recording','Emergency stop'=>'QIL_PERSONALIZATION_DISABLE = true');
        foreach($rows as $key=>$value)echo '<tr><th scope="row">'.esc_html($key).'</th><td>'.esc_html($value).'</td></tr>';
        echo '</tbody></table><p>Existing Qimia AI → Event Ledger remains the source for recorded journeys and attributed cart/order events. Browser visibility is best-effort, not proof of a sale. Missing, blocked or early-click attribution stays unknown. There is no duplicate analytics table.</p><p>Inspect <code>QILPersonalization.diagnostics()</code> in the browser for this page’s request counts and elapsed server time. These are not host-wide load benchmarks. Clear page/CDN caches after changing the rollout or disabling these shelves.</p></section>';
    }
    public static function assets() {
        if(!self::enabled()||is_admin()||qil_is_elementor_context()||'none'===qil_render_mode()||(function_exists('qil_perf_noninteractive_bot')&&qil_perf_noninteractive_bot())||(function_exists('qil_perf_crawler_family')&&''!==qil_perf_crawler_family()))return;
        wp_enqueue_style('qil-personalization',QIL_URL.'assets/qil-personalization.min.css',array('qimia-intelligence-lab'),QIL_VERSION);
        wp_enqueue_script('qil-personalization',QIL_URL.'assets/qil-personalization.min.js',array('qimia-intelligence-lab','qimia-shopping-context'),QIL_VERSION,true);
    }
    public static function shell( $surface = 'home' ) {
        if(!self::enabled())return '';
        $surface=in_array($surface,array('home','product','cart'),true)?$surface:'home';
        $ar=!empty(qil_view_context()['isArabic']);$key='qil-personal-'.$surface;
        $title=$surface==='cart'?($ar?'أكمل اختياراتك':'Complete your selection'):($ar?'اختياراتك، أقرب إليك':'Your choices, close at hand');
        ob_start(); ?>
        <div class="qil-personal-anchor" data-qil-personal-anchor="<?php echo esc_attr($surface); ?>">
        <section class="qil-section qil-commerce-collections qil-repeat-section qil-personal-section" data-qil-personal="<?php echo esc_attr($surface); ?>" data-qil-repeat-section="<?php echo esc_attr($surface); ?>" hidden aria-labelledby="<?php echo esc_attr($key); ?>-title">
            <div class="qil-container"><article class="qil-collection-block">
                <div class="qil-collection-head"><div><small><?php echo esc_html($ar?'تجربة كيميا':'YOUR QIMIA'); ?></small><h3 id="<?php echo esc_attr($key); ?>-title"><?php echo esc_html($title); ?></h3><p data-qil-personal-description></p></div>
                <div class="qil-collection-tools"><div class="qil-rail-nav" data-qil-rail-nav="<?php echo esc_attr($key); ?>"><button type="button" data-qil-rail-prev aria-label="<?php echo esc_attr($ar?'السابق':'Previous'); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button><button type="button" data-qil-rail-next aria-label="<?php echo esc_attr($ar?'التالي':'Next'); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button></div></div></div>
                <div class="qil-personal-tabs" data-qil-personal-tabs role="tablist" aria-label="<?php echo esc_attr($ar?'اختيارات التسوق':'Shopping selections'); ?>"></div>
                <div class="qil-collection-grid qil-rail" data-qil-repeat-grid data-qil-personal-grid data-qil-rail="<?php echo esc_attr($key); ?>" role="tabpanel" id="<?php echo esc_attr($key); ?>-panel" tabindex="0"></div>
                <p class="qil-repeat-status" data-qil-repeat-status role="status" aria-live="polite"></p>
            </article></div>
        </section></div>
        <?php return (string)ob_get_clean();
    }
    private static function cart_markup() {
        if(self::$cart_printed||!self::enabled()||!function_exists('is_cart')||!is_cart()||is_admin())return '';
        self::$cart_printed=true;$ctx=qil_view_context();
        return '<div class="qil-shell qil-personal-cart-shell notranslate" data-qil-shell dir="'.esc_attr($ctx['direction']).'" lang="'.esc_attr($ctx['locale']).'" data-qil-locale="'.esc_attr($ctx['locale']).'" translate="no">'.self::shell('cart').'</div>';
    }
    public static function cart() { echo self::cart_markup(); }
    public static function cart_block( $content, $block = array() ) { return $content.self::cart_markup(); }
}
QIL_Personalization::boot();
