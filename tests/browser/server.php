<?php
/**
 * PHP built-in server router for the Chromium checks:
 *   php -S 127.0.0.1:8765 tests/browser/server.php
 * Renders the REAL homepage template, mini cart and a classic cart page from
 * the real plugin (over the WordPress/WooCommerce doubles), serves the real
 * CSS/JS and answers the plugin's real wc-ajax endpoints. Cart, applied
 * coupons and the signed-in user persist in cookies between requests.
 */
$uri  = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$root = realpath( __DIR__ . '/../../qimia-intelligence-lab-final' );

/* ---- static files ---- */
if ( preg_match( '#^/wp-content/plugins/qimia-intelligence-lab-final/(assets/[A-Za-z0-9._-]+)$#', $uri, $m ) && is_file( $root . '/' . $m[1] ) ) {
	$types = array( 'css' => 'text/css', 'js' => 'application/javascript', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml' );
	header( 'Content-Type: ' . ( $types[ pathinfo( $m[1], PATHINFO_EXTENSION ) ] ?? 'application/octet-stream' ) );
	readfile( $root . '/' . $m[1] );
	return true;
}
if ( '/vendor/jquery.js' === $uri ) { header( 'Content-Type: application/javascript' ); readfile( __DIR__ . '/../../node_modules/jquery/dist/jquery.min.js' ); return true; }
if ( '/vendor/wc-stand-in.js' === $uri ) { header( 'Content-Type: application/javascript' ); readfile( __DIR__ . '/wc-stand-in.js' ); return true; }
if ( '/vendor/woodmart-stand-in.css' === $uri ) { header( 'Content-Type: text/css' ); readfile( __DIR__ . '/woodmart-stand-in.css' ); return true; }
if ( preg_match( '#^/wp-content/uploads/p(\d+)-(\d+)\.webp$#', $uri, $m ) ) {
	// Product packshot placeholder: a jar with the product number.
	$hue = ( (int) $m[1] * 47 ) % 360;
	header( 'Content-Type: image/svg+xml' );
	echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="hsl(' . $hue . ',55%,38%)"/><stop offset="1" stop-color="hsl(' . ( ( $hue + 30 ) % 360 ) . ',60%,22%)"/></linearGradient></defs><rect x="95" y="46" width="110" height="26" rx="8" fill="#20313a"/><rect x="80" y="70" width="140" height="190" rx="26" fill="url(#g)"/><rect x="92" y="120" width="116" height="78" rx="10" fill="#fff" opacity=".92"/><text x="150" y="168" font-family="Arial" font-size="26" font-weight="700" text-anchor="middle" fill="#0b2b36">#' . (int) $m[1] . '</text></svg>';
	return true;
}
if ( 0 === strpos( $uri, '/wp-content/' ) || '/favicon.ico' === $uri ) { http_response_code( 404 ); return true; }

/* ---- WordPress request ---- */
$origin = ( isset( $_SERVER['HTTPS'] ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'];
// The doubles reset superglobals for test isolation; keep the real request.
$request = array( 'get' => $_GET, 'post' => $_POST, 'server' => $_SERVER );
require __DIR__ . '/../php/wp-doubles.php';
require __DIR__ . '/../php/wc-doubles.php';
require __DIR__ . '/../php/fixtures.php';
qt_fixtures();
$_SERVER  = array_merge( $_SERVER, $request['server'] );
$_GET     = $request['get'];
$_POST    = $request['post'];
$_REQUEST = array_merge( $_GET, $_POST );
$_SERVER['REQUEST_URI']    = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTP_HOST']      = 'qimia.om';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) { $_SERVER['HTTP_ORIGIN'] = str_replace( $origin, 'https://qimia.om', $_SERVER['HTTP_ORIGIN'] ); }
if ( isset( $_SERVER['HTTP_REFERER'] ) ) { $_SERVER['HTTP_REFERER'] = $GLOBALS['qt']['referer'] = str_replace( $origin, 'https://qimia.om', $_SERVER['HTTP_REFERER'] ); }
$GLOBALS['qt']['user'] = (int) ( $_COOKIE['qt_user'] ?? 0 );
if ( ! empty( $_COOKIE['qt_currency'] ) ) { $GLOBALS['qt']['currency'] = preg_replace( '/[^A-Z]/', '', $_COOKIE['qt_currency'] ); $GLOBALS['qt']['decimals'] = 'OMR' === $GLOBALS['qt']['currency'] ? 3 : 2; }
$ar = (bool) preg_match( '#^/ar(/|$)#', $uri );

/* Browser-only template doubles. */
function language_attributes() { global $ar; echo $ar ? 'dir="rtl" lang="ar"' : 'lang="en"'; }
function bloginfo( $k ) { echo 'charset' === $k ? 'UTF-8' : 'Qimia'; }
function body_class( $c = '' ) { echo 'class="' . esc_attr( implode( ' ', apply_filters( 'body_class', array_filter( array( $c ) ) ) ) ) . '"'; }
function wp_body_open() { do_action( 'wp_body_open' ); }
function qt_assets( $footer ) {
	$out = '';
	if ( ! $footer ) {
		foreach ( $GLOBALS['qt']['enqueued']['style'] ?? array() as $handle => $src ) { if ( $src ) { $out .= '<link rel="stylesheet" id="' . esc_attr( $handle ) . '" href="' . esc_attr( $src ) . '">' . "\n"; } }
		// The theme's CSS arrives last, as WoodMart enqueues late.
		return $out . '<link rel="stylesheet" id="woodmart-stand-in" href="/vendor/woodmart-stand-in.css">' . "\n";
	}
	$out .= '<script src="/vendor/jquery.js"></script><script src="/vendor/wc-stand-in.js"></script>' . "\n";
	foreach ( $GLOBALS['qt']['enqueued']['script'] ?? array() as $handle => $src ) {
		foreach ( $GLOBALS['qt']['inline'][ $handle ] ?? array() as $code ) { $out .= '<script>' . $code . '</script>' . "\n"; }
		if ( $src ) { $out .= '<script src="' . esc_attr( $src ) . '"></script>' . "\n"; }
	}
	return $out;
}
function wp_head() { do_action( 'wp_enqueue_scripts' ); echo qt_assets( false ); }
function wp_footer() { echo qt_assets( true ); }
function qt_cart_from_cookies() {
	foreach ( array_filter( explode( ',', (string) ( $_COOKIE['qt_cart'] ?? '' ) ) ) as $row ) {
		list( $pid, $vid, $qty ) = array_map( 'intval', array_pad( explode( ':', $row ), 3, 1 ) );
		if ( $pid ) { WC()->cart->add( $pid, max( 1, $qty ), $vid ); }
	}
	foreach ( array_filter( explode( ',', (string) ( $_COOKIE['qt_coupons'] ?? '' ) ) ) as $code ) { WC()->cart->applied[] = strtolower( $code ); }
	if ( ! empty( $_COOKIE['qt_pending'] ) ) { WC()->session->set( 'qil_wallet_pending', array( 'id' => (int) $_COOKIE['qt_pending'], 'at' => time() ) ); }
	WC()->cart->calculate_totals();
}
function qt_persist_cart() {
	$rows = array();
	foreach ( WC()->cart->get_cart() as $line ) { $rows[] = $line['product_id'] . ':' . $line['variation_id'] . ':' . $line['quantity']; }
	setcookie( 'qt_cart', implode( ',', $rows ), 0, '/' );
	setcookie( 'qt_coupons', implode( ',', WC()->cart->applied ), 0, '/' );
	$pending = WC()->session->get( 'qil_wallet_pending' );
	setcookie( 'qt_pending', is_array( $pending ) ? (string) $pending['id'] : '', 0, '/' );
}

$endpoint = isset( $_GET['wc-ajax'] ) ? preg_replace( '/[^a-z0-9_]/', '', $_GET['wc-ajax'] ) : '';
if ( $ar ) { $_SERVER['REQUEST_URI'] = '/ar' . ( substr( $uri, 3 ) ?: '/' ); }
if ( $endpoint ) { $GLOBALS['qt']['render'] = 'none'; }
elseif ( '/cart/' === $uri || '/ar/cart/' === $uri ) { $GLOBALS['qt']['render'] = 'chrome'; $GLOBALS['qt']['is_cart'] = true; }
else { $GLOBALS['qt']['render'] = 'full'; }

require $root . '/qimia-intelligence-lab.php';
qt_cart_from_cookies();
do_action( 'template_redirect' );
do_action( 'rest_api_init' );

ob_start( static function ( $html ) use ( $origin ) { return str_replace( array( 'https://qimia.om', 'https:\/\/qimia.om' ), array( $origin, str_replace( '/', '\/', $origin ) ), $html ); } );

/* REST: the plugin's own routes (quick view, flash feed). */
if ( preg_match( '#^/wp-json/(qimia-lab/v1/[a-z-]+)(?:/(\d+))?#', $uri, $m ) ) {
	header( 'Content-Type: application/json' );
	$route = $GLOBALS['qt']['rest'][ $m[1] . ( isset( $m[2] ) ? '/(?P<id>\d+)' : '' ) ] ?? null;
	if ( ! $route || ! call_user_func( $route['permission_callback'] ) ) { http_response_code( 404 ); echo '{}'; return true; }
	$request = new WP_REST_Request();
	foreach ( $_GET as $k => $v ) { $request->set_param( $k, $v ); }
	if ( isset( $m[2] ) ) { $request->set_param( 'id', (int) $m[2] ); }
	$response = call_user_func( $route['callback'], $request );
	if ( is_wp_error( $response ) ) { http_response_code( 404 ); echo json_encode( array( 'code' => $response->get_error_code() ) ); return true; }
	echo json_encode( $response instanceof WP_REST_Response ? $response->get_data() : $response );
	return true;
}

/* WooCommerce's get_variation: the variation matching the chosen attributes. */
if ( 'get_variation' === $endpoint ) {
	header( 'Content-Type: application/json' );
	$parent = wc_get_product( (int) ( $_POST['product_id'] ?? 0 ) );
	foreach ( $parent ? $parent->kids() : array() as $variation ) {
		$match = true;
		foreach ( $variation->attrs as $name => $value ) {
			if ( ( $_POST[ 'attribute_' . $name ] ?? '' ) !== $value ) { $match = false; }
		}
		if ( $match ) {
			echo json_encode( apply_filters( 'woocommerce_available_variation', array(
				'variation_id' => $variation->id, 'variation_is_active' => true, 'variation_is_visible' => true, 'is_purchasable' => true,
				'is_in_stock' => 'instock' === $variation->stock_status, 'display_price' => (float) $variation->get_price(), 'display_regular_price' => (float) $variation->get_regular_price(),
				'sku' => $variation->sku, 'availability_html' => '', 'image' => array( 'src' => '', 'srcset' => '' ),
			), $parent, $variation ) );
			return true;
		}
	}
	echo 'false';
	return true;
}

if ( $endpoint ) {
	$GLOBALS['qt']['ajax'] = true;
	header( 'Content-Type: application/json' );
	$GLOBALS['qt']['json_exit'] = static function ( $data, $status ) {
		qt_persist_cart();
		http_response_code( $status );
		echo json_encode( $data );
	};
	try {
		if ( 'add_to_cart' === $endpoint ) {
			$added = wc_get_product( (int) $_POST['product_id'] );
			$is_variation = $added && 'variation' === $added->type;
			WC()->cart->add( $is_variation ? $added->parent_id : (int) $_POST['product_id'], max( 1, (int) ( $_POST['quantity'] ?? 1 ) ), $is_variation ? $added->id : 0 );
			do_action( 'woocommerce_add_to_cart' );
			qt_persist_cart();
		}
		if ( in_array( $endpoint, array( 'add_to_cart', 'get_refreshed_fragments' ), true ) ) {
			ob_start(); woocommerce_mini_cart(); $mini = ob_get_clean();
			echo json_encode( array( 'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini . '</div>' ) ), 'cart_hash' => WC()->cart->get_cart_hash() ) );
			return true;
		}
		do_action( 'wc_ajax_' . $endpoint );
		echo json_encode( array( 'error' => true, 'message' => 'no handler' ) );
	} catch ( QT_Json_Response $response ) {
		qt_persist_cart();
		http_response_code( $response->status );
		echo json_encode( $response->data );
	}
	return true;
}

if ( 'full' === $GLOBALS['qt']['render'] ) {
	include $root . '/templates/home.php';
	return true;
}

/* A classic WooCommerce cart page inside a theme page, with the Qimia chrome. */
?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?>
<style>body{margin:0;font-family:Inter,Arial,sans-serif;background:#fff;color:#1b2b33}.qt-page{max-width:1180px;margin:30px auto;padding:0 16px}.qt-cart{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:28px}.shop_table{width:100%;border-collapse:collapse}.shop_table td,.shop_table th{padding:12px;border-bottom:1px solid #e4ecea;text-align:start}.cart_totals{padding:20px;border:1px solid #e4ecea;border-radius:12px}.cart_totals h2{font-size:18px}.checkout-button{display:block;margin-top:14px;padding:14px;border-radius:8px;background:#1b2b33;color:#fff;text-align:center;text-decoration:none}@media (max-width:860px){.qt-cart{grid-template-columns:1fr}}</style>
</head><body <?php body_class(); ?>><?php wp_body_open(); ?>
<main class="qt-page woocommerce"><h1><?php echo $ar ? 'سلة التسوق' : 'Cart'; ?></h1>
<?php if ( WC()->cart->is_empty() ) : ?><p class="cart-empty wc-empty-cart-message">Your cart is currently empty.</p><?php else : ?>
<div class="qt-cart"><form class="woocommerce-cart-form" action="/cart/" method="post"><?php do_action( 'woocommerce_before_cart_table' ); ?><table class="shop_table cart"><thead><tr><th>Product</th><th>Price</th><th>Qty</th></tr></thead><tbody>
<?php foreach ( WC()->cart->get_cart() as $line ) : ?><tr class="cart_item"><td><?php echo esc_html( $line['data']->get_name() ); ?></td><td><?php echo wc_price( $line['data']->get_price() ); ?></td><td><?php echo (int) $line['quantity']; ?></td></tr><?php endforeach; ?>
</tbody></table><?php do_action( 'woocommerce_after_cart_table' ); ?></form>
<div class="cart-collaterals"><div class="cart_totals"><?php do_action( 'woocommerce_before_cart_totals' ); ?><h2>Cart totals</h2><p>Total: <strong><?php echo wc_price( WC()->cart->get_total( 'edit' ) ); ?></strong></p><a class="checkout-button" href="#">Proceed to checkout</a></div></div></div>
<?php do_action( 'woocommerce_after_cart' ); ?>
<?php endif; ?></main>
<div class="cart-widget-side wd-side-hidden wd-right"><div class="widget woocommerce widget_shopping_cart"><div class="widget_shopping_cart_content"><?php woocommerce_mini_cart(); ?></div></div></div><div class="wd-close-side wd-fill"></div>
<?php wp_footer(); ?></body></html>
<?php
return true;
