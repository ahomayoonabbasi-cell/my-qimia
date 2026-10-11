<?php
/**
 * Live cross-plugin test: the REAL Qimia Intelligence Lab 1.18.23 and the REAL Qimia Beauty 2.4.1,
 * loaded together in one process on the shared WordPress/WooCommerce doubles.
 *   php tests/php/beauty-qil-live.php
 *
 * The process is one staging front-page request on qimialab.qimia.om, the only host where
 * Beauty's qby_stage_allowed() is true. WordPress lifecycle hooks are fired in core order.
 * Clean child processes repeat the request elsewhere; the main run starts them and reports
 * their checks: `chrome` (/beauty/, where QIL renders only its chrome), `ar` (staging /ar/
 * that is not the front page, where QIL still renders its home) and `live` (the same /ar/
 * request on production qimia.om with qby_live_enabled, where Beauty prints its fallback home).
 * Exit code 1 when any check fails.
 *
 * The shared doubles are not modified. WordPress functions they lack are defined below, each
 * guarded with function_exists()/class_exists(); core methods and properties they lack are added
 * on QBL_* subclasses (wpdb, WC_Product, term objects). Where a doubles gap cannot be closed from
 * this file the check prints SKIP with the reason; skips are not counted as passes. One gap
 * is completed in the error handler with WordPress core's own value: the WP_Query double has
 * no found_posts / max_num_pages properties (core declares both, default 0). Every use of that
 * completion is counted and printed in a NOTE line.
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
$GLOBALS['qbl_gaps'] = array();
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( E_WARNING === $severity && false !== strpos( str_replace( '\\', '/', $file ), 'qimia-beauty/includes/department-data.php' )
		&& preg_match( '/^Undefined property: WP_Query::\$(found_posts|max_num_pages)$/', $message ) ) {
		$GLOBALS['qbl_gaps'][] = basename( $file ) . ':' . $line . ' ' . $message;
		return true; // Reads null, cast to (int) 0: WP_Query's core default.
	}
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

require __DIR__ . '/wp-doubles.php';
require __DIR__ . '/wc-doubles.php';
require __DIR__ . '/fixtures.php';

/* ------------------------------------------------------------------ results */
$GLOBALS['qbl'] = array( 'passed' => 0, 'failed' => 0, 'skipped' => 0, 'done' => false );
function qbl_detail( $detail ) {
	return is_string( $detail ) ? $detail : json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
}
function qbl_check( $name, $ok, $detail = '' ) {
	$GLOBALS['qbl'][ $ok ? 'passed' : 'failed' ]++;
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $name . ( $ok ? '' : '  → ' . qbl_detail( $detail ) ) . "\n";
	return (bool) $ok;
}
function qbl_skip( $name, $why ) {
	$GLOBALS['qbl']['skipped']++;
	echo '  - SKIP ' . $name . '  → ' . $why . "\n";
}
function qbl_section( $title ) { echo "\n" . $title . "\n"; }
function qbl_where( Throwable $e ) {
	$root = realpath( __DIR__ . '/../..' ) . '/';
	return str_replace( $root, '', $e->getFile() ) . ':' . $e->getLine();
}
function qbl_error( Throwable $e ) { return get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . qbl_where( $e ); }
function qbl_summary() {
	if ( $GLOBALS['qbl']['done'] ) { return; }
	$GLOBALS['qbl']['done'] = true;
	if ( $GLOBALS['qbl_gaps'] ) {
		$sites = array_count_values( $GLOBALS['qbl_gaps'] );
		echo "\n  NOTE WP_Query double gap completed with core default 0: " . implode( '; ', array_map( static function ( $site, $n ) { return $site . ' ×' . $n; }, array_keys( $sites ), $sites ) ) . "\n";
	}
	echo "\n" . $GLOBALS['qbl']['passed'] . ' passed, ' . $GLOBALS['qbl']['failed'] . ' failed' . ( $GLOBALS['qbl']['skipped'] ? ', ' . $GLOBALS['qbl']['skipped'] . ' skipped' : '' ) . "\n";
}
register_shutdown_function( static function () {
	$fatal = error_get_last();
	if ( $fatal && in_array( $fatal['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) && ! $GLOBALS['qbl']['done'] ) {
		qbl_check( 'no PHP fatal error', false, $fatal['message'] . ' @ ' . $fatal['file'] . ':' . $fatal['line'] );
		qbl_summary();
	}
} );

/* ------------------------------------------- WordPress doubles missing from the shared files */
if ( ! class_exists( 'WooCommerce' ) ) { class WooCommerce {} } // Beauty gates its storefront on class_exists('WooCommerce').
if ( ! function_exists( 'register_post_meta' ) ) {
	function register_post_meta( $type, $key, $args = array() ) { $GLOBALS['qt']['registered_meta'][ $type ][ $key ] = $args; return true; }
}
if ( ! function_exists( 'register_rest_field' ) ) {
	function register_rest_field( $type, $field, $args = array() ) { $GLOBALS['qt']['rest_fields'][ $type ][ $field ] = $args; }
}
if ( ! function_exists( 'wp_script_add_data' ) ) {
	function wp_script_add_data( $handle, $key, $value ) { $GLOBALS['qt']['script_data'][ $handle ][ $key ] = $value; return true; }
}
if ( ! function_exists( 'wp_get_object_terms' ) ) {
	function wp_get_object_terms( $ids, $tax, $args = array() ) { return wp_get_post_terms( is_array( $ids ) ? reset( $ids ) : $ids, $tax ); }
}
if ( ! function_exists( 'language_attributes' ) ) { function language_attributes( $doctype = 'html' ) { echo 'lang="' . esc_attr( str_replace( '_', '-', get_locale() ) ) . '"'; } }
if ( ! function_exists( 'bloginfo' ) ) { function bloginfo( $show = '' ) { echo esc_html( get_bloginfo( $show ) ); } }
if ( ! function_exists( 'wp_head' ) ) { function wp_head() { do_action( 'wp_head' ); } }
if ( ! function_exists( 'wp_body_open' ) ) { function wp_body_open() { do_action( 'wp_body_open' ); } }
if ( ! function_exists( 'wp_footer' ) ) { function wp_footer() { do_action( 'wp_footer' ); } }
if ( ! function_exists( 'body_class' ) ) {
	function body_class( $class = '' ) { $classes = (array) apply_filters( 'body_class', array_filter( (array) $class ), (array) $class ); echo 'class="' . esc_attr( implode( ' ', $classes ) ) . '"'; }
}
/**
 * Core wpdb names its term tables and has get_results(); the double has neither. QIL's SKU search
 * reads rows with get_results() (no fixture SKU matches a search here) and Beauty's department
 * presence SQL names the term tables (its get_col() answers no rows, as for any non-coupon SQL).
 */
class QBL_WPDB extends QT_WPDB {
	public $terms = 'wp_terms'; public $term_taxonomy = 'wp_term_taxonomy'; public $term_relationships = 'wp_term_relationships';
	public function get_results( $sql, $output = 'OBJECT' ) { return array(); }
}
$GLOBALS['wpdb'] = new QBL_WPDB();
/**
 * wp_get_post_terms() in the doubles ignores 'fields' => 'names' and returns term objects, which
 * QIL's search scorer implodes. Fixture terms are therefore given their name as their string form.
 */
class QBL_Term extends stdClass {
	public function __toString() { return (string) $this->name; }
}
/** Core WC_Product::get_image(); the product double lacks it. Beauty's home edit prints it. */
class QBL_Product extends WC_Product {
	public function get_image( $size = 'woocommerce_thumbnail', $attr = array(), $placeholder = true ) { return wp_get_attachment_image( $this->image_id, $size, false, $attr ); }
}
function qbl_term( $term ) {
	$t = new QBL_Term();
	foreach ( get_object_vars( $term ) as $k => $v ) { $t->$k = $v; }
	return $t;
}
function qbl_product( $id, array $props, array $cats = array() ) {
	$p = new QBL_Product( $id );
	foreach ( get_object_vars( qt_product( $id, $props, $cats ) ) as $k => $v ) { $p->$k = $v; }
	return $GLOBALS['qt']['products'][ $id ] = $p;
}

/**
 * The shared REST doubles: WP_REST_Request::get_route() always returns '' and WP_REST_Response
 * has no get_status()/set_data(). Core has all three; these subclasses add them and nothing else.
 */
class QBL_REST_Request extends WP_REST_Request {
	private $qbl_route; private $qbl_method;
	public $reads = array(); // Parameter names read, so a test can tell a filter really inspected the request.
	public function __construct( $method = 'GET', $route = '' ) { parent::__construct( $method, $route ); $this->qbl_method = $method; $this->qbl_route = $route; }
	public function get_param( $k ) { $this->reads[] = $k; return parent::get_param( $k ); }
	public function get_route() { return $this->qbl_route; }
	public function get_method() { return $this->qbl_method; }
}
class QBL_REST_Response extends WP_REST_Response {
	public $status = 200;
	public function get_status() { return $this->status; }
	public function set_status( $code ) { $this->status = (int) $code; }
	public function set_data( $data ) { $this->data = $data; }
	public function get_headers() { return $this->headers; }
}
function qbl_rest_response( $result ) {
	if ( $result instanceof QBL_REST_Response || $result instanceof WP_Error ) { return $result; }
	$response = new QBL_REST_Response( $result instanceof WP_REST_Response ? $result->data : $result );
	if ( $result instanceof WP_REST_Response ) { $response->headers = $result->headers; }
	return $response;
}
/**
 * Core WP_REST_Server::dispatch() for one registered GET route: permission callback, route
 * callback, then rest_request_after_callbacks and rest_post_dispatch in that order.
 * Returns array( callback result before filters, final response ).
 */
function qbl_rest_dispatch( $route_key, QBL_REST_Request $request ) {
	$handler = $GLOBALS['qt']['rest'][ $route_key ] ?? null;
	if ( ! $handler ) { throw new RuntimeException( 'route not registered: ' . $route_key ); }
	if ( ! call_user_func( $handler['permission_callback'], $request ) ) { throw new RuntimeException( 'permission denied: ' . $route_key ); }
	$raw      = qbl_rest_response( call_user_func( $handler['callback'], $request ) );
	$before   = $raw instanceof WP_REST_Response ? $raw->get_data() : $raw;
	$request->reads = array(); // From here on, only the response filters read the request.
	$response = apply_filters( 'rest_request_after_callbacks', $raw, $handler, $request );
	$response = apply_filters( 'rest_post_dispatch', qbl_rest_response( rest_ensure_response( $response ) ), null, $request );
	return array( $before, qbl_rest_response( $response ) );
}
/** The <section> of $html that carries id="$id", or '' when there is none. */
function qbl_section_html( $html, $id ) {
	$at = strpos( $html, 'id="' . $id . '"' );
	if ( false === $at ) { return ''; }
	$start = (int) strrpos( substr( $html, 0, $at ), '<section' );
	$end   = strpos( $html, '</section>', $at );
	return false === $end ? '' : substr( $html, $start, $end + 10 - $start );
}
/** Renders the template QIL's template_include picks for this request. */
function qbl_render_home() {
	$template = apply_filters( 'template_include', ABSPATH . 'theme-index.php' );
	ob_start();
	try { include $template; } finally { $home = ob_get_clean(); }
	return array( $template, $home );
}
/** The translator-memory call that asked for exactly these titles (QIL batches its own catalogue separately), or null. */
function qbl_translation_call( array $sources ) {
	sort( $sources );
	foreach ( class_exists( 'QAATM_Memory' ) ? QAATM_Memory::$calls : array() as $call ) {
		$asked = array_column( $call['segments'], 'source' ); sort( $asked );
		if ( $asked === $sources ) { return $call; }
	}
	return null;
}
function qbl_hook_rows( $tag ) {
	$rows = array();
	foreach ( $GLOBALS['qt']['hooks'][ $tag ] ?? array() as $priority => $list ) { foreach ( $list as $row ) { $rows[] = array( 'cb' => $row[0], 'priority' => $priority, 'args' => $row[1] ); } }
	return $rows;
}
/** Temporarily detach the $tag callbacks defined in files whose path contains $file_fragment, run $fn, restore them. */
function qbl_without_beauty_hook( $tag, $file_fragment, callable $fn ) {
	$saved = $GLOBALS['qt']['hooks'][ $tag ] ?? array();
	foreach ( $saved as $priority => $list ) {
		foreach ( $list as $i => $row ) {
			$cb = $row[0];
			$where = is_string( $cb ) && function_exists( $cb ) ? ( new ReflectionFunction( $cb ) )->getFileName() : ( $cb instanceof Closure ? ( new ReflectionFunction( $cb ) )->getFileName() : '' );
			if ( false !== strpos( (string) $where, $file_fragment ) ) { unset( $GLOBALS['qt']['hooks'][ $tag ][ $priority ][ $i ] ); }
		}
	}
	try { return $fn(); } finally { $GLOBALS['qt']['hooks'][ $tag ] = $saved; }
}

/* ------------------------------------------------------------------ fixtures */
// Default: the staging front page. `php beauty-qil-live.php chrome` is the same staging request
// on /beauty/ (any non-front page); `ar` is staging /ar/ and `live` is production /ar/, both with
// is_front_page() false. The main run starts each in a clean process, since qil_render_mode()
// and qil_view_context() are memoised once per request.
$qbl_mode = in_array( $argv[1] ?? '', array( 'chrome', 'ar', 'live' ), true ) ? $argv[1] : 'full';
$qbl_expected_render = 'chrome' === $qbl_mode ? 'chrome' : 'full';
qt_fixtures();
if ( 'chrome' === $qbl_mode ) { $GLOBALS['qt']['render'] = 'chrome'; $_SERVER['REQUEST_URI'] = '/beauty/'; }
if ( 'ar' === $qbl_mode || 'live' === $qbl_mode ) { $GLOBALS['qt']['render'] = 'chrome'; $_SERVER['REQUEST_URI'] = '/ar/'; }
// The staging request: Beauty requires host, home and siteurl all on qimialab.qimia.om.
// Production (live) is qimia.om, where Beauty's storefront also needs the explicit qby_live_enabled switch.
$qbl_host = 'live' === $qbl_mode ? 'qimia.om' : 'qimialab.qimia.om';
$_SERVER['HTTP_HOST'] = $qbl_host;
$GLOBALS['qt']['options']['home']        = 'https://' . $qbl_host;
$GLOBALS['qt']['options']['siteurl']     = 'https://' . $qbl_host;
$GLOBALS['qt']['options']['qby_enabled'] = '1';
if ( 'live' === $qbl_mode ) { $GLOBALS['qt']['options']['qby_live_enabled'] = '1'; }
if ( ( 'ar' === $qbl_mode || 'live' === $qbl_mode ) && ! class_exists( 'QAATM_Memory' ) ) {
	/** Translator memory double: QIL's qil_translate_batch() reads existing rows through bulk_resolve(). */
	class QAATM_Memory {
		public static $rows = array( 'product_title' => array( 'Biotin Thickening Shampoo 250ml' => 'شامبو البيوتين المكثف 250 مل', 'Argan Repair Shampoo 400ml' => 'شامبو الأرغان المرمم 400 مل' ) );
		public static $calls = array();
		public static function bulk_resolve( array $segments, $queue_missing = false ) {
			self::$calls[] = array( 'segments' => $segments, 'queue' => $queue_missing );
			return array_map( static function ( $s ) { $t = self::$rows[ $s['context'] ][ $s['source'] ] ?? ''; return '' === $t ? array( 'status' => 'missing' ) : array( 'status' => 'ready', 'target' => $t ); }, $segments );
		}
	}
}

// The Beauty root category. Department children are deliberately not created: their links
// would call qby_beauty_presence(), whose SQL needs wpdb tables the QT_WPDB double lacks.
qt_term( 'product_cat', 30, 'beauty', 'Beauty & Personal Care' );
// 401: explicit Beauty class + recorded details. The AI Product Box also left supplement-shaped
// records on it (serving, FAQ, facts table) that Beauty must keep out of QIL's knowledge band.
qt_product( 401, array( 'name' => 'Repair Shampoo 250ml', 'regular_price' => '6.500', 'total_sales' => 45, 'short' => 'Repairing shampoo for dry hair.' ), array( 30 ) );
update_post_meta( 401, '_qby_product_class', 'beauty' );
update_post_meta( 401, '_qby_cosmetic_details', array( 'size' => '250 ml', 'inci' => 'Aqua, Sodium Laureth Sulfate, Glycerin', 'hair_type' => 'Dry hair', 'hair_type_ar' => 'شعر جاف', 'directions_en' => 'Apply to wet hair.' ) );
update_post_meta( 401, '_qimia_ai_box_structured', array( 'title_en' => 'Shampoo guide', 'en' => array( 'serving_summary' => 'One scoop daily', 'faq' => array( array( 'q' => 'When to take?', 'a' => 'After training.' ) ), 'who_for' => array( 'Athletes' ) ) ) );
update_post_meta( 401, '_qimia_supplement_facts_structured', array( 'serving_size_en' => '1 scoop', 'servings_en' => '30', 'total_content_en' => '250 ml', 'ingredients_en' => 'Aqua', 'rows' => array( array( 'label_en' => 'Protein', 'value' => '24 g', 'basis' => 'per_serving' ) ) ) );
// 402: Beauty only through the category tree (no explicit class).
qt_product( 402, array( 'name' => 'Hydrating Face Serum 30ml', 'regular_price' => '9.900', 'total_sales' => 30 ), array( 30 ) );
// 403: explicit Beauty class, out of stock.
qt_product( 403, array( 'name' => 'Curl Defining Cream 200ml', 'regular_price' => '7.200', 'total_sales' => 12, 'stock_status' => 'outofstock' ), array( 30 ) );
update_post_meta( 403, '_qby_product_class', 'beauty' );
// 102 (Creatine Monohydrate) stays a supplement; give it the same merchant records as a control.
update_post_meta( 102, '_qimia_ai_box_structured', array( 'title_en' => 'Creatine guide', 'en' => array( 'serving_summary' => 'Mix one scoop (5 g) with water daily.', 'faq' => array( array( 'q' => 'Loading phase?', 'a' => 'Not required.' ) ) ) ) );
update_post_meta( 102, '_qimia_supplement_facts_structured', array( 'serving_size_en' => '5 g', 'servings_en' => '60', 'ingredients_en' => 'Creatine monohydrate' ) );

// Search and home-edit fixtures. The WP_Query double compares tax_query terms with intval(), so a
// field=slug clause such as Beauty's product_cat 'shampoo' resolves to term 0 there. Giving the
// shampoo term ID 0 lets Beauty's real qby_beauty_query() find 404-406 (also filed under the
// Beauty root), in fixture order: the double keeps insertion order, standing in for
// menu_order/title. Term 0 stays a root term: the double's get_term_children() would recurse
// forever through parent 0 if a category walk such as Beauty's presence query reached it.
qt_term( 'product_cat', 0, 'shampoo', 'Shampoo' );
qt_term( 'product_cat', 31, 'hair-nail-skin-health', 'Hair, Skin & Nails' );
qt_term( 'product_cat', 32, 'beauty-tools', 'Beauty tools', 30 );
qt_term( 'product_tag', 40, 'biotin', 'Biotin' );
// 404: first in Beauty's order, but a slow seller that QIL's own _rank places after 405. Its 'biotin'
// tag IS beauty_support evidence for QIL; only Beauty's record adapter keeps it off the goal.
qbl_product( 404, array( 'name' => 'Biotin Thickening Shampoo 250ml', 'regular_price' => '5.500', 'total_sales' => 2 ), array( 30, 0 ) );
update_post_meta( 404, '_qby_product_class', 'beauty' );
$GLOBALS['qt']['rel'][404]['product_tag'] = array( 40 );
// 405: the best seller; its catalogue title ends in a space, as merchant titles sometimes do.
qbl_product( 405, array( 'name' => 'Argan Repair Shampoo 400ml ', 'regular_price' => '8.900', 'total_sales' => 300 ), array( 30, 0 ) );
update_post_meta( 405, '_qby_product_class', 'beauty' );
// 406: variable, every size sold out, so Woo reports it out of stock. The WP_Query double applies
// any product_visibility clause to the parent's own stock_status field (left at its default), so
// the parent is kept, as core keeps it: Beauty only excludes exclude-from-catalog/-search terms.
qbl_product( 406, array( 'name' => 'Silk Gloss Shampoo', 'type' => 'variable', 'total_sales' => 5 ), array( 30, 0 ) );
update_post_meta( 406, '_qby_product_class', 'beauty' );
qt_variation( 4061, 406, '6.000', array( 'pa_size' => '250ml' ), array( 'stock_status' => 'outofstock' ) );
qt_variation( 4062, 406, '9.000', array( 'pa_size' => '500ml' ), array( 'stock_status' => 'outofstock' ) );
// 407: a Beauty tool (an accessory under beauty-tools). 501: an oral hair supplement.
qbl_product( 407, array( 'name' => 'Detangling Paddle Brush', 'regular_price' => '3.500', 'total_sales' => 9 ), array( 32 ) );
update_post_meta( 407, '_qby_product_class', 'accessory' );
qt_product( 501, array( 'name' => 'Biotin Hair Gummies', 'regular_price' => '6.900', 'total_sales' => 80 ), array( 31 ) );
foreach ( $GLOBALS['qt']['terms'] as $qbl_tax => $qbl_rows ) {
	foreach ( $qbl_rows as $qbl_id => $qbl_term ) { $GLOBALS['qt']['terms'][ $qbl_tax ][ $qbl_id ] = qbl_term( $qbl_term ); }
}

/* ------------------------------------------------------------------ 1. load */
qbl_section( '1. Both real plugins load together' );
$load_error = null;
try {
	require realpath( __DIR__ . '/../../qimia-intelligence-lab-final/qimia-intelligence-lab.php' );
	require realpath( __DIR__ . '/../../qimia-beauty/qimia-beauty.php' );
	do_action( 'plugins_loaded' );
	do_action( 'init' );
	do_action( 'wp' );
	do_action( 'template_redirect' );
} catch ( Throwable $e ) {
	$load_error = $e;
}
$gaps_during_load = count( $GLOBALS['qbl_gaps'] );
qbl_check( 'QIL + Beauty load and plugins_loaded, init, wp, template_redirect fire with no notice, warning or fatal', null === $load_error && 0 === $gaps_during_load, $load_error ? qbl_error( $load_error ) : $GLOBALS['qbl_gaps'] );
if ( $load_error ) { qbl_summary(); exit( 1 ); }
qbl_check( 'QIL_VERSION is 1.18.23 and QBY_VERSION is 2.4.1', defined( 'QIL_VERSION' ) && '1.18.23' === QIL_VERSION && defined( 'QBY_VERSION' ) && '2.4.1' === QBY_VERSION, array( defined( 'QIL_VERSION' ) ? QIL_VERSION : null, defined( 'QBY_VERSION' ) ? QBY_VERSION : null ) );
if ( 'live' === $qbl_mode ) {
	qbl_check( 'Beauty storefront gate opened on production by qby_live_enabled (not staging, QIL experience on)', ! qby_stage_allowed() && qby_frontend_enabled() && qil_experience_enabled() && ! qil_is_staging_sandbox() );
	qbl_check( 'Beauty integration files loaded at plugins_loaded:100 without the staging experience (no qbx_home)', function_exists( 'qby_qil_beauty_record' ) && function_exists( 'qby_product_details' ) && function_exists( 'qby_search_scope' ) && ! function_exists( 'qbx_home' ) );
} else {
	qbl_check( 'Beauty storefront gate opened on the staging host (qby_stage_allowed, qby_frontend_enabled, QIL experience on)', qby_stage_allowed() && qby_frontend_enabled() && qil_experience_enabled() && qil_is_staging_sandbox() );
	qbl_check( 'Beauty integration files loaded at plugins_loaded:100 (qby_qil_beauty_record, qby_product_details, qby_search_scope, qbx_home)', function_exists( 'qby_qil_beauty_record' ) && function_exists( 'qby_product_details' ) && function_exists( 'qby_search_scope' ) && function_exists( 'qbx_home' ) );
}
$record_hooks = array_values( array_filter( qbl_hook_rows( 'qil_catalogue_product_record' ), static function ( $r ) { return 'qby_qil_beauty_record' === $r['cb']; } ) );
qbl_check( 'Beauty card adapter is on qil_catalogue_product_record at priority 100 with the 3 arguments QIL passes', 1 === count( $record_hooks ) && 100 === $record_hooks[0]['priority'] && 3 === $record_hooks[0]['args'], $record_hooks );
qbl_check( 'QIL render mode settled to ' . $qbl_expected_render . ' after template_redirect', $qbl_expected_render === qil_render_mode(), qil_render_mode() );

if ( 'chrome' === $qbl_mode ) {
	qbl_section( 'Chrome request (/beauty/): Beauty shelf records and QIL docks on an inner page' );
	try {
		$shelf = qby_qil_shelf( array( wc_get_product( 401 ) ), '<ul class="products"><li class="product" data-product-id="401"></li></ul>' );
		qbl_check( 'qby_qil_shelf() keeps the Woo fallback, embeds QIL records and asks for docks', 0 === strpos( $shelf, '<div class="qby-native-products qby-qil-shelf qil-shell"' ) && false !== strpos( $shelf, 'data-product-id="401"' ) && false !== strpos( $shelf, 'class="qby-qil-records"' ) && ! empty( $GLOBALS['qby_qil_docks_needed'] ), $shelf );
		ob_start(); do_action( 'wp_body_open' ); $open = ob_get_clean();
		ob_start(); do_action( 'wp_footer' ); $footer = ob_get_clean();
		$beauty_link = 'href="https://qimialab.qimia.om/beauty/" aria-current="page"';
		qbl_check( 'QIL chrome header (wp_body_open) carries the Beauty link once per nav, marked current on /beauty/', false !== strpos( $open, 'data-qil-chrome' ) && 2 === substr_count( $open, $beauty_link ), substr_count( $open, 'href="https://qimialab.qimia.om/beauty/"' ) );
		qbl_check( 'Beauty prints QIL\'s compare dock exactly once on wp_footer (qil_section_docks, render mode chrome)', 1 === substr_count( $open . $footer, 'data-qil-compare-dock' ) && false !== strpos( $footer, '<div class="qby-qil-docks qil-shell' ), substr_count( $open . $footer, 'data-qil-compare-dock' ) );
	} catch ( Throwable $e ) {
		qbl_check( 'chrome request checks ran', false, qbl_error( $e ) );
	}
	qbl_summary();
	exit( $GLOBALS['qbl']['failed'] ? 1 : 0 );
}

$qbl_ar_names = array( 'شامبو البيوتين المكثف 250 مل', 'شامبو الأرغان المرمم 400 مل' );
if ( 'ar' === $qbl_mode ) {
	qbl_section( 'Staging /ar/ that is not the front page: QIL renders its home, Beauty its experience' );
	try {
		qbl_check( 'precondition: is_front_page() is false, QIL qil_should_render() is true on /ar/', ! is_front_page() && qil_should_render() );
		do_action( 'wp_enqueue_scripts' );
		$scripts = $GLOBALS['qt']['enqueued']['script'] ?? array();
		$styles  = $GLOBALS['qt']['enqueued']['style'] ?? array();
		qbl_check( 'Beauty enqueues experience.min.css/js on QIL\'s /ar/ home, where qil_home_before_flash prints qbx_home', isset( $scripts['qimia-beauty-experience'], $styles['qimia-beauty-experience'] ), array( array_keys( $scripts ), array_keys( $styles ) ) );
		list( $template, $home ) = qbl_render_home();
		$section = qbl_section_html( $home, 'qbx-home' );
		preg_match_all( '#<strong[^>]*>(.*?)</strong>#s', $section, $printed );
		qbl_check( 'QIL serves templates/home.php on /ar/ and it prints Beauty\'s qbx_home once, right-to-left', realpath( QIL_DIR . 'templates/home.php' ) === realpath( $template ) && 1 === substr_count( $home, 'id="qbx-home"' ) && false !== strpos( $section, 'dir="rtl"' ), $template );
		qbl_check( 'qbx_home prints existing Arabic titles matched on the trimmed title (405 ends in a space); an untranslated title stays as written', 3 === substr_count( $section, 'class="qby-home-product"' ) && false !== strpos( $section, $qbl_ar_names[0] ) && false !== strpos( $section, $qbl_ar_names[1] ) && false === strpos( $section, 'Argan Repair' ) && false !== strpos( $section, 'Silk Gloss Shampoo' ), $printed[0] );
		$call = qbl_translation_call( array( 'Biotin Thickening Shampoo 250ml', 'Argan Repair Shampoo 400ml', 'Silk Gloss Shampoo' ) );
		qbl_check( 'Beauty reads its three titles in one product_title batch with queueing off', $call && false === $call['queue'] && 'product_title' === $call['segments'][0]['context'], count( QAATM_Memory::$calls ) );
	} catch ( Throwable $e ) {
		qbl_check( 'staging /ar/ checks ran', false, qbl_error( $e ) );
	}
	qbl_summary();
	exit( $GLOBALS['qbl']['failed'] ? 1 : 0 );
}
if ( 'live' === $qbl_mode ) {
	qbl_section( 'Production /ar/ (qby_live_enabled): Beauty\'s fallback home section inside QIL\'s home' );
	try {
		qbl_check( 'precondition: is_front_page() is false, QIL qil_should_render() is true on /ar/', ! is_front_page() && qil_should_render() );
		do_action( 'wp_enqueue_scripts' );
		list( $template, $home ) = qbl_render_home();
		$section = qbl_section_html( $home, 'qby-home-beauty' );
		preg_match_all( '#<strong[^>]*>(.*?)</strong>#s', $section, $printed );
		qbl_check( 'QIL home on /ar/ prints Beauty\'s fallback section once, right-to-left (no staging qbx_home)', realpath( QIL_DIR . 'templates/home.php' ) === realpath( $template ) && 1 === substr_count( $home, 'id="qby-home-beauty"' ) && false === strpos( $home, 'id="qbx-home"' ) && false !== strpos( $section, 'dir="rtl"' ), $template );
		qbl_check( 'fallback edit panel lists Beauty\'s three products under its title', 3 === substr_count( $section, 'class="qby-home-product"' ) && 1 === substr_count( $section, 'class="qby-home-edit-title"' ), array( substr_count( $section, 'class="qby-home-product"' ), substr_count( $section, 'class="qby-home-edit-title"' ) ) );
		qbl_check( 'fallback prints existing Arabic titles inside QIL\'s no-translate shell, matched on the trimmed title; an untranslated title stays as written', false !== strpos( $section, $qbl_ar_names[0] ) && false !== strpos( $section, $qbl_ar_names[1] ) && false === strpos( $section, 'Argan Repair' ) && false === strpos( $section, 'Biotin Thickening' ) && false !== strpos( $section, 'Silk Gloss Shampoo' ), $printed[0] );
		qbl_check( 'every fallback product name is printed with dir="auto"', 3 === substr_count( $section, '<strong dir="auto">' ), $printed[0] );
		$call = qbl_translation_call( array( 'Biotin Thickening Shampoo 250ml', 'Argan Repair Shampoo 400ml', 'Silk Gloss Shampoo' ) );
		qbl_check( 'fallback reads its three titles in one product_title batch with queueing off', $call && false === $call['queue'] && 'product_title' === $call['segments'][0]['context'], count( QAATM_Memory::$calls ) );
		// No Beauty product to show: the edit panel is left out, the rest of the section stays.
		foreach ( array( 404, 405, 406 ) as $id ) { $GLOBALS['qt']['products'][ $id ]->status = 'draft'; }
		try { ob_start(); do_action( 'qil_home_before_flash' ); } finally { $empty = ob_get_clean(); foreach ( array( 404, 405, 406 ) as $id ) { $GLOBALS['qt']['products'][ $id ]->status = 'publish'; } }
		qbl_check( 'with no Beauty products the fallback prints no empty "THE BEAUTY EDIT" panel, and keeps its copy and department cards', false !== strpos( $empty, 'id="qby-home-beauty"' ) && false === strpos( $empty, 'qby-home-art' ) && false === strpos( $empty, 'مختارات الجمال' ) && false === strpos( $empty, 'qby-home-product' ) && false !== strpos( $empty, 'qby-home-copy' ) && false !== strpos( $empty, 'qby-department-grid' ), array( 'art' => substr_count( $empty, 'qby-home-art' ), 'edit' => substr_count( $empty, 'مختارات الجمال' ), 'products' => substr_count( $empty, 'qby-home-product' ) ) );
	} catch ( Throwable $e ) {
		qbl_check( 'production /ar/ checks ran', false, qbl_error( $e ) );
	}
	qbl_summary();
	exit( $GLOBALS['qbl']['failed'] ? 1 : 0 );
}

/* ------------------------------------------------------------------ 2. catalogue */
qbl_section( '2. qil_get_catalogue() carries Beauty records (qil_catalogue_product_record)' );
try {
	$catalogue = qil_get_catalogue( array( 'include' => array( 401, 402, 102 ), 'limit' => 3, 'orderby' => 'include' ) );
	$by = array(); foreach ( $catalogue as $row ) { $by[ (int) $row['id'] ] = $row; }
	$b = $by[401] ?? null;
	qbl_check( 'Beauty product 401 (explicit class) and 402 (category only) and supplement 102 are all returned', isset( $by[401], $by[402], $by[102] ), array_keys( $by ) );
	qbl_check( '401 is productClass beauty with beauty.schema 1', $b && 'beauty' === ( $b['productClass'] ?? null ) && 1 === ( $b['beauty']['schema'] ?? null ), $b ? array( $b['productClass'] ?? null, $b['beauty'] ?? null ) : null );
	qbl_check( '401 has empty facts and dietary, no per-serving price, purposeKey beauty with no reasons', $b && array() === $b['facts'] && array() === $b['dietary'] && ! preg_grep( '/^perServing/', array_keys( (array) $b['price'] ) ) && 'beauty' === $b['match']['purposeKey'] && array() === $b['match']['reasons'], $b ? array( $b['facts'], $b['dietary'], array_keys( (array) $b['price'] ), $b['match'] ) : null );
	qbl_check( '401 Beauty facts come from its recorded details in both languages (Size 250 ml / الحجم)', $b && '250 ml' === ( $b['beauty']['facts']['en'][0]['value'] ?? null ) && 'الحجم' === ( $b['beauty']['facts']['ar'][0]['label'] ?? null ) && 2 === count( $b['beauty']['facts']['en'] ) && 2 === count( $b['beauty']['facts']['ar'] ), $b['beauty']['facts'] ?? null );
	qbl_check( '402 (Beauty through the beauty product_cat tree) is productClass beauty', 'beauty' === ( $by[402]['productClass'] ?? null ) && 1 === ( $by[402]['beauty']['schema'] ?? null ), $by[402] ?? null );
	$s = $by[102] ?? array();
	qbl_check( 'supplement 102 has no beauty or productClass key and keeps its servings fact', ! array_key_exists( 'beauty', $s ) && ! array_key_exists( 'productClass', $s ) && '' !== (string) ( $s['facts']['servings'] ?? '' ), array( array_keys( $s ), $s['facts'] ?? null ) );
	// Same builder, Beauty's filter detached: the supplement record must be byte-identical.
	$plain = qbl_without_beauty_hook( 'qil_catalogue_product_record', 'qimia-beauty/', static function () { return qil_build_catalogue( array( 'include' => array( 401, 102 ), 'limit' => 2, 'orderby' => 'include' ), '', false, false ); } );
	$with  = qil_build_catalogue( array( 'include' => array( 401, 102 ), 'limit' => 2, 'orderby' => 'include' ), '', false, false );
	$index = static function ( $rows ) { $o = array(); foreach ( $rows as $r ) { $o[ (int) $r['id'] ] = $r; } return $o; };
	$plain = $index( $plain ); $with = $index( $with );
	// inventory.checkedAt is time(); the two builds may straddle a second boundary.
	$stable = static function ( $r ) { unset( $r['inventory']['checkedAt'] ); return $r; };
	qbl_check( 'supplement 102 record is identical with and without Beauty loaded', isset( $plain[102], $with[102] ) && $stable( $plain[102] ) === $stable( $with[102] ), array( 'diff' => array_keys( array_diff_key( $plain[102] ?? array(), $with[102] ?? array() ) + array_diff_key( $with[102] ?? array(), $plain[102] ?? array() ) ) ) );
	qbl_check( 'without Beauty, QIL would have given 401 supplement fields (control: the adapter is what removes them)', isset( $plain[401] ) && ! isset( $plain[401]['beauty'] ) && array() !== $plain[401]['facts'], $plain[401]['facts'] ?? null );
	// Arabic: QIL localizes after the record filter (qil_localize_catalogue) and must accept the emptied facts.
	$ar = $index( qil_get_catalogue( array( 'include' => array( 401, 102 ), 'limit' => 2, 'orderby' => 'include', '_qil_locale' => 'ar' ) ) );
	qbl_check( 'Arabic catalogue (_qil_locale ar) keeps the Beauty record and the supplement servings, with no warning', 'beauty' === ( $ar[401]['productClass'] ?? null ) && array() === $ar[401]['facts'] && 'الحجم' === ( $ar[401]['beauty']['facts']['ar'][0]['label'] ?? null ) && ! isset( $ar[102]['beauty'] ) && '' !== (string) ( $ar[102]['facts']['servings'] ?? '' ), array_keys( $ar ) );
} catch ( Throwable $e ) {
	qbl_check( 'catalogue checks ran', false, qbl_error( $e ) );
}

qbl_section( '2b. QIL\'s supplement goal "Hair, skin & beauty" (beauty) leaves Beauty products out' );
try {
	$policy = qil_goal_term_policy()['beauty_support'] ?? array();
	qbl_check( 'qil_goal_term_policy: the bare Beauty root slug is no longer beauty_support; collagen, biotin and hair/skin/nails terms stay', ! in_array( 'beauty', $policy, true ) && array() === array_diff( array( 'beauty-support', 'collagen', 'collagens', 'collagen-oman', 'biotin', 'hair-nail-skin-health', 'hair-skin-nails' ), $policy ), $policy );
	$goals = static function ( $id ) { return qil_goal_taxonomy_profile( $id )['goals'] ?? array(); };
	qbl_check( 'a cosmetic only under the Beauty root (401) gets no beauty goal; the hair/skin/nails supplement (501) keeps it', ! in_array( 'beauty', $goals( 401 ), true ) && in_array( 'beauty', $goals( 501 ), true ), array( 401 => $goals( 401 ), 501 => $goals( 501 ) ) );
	$goal_rows = qil_get_catalogue( array( 'include' => array( 404, 401, 501 ), 'limit' => 3, 'orderby' => 'include' ) );
	$m = array(); foreach ( $goal_rows as $row ) { $m[ (int) $row['id'] ] = $row['match']; }
	qbl_check( 'Beauty records carry no QIL goals, evidence or sources (the JS goal filter reads these); the supplement keeps its own', isset( $m[401], $m[404], $m[501] ) && array() === $m[401]['goals'] && array() === $m[401]['evidence'] && array() === $m[401]['sources'] && array() === $m[404]['goals'] && array() === $m[404]['evidence'] && in_array( 'beauty', $m[501]['goals'], true ) && $m[501]['evidence'], $m );
	$ranked = array_map( static function ( $r ) { return (int) $r['id']; }, qil_goal_rank_records( $goal_rows, 'beauty' ) );
	qbl_check( 'qil_goal_rank_records(beauty) keeps only the supplement, although 404\'s biotin tag is QIL taxonomy evidence', in_array( 'beauty', $goals( 404 ), true ) && array( 501 ) === $ranked, array( 'ranked' => $ranked, 404 => $goals( 404 ) ) );
} catch ( Throwable $e ) {
	qbl_check( 'goal checks ran', false, qbl_error( $e ) );
}

/* ------------------------------------------------------------------ 3. cache identity */
qbl_section( '3. Catalogue cache identity splits by Beauty record schema' );
try {
	$identity = qil_perf_market_identity();
	qbl_check( 'qil_perf_market_identity() contains beautyRecordSchema qby-' . QBY_VERSION . '-2 (bumped when the Beauty record stopped carrying QIL goals)', 'qby-' . QBY_VERSION . '-2' === ( $identity['beautyRecordSchema'] ?? null ), $identity );
	qbl_check( 'QIL market keys survive Beauty\'s identity filter (currency, user, session, pricing)', 'OMR' === $identity['currency'] && 0 === $identity['user'] && '' === $identity['session'] && array_key_exists( 'pricing', $identity ), $identity );
	$plain_identity = qbl_without_beauty_hook( 'qil_cache_market_identity', 'qimia-beauty/', 'qil_perf_market_identity' );
	qbl_check( 'Beauty-enabled and plain identities differ, so QIL never serves a cached plain card as a Beauty card', $plain_identity !== $identity && ! isset( $plain_identity['beautyRecordSchema'] ), $plain_identity );
} catch ( Throwable $e ) {
	qbl_check( 'cache identity checks ran', false, qbl_error( $e ) );
}

/* ------------------------------------------------------------------ 4. home */
qbl_section( '4. QIL home template fires qil_home_before_flash and Beauty renders before the flash drop' );
try {
	$template = apply_filters( 'template_include', ABSPATH . 'theme-index.php' );
	qbl_check( 'template_include routes the front page to QIL templates/home.php', realpath( QIL_DIR . 'templates/home.php' ) === realpath( $template ), $template );
	// Core enqueues on wp_head priority 1; fire it first so both plugins' asset hooks run in order (QIL 20, Beauty 110, QIL dequeue PHP_INT_MAX).
	do_action( 'wp_enqueue_scripts' );
	$scripts = $GLOBALS['qt']['enqueued']['script'] ?? array();
	$styles  = $GLOBALS['qt']['enqueued']['style'] ?? array();
	qbl_check( 'wp_enqueue_scripts: QIL handle qimia-intelligence-lab is enqueued, so Beauty attaches its native-card assets to it', isset( $scripts['qimia-intelligence-lab'], $scripts['qimia-beauty-native-cards'], $styles['qimia-beauty-native-cards'] ), array_keys( $scripts ) );
	qbl_check( 'wp_enqueue_scripts: Beauty base and home experience assets survive QIL\'s dedicated-template dequeue', isset( $scripts['qimia-beauty'], $styles['qimia-beauty'], $scripts['qimia-beauty-experience'], $styles['qimia-beauty-experience'] ), array( array_keys( $scripts ), array_keys( $styles ) ) );
	$before_count = did_action( 'qil_home_before_flash' );
	ob_start();
	try { include $template; } finally { $home = ob_get_clean(); }
	qbl_check( 'rendering the template fires qil_home_before_flash exactly once', 0 === $before_count && 1 === did_action( 'qil_home_before_flash' ), did_action( 'qil_home_before_flash' ) );
	$beauty_at = strpos( $home, 'id="qbx-home"' );
	if ( false === $beauty_at ) { $beauty_at = strpos( $home, 'id="qby-home-beauty"' ); }
	$flash_at  = strpos( $home, 'id="qil-flash-drop"' );
	$goal_at   = strpos( $home, 'id="qil-match"' );
	qbl_check( 'output contains the Beauty home section exactly once', false !== $beauty_at && 1 === substr_count( $home, 'id="qbx-home"' ) + substr_count( $home, 'id="qby-home-beauty"' ) );
	qbl_check( 'Beauty home section sits after the goal engine and before the flash drop section', false !== $beauty_at && false !== $flash_at && false !== $goal_at && $goal_at < $beauty_at && $beauty_at < $flash_at, array( 'goal' => $goal_at, 'beauty' => $beauty_at, 'flash' => $flash_at ) );
	qbl_check( 'Beauty home section is inside QIL main flow and links to the staging Beauty hub', false !== $beauty_at && $beauty_at > strpos( $home, 'id="qil-main"' ) && false !== strpos( $home, 'href="https://qimialab.qimia.om/beauty/"' ) );
	qbl_check( 'Beauty home edit lists the three products Beauty\'s own query returns', 3 === substr_count( $home, 'class="qby-home-product"' ) && false !== strpos( $home, 'Biotin Thickening Shampoo 250ml' ), substr_count( $home, 'class="qby-home-product"' ) );
} catch ( Throwable $e ) {
	qbl_check( 'home template rendered', false, qbl_error( $e ) );
}

/* ------------------------------------------------------------------ 5. product knowledge */
qbl_section( '5. qil_product_knowledge on a Beauty product drops supplement fields' );
try {
	$k   = qil_product_knowledge( 401, false );
	$kar = qil_product_knowledge( 401, true );
	qbl_check( 'Beauty 401 has no serving, faq, servings, servingSize, directions, rows or who-for', '' === $k['serving'] && array() === $k['faq'] && '' === $k['servings'] && '' === $k['servingSize'] && '' === $k['directions'] && array() === $k['rows'] && array() === $k['whoFor'], $k );
	qbl_check( 'Beauty 401 has hasBox/hasFacts false, title Topical care, INCI and net content from Beauty details', false === $k['hasBox'] && false === $k['hasFacts'] && 'Topical care' === $k['title'] && 'Aqua, Sodium Laureth Sulfate, Glycerin' === $k['ingredients'] && '250 ml' === $k['netContent'], $k );
	qbl_check( 'Arabic route: title العناية الموضعية, still no supplement fields', 'العناية الموضعية' === $kar['title'] && '' === $kar['serving'] && array() === $kar['faq'], $kar );
	$cards = qil_product_knowledge_cards( $k, false );
	qbl_check( 'QIL knowledge cards for 401 print no "Serving & label" or "How to use" supplement panel', false === strpos( $cards, 'Serving &amp; label' ) && false === strpos( $cards, 'How to use' ) && false === strpos( $cards, 'One scoop daily' ), $cards );
	$sk = qil_product_knowledge( 102, false );
	qbl_check( 'supplement 102 is unchanged: merchant title, serving, FAQ and facts kept', 'Creatine guide' === $sk['title'] && 'Mix one scoop (5 g) with water daily.' === $sk['serving'] && 1 === count( $sk['faq'] ) && '60' === $sk['servings'] && true === $sk['hasBox'] && true === $sk['hasFacts'], $sk );
} catch ( Throwable $e ) {
	qbl_check( 'product knowledge checks ran', false, qbl_error( $e ) );
}

/* ------------------------------------------------------------------ 6. search */
qbl_section( '6. /qimia-lab/v1/search with Beauty\'s rest_request_after_callbacks adapter' );
try {
	do_action( 'rest_api_init' );
	$route = $GLOBALS['qt']['rest']['qimia-lab/v1/search'] ?? null;
	$beauty_rest = array_values( array_filter( qbl_hook_rows( 'rest_request_after_callbacks' ), static function ( $r ) { return $r['cb'] instanceof Closure && false !== strpos( ( new ReflectionFunction( $r['cb'] ) )->getFileName(), 'qimia-beauty/includes/integration.php' ); } ) );
	qbl_check( 'QIL registers qimia-lab/v1 /search (core route /qimia-lab/v1/search, the string Beauty compares) with q, qil_locale, limit', $route && 'qil_rest_search_products' === $route['callback'] && isset( $route['args']['q'], $route['args']['qil_locale'], $route['args']['limit'] ), $route ? array_keys( $route['args'] ) : 'not registered' );
	qbl_check( 'Beauty search adapter is hooked on rest_request_after_callbacks with 3 arguments', 1 === count( $beauty_rest ) && 3 === $beauty_rest[0]['args'] && 100 === $beauty_rest[0]['priority'], $beauty_rest );

	$req = new QBL_REST_Request( 'GET', '/qimia-lab/v1/search' );
	$req->set_param( 'q', 'protein' ); $req->set_param( 'qil_locale', 'en' ); $req->set_param( 'limit', 8 );
	list( $before, $after ) = qbl_rest_dispatch( 'qimia-lab/v1/search', $req );
	$ids = array_map( static function ( $p ) { return (int) $p['id']; }, $after->get_data()['products'] ?? array() );
	qbl_check( 'q=protein: Beauty\'s adapter matched the route and read the request (qil_locale, q)', array( 'qil_locale', 'q' ) === array_values( array_intersect( $req->reads, array( 'qil_locale', 'q' ) ) ), $req->reads );
	qbl_check( 'q=protein: QIL returns products, Beauty leaves the payload byte-identical', $ids && $before === $after->get_data(), array( 'ids' => $ids, 'same' => $before === $after->get_data() ) );
	qbl_check( 'q=protein: no Beauty product enters the results and QIL\'s no-store header survives', ! array_intersect( $ids, array( 401, 402, 403, 404, 405, 406, 407 ) ) && false !== strpos( (string) ( $after->headers['Cache-Control'] ?? '' ), 'no-store' ), $after->headers );

	// Beauty's real adapter on a real dispatch: QIL's search runs first, then Beauty swaps in its own hits.
	$search = static function ( $q, $locale = 'en', $limit = 8 ) {
		$req = new QBL_REST_Request( 'GET', '/qimia-lab/v1/search' );
		$req->set_param( 'q', $q ); $req->set_param( 'qil_locale', $locale ); $req->set_param( 'limit', $limit );
		return qbl_rest_dispatch( 'qimia-lab/v1/search', $req )[1]->get_data()['products'] ?? array();
	};
	$GLOBALS['qt']['queries'] = array();
	$rows = $search( 'shampoo' );
	$by   = array(); foreach ( $rows as $row ) { $by[ (int) $row['id'] ] = $row; }
	$ids  = array_keys( $by );
	$beauty_query = array_values( array_filter( $GLOBALS['qt']['queries'], static function ( $q ) { return ! empty( $q['qby_collection_scope'] ); } ) );
	$hidden = array();
	foreach ( (array) ( $beauty_query[0]['tax_query'] ?? array() ) as $clause ) { if ( is_array( $clause ) && 'product_visibility' === ( $clause['taxonomy'] ?? '' ) && 'NOT IN' === ( $clause['operator'] ?? '' ) ) { $hidden = array_merge( $hidden, (array) $clause['terms'] ); } }
	$visibility = wc_get_product_visibility_term_ids();
	qbl_check( 'q=shampoo: Beauty queries its shampoo department without products hidden from the catalogue or from search', 1 === count( $beauty_query ) && array( 'shampoo' ) === ( $beauty_query[0]['tax_query'][0]['terms'] ?? null ) && in_array( $visibility['exclude-from-catalog'], $hidden, true ) && in_array( $visibility['exclude-from-search'], $hidden, true ), $beauty_query );
	qbl_check( 'q=shampoo returns only Beauty products, in Beauty\'s query order (404, 405, 406)', array( 404, 405, 406 ) === $ids && array( 'beauty' ) === array_values( array_unique( array_column( $rows, 'productClass' ) ) ), array( 'ids' => $ids, 'classes' => array_column( $rows, 'productClass' ) ) );
	$qil_order = array_map( static function ( $r ) { return (int) $r['id']; }, qil_get_catalogue( array( 'include' => array( 404, 405, 406 ), 'limit' => 3, 'orderby' => 'include', '_qil_catalogue_selection' => true, '_qil_locale' => 'en' ) ) );
	qbl_check( 'control: QIL\'s catalogue ranks the same ids its own way, so the adapter is what keeps Beauty\'s order', array( 404, 405, 406 ) !== $qil_order && array( 404, 405, 406 ) === array_values( array_intersect( array( 404, 405, 406 ), $qil_order ) ) && 3 === count( $qil_order ), $qil_order );
	qbl_check( 'q=shampoo keeps the sold-out 406 among several hits, with stock.inStock false', isset( $by[406] ) && false === $by[406]['stock']['inStock'] && true === $by[404]['stock']['inStock'], isset( $by[406] ) ? $by[406]['stock'] : $ids );
	$names = array();
	foreach ( array( 'en', 'ar' ) as $locale ) { foreach ( 'en' === $locale ? $rows : $search( 'shampoo', 'ar' ) as $row ) { $names[] = $row['name']; } }
	qbl_check( 'no hit carries an " · Out of stock" / " · غير متوفر" name suffix (EN and AR); stock stays in stock.inStock', 6 === count( $names ) && ! preg_grep( '/ · (?:Out of stock|غير متوفر)/u', $names ) && in_array( 'Silk Gloss Shampoo', $names, true ), $names );

	// Ambiguous 'hair': QIL's own hits come from title SQL the wpdb double does not model, so the
	// adapter gets the real catalogue records QIL would return for a Beauty tool and an oral supplement.
	$qil_hits = qil_get_catalogue( array( 'include' => array( 407, 501 ), 'limit' => 2, 'orderby' => 'include', '_qil_locale' => 'en' ) );
	$req = new QBL_REST_Request( 'GET', '/qimia-lab/v1/search' );
	$req->set_param( 'q', 'hair' ); $req->set_param( 'qil_locale', 'en' ); $req->set_param( 'limit', 4 );
	$mixed = apply_filters( 'rest_request_after_callbacks', new QBL_REST_Response( array( 'products' => $qil_hits, 'query' => 'hair' ) ), $route, $req );
	$mixed = array_map( static function ( $p ) { return (int) $p['id']; }, $mixed->get_data()['products'] ?? array() );
	qbl_check( 'q=hair: two Beauty hits in Beauty\'s order, then the oral supplement; the Beauty tool 407 is not counted as oral', 2 === count( $qil_hits ) && array( 404, 405, 501 ) === $mixed, array( 'qil' => array_column( $qil_hits, 'id' ), 'out' => $mixed ) );
} catch ( Throwable $e ) {
	qbl_check( 'search checks ran', false, qbl_error( $e ) );
}

/* ------------------------------------------------------------------ 7. header */
qbl_section( '7. qil_header_links gets the Beauty destination exactly once' );
try {
	$header = qil_section_header();
	$beauty_url = 'https://qimialab.qimia.om/beauty/';
	$navs = array();
	foreach ( array( 'qil-pages' => '#<details class="qil-pages".*?</details>#s', 'qil-links' => '#<nav class="qil-links".*?</nav>#s' ) as $key => $pattern ) {
		$navs[ $key ] = preg_match( $pattern, $header, $m ) ? substr_count( $m[0], 'href="' . $beauty_url . '"' ) : -1;
	}
	qbl_check( 'rendered QIL header: Beauty link appears exactly once in each of its two navs', array( 'qil-pages' => 1, 'qil-links' => 1 ) === $navs, $navs );
	$context = qil_view_context();
	$links = array( array( 'key' => 'home', 'url' => $context['homeUrl'] ), array( 'key' => 'shop', 'url' => $context['shopUrl'], 'label' => 'Shop' ), array( 'key' => 'account', 'url' => $context['accountUrl'], 'label' => 'My Account' ) );
	$once  = apply_filters( 'qil_header_links', $links, $context );
	$twice = apply_filters( 'qil_header_links', $once, $context );
	$keys  = array_map( static function ( $l ) { return $l['key'] ?? ''; }, $twice );
	qbl_check( 'filtering an already-filtered list adds no second Beauty link; it sits right after Shop', array( 'home', 'shop', 'beauty', 'account' ) === $keys && 1 === count( array_keys( $keys, 'beauty', true ) ), $keys );
	qbl_check( 'Beauty link carries QIL\'s link shape (url, label, labelAr, i18n)', isset( $once[2]['url'], $once[2]['label'], $once[2]['labelAr'], $once[2]['i18n'] ) && $beauty_url === $once[2]['url'], $once[2] ?? null );
} catch ( Throwable $e ) {
	qbl_check( 'header checks ran', false, qbl_error( $e ) );
}

/* ------------------------------------------------------------------ 8. records */
qbl_section( '8. qby_qil_records() through the real QIL catalogue' );
try {
	qbl_check( 'QIL_VERSION passes Beauty\'s >= 1.18.12 gate', version_compare( QIL_VERSION, '1.18.12', '>=' ) );
	$one = qby_qil_records( array( 401 ) );
	qbl_check( 'qby_qil_records([401]) returns one Beauty record', 1 === count( $one ) && 401 === (int) $one[0]['id'] && 'beauty' === $one[0]['productClass'] && 1 === $one[0]['beauty']['schema'], $one );
	$many = qby_qil_records( array( wc_get_product( 402 ), 102, 403, 401, 401, (object) array( 'ID' => 402 ) ) );
	qbl_check( 'mixed input: caller order kept, supplement and duplicates skipped, out-of-stock selection kept', array( 402, 403, 401 ) === array_map( static function ( $r ) { return (int) $r['id']; }, $many ), array_map( static function ( $r ) { return (int) $r['id']; }, $many ) );
	$json = qby_qil_json( $many );
	qbl_check( 'embedded records JSON decodes back to the same three QIL records', array( 402, 403, 401 ) === array_column( json_decode( preg_replace( '#^<script[^>]*>|</script>$#', '', $json ), true ) ?: array(), 'id' ) );
} catch ( Throwable $e ) {
	qbl_check( 'records checks ran', false, qbl_error( $e ) );
}

/* ------------------------------------------------------------------ extra seams */
qbl_section( 'Extra seams exercised by the same request' );
try {
	qbl_check( 'QIL_Personalization::eligible() lets Beauty veto 401 and keeps supplement 102 (qil_personal_product_eligible)', false === QIL_Personalization::eligible( wc_get_product( 401 ) ) && true === QIL_Personalization::eligible( wc_get_product( 102 ) ) );
	qbl_check( 'QIL passes $repeat to Beauty\'s veto, so the shopper\'s own Beauty purchase 401 stays reorderable in Buy again', true === QIL_Personalization::eligible( wc_get_product( 401 ), 'home', true ) );
} catch ( Throwable $e ) {
	qbl_check( 'personal eligibility check ran', false, qbl_error( $e ) );
}
// The same two plugins on other requests, each in a clean process (see $qbl_mode).
foreach ( array( 'chrome' => '[chrome /beauty/]', 'ar' => '[staging /ar/]', 'live' => '[production /ar/]' ) as $mode => $label ) {
	$child = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . $mode . ' 2>&1' );
	if ( ! preg_match( '/^(\d+) passed, (\d+) failed/m', $child, $totals ) ) {
		qbl_check( $label . ' request ran to completion', false, trim( $child ) );
	}
	// Split on fixed markers, not a backtracking regex: a long failure detail must never hide its check.
	$seen = 0;
	foreach ( preg_split( '/\R/', $child ) as $line ) {
		$mark = substr( $line, 0, 6 );
		if ( '  ✓ ' === $mark || '  ✗ ' === $mark ) { $parts = explode( '  → ', substr( $line, 6 ), 2 ); qbl_check( $label . ' ' . $parts[0], '  ✓ ' === $mark, $parts[1] ?? '' ); ++$seen; }
		elseif ( 0 === strpos( $line, '  - SKIP ' ) ) { $parts = explode( '  → ', substr( $line, 9 ), 2 ); qbl_skip( $label . ' ' . $parts[0], $parts[1] ?? '' ); }
		elseif ( 0 === strpos( $line, '  NOTE ' ) ) { echo '  NOTE ' . $label . ' ' . substr( $line, 7 ) . "\n"; }
	}
	if ( $totals && (int) $totals[1] + (int) $totals[2] !== $seen ) {
		qbl_check( $label . ' every check the child ran is reported here', false, array( 'child' => $totals[0], 'reported' => $seen ) );
	}
}

qbl_summary();
exit( $GLOBALS['qbl']['failed'] ? 1 : 0 );
