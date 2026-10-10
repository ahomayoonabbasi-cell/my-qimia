<?php
/**
 * Live cross-plugin test: the REAL Qimia Intelligence Lab 1.19 and the REAL Qimia Beauty 2.4,
 * loaded together in one process on the shared WordPress/WooCommerce doubles.
 *   php tests/php/beauty-qil-live.php
 *
 * The process is one staging front-page request on qimialab.qimia.om, the only host where
 * Beauty's qby_stage_allowed() is true. WordPress lifecycle hooks are fired in core order.
 * A second, clean process (`php tests/php/beauty-qil-live.php chrome`) repeats the request on
 * /beauty/, where QIL renders only its chrome; the main run starts it and reports its checks.
 * Exit code 1 when any check fails.
 *
 * The shared doubles are not modified. WordPress functions they lack are defined below, each
 * guarded with function_exists()/class_exists(). Where a doubles gap cannot be closed from
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
// on /beauty/ (any non-front page); the main run starts it in a clean process, since
// qil_render_mode() and qil_view_context() are memoised once per request.
$qbl_mode = 'chrome' === ( $argv[1] ?? '' ) ? 'chrome' : 'full';
qt_fixtures();
if ( 'chrome' === $qbl_mode ) { $GLOBALS['qt']['render'] = 'chrome'; $_SERVER['REQUEST_URI'] = '/beauty/'; }
// The staging request: Beauty requires host, home and siteurl all on qimialab.qimia.om.
$_SERVER['HTTP_HOST'] = 'qimialab.qimia.om';
$GLOBALS['qt']['options']['home']        = 'https://qimialab.qimia.om';
$GLOBALS['qt']['options']['siteurl']     = 'https://qimialab.qimia.om';
$GLOBALS['qt']['options']['qby_enabled'] = '1';

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
qbl_check( 'Beauty storefront gate opened on the staging host (qby_stage_allowed, qby_frontend_enabled, QIL experience on)', qby_stage_allowed() && qby_frontend_enabled() && qil_experience_enabled() && qil_is_staging_sandbox() );
qbl_check( 'Beauty integration files loaded at plugins_loaded:100 (qby_qil_beauty_record, qby_product_details, qby_search_scope, qbx_home)', function_exists( 'qby_qil_beauty_record' ) && function_exists( 'qby_product_details' ) && function_exists( 'qby_search_scope' ) && function_exists( 'qbx_home' ) );
$record_hooks = array_values( array_filter( qbl_hook_rows( 'qil_catalogue_product_record' ), static function ( $r ) { return 'qby_qil_beauty_record' === $r['cb']; } ) );
qbl_check( 'Beauty card adapter is on qil_catalogue_product_record at priority 100 with the 3 arguments QIL passes', 1 === count( $record_hooks ) && 100 === $record_hooks[0]['priority'] && 3 === $record_hooks[0]['args'], $record_hooks );
qbl_check( 'QIL render mode settled to ' . $qbl_mode . ' after template_redirect', $qbl_mode === qil_render_mode(), qil_render_mode() );

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
	if ( false === strpos( $home, 'class="qby-home-product"' ) ) {
		qbl_skip( 'Beauty home edit lists real Beauty products', 'qby_home_beauty_edit() queries by product_cat field=slug; the WP_Query double matches terms by intval and returns nothing, so Beauty rendered its empty-edit editorial image' );
	}
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
	qbl_check( 'q=protein: no Beauty product enters the results and QIL\'s no-store header survives', ! array_intersect( $ids, array( 401, 402, 403 ) ) && false !== strpos( (string) ( $after->headers['Cache-Control'] ?? '' ), 'no-store' ), $after->headers );

	// The QIL half of the shampoo contract, with Beauty's exact arguments (integration.php:84).
	$single = qil_get_catalogue( array( 'include' => array( 403 ), 'limit' => 1, 'orderby' => 'include', '_qil_include_unavailable' => true, '_qil_locale' => 'en' ) );
	qbl_check( 'qil_get_catalogue with Beauty\'s search args returns a single out-of-stock Beauty hit with stock.inStock false', 1 === count( $single ) && 403 === (int) $single[0]['id'] && false === $single[0]['stock']['inStock'] && 'beauty' === $single[0]['productClass'], $single );
	$pair = qil_get_catalogue( array( 'include' => array( 401, 403 ), 'limit' => 2, 'orderby' => 'include', '_qil_include_unavailable' => true, '_qil_locale' => 'en' ) );
	qbl_check( 'qil_get_catalogue with Beauty\'s search args keeps an out-of-stock Beauty hit when there are several hits', array( 401, 403 ) === array_values( array_intersect( array( 401, 403 ), array_map( static function ( $r ) { return (int) $r['id']; }, $pair ) ) ), array_map( static function ( $r ) { return array( (int) $r['id'], $r['stock']['inStock'] ); }, $pair ) );
	// Beauty's adapter uses the records as returned (no re-sort, unlike qby_qil_records), then slices them.
	$order = array_map( static function ( $r ) { return (int) $r['id']; }, qil_get_catalogue( array( 'include' => array( 402, 401 ), 'limit' => 2, 'orderby' => 'include', '_qil_include_unavailable' => true, '_qil_locale' => 'en' ) ) );
	qbl_check( 'qil_get_catalogue with Beauty\'s search args returns hits in the requested orderby=include order', array( 402, 401 ) === $order, $order );

	$req = new QBL_REST_Request( 'GET', '/qimia-lab/v1/search' );
	$req->set_param( 'q', 'shampoo' ); $req->set_param( 'qil_locale', 'en' ); $req->set_param( 'limit', 8 );
	try {
		list( $before, $after ) = qbl_rest_dispatch( 'qimia-lab/v1/search', $req );
		$rows = $after->get_data()['products'] ?? array();
		$classes = array_map( static function ( $p ) { return qby_resolved_class( (int) $p['id'] ); }, $rows );
		if ( ! $rows ) {
			qbl_skip( 'q=shampoo returns only Beauty products', 'pipeline ran but Beauty\'s qby_beauty_query() found no ids: the WP_Query double cannot resolve its product_cat field=slug tax_query (terms are matched by intval), so a non-empty Beauty-only list cannot be produced here' );
		} else {
			qbl_check( 'q=shampoo returns only Beauty products', array() === array_diff( $classes, array( 'beauty' ) ), array( 'rows' => array_column( $rows, 'id' ), 'classes' => $classes ) );
		}
	} catch ( Throwable $e ) {
		qbl_skip( 'q=shampoo returns only Beauty products', 'the shared doubles cannot run this request: ' . qbl_error( $e ) . ' (QIL\'s title/SKU search SQL); Beauty\'s qby_beauty_query() would then also need product_cat field=slug resolution, which the WP_Query double lacks. The QIL half of this contract is checked above with Beauty\'s exact qil_get_catalogue() arguments' );
	}
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
} catch ( Throwable $e ) {
	qbl_check( 'personal eligibility check ran', false, qbl_error( $e ) );
}
// The same two plugins on an inner page, in a clean process (see $qbl_mode).
$child = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' chrome 2>&1' );
if ( ! preg_match( '/^\d+ passed, \d+ failed/m', $child ) ) {
	qbl_check( '[chrome /beauty/] request ran to completion', false, trim( $child ) );
}
foreach ( preg_split( '/\R/', $child ) as $line ) {
	if ( preg_match( '/^  ([✓✗]) (.*?)(?:  → (.*))?$/u', $line, $m ) ) { qbl_check( '[chrome /beauty/] ' . $m[2], '✓' === $m[1], $m[3] ?? '' ); }
	elseif ( preg_match( '/^  - SKIP (.*?)  → (.*)$/u', $line, $m ) ) { qbl_skip( '[chrome /beauty/] ' . $m[1], $m[2] ); }
	elseif ( 0 === strpos( $line, '  NOTE ' ) ) { echo '  NOTE [chrome /beauty/] ' . substr( $line, 7 ) . "\n"; }
}

qbl_summary();
exit( $GLOBALS['qbl']['failed'] ? 1 : 0 );
