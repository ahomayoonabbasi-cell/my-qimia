<?php
/**
 * Runs the real Qimia Intelligence Lab 1.18 code against the doubles.
 *   php tests/php/run.php [results.json]
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
set_error_handler( static function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

require __DIR__ . '/wp-doubles.php';
require __DIR__ . '/wc-doubles.php';
require __DIR__ . '/fixtures.php';

qt_reset();
$plugin = realpath( __DIR__ . '/../../qimia-intelligence-lab-final/qimia-intelligence-lab.php' );
require $plugin;
do_action( 'template_redirect' ); // Settles qil_render_mode() like a real front-end request.

$results = array( 'passed' => 0, 'failed' => 0, 'tests' => array() );
function check( $name, $condition, $detail = '' ) {
	global $results;
	$ok = (bool) $condition;
	$results[ $ok ? 'passed' : 'failed' ]++;
	$results['tests'][] = array( 'name' => $name, 'ok' => $ok, 'detail' => $ok ? '' : (string) $detail );
	echo ( $ok ? '  ✓ ' : '  ✗ ' ) . $name . ( $ok ? '' : '  → ' . $detail ) . "\n";
}
function section( $title ) { echo "\n" . $title . "\n"; }
function qt_isolated( $scenario ) {
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/scenario.php' ) . ' ' . escapeshellarg( $scenario );
	$out = shell_exec( $cmd . ' 2>&1' );
	$data = json_decode( (string) $out, true );
	if ( ! is_array( $data ) ) {
		return array( 'error' => trim( (string) $out ) );
	}
	return $data;
}

/* ------------------------------------------------------------------ */
section( 'Plugin load' );
check( 'plugin version is 1.18.1', defined( 'QIL_VERSION' ) && '1.18.1' === QIL_VERSION, defined( 'QIL_VERSION' ) ? QIL_VERSION : 'undefined' );
foreach ( array( 'QIL_Boost', 'QIL_Flash_Drop', 'QIL_Member', 'QIL_Stacks', 'QIL_Cashback', 'QIL_Personalization' ) as $class ) {
	check( "class $class loaded", class_exists( $class ) );
}
check( 'mini-cart ladder hook registered', has_filter( 'woocommerce_before_mini_cart' ) );
check( 'cart totals panel hook registered', has_filter( 'woocommerce_before_cart_totals' ) );
check( 'flash drop REST route registered after rest_api_init', ( do_action( 'rest_api_init' ) || true ) && isset( $GLOBALS['qt']['rest']['qimia-lab/v1/flash-drop'] ) );

/* ------------------------------------------------------------------ */
section( 'Scenarios (each in a clean PHP process)' );
$scenarios = array( 'ladder', 'ladder_sar:SAR:2', 'ladder_property:OMR:3', 'ladder_property:OMR:2', 'ladder_property:SAR:2', 'picks', 'minicart', 'minicart_ar', 'lang_ajax:/ar/', 'lang_ajax:/ar/cart/', 'lang_ajax:/', 'lang_ajax:/arabic-oils/', 'lang_ajax:/ar/:en', 'lang_ajax:/:ar', 'cartpage', 'fragments', 'flash', 'flash_contended', 'flash_last_scan', 'flash_budget', 'flash_stock_purge', 'upgrade_purge', 'flash_rotation', 'flash_soldout', 'member_wallet', 'member_wallet_strict', 'member_apply', 'member_running', 'member_ajax', 'stacks', 'admin', 'xss', 'xss_json', 'disabled', 'perf' );
foreach ( $scenarios as $scenario ) {
	$data = qt_isolated( $scenario );
	if ( isset( $data['error'] ) ) {
		check( "scenario $scenario ran", false, $data['error'] );
		continue;
	}
	foreach ( $data['checks'] as $row ) {
		check( "[$scenario] " . $row['name'], $row['ok'], $row['detail'] ?? '' );
	}
	if ( ! empty( $data['metrics'] ) ) {
		$results['metrics'][ $scenario ] = $data['metrics'];
	}
}

echo "\n" . $results['passed'] . ' passed, ' . $results['failed'] . " failed\n";
if ( ! empty( $argv[1] ) ) {
	file_put_contents( $argv[1], json_encode( array( 'version' => QIL_VERSION, 'php' => PHP_VERSION ) + $results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
}
exit( $results['failed'] ? 1 : 0 );
