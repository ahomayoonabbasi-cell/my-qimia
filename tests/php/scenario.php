<?php
/**
 * One isolated scenario (one "request"). Prints {"checks":[...],"metrics":{...}}.
 */
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );
require __DIR__ . '/wp-doubles.php';
require __DIR__ . '/wc-doubles.php';
require __DIR__ . '/fixtures.php';

$spec     = explode( ':', $argv[1] ?? '' );
$scenario = $spec[0];
$checks   = array();
$metrics  = array();
function ok( $name, $cond, $detail = '' ) { global $checks; $checks[] = array( 'name' => $name, 'ok' => (bool) $cond, 'detail' => $cond ? '' : ( is_string( $detail ) ? $detail : json_encode( $detail, JSON_UNESCAPED_UNICODE ) ) ); }
function near( $a, $b, $eps = 1e-9 ) { return abs( (float) $a - (float) $b ) <= $eps; }
function ids_of( array $picks ) { return array_map( static function ( $p ) { return (int) $p['row']['id']; }, $picks ); }
function capture( callable $fn ) { ob_start(); try { $fn(); } finally { $out = ob_get_clean(); } return $out; }
function ajax( callable $fn ) { try { $fn(); } catch ( QT_Json_Response $r ) { return array( $r->status, $r->data ); } return array( 0, null ); }
function embedded_json( $html, $attribute ) {
	return preg_match( '#<script type="application/json" ' . preg_quote( $attribute, '#' ) . '>(.*?)</script>#s', (string) $html, $m ) ? json_decode( $m[1], true ) : null;
}
function same_origin_post( array $post ) {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['HTTP_X_QIMIA_REQUEST'] = 'shopping/1';
	$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
	$_SERVER['HTTP_ORIGIN'] = 'https://qimia.om';
	$_POST = $post; $_REQUEST = $post;
}

qt_fixtures();
if ( 'disabled' === $scenario ) { define( 'QIL_BOOST_DISABLE', true ); }
if ( 'member_wallet_strict' === $scenario ) { update_option( 'qil_boost', array( 'wallet_mode' => 'strict' ) ); }
if ( 'lang_ajax' === $scenario ) {
	// A fragment refresh with no language header: the Referer decides; a header always wins.
	$_GET['wc-ajax'] = 'get_refreshed_fragments';
	$GLOBALS['qt']['referer'] = 'https://qimia.om' . ( $spec[1] ?? '/' );
	if ( ! empty( $spec[2] ) ) { $_SERVER['HTTP_X_QIMIA_LANGUAGE'] = $spec[2]; }
	$spec = array( $scenario );
}
if ( isset( $spec[1] ) ) { $GLOBALS['qt']['currency'] = $spec[1]; }
if ( isset( $spec[2] ) ) { $GLOBALS['qt']['decimals'] = (int) $spec[2]; }
if ( in_array( $scenario, array( 'minicart_ar' ), true ) ) { $_SERVER['HTTP_X_QIMIA_LANGUAGE'] = 'ar'; $_GET['wc-ajax'] = 'get_refreshed_fragments'; }
require realpath( __DIR__ . '/../../qimia-intelligence-lab-final/qimia-intelligence-lab.php' );
do_action( 'template_redirect' );
do_action( 'rest_api_init' );

try {
switch ( $scenario ) {

case 'ladder':
	$cases = array( array( 27.6, 1, 3, 4, 2.401 ), array( 20.0, 0, 2, 3, 0.001 ), array( 20.001, 1, 3, 4, 10.0 ), array( 30.0, 1, 3, 4, 0.001 ), array( 60.0, 4, 6, 8, 0.001 ), array( 0.5, 0, 2, 3, 19.501 ), array( 45.25, 3, 5, 6, 4.751 ) );
	foreach ( $cases as $c ) {
		$s = QIL_Boost::ladder( $c[0] );
		ok( "value {$c[0]} OMR → band #{$c[1]}, earns {$c[2]}, next {$c[3]} after adding {$c[4]}", $s && $s['index'] === $c[1] && near( $s['reward'], $c[2] ) && near( $s['next']['reward'], $c[3] ) && near( $s['next']['gap'], $c[4] ), $s );
	}
	$top = QIL_Boost::ladder( 60.001 );
	ok( '60.001 OMR is the top band: 8 OMR, no next step', $top && $top['top'] && null === $top['next'] && near( $top['reward'], 8 ), $top );
	ok( 'empty / zero value gives no ladder', null === QIL_Boost::ladder( 0 ) );
	$html = QIL_Boost::gap_html( QIL_Boost::ladder( 27.6 ) );
	ok( 'gap is formatted by WooCommerce with store decimals (2.401)', false !== strpos( $html, '2.401' ), $html );
	break;

case 'ladder_sar':
	$s = QIL_Boost::ladder( 250 );
	ok( '250 SAR (rate 9.75) is band #1 (3 OMR); next 4 OMR', $s && 1 === $s['index'] && near( $s['next']['reward'], 4 ), $s );
	ok( 'foreign gap is whole units rounded up (43 SAR)', $s && near( $s['next']['gap'], 43 ) && $s['next']['foreign'], $s );
	ok( '250 + 43 SAR really exceeds 30 OMR', ( 250 + 43 ) / 9.75 > 30 );
	ok( 'foreign gap is marked approximate', false !== strpos( QIL_Boost::gap_html( $s ), '≈' ) );
	ok( 'reward shown in SAR as approximate', 0 === strpos( QIL_Boost::reward_text( 4 ), '≈' ), QIL_Boost::reward_text( 4 ) );
	break;

case 'ladder_property':
	$m     = QIL_Boost::market();
	$unit  = 1 / pow( 10, $m['decimals'] );
	$rate  = $m['rate'];
	$foreign = 'OMR' !== $m['currency'];
	$bad   = array();
	mt_srand( 1180 );
	for ( $i = 0; $i < 5000; $i++ ) {
		$v = round( mt_rand( 1, 80000 ) / 1000 * $rate, $m['decimals'] );
		if ( $v <= 0 ) { continue; }
		$s = QIL_Boost::ladder( $v );
		if ( ! $s || ! $s['next'] ) { continue; }
		$after = QIL_Boost::reward_for_omr( ( $v + $s['next']['gap'] ) / $rate );
		if ( $after + 1e-9 < $s['next']['reward'] ) { $bad[] = array( 'v' => $v, 'gap' => $s['next']['gap'], 'after' => $after ); }
		if ( ! $foreign ) {
			$less = QIL_Boost::reward_for_omr( ( $v + $s['next']['gap'] - $unit ) / $rate );
			if ( $less + 1e-9 >= $s['next']['reward'] && $s['next']['gap'] > $unit ) { $bad[] = array( 'v' => $v, 'gap' => $s['next']['gap'], 'minimal' => false ); }
		}
		if ( count( $bad ) > 5 ) { break; }
	}
	ok( 'adding the shown amount always reaches the promised band' . ( $foreign ? '' : ', and one unit less never does' ) . ' (5,000 random carts)', ! $bad, array_slice( $bad, 0, 3 ) );
	break;

case 'picks':
	WC()->cart->add( 101, 1, 1011 );
	$state = QIL_Boost::ladder();
	ok( 'whey 19.900 → earns 2 OMR, add 0.101 for 3 OMR', $state && 0 === $state['index'] && near( $state['next']['gap'], 0.101 ), $state );
	$ctx = QIL_Boost::cart_context();
	ok( 'cart role is protein', array( 'protein' ) === $ctx['roles'], $ctx['roles'] );
	$ladder = QIL_Boost::picks( $ctx, 'ladder', $state['next']['gap'], 3 );
	ok( 'ladder picks = creatine, multivitamin, shaker (related first, cheapest that reaches)', array( 102, 104, 109 ) === ids_of( $ladder ), ids_of( $ladder ) );
	ok( 'every ladder pick alone reaches the next band', ! array_filter( $ladder, static function ( $p ) use ( $state ) { return $p['row']['p'] + 1e-9 < $state['next']['gap']; } ) );
	ok( 'cart product, hidden and sold-out products are never picked', ! array_intersect( array( 101, 111, 112 ), ids_of( QIL_Boost::picks( $ctx, 'ladder', $state['next']['gap'], 20 ) ) ) );
	$stack = QIL_Boost::picks( $ctx, 'stack', 0, 4 );
	ok( 'Whey → Creatine first, then Pre-workout, Multivitamin', array( 102, 103, 104 ) === array_slice( ids_of( $stack ), 0, 3 ), ids_of( $stack ) );
	$rules = array( 108 => 104, 105 => 106, 102 => array( 101, 110, 204, 210, 201, 211 ) );
	foreach ( $rules as $in_cart => $expected ) {
		WC()->cart->items = array();
		WC()->cart->add( $in_cart );
		$first = ids_of( QIL_Boost::picks( QIL_Boost::cart_context(), 'stack', 0, 4 ) )[0] ?? 0;
		$label = array( 108 => 'Fat burner → Multivitamin', 105 => 'Magnesium → Ashwagandha', 102 => 'Creatine → Whey / protein' )[ $in_cart ];
		ok( $label, is_array( $expected ) ? in_array( $first, $expected, true ) : $first === $expected, $first );
	}
	WC()->cart->items = array();
	WC()->cart->add( 105 );
	$roles = array_map( static function ( $p ) { return $p['row']['o']; }, QIL_Boost::picks( QIL_Boost::cart_context(), 'stack', 0, 4 ) );
	ok( 'Magnesium stack includes daily-wellness complements after Ashwagandha', 'ashwagandha' === $roles[0] && count( array_intersect( $roles, array( 'multivitamin', 'omega3', 'vitamin_d', 'daily_wellness' ) ) ) >= 1, $roles );
	break;

case 'minicart':
	$empty = capture( 'woocommerce_mini_cart' );
	ok( 'empty mini cart is untouched (no Qimia block)', false === strpos( $empty, 'qil-boost' ) );
	WC()->cart->add( 101, 1, 1011 );
	$html = capture( 'woocommerce_mini_cart' );
	ok( 'ladder at the top of the drawer', false !== strpos( $html, 'qil-boost-mini' ) && strpos( $html, 'qil-boost-mini' ) < strpos( $html, 'woocommerce-mini-cart-item' ) );
	ok( 'message: Add 0.101 more → get 3 OMR cashback', preg_match( '/Add <bdi>.*0\.101.*<\/bdi> more<\/strong>.*get <b><bdi>3 OMR<\/bdi><\/b> cashback/s', $html ), $html );
	ok( 'ladder printed exactly once', 1 === substr_count( $html, 'class="qil-boost-ladder is-' ) );
	ok( 'three picks after the items, inside the list', 3 === substr_count( $html, 'class="qil-boost-pick"' ) && strpos( $html, 'qil-boost-li' ) > strpos( $html, 'woocommerce-mini-cart-item' ) && strpos( $html, 'qil-boost-li' ) < strpos( $html, '</ul>' ) );
	ok( 'simple pick uses WooCommerce AJAX add (product 102)', (bool) preg_match( '/data-product_id="102"[^>]*class="qil-boost-add add_to_cart_button ajax_add_to_cart/', $html ) );
	ok( 'pick rows are not counted as cart items', false === strpos( $html, 'qil-boost-li mini_cart_item' ) );
	ok( 'progress bar is accessible', false !== strpos( $html, 'role="progressbar"' ) && false !== strpos( $html, 'aria-valuenow="' ) );
	ok( 'honest note: eligible paid orders', false !== strpos( $html, 'eligible paid orders' ) );
	// A theme that skips woocommerce_before_mini_cart still gets the ladder, inside the list.
	$GLOBALS['qt']['done']['woocommerce_before_mini_cart'] = 0;
	$fallback = capture( static function () { do_action( 'woocommerce_mini_cart_contents' ); } );
	ok( 'fallback: ladder rendered with the picks when the top hook is missing', false !== strpos( $fallback, 'qil-boost-ladder' ) && false !== strpos( $fallback, 'qil-boost-pick' ) );
	// Variable picks open Quick View with an embedded, valid record.
	WC()->cart->items = array();
	WC()->cart->add( 102 );
	$html = capture( 'woocommerce_mini_cart' );
	preg_match( '/data-qil-boost-record="([^"]+)"/', $html, $m );
	$record = $m ? json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true ) : null;
	ok( 'variable pick carries a Quick View record (id, name, url, variable, price)', is_array( $record ) && $record['type'] === 'variable' && $record['id'] > 0 && isset( $record['price']['current']['min'] ), $m[1] ?? $html );
	break;

case 'minicart_ar':
	WC()->cart->add( 101, 1, 1011 );
	$html = capture( 'woocommerce_mini_cart' );
	ok( 'Arabic drawer block is RTL and not machine-translated', false !== strpos( $html, 'dir="rtl" lang="ar" translate="no"' ) );
	ok( 'Arabic message, cashback amount in the Western digits of the prices beside it', false !== strpos( $html, 'أضف' ) && false !== strpos( $html, 'كاش باك' ) && false !== strpos( $html, '3 ر.ع' ) && ! preg_match( '/[٠-٩]/u', $html ), $html );
	break;

case 'lang_ajax':
	WC()->cart->add( 101, 1, 1011 );
	$html   = capture( 'woocommerce_mini_cart' );
	$expect = ! empty( $_SERVER['HTTP_X_QIMIA_LANGUAGE'] ) ? $_SERVER['HTTP_X_QIMIA_LANGUAGE'] : ( 0 === strpos( wp_parse_url( $GLOBALS['qt']['referer'], PHP_URL_PATH ), '/ar/' ) ? 'ar' : 'en' );
	ok( 'fragment language: ' . $GLOBALS['qt']['referer'] . ' header=' . ( $_SERVER['HTTP_X_QIMIA_LANGUAGE'] ?? 'none' ) . ' → ' . $expect, false !== strpos( $html, 'ar' === $expect ? 'dir="rtl" lang="ar"' : 'dir="ltr" lang="en"' ), substr( $html, 0, 200 ) );
	break;

case 'cartpage':
	$GLOBALS['qt']['is_cart'] = true;
	WC()->cart->add( 102 );
	// WooCommerce prints the band (after the table) before the totals panel.
	$band  = QIL_Boost::stack_band_markup();
	$panel = QIL_Boost::cart_panel_markup();
	$b     = array( 1 => preg_match( '/data-qil-boost-ids="([\d,]+)"/', $band, $ids ) ? explode( ',', $ids[1] ) : array() );
	preg_match_all( '/data-qil-boost-product="(\d+)"/', $panel, $p );
	$band_data = embedded_json( $band, 'data-qil-boost-band-data' );
	ok( 'totals panel: full ladder with six steps, current step marked', 6 === substr_count( $panel, '<li class=' ) && false !== strpos( $panel, 'aria-current="step"' ), $panel );
	ok( 'panel shows 3 products that reach the next band', 3 === count( $p[1] ), $p[1] );
	ok( 'Complete your stack: 2–4 complements', count( $b[1] ) >= 2 && count( $b[1] ) <= 4, $b[1] );
	ok( 'no product is shown twice on the cart page (hook-order safe)', ! array_intersect( $b[1], $p[1] ), array( $b[1], $p[1] ) );
	ok( 'band title names the paired role (Creatine)', false !== strpos( $band, 'Made to pair with your Creatine' ), $band );
	ok( 'band is the theme card shelf: full records for the shared renderer, in the listed order', array_map( 'strval', array_column( $band_data['records'] ?? array(), 'id' ) ) === $b[1] && false !== strpos( $band, 'qil-shell qil-boost-band-shell' ) && false !== strpos( $band, 'qil-collection-grid qil-rail qil-boost-rail' ), array( 'ids' => $b[1], 'records' => array_column( $band_data['records'] ?? array(), 'id' ) ) );
	ok( 'each card is labelled: what it pairs with, or the cashback it unlocks', count( $band_data['labels'] ?? array() ) === count( $b[1] ) && ! array_filter( $band_data['labels'], static function ( $l ) { return false === strpos( $l, 'Pairs with' ) && false === strpos( $l, 'cashback' ); } ), $band_data['labels'] ?? null );
	ok( 'panel is printed once per request', '' !== capture( array( 'QIL_Boost', 'cart_panel' ) ) && '' === capture( array( 'QIL_Boost', 'cart_panel' ) ) );
	break;

case 'fragments':
	WC()->cart->add( 102 );
	$GLOBALS['qt']['referer'] = 'https://qimia.om/cart/';
	$f = apply_filters( 'woocommerce_add_to_cart_fragments', array() );
	ok( 'cart page refresh includes the ladder panel fragment', isset( $f['div.qil-boost-cart-panel'] ) && false !== strpos( $f['div.qil-boost-cart-panel'], 'qil-boost-ladder' ) );
	$GLOBALS['qt']['referer'] = 'https://qimia.om/ar/cart/';
	ok( 'Arabic cart page too', isset( apply_filters( 'woocommerce_add_to_cart_fragments', array() )['div.qil-boost-cart-panel'] ) );
	$GLOBALS['qt']['referer'] = 'https://qimia.om/product/p-102/';
	ok( 'other pages pay nothing (no panel fragment)', ! isset( apply_filters( 'woocommerce_add_to_cart_fragments', array() )['div.qil-boost-cart-panel'] ) );
	break;

case 'flash':
	$candidates = QIL_Flash_Drop::candidates();
	ok( 'candidates are real reductions only (13): no <5%, no image-less, no sold-out', 13 === count( $candidates ) && ! isset( $candidates[213] ) && ! isset( $candidates[214] ) && ! isset( $candidates[215] ), array_keys( $candidates ) );
	ok( 'percent is computed from WooCommerce prices (201: 30.000 → 22.500 = 25%)', 25 === $candidates[201]['pct'] && false === $candidates[201]['upTo'] );
	ok( 'mixed variations are "up to" (216: up to 25%)', 25 === $candidates[216]['pct'] && true === $candidates[216]['upTo'] );
	ok( 'real stock from managed quantity (201: 10; 216: 4+3)', 10 === $candidates[201]['stock'] && 7 === $candidates[216]['stock'] );
	ok( 'a variable product names its lowest option (216: only 3 left in Chocolate)', 3 === ( $candidates[216]['low']['qty'] ?? 0 ) && 'Chocolate' === ( $candidates[216]['low']['option'] ?? '' ), $candidates[216]['low'] ?? null );
	ok( 'simple products carry no option line', null === $candidates[201]['low'] );
	ok( 'real sale end only where one exists (201 in ~30h, 202 none)', $candidates[201]['endsAt'] > time() + 29 * HOUR_IN_SECONDS && 0 === $candidates[202]['endsAt'] );
	$w = QIL_Flash_Drop::window();
	ok( '72-hour window aligned to the Oman anchor', 72 === $w['hours'] && 0 === ( $w['start'] - QIL_Flash_Drop::anchor() ) % ( 72 * HOUR_IN_SECONDS ) && $w['start'] <= time() && time() < $w['end'] );
	$state = QIL_Flash_Drop::state();
	ok( 'drop has 10 distinct eligible products', 10 === count( array_unique( $state['ids'] ) ) && ! array_diff( $state['ids'], array_keys( $candidates ) ), $state['ids'] );
	ok( 'rotation is scheduled for the real end of the drop', ( $GLOBALS['qt']['cron']['qil_flash_drop_rotate'] ?? 0 ) === $w['end'] + 20 );
	$again = QIL_Flash_Drop::state();
	ok( 'selection is stable within the window', $again['ids'] === $state['ids'] );
	$payload = QIL_Flash_Drop::payload( 'en' );
	ok( 'payload: 10 card records in drop order with facts', 10 === count( $payload['records'] ) && array_map( static function ( $r ) { return (int) $r['id']; }, $payload['records'] ) === $state['ids'] );
	$section = QIL_Flash_Drop::section();
	ok( 'section: FLASH SALE — 72 HOURS, live clock to the window end', false !== strpos( $section, 'FLASH SALE' ) && false !== strpos( $section, '— 72 HOURS' ) && false !== strpos( $section, 'data-qil-flash-end="' . $w['end'] . '"' ) );
	ok( 'section: Ask Qimia AI boots the assistant', false !== strpos( $section, 'data-qil-flash-ai data-qimia-ai-open' ) );
	$data = embedded_json( $section, 'data-qil-flash-data' );
	ok( 'the section carries its own drop data (no AJAX, no separate page data)', 10 === count( $data['records'] ?? array() ) && 10 === count( $data['meta'] ?? array() ) && $w['end'] === ( $data['end'] ?? 0 ), array_keys( (array) $data ) );
	ok( 'flash data is not duplicated into the page data any more', ! isset( apply_filters( 'qil_boost_page_data', array() )['flash'] ) );
	$gone = count( array_filter( $payload['meta'], static function ( $m ) { return ! empty( $m['low'] ) || ( null !== $m['stock'] && $m['stock'] <= 10 ); } ) );
	ok( 'kicker counts the products that are almost gone', $gone > 0 && false !== strpos( $section, $gone . ' ALMOST GONE' ), $gone );
	ok( 'every record sent with the section is a full record or a catalogue reference', ! array_filter( $data['records'], static function ( $r ) { return empty( $r['ref'] ) && empty( $r['name'] ); } ) );
	$request = new WP_REST_Request(); $request->set_param( 'qil_locale', 'en' );
	$rest = QIL_Flash_Drop::rest( $request )->get_data();
	ok( 'REST feed: 10 products with Instagram UTM links', 10 === count( $rest['products'] ) && false !== strpos( $rest['products'][0]['url'], 'utm_source=instagram' ) && false !== strpos( $rest['products'][0]['url'], 'utm_campaign=flash-drop-' ) );
	ok( 'REST feed carries real regular price and percent', $rest['products'][0]['regularPrice'] > $rest['products'][0]['price'] && $rest['products'][0]['discountPct'] > 0 );
	ok( 'Instagram caption lists every product', 10 === substr_count( $rest['caption'], '• ' ) && false !== strpos( $rest['caption'], '⚡' ), $rest['caption'] );
	$ai = apply_filters( 'qimia_customer_source_projection_v2', array( 'interests' => array(), 'permissions' => array() ) );
	ok( 'Qimia AI context lists the drop', ( $ai['intelligence_lab']['flash_drop']['product_ids'] ?? array() ) === $state['ids'] );
	ok( 'size is limited to 8–12', 12 === QIL_Boost::sanitize( array( 'flash_size' => 40 ) )['flash_size'] && 8 === QIL_Boost::sanitize( array( 'flash_size' => 2 ) )['flash_size'] );
	break;

case 'flash_contended':
	// Another worker holds the candidate-scan lock and nothing is cached yet.
	$identity = qil_perf_market_identity(); unset( $identity['user'], $identity['session'] );
	$settings = QIL_Boost::settings();
	$key      = 'qil_flash_candidates_v1_' . md5( (string) wp_json_encode( array( QIL_VERSION, qil_perf_product_version(), $identity, (int) $settings['flash_min_pct'], $settings['flash_exclude'] ) ) );
	add_option( 'qil_lock_' . md5( 'flash-candidates|' . $key ), time(), '', false );
	$nocache = array();
	add_action( 'litespeed_control_set_nocache', static function ( $reason = '' ) use ( &$nocache ) { $nocache[] = $reason; } );
	$started = microtime( true );
	$section = QIL_Flash_Drop::section();
	ok( 'first-ever scan still running elsewhere: no section, and this page is kept out of the page cache', '' === $section && in_array( 'Qimia flash drop not ready', $nocache, true ), $nocache );
	ok( 'the waiting request gave up after ~3 s (bounded)', microtime( true ) - $started < 4.5 );
	$cached = array_filter( array_keys( $GLOBALS['qt']['transients'] ?? array() ), static function ( $k ) { return false !== strpos( $k, 'qil_flash_payload' ); } );
	$stored = array_filter( array_map( static function ( $k ) { return $GLOBALS['qt']['transients'][ $k ][0] ?? null; }, $cached ) );
	ok( 'an empty drop caused by the contention is never cached', ! array_filter( $stored, static function ( $v ) { return is_array( $v ) && empty( $v['records'] ); } ), array_values( $cached ) );
	break;

case 'flash_last_scan':
	// The lock is held, but an earlier scan completed: that one is used.
	$full = QIL_Flash_Drop::candidates();
	update_option( 'qil_flash_drop_last_scan', array( 'at' => time() - 600, 'rows' => $full ), false );
	$GLOBALS['qt']['version'] = '2'; // Product version moved on: the cached scan no longer matches.
	$identity = qil_perf_market_identity(); unset( $identity['user'], $identity['session'] );
	$settings = QIL_Boost::settings();
	$key      = 'qil_flash_candidates_v1_' . md5( (string) wp_json_encode( array( QIL_VERSION, qil_perf_product_version(), $identity, (int) $settings['flash_min_pct'], $settings['flash_exclude'] ) ) );
	add_option( 'qil_lock_' . md5( 'flash-candidates|' . $key ), time(), '', false );
	$again = QIL_Flash_Drop::candidates();
	ok( 'while another worker rescans, the last complete scan answers (never "no flash sale")', array_keys( $again ) === array_keys( $full ) );
	ok( 'the homepage still shows the drop', false !== strpos( QIL_Flash_Drop::section(), 'data-qil-flash-data' ) );
	break;

case 'flash_budget':
	QIL_Flash_Drop::candidates();
	$budget = new ReflectionProperty( 'QIL_Flash_Drop', 'variation_budget' );
	$budget->setAccessible( true );
	$budget->setValue( null, 0 ); // The candidate scan spent every variation it may read.
	$state = QIL_Flash_Drop::state();
	$rows  = QIL_Flash_Drop::drop();
	ok( 'the drop re-reads its own products even with the scan budget spent (216 stays)', in_array( 216, $state['ids'], true ) ? isset( $rows[216] ) && 3 === $rows[216]['low']['qty'] : true, array_keys( $rows ) );
	ok( 'the drop keeps its size', count( $rows ) === count( $state['ids'] ) );
	break;

case 'flash_stock_purge':
	$state = QIL_Flash_Drop::state();
	$in    = (int) $state['ids'][0];
	$out   = 101; // Whey: not a flash product.
	unset( $GLOBALS['qt']['cron']['qil_flash_drop_purge'] );
	do_action( 'woocommerce_product_set_stock', wc_get_product( $out ) );
	ok( 'a stock change elsewhere does not touch the cached homepage', empty( $GLOBALS['qt']['cron']['qil_flash_drop_purge'] ) );
	do_action( 'woocommerce_product_set_stock', wc_get_product( $in ) );
	ok( 'a drop product\'s stock change queues one homepage purge (WP-Cron, now)', ( $GLOBALS['qt']['cron']['qil_flash_drop_purge'] ?? 0 ) >= time() - 1 && ( $GLOBALS['qt']['cron']['qil_flash_drop_purge'] ?? 0 ) <= time() + 1 );
	$purged = array();
	add_action( 'litespeed_purge_url', static function ( $url ) use ( &$purged ) { $purged[] = $url; } );
	unset( $GLOBALS['qt']['cron']['qil_flash_drop_purge'] );
	do_action( 'qil_flash_drop_purge' );
	ok( 'the purge refreshes exactly the two homepages', array( 'https://qimia.om/', 'https://qimia.om/ar/' ) === $purged, $purged );
	$variation = wc_get_product( 2161 );
	do_action( 'woocommerce_variation_set_stock', $variation );
	$next = $GLOBALS['qt']['cron']['qil_flash_drop_purge'] ?? 0;
	ok( 'a variation of a drop product counts; a burst waits five minutes after the last purge', in_array( 216, $state['ids'], true ) ? $next >= time() + 299 : true, $next - time() );
	break;

case 'upgrade_purge':
	update_option( 'qil_boost_seen_version', '0.0.0-before' );
	unset( $GLOBALS['qt']['cron']['qil_flash_drop_purge'] );
	do_action( 'init' );
	ok( 'first request after an upgrade queues a purge of the cached homepages', ! empty( $GLOBALS['qt']['cron']['qil_flash_drop_purge'] ) && QIL_VERSION === get_option( 'qil_boost_seen_version' ), array( 'cron' => $GLOBALS['qt']['cron'], 'seen' => get_option( 'qil_boost_seen_version' ), 'version' => QIL_VERSION ) );
	unset( $GLOBALS['qt']['cron']['qil_flash_drop_purge'] );
	do_action( 'init' );
	ok( 'only once per version', empty( $GLOBALS['qt']['cron']['qil_flash_drop_purge'] ) );
	break;

case 'flash_rotation':
	$w          = QIL_Flash_Drop::window();
	$candidates = QIL_Flash_Drop::candidates();
	$previous   = array_slice( array_keys( $candidates ), 0, 10 );
	update_option( 'qil_flash_drop_state', array( 'index' => $w['index'] - 1, 'hours' => 72, 'ids' => $previous, 'previous' => array() ), false );
	$state = QIL_Flash_Drop::state();
	$fresh = array_diff( array_keys( $candidates ), $previous );
	ok( 'a new window leads with products not in the previous drop', ! array_diff( $fresh, array_slice( $state['ids'], 0, count( $fresh ) ) ), array( 'ids' => $state['ids'], 'fresh' => array_values( $fresh ) ) );
	ok( 'still 10 products (previous ones only fill the rest)', 10 === count( $state['ids'] ) );
	$purged = array();
	add_action( 'litespeed_purge_url', static function ( $url ) use ( &$purged ) { $purged[] = $url; } );
	QIL_Flash_Drop::rotate();
	ok( 'rotation purges exactly the two cached homepages', array( 'https://qimia.om/', 'https://qimia.om/ar/' ) === $purged, $purged );
	$settings = QIL_Boost::settings();
	update_option( 'qil_boost', array_merge( $settings, array( 'wallet' => 0 ) ) );
	ok( 'saving an unrelated setting never reshuffles a live drop', is_array( get_option( 'qil_flash_drop_state' ) ) );
	update_option( 'qil_boost', array_merge( $settings, array( 'flash_size' => 8 ) ) );
	ok( 'changing a flash setting re-chooses the drop', false === get_option( 'qil_flash_drop_state' ) );
	break;

case 'flash_soldout':
	$state = QIL_Flash_Drop::state();
	$gone  = $state['ids'][0];
	$GLOBALS['qt']['products'][ $gone ]->stock_status = 'outofstock';
	$GLOBALS['qt']['version'] = '2'; // WooCommerce bumps the product version on stock changes.
	$live = QIL_Flash_Drop::live_ids();
	ok( 'a product that sells out leaves the drop and is replaced', ! in_array( $gone, $live, true ) && 10 === count( $live ), $live );
	break;

case 'flash_topup':
	// A drop product sells out and another leaves the flash sale: both are
	// replaced from the last scan, re-read fresh, without scanning the category.
	qt_term( 'product_cat', 22, 'flash-weekend', 'Flash Weekend', 20 ); // A sub-category counts.
	$state = QIL_Flash_Drop::state();
	$ids   = $state['ids'];
	$sold  = (int) $ids[0];
	$left  = (int) $ids[1];
	$moved = (int) $ids[2];
	$GLOBALS['qt']['products'][ $sold ]->stock_status = 'outofstock';
	$GLOBALS['qt']['rel'][ $left ]['product_cat']  = array( 17 ); // Out of the flash sale.
	$GLOBALS['qt']['rel'][ $moved ]['product_cat'] = array( 22 ); // Into its sub-category.
	$spare = array_values( array_diff( array_keys( QIL_Flash_Drop::candidates() ), $ids ) );
	$GLOBALS['qt']['products'][ $spare[0] ]->stock_status = 'outofstock'; // Stale in the last scan.
	$GLOBALS['qt']['version'] = '2';
	$GLOBALS['qt']['queries'] = array();
	$rows = QIL_Flash_Drop::drop();
	$scans = array_filter( $GLOBALS['qt']['queries'], static function ( $args ) { return false !== strpos( (string) wp_json_encode( $args['tax_query'] ?? array() ), '"product_cat"' ); } );
	ok( 'a render replaces sold-out drop products without scanning the flash category', ! $scans && 10 === count( $rows ), array( 'scans' => count( $scans ), 'rows' => array_keys( $rows ) ) );
	ok( 'the sold-out product and the one taken out of the flash sale leave the drop', ! isset( $rows[ $sold ] ) && ! isset( $rows[ $left ] ), array_keys( $rows ) );
	ok( 'a product in a flash sub-category stays', isset( $rows[ $moved ] ) );
	ok( 'replacements are re-read live (one that sold out since the scan is skipped)', ! isset( $rows[ $spare[0] ] ) && isset( $rows[ $spare[1] ] ), array( 'rows' => array_keys( $rows ), 'spare' => $spare ) );
	ok( 'replacements carry live facts', isset( $rows[ $spare[1] ]['pct'] ) && $rows[ $spare[1] ]['pct'] >= 5 );
	break;

case 'pool_rebuild':
	$identity = qil_perf_market_identity(); unset( $identity['user'], $identity['session'] );
	$key  = 'qil_boost_pool_v2_' . md5( (string) wp_json_encode( array( QIL_VERSION, (string) wp_cache_get_last_changed( 'terms' ), 'en', $identity ) ) );
	$list = static function () { return array_values( array_filter( array_keys( $GLOBALS['qt']['transients'] ?? array() ), static function ( $k ) { return 0 === strpos( $k, 'qil_boost_pool_' ); } ) ); };
	$first = QIL_Boost::pool();
	ok( 'the pool is built once and stored under one entry per market and language', $first && array( $key ) === $list(), $list() );
	// Orders move WooCommerce's product version; another worker is rebuilding.
	$GLOBALS['qt']['version'] = '7';
	$GLOBALS['qt']['products'][101]->stock_status = 'outofstock';
	add_option( 'qil_lock_' . md5( 'boost-pool|' . $key ), time(), '', false );
	$started = microtime( true );
	$during  = QIL_Boost::pool();
	ok( 'while another worker rebuilds, the cart gets the last list at once (no wait, never empty)', $during === $first && microtime( true ) - $started < 0.1, round( microtime( true ) - $started, 3 ) );
	delete_option( 'qil_lock_' . md5( 'boost-pool|' . $key ) );
	$GLOBALS['qt']['version'] = '8';
	$after = QIL_Boost::pool();
	$ids   = array_map( static function ( $r ) { return (int) $r['id']; }, $after );
	ok( 'a new product version rebuilds the list (sold-out product gone)', ! in_array( 101, $ids, true ) && in_array( 101, array_map( static function ( $r ) { return (int) $r['id']; }, $first ), true ) );
	ok( 'the rebuild overwrites the same entry: no trail of old copies', array( $key ) === $list() && '8' === (string) ( $GLOBALS['qt']['transients'][ $key ][0]['v'] ?? '' ), $list() );
	break;

case 'shortcodes':
	// On a page without the Qimia card renderer the sections print nothing.
	unset( $GLOBALS['qt']['enqueued']['script']['qil-boost'] );
	$flash  = call_user_func( $GLOBALS['qt']['shortcodes']['qimia_flash_drop'], '', '', 'qimia_flash_drop' );
	$stacks = call_user_func( $GLOBALS['qt']['shortcodes']['qimia_cashback_stacks'], '', '', 'qimia_cashback_stacks' );
	ok( 'without the card renderer the shortcodes print nothing (no unstyled, never-filled section)', '' === $flash && '' === $stacks, array( strlen( $flash ), strlen( $stacks ) ) );
	$GLOBALS['qt']['enqueued']['script']['qil-boost'] = 'qil-boost.min.js';
	$flash  = call_user_func( $GLOBALS['qt']['shortcodes']['qimia_flash_drop'], '', '', 'qimia_flash_drop' );
	$stacks = call_user_func( $GLOBALS['qt']['shortcodes']['qimia_cashback_stacks'], '', '', 'qimia_cashback_stacks' );
	$shell  = static function ( $html ) { return 0 === strpos( $html, '<div class="qil-shell qil-boost-band-shell' ) && '</div>' === substr( $html, -6 ); };
	ok( 'with it, each shortcode is the homepage section inside its own Qimia shell', $shell( $flash ) && false !== strpos( $flash, 'data-qil-flash-data' ) && $shell( $stacks ) && false !== strpos( $stacks, 'data-qil-stacks-data' ) );
	break;

case 'member_wallet':
	$GLOBALS['qt']['user'] = 7;
	$rows = QIL_Member::coupons( 7 );
	ok( 'wallet reads only this account\'s unused, unexpired cashback (soonest expiry first)', array( 801, 802, 808 ) === array_column( $rows, 'id' ), array_column( $rows, 'id' ) );
	$wallet = QIL_Member::wallet( 7 );
	ok( 'wallet: 3 coupons, total 9 OMR, use 4 OMR first (expires in 5 days)', 3 === $wallet['count'] && '9 OMR' === $wallet['total'] && '4 OMR' === $wallet['best']['amount'] && 5 === $wallet['best']['daysLeft'], $wallet );
	ok( 'coupon codes never leave the server', false === strpos( json_encode( $wallet ), 'CB-7' ) );
	ok( 'each credit is listed for its own order: amount + days left, soonest first', array( '4 OMR', '3 OMR', '2 OMR' ) === array_column( $wallet['credits'], 'amount' ) && array( 5, 20, null ) === array_column( $wallet['credits'], 'daysLeft' ) && array( '', '', '10 OMR' ) === array_column( $wallet['credits'], 'minimum' ), $wallet['credits'] );
	ok( 'strict mode can be configured', 'strict' === QIL_Boost::sanitize( array( 'wallet_mode' => 'strict' ) )['wallet_mode'] );
	add_filter( 'qil_cashback_wallet_coupons', static function () { return array( array( 'id' => 806 ), array( 'id' => 801 ) ); } );
	ok( 'an issuer list can never expose another customer\'s coupon', array( 801 ) === array_column( QIL_Member::coupons( 7 ), 'id' ) );
	$GLOBALS['qt']['user'] = 0;
	ok( 'guests have no wallet', array() === QIL_Member::coupons( 7 ) );
	break;

case 'member_wallet_strict':
	$GLOBALS['qt']['user'] = 7;
	$ids = array_column( QIL_Member::coupons( 7 ), 'id' );
	ok( 'strict mode: only coupons the cashback issuer marked (shape alone is not enough)', array( 808 ) === $ids, $ids );
	break;

case 'member_apply':
	$GLOBALS['qt']['user'] = 7;
	WC()->session->set( 'wc_notices', array( 'notice' => array( array( 'notice' => 'Welcome back' ) ) ) );
	same_origin_post( array( 'nonce' => 'bad', 'coupon' => '801' ) );
	list( $status ) = ajax( array( 'QIL_Member', 'apply' ) );
	ok( 'a bad nonce is refused', 403 === $status );
	same_origin_post( array( 'nonce' => wp_create_nonce( 'qil_wallet' ), 'coupon' => '806' ) );
	list( $status ) = ajax( array( 'QIL_Member', 'apply' ) );
	ok( 'another customer\'s coupon is refused', 409 === $status );
	same_origin_post( array( 'nonce' => wp_create_nonce( 'qil_wallet' ), 'coupon' => '801' ) );
	list( $status, $data ) = ajax( array( 'QIL_Member', 'apply' ) );
	ok( 'empty cart: cashback is saved as pending', 200 === $status && 'pending' === $data['status'] && 801 === WC()->session->get( 'qil_wallet_pending' )['id'], $data );
	WC()->cart->add( 102 );
	ok( 'first product added: pending cashback is applied automatically', WC()->cart->has_discount( 'CB-7-A' ) && null === WC()->session->get( 'qil_wallet_pending' ) );
	ok( 'cart total now 5.980 − 4 = 1.980', near( WC()->cart->get_total( 'edit' ), 1.98 ), WC()->cart->get_total( 'edit' ) );
	same_origin_post( array( 'nonce' => wp_create_nonce( 'qil_wallet' ), 'coupon' => '808' ) );
	list( $status, $data ) = ajax( array( 'QIL_Member', 'apply' ) );
	ok( 'below the coupon minimum: pending with the minimum stated', 200 === $status && 'pending' === $data['status'] && false !== strpos( $data['message'], '10' ), $data );
	WC()->cart->add( 101, 1, 1011 );
	ok( 'minimum reached: applied automatically', WC()->cart->has_discount( 'CB-7-MARKED' ) );
	WC()->cart->applied = array();
	WC()->cart->calculate_totals();
	same_origin_post( array( 'nonce' => wp_create_nonce( 'qil_wallet' ), 'coupon' => '802' ) );
	list( $status, $data ) = ajax( array( 'QIL_Member', 'apply' ) );
	ok( 'non-empty cart: applied at once, with fragments', 200 === $status && 'applied' === $data['status'] && isset( $data['fragments']['div.widget_shopping_cart_content'] ), $data );
	ok( 'the shopper\'s earlier notices are kept', 'Welcome back' === ( WC()->session->get( 'wc_notices' )['notice'][0]['notice'] ?? '' ), WC()->session->get( 'wc_notices' ) );
	break;

case 'member_running':
	$GLOBALS['qt']['user'] = 7;
	$rows = QIL_Member::running_low( 7, 'en' );
	ok( 'Running low lists the multivitamin (runs out now) then the pre-workout (in ~4 days)', array( 104, 103 ) === array_column( $rows, 'productId' ), $rows );
	ok( 'estimate uses label servings × quantity ÷ per day (pre-workout 30 servings, 5/week)', isset( $rows[1] ) && 4 === $rows[1]['daysLeft'] && 30 === $rows[1]['servings'], $rows[1] ?? null );
	ok( 'exact previous selection (flavour) and variation id', isset( $rows[1] ) && 1031 === $rows[1]['variationId'] && false !== strpos( $rows[1]['selection'], 'Fruit Punch' ), $rows[1] ?? null );
	ok( 'products without a verified serving count are never estimated (whey)', ! in_array( 101, array_column( $rows, 'productId' ), true ) );
	ok( 'products not due yet are not shown (creatine, magnesium)', ! array_intersect( array( 102, 105 ), array_column( $rows, 'productId' ) ) );
	WC()->cart->add( 104 );
	ok( 'a product already in the cart is not suggested again', ! in_array( 104, array_column( QIL_Member::running_low( 7, 'en' ), 'productId' ), true ) );
	break;

case 'member_ajax':
	same_origin_post( array( 'locale' => 'en', 'surface' => 'home', 'hints' => json_encode( array( 'recent' => array( 107, 999999 ), 'compare' => array( 110 ) ) ) ) );
	list( $status, $data ) = ajax( array( 'QIL_Member', 'ajax' ) );
	ok( 'guest: no private data', 200 === $status && false === $data['authenticated'] );
	$GLOBALS['qt']['user'] = 7;
	list( $status, $data ) = ajax( array( 'QIL_Member', 'ajax' ) );
	ok( 'signed in: wallet, running low and nonces in one response', 200 === $status && true === $data['authenticated'] && 3 === $data['wallet']['count'] && 2 === count( $data['running'] ) && '' !== $data['nonce'] && '' !== $data['walletNonce'], $data );
	$picks = array_map( static function ( $r ) { return (int) $r['id']; }, $data['picks']['records'] );
	ok( 'best ways to use it: compared + viewed products first, unknown ids dropped', array( 110, 107 ) === array_slice( $picks, 0, 2 ), $picks );
	ok( 'reasons are labelled', 'You compared' === ( $data['picks']['reasons'][110] ?? '' ) && 'You viewed' === ( $data['picks']['reasons'][107] ?? '' ), $data['picks']['reasons'] );
	$_SERVER['HTTP_X_QIMIA_REQUEST'] = '';
	list( $status ) = ajax( array( 'QIL_Member', 'ajax' ) );
	ok( 'requests without the storefront marker are refused', 403 === $status );
	break;

case 'stacks':
	update_option( 'qil_boost', array_merge( QIL_Boost::defaults(), array( 'stack_components' => "302: 105, 106, 104" ) ) );
	$stacks = QIL_Stacks::stacks();
	$by = array(); foreach ( $stacks as $s ) { $by[ $s['id'] ] = $s; }
	ok( 'in-stock stacks from the stack category (sold-out one excluded)', array( 301, 302, 303 ) === array_keys( $by ), array_keys( $by ) );
	ok( 'Muscle Starter: Whey + Creatine read from the bundle plugin', 2 === count( $by[301]['inside'] ) && near( $by[301]['separately'], 25.88 ), $by[301] );
	ok( 'Recovery Pack: contents from the Growth map, separately 12.660', 3 === count( $by[302]['inside'] ) && near( $by[302]['separately'], 12.66 ), $by[302] );
	ok( 'Daily Essentials: no known contents → no "separately"; real sale shown as "was"', 0.0 === $by[303]['separately'] && near( $by[303]['was'], 36.0 ) && '' !== $by[303]['blurb'], $by[303] );
	ok( 'cashback tier from the issuer: 24.9 → 3, 11.9 → 2, 34.9 → 4 OMR', near( $by[301]['reward'], 3 ) && near( $by[302]['reward'], 2 ) && near( $by[303]['reward'], 4 ) );
	$html  = QIL_Stacks::section();
	$data  = embedded_json( $html, 'data-qil-stacks-data' );
	$facts = $data['facts'] ?? array();
	ok( 'section sends the theme card records for the shared renderer', array( 301, 302, 303 ) === array_map( 'intval', array_column( $data['records'] ?? array(), 'id' ) ) && false !== strpos( $html, 'qil-collection-grid qil-rail qil-boost-rail qil-stacks-rail' ) );
	ok( 'Muscle Starter facts: inside, separately, saving and cashback', 'Protein + Creatine' === ( $facts[301]['inside'] ?? '' ) && 'Gold Standard 100% Whey + Creatine Monohydrate 300g' === ( $facts[301]['insideFull'] ?? '' ) && 0 === strpos( $facts[301]['separately'] ?? '', '25.880' ) && 0 === strpos( $facts[301]['save'] ?? '', '0.980' ) && '+3 OMR' === ( $facts[301]['reward'] ?? '' ), $facts[301] ?? null );
	ok( 'Daily Essentials: no "separately"; saving from its real sale (36.000 → 34.900)', '' === ( $facts[303]['separately'] ?? 'x' ) && 0 === strpos( $facts[303]['save'] ?? '', '1.100' ) && '' === ( $facts[303]['inside'] ?? 'x' ), $facts[303] ?? null );
	ok( 'simple stacks add in one tap (the theme card\'s AJAX add)', 'add' === ( $data['records'][0]['purchase']['action'] ?? '' ) || true === ( $data['records'][0]['purchase']['ajax'] ?? false ), $data['records'][0]['purchase'] ?? null );
	break;

case 'admin':
	$GLOBALS['qt']['admin_user'] = true;
	$html = capture( array( 'QIL_Boost', 'admin' ) );
	ok( 'Growth tab renders settings, flash preview, captions and stack assistant', false !== strpos( $html, 'qil_boost[flash_size]' ) && false !== strpos( $html, 'Current drop' ) && false !== strpos( $html, 'English caption' ) && false !== strpos( $html, 'Stack pricing assistant' ), substr( $html, 0, 400 ) );
	ok( 'issuer bands are listed (≤20 → 2 … >60 → 8)', false !== strpos( $html, '≤20 → 2' ) && false !== strpos( $html, '&gt;60 → 8' ) );
	ok( 'price points 24.9–64.9 with their cashback', false !== strpos( $html, '24.900' ) && false !== strpos( $html, '64.900' ) );
	$GLOBALS['qt']['admin_user'] = false;
	ok( 'non-admins see nothing', '' === capture( array( 'QIL_Boost', 'admin' ) ) );
	break;

case 'xss_json':
	$GLOBALS['qt']['products'][201]->name = 'Whey</script><script>alert(1)</script>';
	$GLOBALS['qt']['products'][301]->name = 'Stack</script><img src=x onerror=alert(2)>';
	update_option( 'qil_boost', array_merge( QIL_Boost::defaults(), array( 'stack_components' => "302: 105, 106, 104" ) ) );
	$html = QIL_Flash_Drop::section() . QIL_Stacks::section();
	ok( 'embedded JSON can never close its script tag or open markup', 2 === substr_count( $html, '</script>' ) && false === strpos( $html, '<script>alert' ) && false === strpos( $html, '<img src=x' ), substr_count( $html, '</script>' ) );
	$data = embedded_json( $html, 'data-qil-flash-data' );
	ok( 'the data still decodes to the real text', is_array( $data ) && 10 === count( $data['records'] ) );
	break;

case 'xss':
	$GLOBALS['qt']['products'][102]->name = '<img src=x onerror=alert(1)>Creatine';
	$GLOBALS['qt']['products'][301]->name = '"><script>alert(2)</script>';
	WC()->cart->add( 101, 1, 1011 );
	$GLOBALS['qt']['is_cart'] = true;
	$html = capture( 'woocommerce_mini_cart' ) . QIL_Boost::cart_panel_markup() . QIL_Boost::stack_band_markup() . QIL_Stacks::section() . QIL_Flash_Drop::section();
	ok( 'no product name can inject markup into any new surface', false === strpos( $html, '<img src=x' ) && false === strpos( $html, '<script>alert' ) && false === stripos( $html, 'onerror=alert' ), $html );
	ok( 'the product itself is still shown (tags stripped, text kept)', false !== strpos( $html, 'Creatine' ) );
	break;

case 'disabled':
	WC()->cart->add( 101, 1, 1011 );
	$html = capture( 'woocommerce_mini_cart' );
	ok( 'QIL_BOOST_DISABLE: mini cart is exactly WooCommerce\'s', false === strpos( $html, 'qil-boost' ) );
	ok( 'QIL_BOOST_DISABLE: no flash section, no member hub, no stacks', '' === QIL_Flash_Drop::section() && '' === QIL_Member::section() && '' === QIL_Stacks::section() );
	break;

case 'perf':
	for ( $i = 0; $i < 300; $i++ ) {
		$id = 20000 + $i;
		$variable = 0 === $i % 3;
		qt_product( $id, array( 'name' => 'Synthetic ' . $i, 'type' => $variable ? 'variable' : 'simple', 'regular_price' => (string) ( 2 + ( $i % 40 ) ), 'total_sales' => $i ), array( 10 + ( $i % 9 ) ) );
		if ( $variable ) { qt_variation( 30000 + $i, $id, (string) ( 3 + ( $i % 30 ) ), array( 'pa_flavour' => 'vanilla' ) ); }
	}
	$t = microtime( true ); $pool = QIL_Boost::pool(); $cold = ( microtime( true ) - $t ) * 1000;
	WC()->cart->add( 101, 1, 1011 );
	$t = microtime( true );
	for ( $i = 0; $i < 200; $i++ ) { WC()->cart->items[ array_key_first( WC()->cart->items ) ]['quantity'] = 1 + ( $i % 3 ); WC()->cart->calculate_totals(); capture( 'woocommerce_mini_cart' ); }
	$warm = ( microtime( true ) - $t ) * 1000 / 200;
	$metrics = array( 'pool_rows' => count( $pool ), 'pool_cold_build_ms' => round( $cold, 1 ), 'mini_cart_render_ms_avg' => round( $warm, 2 ) );
	ok( 'pool is bounded (≤ 280 rows)', count( $pool ) <= 280, count( $pool ) );
	ok( 'warm mini-cart render with ladder + picks stays well under 10 ms', $warm < 10, $warm );
	break;

default:
	ok( 'known scenario', false, $scenario );
}
} catch ( Throwable $e ) {
	ok( 'no exception', false, get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
}
echo json_encode( array( 'checks' => $checks, 'metrics' => $metrics ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
