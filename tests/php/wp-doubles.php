<?php
/**
 * Minimal WordPress + WooCommerce runtime doubles.
 *
 * Just enough of both APIs to load the real plugin and execute the real code
 * paths of the 1.18 features (and the existing catalogue builder they reuse).
 * This is not a WordPress emulator: SQL, templates, themes and HTTP are not
 * modelled. State lives in $GLOBALS['qt'] and is reset per test.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'WP_DEBUG', false );

function qt_reset() {
	$hooks = $GLOBALS['qt']['hooks'] ?? array();
	$GLOBALS['qt'] = array(
		'hooks'      => $hooks,
		'done'       => array(),
		'options'    => array( 'home' => 'https://qimia.om', 'woocommerce_currency' => 'OMR', 'qil_enabled' => '1', 'woocommerce_tax_display_shop' => 'incl' ),
		'transients' => array(),
		'cache'      => array(),
		'products'   => array(),
		'meta'       => array(),
		'terms'      => array(),
		'rel'        => array(),
		'coupons'    => array(),
		'orders'     => array(),
		'users'      => array(),
		'usermeta'   => array(),
		'user'       => 0,
		'currency'   => 'OMR',
		'decimals'   => 3,
		'enqueued'   => array(),
		'inline'     => array(),
		'shortcodes' => $GLOBALS['qt']['shortcodes'] ?? array(),
		'rest'       => $GLOBALS['qt']['rest'] ?? array(),
		'cron'       => array(),
		'purged'     => array(),
		'render'     => 'full',
		'is_cart'    => false,
		'referer'    => '',
		'notices'    => array(),
		'taxonomies' => array( 'product_cat', 'product_tag', 'product_type', 'product_visibility', 'pa_flavour', 'pa_size' ),
	);
	$GLOBALS['qt_cart'] = new WC_Cart();
	$GLOBALS['qt_session'] = new QT_Session();
	$_SERVER['HTTP_HOST'] = 'qimia.om';
	$_SERVER['REQUEST_URI'] = '/';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	unset( $_SERVER['HTTP_X_QIMIA_LANGUAGE'], $_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_SEC_FETCH_SITE'], $_SERVER['HTTP_X_QIMIA_REQUEST'], $_SERVER['HTTP_REFERER'] );
	$_GET = array();
	$_POST = array();
	$_REQUEST = array();
}

/* ---------------------------------------------------------------- hooks */
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['qt']['hooks'][ $tag ][ $prio ][] = array( $cb, (int) $args ); return true; }
function add_action( $tag, $cb, $prio = 10, $args = 1 ) { return add_filter( $tag, $cb, $prio, $args ); }
function remove_filter( $tag, $cb, $prio = 10 ) {
	foreach ( $GLOBALS['qt']['hooks'][ $tag ][ $prio ] ?? array() as $i => $row ) {
		if ( $row[0] === $cb ) { unset( $GLOBALS['qt']['hooks'][ $tag ][ $prio ][ $i ] ); return true; }
	}
	return false;
}
function remove_action( $tag, $cb, $prio = 10 ) { return remove_filter( $tag, $cb, $prio ); }
function has_filter( $tag, $cb = false ) { return ! empty( $GLOBALS['qt']['hooks'][ $tag ] ); }
function has_action( $tag, $cb = false ) { return has_filter( $tag, $cb ); }
function apply_filters( $tag, $value, ...$args ) {
	$hooks = $GLOBALS['qt']['hooks'][ $tag ] ?? array();
	if ( ! $hooks ) { return $value; }
	ksort( $hooks );
	foreach ( $hooks as $rows ) {
		foreach ( $rows as $row ) {
			$value = call_user_func_array( $row[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $row[1] ) ) );
		}
	}
	return $value;
}
function do_action( $tag, ...$args ) {
	$GLOBALS['qt']['done'][ $tag ] = ( $GLOBALS['qt']['done'][ $tag ] ?? 0 ) + 1;
	$hooks = $GLOBALS['qt']['hooks'][ $tag ] ?? array();
	ksort( $hooks );
	foreach ( $hooks as $rows ) {
		foreach ( $rows as $row ) {
			call_user_func_array( $row[0], array_slice( $args, 0, $row[1] ) );
		}
	}
}
function did_action( $tag ) { return (int) ( $GLOBALS['qt']['done'][ $tag ] ?? 0 ); }
function doing_action( $tag = null ) { return false; }
function register_activation_hook( $file, $cb ) {}
function register_deactivation_hook( $file, $cb ) { $GLOBALS['qt']['deactivate'][] = $cb; }
function add_shortcode( $tag, $cb ) { $GLOBALS['qt']['shortcodes'][ $tag ] = $cb; }
function has_shortcode( $content, $tag ) { return false !== strpos( (string) $content, '[' . $tag ); }

/* ------------------------------------------------------ options & cache */
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['qt']['options'] ) ? $GLOBALS['qt']['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) {
	$exists = array_key_exists( $key, $GLOBALS['qt']['options'] );
	$old    = $exists ? $GLOBALS['qt']['options'][ $key ] : false;
	$GLOBALS['qt']['options'][ $key ] = $value;
	if ( $exists ) { do_action( 'update_option_' . $key, $old, $value, $key ); } else { do_action( 'add_option_' . $key, $key, $value ); }
	return true;
}
function add_option( $key, $value = '', $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $key, $GLOBALS['qt']['options'] ) ) { return false; }
	$GLOBALS['qt']['options'][ $key ] = $value;
	do_action( 'add_option_' . $key, $key, $value );
	return true;
}
function delete_option( $key ) { unset( $GLOBALS['qt']['options'][ $key ] ); return true; }
function get_transient( $key ) {
	$row = $GLOBALS['qt']['transients'][ $key ] ?? null;
	if ( ! $row || ( $row[1] && $row[1] < time() ) ) { return false; }
	return $row[0];
}
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['qt']['transients'][ $key ] = array( $value, $ttl ? time() + $ttl : 0 ); return true; }
function delete_transient( $key ) { unset( $GLOBALS['qt']['transients'][ $key ] ); return true; }
function wp_using_ext_object_cache() { return false; }
function wp_cache_get( $key, $group = '', $force = false, &$found = null ) { $found = isset( $GLOBALS['qt']['cache'][ $group ][ $key ] ); return $found ? $GLOBALS['qt']['cache'][ $group ][ $key ] : false; }
function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) { $GLOBALS['qt']['cache'][ $group ][ $key ] = $value; return true; }
function wp_cache_add( $key, $value, $group = '', $ttl = 0 ) { if ( isset( $GLOBALS['qt']['cache'][ $group ][ $key ] ) ) { return false; } return wp_cache_set( $key, $value, $group ); }
function wp_cache_delete( $key, $group = '' ) { unset( $GLOBALS['qt']['cache'][ $group ][ $key ] ); return true; }
function wp_cache_get_last_changed( $group ) { return '1'; }
function get_site_transient( $k ) { return false; }

/* ---------------------------------------------------- strings & escaping */
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $u, $p = null ) { $u = (string) $u; if ( '' === $u ) { return ''; } if ( preg_match( '#^\s*javascript:#i', $u ) ) { return ''; } return str_replace( array( '"', "'", '<', '>', ' ' ), array( '%22', '%27', '%3C', '%3E', '%20' ), $u ); }
function esc_url_raw( $u, $p = null ) { return esc_url( $u ); }
function esc_html__( $t, $d = '' ) { return esc_html( $t ); }
function esc_attr__( $t, $d = '' ) { return esc_attr( $t ); }
function __( $t, $d = '' ) { return $t; }
function _x( $t, $c, $d = '' ) { return $t; }
function _n( $s, $p, $n, $d = '' ) { return 1 === (int) $n ? $s : $p; }
function wp_kses_post( $html ) { return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html ); }
function wp_strip_all_tags( $t, $breaks = false ) { return trim( strip_tags( preg_replace( '#<(script|style)[^>]*?>.*?</\\1>#si', '', (string) $t ) ) ); }
function sanitize_text_field( $t ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $t ) ) ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
// Same rules as WordPress's sanitize_title_with_dashes() for ASCII input: underscores stay.
function sanitize_title( $t ) { $t = strtolower( remove_accents( wp_strip_all_tags( (string) $t ) ) ); $t = str_replace( '.', '-', $t ); $t = preg_replace( '/[^%a-z0-9 _-]/', '', $t ); $t = preg_replace( '/\s+/', '-', $t ); return trim( preg_replace( '/-+/', '-', $t ), '-' ); }
function sanitize_email( $e ) { return filter_var( trim( (string) $e ), FILTER_SANITIZE_EMAIL ); }
function is_email( $e ) { return false !== filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
function absint( $n ) { return abs( (int) $n ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wp_json_encode( $d, $o = 0, $depth = 512 ) { return json_encode( $d, $o, $depth ); }
function remove_accents( $t ) { return (string) $t; }
function untrailingslashit( $t ) { return rtrim( (string) $t, '/\\' ); }
function trailingslashit( $t ) { return untrailingslashit( $t ) . '/'; }
function wp_trim_words( $text, $num = 55, $more = '…' ) { $w = preg_split( '/\s+/', trim( (string) $text ) ); return count( $w ) > $num ? implode( ' ', array_slice( $w, 0, $num ) ) . $more : trim( (string) $text ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function wp_list_pluck( $list, $field ) { return array_map( static function ( $r ) use ( $field ) { return is_array( $r ) ? ( $r[ $field ] ?? null ) : ( $r->$field ?? null ); }, (array) $list ); }
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) { $params = $args[0]; $url = $args[1] ?? ''; } else { $params = array( $args[0] => $args[1] ); $url = $args[2] ?? ''; }
	$parts = explode( '#', (string) $url, 2 );
	$base  = $parts[0];
	$query = array();
	if ( false !== strpos( $base, '?' ) ) { list( $base, $qs ) = explode( '?', $base, 2 ); parse_str( $qs, $query ); }
	foreach ( $params as $k => $v ) { if ( false === $v || null === $v ) { unset( $query[ $k ] ); } else { $query[ $k ] = $v; } }
	return $base . ( $query ? '?' . http_build_query( $query ) : '' ) . ( isset( $parts[1] ) ? '#' . $parts[1] : '' );
}
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) { echo $r; } return $r; }
function selected( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) { echo $r; } return $r; }
function wp_unique_id( $p = '' ) { static $i = 0; return $p . ( ++$i ); }
function wp_rand( $min = 0, $max = 0 ) { return random_int( $min, max( $min, $max ) ); }
function wp_generate_uuid4() { return sprintf( '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 4095 ), random_int( 32768, 49151 ), random_int( 0, 65535 ), random_int( 0, 65535 ), random_int( 0, 65535 ) ); }
function wp_salt( $s = 'auth' ) { return 'test-salt-' . $s; }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d ); }
function human_time_diff( $a, $b = 0 ) { return abs( ( $b ?: time() ) - $a ) . ' seconds'; }
function current_time( $type ) { return 'Y-m-d' === $type ? gmdate( 'Y-m-d' ) : time(); }
function get_bloginfo( $k = '' ) { return 'charset' === $k ? 'UTF-8' : 'Qimia'; }
function is_rtl() { return false; }
function determine_locale() { return 'en_US'; }
function get_locale() { return 'en_US'; }
function wp_die( $m = '' ) { throw new RuntimeException( 'wp_die: ' . (is_string( $m ) ? $m : 'die') ); }
function nocache_headers() {}
function wp_doing_ajax() { return ! empty( $GLOBALS['qt']['ajax'] ); }
function wp_doing_cron() { return false; }
function is_admin() { return ! empty( $GLOBALS['qt']['admin'] ); }
function is_front_page() { return 'full' === $GLOBALS['qt']['render']; }
function is_singular( $t = '' ) { return false; }
function is_tax( $t = '' ) { return false; }
function is_404() { return false; }
function is_feed() { return false; }
function is_search() { return false; }
function is_cart() { return ! empty( $GLOBALS['qt']['is_cart'] ); }
function is_checkout() { return false; }
function is_product() { return false; }
function is_shop() { return false; }
function is_product_taxonomy() { return false; }
function is_product_category( $t = '' ) { return false; }
function is_account_page() { return ! empty( $GLOBALS['qt']['is_account'] ); }
function is_order_received_page() { return ! empty( $GLOBALS['qt']['is_received'] ); }
function is_ssl() { return true; }
function wp_get_referer() { return $GLOBALS['qt']['referer'] ?: false; }
function wp_get_theme() { return new class { public function get( $k ) { return 'Test'; } }; }
function get_theme_mod( $k, $d = false ) { return $d; }

/* -------------------------------------------------------------- URLs */
function home_url( $path = '/' ) { return untrailingslashit( (string) ( $GLOBALS['qt']['options']['home'] ?? 'https://qimia.om' ) ) . ( '/' === substr( (string) $path, 0, 1 ) ? $path : '/' . $path ); }
function site_url( $path = '' ) { return home_url( $path ); }
function admin_url( $path = '' ) { return 'https://qimia.om/wp-admin/' . ltrim( $path, '/' ); }
function rest_url( $path = '' ) { return 'https://qimia.om/wp-json/' . ltrim( $path, '/' ); }
function plugin_dir_path( $f ) { return trailingslashit( dirname( $f ) ); }
function plugin_dir_url( $f ) { return 'https://qimia.om/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function get_permalink( $id = 0 ) { return 'https://qimia.om/product/p-' . (int) ( is_object( $id ) ? $id->ID : $id ) . '/'; }
function get_edit_post_link( $id ) { return admin_url( 'post.php?post=' . (int) $id . '&action=edit' ); }
function get_term_link( $term, $tax = '' ) { $t = is_object( $term ) ? $term : get_term( $term, $tax ); return $t ? 'https://qimia.om/product-category/' . $t->slug . '/' : new WP_Error( 'x' ); }
/** Redirect double: records the target; scenarios that need to stop at the redirect set qt[throw_redirect]. */
class QT_Redirect extends Exception { public $url; public function __construct( $url ) { parent::__construct( 'redirect' ); $this->url = $url; } }
function wp_safe_redirect( $u ) { $GLOBALS['qt']['redirect'] = $u; if ( ! empty( $GLOBALS['qt']['throw_redirect'] ) ) { throw new QT_Redirect( $u ); } if ( ! empty( $GLOBALS['qt']['real_redirect'] ) && ! headers_sent() ) { header( 'Location: ' . str_replace( 'https://qimia.om', $GLOBALS['qt']['origin'] ?? 'https://qimia.om', $u ), true, 302 ); } return true; }

/* --------------------------------------------------------- users */
class WP_User { public $ID; public $roles = array( 'customer' ); public $user_email; public function __construct( $id = 0, $email = '' ) { $this->ID = $id; $this->user_email = $email; } }
function get_current_user_id() { return (int) $GLOBALS['qt']['user']; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function wp_get_current_user() { $id = get_current_user_id(); return $id ? ( $GLOBALS['qt']['users'][ $id ] ?? new WP_User( $id ) ) : new WP_User( 0 ); }
function get_userdata( $id ) { return $GLOBALS['qt']['users'][ $id ] ?? false; }
function get_user_meta( $id, $key = '', $single = false ) { $v = $GLOBALS['qt']['usermeta'][ $id ][ $key ] ?? ''; return $single ? $v : array( $v ); }
function update_user_meta( $id, $key, $v ) { $GLOBALS['qt']['usermeta'][ $id ][ $key ] = $v; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['qt']['usermeta'][ $id ][ $key ] ); return true; }
/** get_users double: meta_key (+ numeric meta_value / meta_compare), orderby meta_value_num, number, fields => ID. */
function get_users( $args = array() ) {
	$key = $args['meta_key'] ?? ''; $rows = array();
	foreach ( $GLOBALS['qt']['usermeta'] ?? array() as $id => $meta ) {
		if ( '' === $key || ! array_key_exists( $key, $meta ) || '' === $meta[ $key ] ) { if ( '' !== $key ) { continue; } }
		if ( isset( $args['meta_value'] ) ) {
			$v = (float) ( $meta[ $key ] ?? 0 ); $want = (float) $args['meta_value'];
			$ok = array( '<=' => $v <= $want, '<' => $v < $want, '>=' => $v >= $want, '>' => $v > $want, '=' => $v == $want )[ $args['meta_compare'] ?? '=' ] ?? false;
			if ( ! $ok ) { continue; }
		}
		$rows[ $id ] = (float) ( $meta[ $key ] ?? 0 );
	}
	if ( 'meta_value_num' === ( $args['orderby'] ?? '' ) ) { 'DESC' === ( $args['order'] ?? 'ASC' ) ? arsort( $rows ) : asort( $rows ); }
	$ids = array_keys( $rows );
	if ( isset( $args['number'] ) && $args['number'] > 0 ) { $ids = array_slice( $ids, 0, (int) $args['number'] ); }
	return array_map( 'intval', $ids );
}
function wp_get_session_token() { return 'session-token-' . get_current_user_id(); }
function current_user_can( $cap ) { return ! empty( $GLOBALS['qt']['admin_user'] ); }
function wp_create_nonce( $action = -1 ) { return substr( md5( $action . '|' . get_current_user_id() ), 0, 10 ); }
function wp_verify_nonce( $nonce, $action = -1 ) { return hash_equals( wp_create_nonce( $action ), (string) $nonce ) ? 1 : false; }
function check_ajax_referer( $action = -1, $arg = false, $die = true ) { $n = $_REQUEST[ $arg ] ?? ( $_POST[ $arg ] ?? '' ); return wp_verify_nonce( $n, $action ); }

/* --------------------------------------------------------- errors & REST */
class WP_Error {
	public $code; public $message; public $data;
	public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function add( $c, $m ) { $this->code = $c; $this->message = $m; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
class WP_REST_Request { private $params = array(); public function __construct( $m = 'GET', $r = '' ) {} public function get_param( $k ) { return $this->params[ $k ] ?? null; } public function set_param( $k, $v ) { $this->params[ $k ] = $v; } public function get_route() { return ''; } public function get_method() { return 'GET'; } }
class WP_REST_Response { public $data; public $headers = array(); public function __construct( $d = null ) { $this->data = $d; } public function header( $k, $v ) { $this->headers[ $k ] = $v; } public function get_data() { return $this->data; } }
function rest_ensure_response( $d ) { return $d instanceof WP_REST_Response ? $d : new WP_REST_Response( $d ); }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['qt']['rest'][ $ns . $route ] = $args; }

class QT_Json_Response extends Exception { public $data; public $status; public function __construct( $data, $status ) { parent::__construct( 'json' ); $this->data = $data; $this->status = $status; } }
function wp_send_json( $data, $status = null ) {
	// Browser router: end the request like WordPress does (a catch (Throwable) in plugin code must not see it).
	if ( ! empty( $GLOBALS['qt']['json_exit'] ) ) { call_user_func( $GLOBALS['qt']['json_exit'], $data, $status ?: 200 ); exit; }
	throw new QT_Json_Response( $data, $status ?: 200 );
}
function wp_send_json_success( $d = null ) { wp_send_json( array( 'success' => true, 'data' => $d ) ); }
function wp_send_json_error( $d = null, $s = null ) { wp_send_json( array( 'success' => false, 'data' => $d ), $s ); }

/* --------------------------------------------------------- scripts */
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) { $GLOBALS['qt']['enqueued']['style'][ $h ] = $src; }
function wp_enqueue_script( $h, $src = '', $deps = array(), $ver = false, $f = false ) { $GLOBALS['qt']['enqueued']['script'][ $h ] = $src; }
function wp_register_script( ...$a ) { return true; }
function wp_register_style( ...$a ) { return true; }
function wp_dequeue_script( $h ) { unset( $GLOBALS['qt']['enqueued']['script'][ $h ] ); }
function wp_script_is( $h, $list = 'enqueued' ) { return isset( $GLOBALS['qt']['enqueued']['script'][ $h ] ); }
function wp_style_is( $h, $list = 'enqueued' ) { return isset( $GLOBALS['qt']['enqueued']['style'][ $h ] ); }
function wp_add_inline_script( $h, $code, $pos = 'after' ) { $GLOBALS['qt']['inline'][ $h ][] = $code; return true; }
function wp_localize_script( ...$a ) { return true; }

/* --------------------------------------------------------- cron */
function wp_next_scheduled( $hook ) { return $GLOBALS['qt']['cron'][ $hook ] ?? false; }
function wp_schedule_single_event( $ts, $hook, $args = array() ) { $GLOBALS['qt']['cron'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['qt']['cron'][ $hook ] ); return 1; }
function wp_schedule_event( $ts, $recurrence, $hook, $args = array() ) { $GLOBALS['qt']['cron'][ $hook ] = $ts; $GLOBALS['qt']['cron_recurrence'][ $hook ] = $recurrence; return true; }
function flush_rewrite_rules( $hard = true ) { $GLOBALS['qt']['flushed'] = ( $GLOBALS['qt']['flushed'] ?? 0 ) + 1; }
function wp_mail( $to, $subject, $message, $headers = '' ) { $GLOBALS['qt']['mail'][] = array( 'to' => $to, 'subject' => $subject, 'message' => $message, 'via' => 'wp_mail' ); return empty( $GLOBALS['qt']['mail_fails'] ); }

/* --------------------------------------------------------- admin forms */
function settings_fields( $g ) { echo '<input type="hidden" name="option_page" value="' . esc_attr( $g ) . '">'; }
function submit_button( $t = 'Save' ) { echo '<button type="submit">' . esc_html( $t ) . '</button>'; }
function register_setting( ...$a ) { return true; }
function add_options_page( ...$a ) { return 'hook'; }
function remove_submenu_page( ...$a ) { return true; }
function get_the_title( $id = 0 ) { return 'Title ' . (int) $id; }

/* --------------------------------------------------------- posts, meta, terms */
function get_post_meta( $id, $key = '', $single = false ) {
	if ( '' === $key ) { $all = $GLOBALS['qt']['meta'][ $id ] ?? array(); return array_map( static function ( $v ) { return array( $v ); }, $all ); }
	$v = $GLOBALS['qt']['meta'][ $id ][ $key ] ?? ( $single ? '' : null );
	return $single ? $v : ( null === $v ? array() : array( $v ) );
}
function update_post_meta( $id, $key, $v ) { $GLOBALS['qt']['meta'][ $id ][ $key ] = $v; return true; }
function add_post_meta( $id, $key, $v, $unique = false ) { if ( $unique && isset( $GLOBALS['qt']['meta'][ $id ][ $key ] ) ) { return false; } $GLOBALS['qt']['meta'][ $id ][ $key ] = $v; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['qt']['meta'][ $id ][ $key ] ); return true; }
function update_meta_cache( $type, $ids ) { return true; }
function _prime_post_caches( $ids, $t = true, $m = true ) {}
function get_post_status( $id ) { $p = $GLOBALS['qt']['products'][ $id ] ?? null; return $p ? $p->status : false; }
function get_post_type( $id ) { $p = $GLOBALS['qt']['products'][ $id ] ?? null; return $p ? ( 'variation' === $p->type ? 'product_variation' : 'product' ) : ( isset( $GLOBALS['qt']['coupons'][ $id ] ) ? 'shop_coupon' : false ); }
function get_post_time( $f, $gmt, $post ) { return time() - 90 * DAY_IN_SECONDS; }
function wp_get_post_parent_id( $id ) { $p = $GLOBALS['qt']['products'][ $id ] ?? null; return $p ? $p->parent_id : 0; }
function post_password_required( $id = null ) { return false; }
function get_post_class( $c = '', $id = 0 ) {
	$classes = array( 'product', 'type-product' );
	foreach ( (array) ( $GLOBALS['qt']['rel'][ $id ]['product_cat'] ?? array() ) as $tid ) { $classes[] = 'product_cat-' . $GLOBALS['qt']['terms']['product_cat'][ $tid ]->slug; }
	return array_merge( $classes, (array) ( $GLOBALS['qt']['meta'][ $id ]['_qt_classes'] ?? array() ) );
}
function taxonomy_exists( $tax ) { return in_array( $tax, $GLOBALS['qt']['taxonomies'], true ); }
function qt_term( $tax, $id, $slug, $name, $parent = 0 ) { $GLOBALS['qt']['terms'][ $tax ][ $id ] = (object) array( 'term_id' => $id, 'term_taxonomy_id' => $id, 'slug' => $slug, 'name' => $name, 'parent' => $parent, 'taxonomy' => $tax, 'count' => 1 ); }
function get_term( $id, $tax = '' ) { foreach ( $GLOBALS['qt']['terms'] as $t => $rows ) { if ( ( '' === $tax || $t === $tax ) && isset( $rows[ $id ] ) ) { return $rows[ $id ]; } } return null; }
function get_term_by( $field, $value, $tax ) { foreach ( $GLOBALS['qt']['terms'][ $tax ] ?? array() as $term ) { if ( ( 'slug' === $field && $term->slug === $value ) || ( 'name' === $field && $term->name === $value ) || ( 'id' === $field && (int) $term->term_id === (int) $value ) ) { return $term; } } return false; }
function get_terms( $args = array() ) { $tax = is_array( $args ) ? ( $args['taxonomy'] ?? '' ) : $args; return array_values( $GLOBALS['qt']['terms'][ $tax ] ?? array() ); }
function wp_count_terms( $args = array() ) { return count( get_terms( $args ) ); }
function get_term_children( $id, $tax ) { $out = array(); foreach ( $GLOBALS['qt']['terms'][ $tax ] ?? array() as $term ) { if ( (int) $term->parent === (int) $id ) { $out[] = (int) $term->term_id; $out = array_merge( $out, get_term_children( $term->term_id, $tax ) ); } } return $out; }
function get_ancestors( $id, $tax = '', $type = '' ) { $out = array(); $t = get_term( $id, $tax ); while ( $t && $t->parent ) { $out[] = $t->parent; $t = get_term( $t->parent, $tax ); } return $out; }
function get_the_terms( $id, $tax ) { $ids = $GLOBALS['qt']['rel'][ $id ][ $tax ] ?? array(); if ( ! $ids ) { return false; } return array_values( array_filter( array_map( static function ( $t ) use ( $tax ) { return $GLOBALS['qt']['terms'][ $tax ][ $t ] ?? null; }, $ids ) ) ); }
function wp_get_post_terms( $id, $tax, $args = array() ) { return get_the_terms( $id, $tax ) ?: array(); }
function wc_get_product_terms( $id, $tax, $args = array() ) { return wp_get_post_terms( $id, $tax ); }
function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
	if ( (int) $id < 1 ) { return false; }
	$dims = array( 'woocommerce_gallery_thumbnail' => 100, 'thumbnail' => 150, 'woocommerce_thumbnail' => 300, 'medium' => 300, 'woocommerce_single' => 600, 'medium_large' => 768, 'large' => 1024, '1536x1536' => 1536, 'full' => 1200 );
	$w = $dims[ is_string( $size ) ? $size : 'full' ] ?? 300;
	return array( 'https://qimia.om/wp-content/uploads/p' . (int) $id . '-' . $w . '.webp', $w, $w, true );
}
function wp_get_attachment_image( $id, $size = 'full', $icon = false, $attr = array() ) { $src = $id ? wp_get_attachment_image_src( $id, $size ) : false; return $src ? '<img src="' . esc_url( $src[0] ) . '" width="' . (int) $src[1] . '" height="' . (int) $src[2] . '" alt="' . esc_attr( $attr['alt'] ?? '' ) . '" loading="lazy">' : ''; }
function wp_get_attachment_metadata( $id ) { return array(); }
function get_the_ID() { return 0; }

/* ------------------------------------------------------ WP_Query double */
class WP_Query {
	public $posts = array();
	public $query_vars = array();
	public function __construct( $args = array() ) {
		$this->query_vars = $args;
		$GLOBALS['qt']['queries'][] = $args; // Scenarios assert which scans a request ran.
		$ids = array();
		foreach ( $GLOBALS['qt']['products'] as $id => $p ) {
			if ( 'variation' === $p->type || 'publish' !== $p->status ) { continue; }
			$ids[] = $id;
		}
		$ids = array_values( array_filter( $ids, function ( $id ) use ( $args ) { return $this->match( $id, $args ); } ) );
		if ( ! empty( $args['meta_key'] ) && 'total_sales' === $args['meta_key'] ) {
			usort( $ids, static function ( $a, $b ) { $pa = $GLOBALS['qt']['products'][ $a ]; $pb = $GLOBALS['qt']['products'][ $b ]; return ( $pb->total_sales <=> $pa->total_sales ) ?: ( $b <=> $a ); } );
		}
		if ( ! empty( $args['post__in'] ) ) { $order = array_flip( array_map( 'intval', $args['post__in'] ) ); $ids = array_values( array_filter( $ids, static function ( $id ) use ( $order ) { return isset( $order[ $id ] ); } ) ); }
		$limit = (int) ( $args['posts_per_page'] ?? 10 );
		$this->posts = $limit > 0 ? array_slice( $ids, 0, $limit ) : $ids;
	}
	private function match( $id, $args ) {
		$p = $GLOBALS['qt']['products'][ $id ];
		if ( in_array( $id, array_map( 'intval', (array) ( $args['post__not_in'] ?? array() ) ), true ) ) { return false; }
		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! is_array( $clause ) ) { continue; }
			if ( '_stock_status' === ( $clause['key'] ?? '' ) && $p->stock_status !== $clause['value'] ) { return false; }
			if ( 'total_sales' === ( $clause['key'] ?? '' ) && '>' === ( $clause['compare'] ?? '' ) && $p->total_sales <= 0 ) { return false; }
		}
		foreach ( (array) ( $args['tax_query'] ?? array() ) as $key => $clause ) {
			if ( 'relation' === $key || ! is_array( $clause ) ) { continue; }
			$tax = $clause['taxonomy'];
			if ( 'product_visibility' === $tax ) { if ( ! $p->visible || 'instock' !== $p->stock_status ) { return false; } continue; }
			if ( 'product_type' === $tax ) { if ( ! in_array( $p->type, (array) $clause['terms'], true ) ) { return false; } continue; }
			$wanted = array_map( 'intval', (array) $clause['terms'] );
			$all    = array();
			foreach ( (array) ( $GLOBALS['qt']['rel'][ $id ][ $tax ] ?? array() ) as $tid ) { $all[] = (int) $tid; foreach ( get_ancestors( $tid, $tax ) as $a ) { $all[] = (int) $a; } }
			$hit = (bool) array_intersect( $wanted, $all );
			if ( 'NOT IN' === ( $clause['operator'] ?? 'IN' ) ? $hit : ! $hit ) { return false; }
		}
		return true;
	}
}

/* --------------------------------------------------------- script registries */
class QT_Dependencies { public $queue = array(); public $registered = array(); public $type; public function __construct( $type ) { $this->type = $type; } }
function qt_registry( $type ) {
	static $registries = array();
	$registries[ $type ] = $registries[ $type ] ?? new QT_Dependencies( $type );
	$registries[ $type ]->queue = array_keys( $GLOBALS['qt']['enqueued'][ $type ] ?? array() );
	foreach ( $GLOBALS['qt']['enqueued'][ $type ] ?? array() as $handle => $src ) { $registries[ $type ]->registered[ $handle ] = (object) array( 'src' => $src, 'handle' => $handle, 'deps' => array() ); }
	return $registries[ $type ];
}
function wp_styles() { return qt_registry( 'style' ); }
function wp_scripts() { return qt_registry( 'script' ); }
function wp_dequeue_style( $h ) { unset( $GLOBALS['qt']['enqueued']['style'][ $h ] ); }
function wp_deregister_style( $h ) { wp_dequeue_style( $h ); }
function wp_deregister_script( $h ) { wp_dequeue_script( $h ); }
function wp_add_inline_style( $h, $css ) { $GLOBALS['qt']['inline_style'][ $h ][] = $css; return true; }

/* --------------------------------------------------------- template helpers */
function remove_query_arg( $keys, $url = false ) {
	$url = false === $url ? ( $_SERVER['REQUEST_URI'] ?? '/' ) : $url;
	$parts = explode( '?', (string) $url, 2 );
	if ( ! isset( $parts[1] ) ) { return $url; }
	parse_str( $parts[1], $q );
	foreach ( (array) $keys as $k ) { unset( $q[ $k ] ); }
	return $parts[0] . ( $q ? '?' . http_build_query( $q ) : '' );
}
function get_query_var( $k, $d = '' ) { return $d; }
function get_queried_object() { return null; }
function get_queried_object_id() { return 0; }
function is_page( $p = '' ) { return false; }
function is_home() { return false; }
function is_archive() { return false; }
function is_page_template( $t = '' ) { return false; }
function get_post( $id = null ) { return null; }
function esc_js( $t ) { return addslashes( (string) $t ); }
function wp_kses( $t, $allowed = array() ) { return wp_kses_post( $t ); }
function wp_kses_data( $t ) { return wp_kses_post( $t ); }
function wp_nonce_field( ...$a ) { return ''; }
function wp_timezone() { return new DateTimeZone( '+04:00' ); }
function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); }
function date_i18n( $f, $ts = false ) { return gmdate( $f, $ts ?: time() ); }
function wp_get_environment_type() { return 'production'; }
function wp_login_url( $r = '' ) { return home_url( '/my-account/' ); }
function wp_logout_url( $r = '' ) { return home_url( '/my-account/customer-logout/' ); }
function get_privacy_policy_url() { return home_url( '/privacy/' ); }
function has_nav_menu( $l ) { return false; }
function wp_nav_menu( $a = array() ) { return ''; }
function get_search_query() { return ''; }
function is_customize_preview() { return false; }
function wp_is_mobile() { return false; }
function get_template_directory_uri() { return home_url( '/wp-content/themes/test' ); }
function get_stylesheet_directory_uri() { return get_template_directory_uri(); }
function get_site_icon_url( $s = 512 ) { return ''; }
function wp_get_document_title() { return 'Qimia'; }
function get_the_post_thumbnail_url( $p = null, $s = 'post-thumbnail' ) { return ''; }
function wp_attachment_is_image( $id ) { return true; }
function get_attached_file( $id ) { return false; }
function apply_filters_deprecated( $tag, $args, $v, $r = '', $m = '' ) { return apply_filters( $tag, ...$args ); }
