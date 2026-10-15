<?php
/**
 * Qimia Beauty on the browser router (tests/browser/server.php).
 *
 * Loaded ONLY for a request that carries the cookie qt_beauty=1. Without that cookie the router
 * never reads this file and serves exactly what it served before.
 *
 * It turns the router's request into the staging request tests/php/beauty-qil-live.php runs in
 * the CLI, then lets the REAL Qimia Beauty plugin load next to the REAL Intelligence Lab:
 *  - host, home and siteurl are qimialab.qimia.om, the only host where Beauty's
 *    qby_stage_allowed() is true (so includes/experience.php loads), with qby_enabled on;
 *  - the same Beauty fixtures (401-407, 501), terms (30 beauty, 0 shampoo, 31, 32, tag 40) and
 *    the same doubles that file adds for what the shared WordPress/WooCommerce doubles lack
 *    (WooCommerce class, register_post_meta, register_rest_field, wp_script_add_data,
 *    wp_get_object_terms, a wpdb with get_results and term tables, terms that print their name,
 *    WC_Product::get_image, REST request/response with route and status);
 *  - WordPress fires plugins_loaded, init and wp before template_redirect, in core order.
 *
 * What a browser page needs on top of the CLI test, each kept as close to core as a double can be:
 *  - do_shortcode() with WooCommerce's [products] loop (WoodMart grid markup), which Beauty's
 *    qby_cards() uses as its server-rendered fallback before QIL's cards replace it;
 *  - REST dispatch in core order (rest_request_before_callbacks, callback,
 *    rest_request_after_callbacks, rest_post_dispatch), so Beauty's search adapter runs;
 *  - the QBYNavigation object Beauty passes to wp_localize_script(), which is a no-op double;
 *  - the /beauty/ page: a WoodMart page holding [qimia_beauty]. is_page() is always false in the
 *    shared doubles, so the two things Beauty keys on is_page('beauty') are done here: the
 *    experience assets and the qby-hub / qby-beauty-page body classes.
 * The shared doubles and both plugins are not modified.
 */

/* ------------------------------------------------------------------ PHP notices */
// The WP_Query double has no found_posts / max_num_pages (core declares both, default 0). Beauty's
// qby_beauty_query() reads them; that read gets core's default and nothing else is silenced.
set_error_handler( static function ( $severity, $message, $file ) {
	return E_WARNING === $severity && false !== strpos( str_replace( '\\', '/', (string) $file ), 'qimia-beauty/includes/department-data.php' )
		&& (bool) preg_match( '/^Undefined property: WP_Query::\$(found_posts|max_num_pages)$/', $message );
} );

/* ------------------------------------------------------------------ the staging request */
define( 'QTB_HOST', 'qimialab.qimia.om' );
$_SERVER['HTTP_HOST'] = QTB_HOST;
foreach ( array( 'HTTP_ORIGIN', 'HTTP_REFERER' ) as $qtb_header ) {
	if ( isset( $_SERVER[ $qtb_header ] ) ) { $_SERVER[ $qtb_header ] = str_replace( 'https://qimia.om', 'https://' . QTB_HOST, $_SERVER[ $qtb_header ] ); }
}
if ( ! empty( $GLOBALS['qt']['referer'] ) ) { $GLOBALS['qt']['referer'] = str_replace( 'https://qimia.om', 'https://' . QTB_HOST, $GLOBALS['qt']['referer'] ); }
$GLOBALS['qt']['options']['home']        = 'https://' . QTB_HOST;
$GLOBALS['qt']['options']['siteurl']     = 'https://' . QTB_HOST;
$GLOBALS['qt']['options']['qby_enabled'] = '1';

/* ------------------------------------------------------------------ doubles (as in beauty-qil-live.php) */
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
/** Core wpdb names its term tables and has get_results(); every answer stays the double's (no rows). */
class QTB_WPDB extends QT_WPDB {
	public $terms = 'wp_terms'; public $term_taxonomy = 'wp_term_taxonomy'; public $term_relationships = 'wp_term_relationships';
	public function get_results( $sql, $output = 'OBJECT' ) { return array(); }
}
$GLOBALS['wpdb'] = new QTB_WPDB();
/** wp_get_post_terms() in the doubles returns term objects where QIL's search scorer implodes names. */
class QTB_Term extends stdClass {
	public function __toString() { return (string) $this->name; }
}
/** Core WC_Product::get_image(); the product double lacks it. Beauty's home edit prints it. */
class QTB_Product extends WC_Product {
	public function get_image( $size = 'woocommerce_thumbnail', $attr = array(), $placeholder = true ) { return wp_get_attachment_image( $this->image_id, $size, false, $attr ); }
}
/** Core's WP_REST_Request knows its route and method; the shared double answers '' and GET. */
class QTB_REST_Request extends WP_REST_Request {
	private $qtb_route; private $qtb_method;
	public function __construct( $method = 'GET', $route = '' ) { parent::__construct( $method, $route ); $this->qtb_method = $method; $this->qtb_route = $route; }
	public function get_route() { return $this->qtb_route; }
	public function get_method() { return $this->qtb_method; }
}
/** Core's WP_REST_Response has a status and set_data(); the shared double has neither. */
class QTB_REST_Response extends WP_REST_Response {
	public $status = 200;
	public function get_status() { return $this->status; }
	public function set_status( $code ) { $this->status = (int) $code; }
	public function set_data( $data ) { $this->data = $data; }
	public function get_headers() { return $this->headers; }
}
function qtb_rest_response( $result ) {
	if ( $result instanceof QTB_REST_Response || $result instanceof WP_Error ) { return $result; }
	$response = new QTB_REST_Response( $result instanceof WP_REST_Response ? $result->data : $result );
	if ( $result instanceof WP_REST_Response ) { $response->headers = $result->headers; }
	return $response;
}
/** Core WP_REST_Server::dispatch() order for one registered GET route, after its permission check. */
function qtb_rest_dispatch( array $handler, WP_REST_Request $request ) {
	$early = apply_filters( 'rest_request_before_callbacks', null, $handler, $request );
	$response = null !== $early ? $early : call_user_func( $handler['callback'], $request );
	$response = apply_filters( 'rest_request_after_callbacks', qtb_rest_response( $response ), $handler, $request );
	return apply_filters( 'rest_post_dispatch', qtb_rest_response( rest_ensure_response( $response ) ), null, $request );
}
/** Translator memory double (as beauty-qil-live.php): Arabic titles already translated for two products. */
if ( ! class_exists( 'QAATM_Memory' ) ) {
	class QAATM_Memory {
		public static $rows = array( 'product_title' => array( 'Biotin Thickening Shampoo 250ml' => 'شامبو البيوتين المكثف 250 مل', 'Argan Repair Shampoo 400ml' => 'شامبو الأرغان المرمم 400 مل' ) );
		public static function bulk_resolve( array $segments, $queue_missing = false ) {
			return array_map( static function ( $s ) { $t = self::$rows[ $s['context'] ][ $s['source'] ] ?? ''; return '' === $t ? array( 'status' => 'missing' ) : array( 'status' => 'ready', 'target' => $t ); }, $segments );
		}
	}
}

/* ------------------------------------------------------------------ shortcodes */
if ( ! function_exists( 'shortcode_parse_atts' ) ) {
	function shortcode_parse_atts( $text ) {
		preg_match_all( '/([\w-]+)\s*=\s*"([^"]*)"/', (string) $text, $m, PREG_SET_ORDER );
		$atts = array();
		foreach ( $m as $row ) { $atts[ strtolower( $row[1] ) ] = $row[2]; }
		return $atts;
	}
}
if ( ! function_exists( 'do_shortcode' ) ) {
	/** Self-closing shortcodes only, with core's pre_do_shortcode_tag / do_shortcode_tag filters. */
	function do_shortcode( $content ) {
		return preg_replace_callback( '/\[([a-z0-9_-]+)([^\]]*)\]/i', static function ( $m ) {
			$tag  = strtolower( $m[1] );
			$atts = shortcode_parse_atts( $m[2] );
			$pre  = apply_filters( 'pre_do_shortcode_tag', false, $tag, $atts, $m );
			if ( false !== $pre ) { return $pre; }
			if ( 'products' === $tag ) { $out = qtb_products_shortcode( $atts ); }
			elseif ( isset( $GLOBALS['qt']['shortcodes'][ $tag ] ) ) { $out = call_user_func( $GLOBALS['qt']['shortcodes'][ $tag ], $atts, '', $tag ); }
			else { return $m[0]; }
			return apply_filters( 'do_shortcode_tag', $out, $tag, $atts, $m );
		}, (string) $content );
	}
}
/**
 * WooCommerce's [products ids="…"] loop as WoodMart prints it (a .wd-products grid of
 * .wd-product[data-id] > .product-wrapper cells), with the loop filters and title actions core fires.
 */
function qtb_products_shortcode( array $atts ) {
	$ids     = array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $atts['ids'] ?? '' ) ) ) ) );
	$columns = max( 1, (int) ( $atts['columns'] ?? 4 ) );
	$limit   = (int) ( $atts['limit'] ?? -1 );
	if ( $limit > 0 ) { $ids = array_slice( $ids, 0, $limit ); }
	$products = array_values( array_filter( array_map( 'wc_get_product', $ids ), static function ( $p ) { return $p && $p->is_visible(); } ) );
	if ( ! $products ) { return ''; }
	$previous = $GLOBALS['product'] ?? null;
	$html = '<div class="woocommerce columns-' . $columns . '">';
	$html .= apply_filters( 'woocommerce_product_loop_start', '<div class="products wd-products wd-grid-g grid-columns-' . $columns . ' elements-grid" data-columns="' . $columns . '" style="--wd-col-lg:' . $columns . ';--wd-col-md:' . min( 3, $columns ) . ';--wd-col-sm:2;--wd-gap-lg:20px;--wd-gap-sm:10px;">' );
	foreach ( $products as $index => $product ) {
		$GLOBALS['product'] = $product;
		$id = $product->get_id(); $url = get_permalink( $id ); $variable = 'variable' === $product->get_type();
		ob_start(); do_action( 'woocommerce_before_shop_loop_item_title' ); $before_title = ob_get_clean();
		ob_start(); do_action( 'woocommerce_after_shop_loop_item_title' ); $after_title = ob_get_clean();
		$price = $variable ? wc_format_price_range( $product->get_variation_price( 'min' ), $product->get_variation_price( 'max' ) ) : wc_price( $product->get_price() );
		$html .= '<div class="wd-product wd-hover-fw-button product-grid-item ' . esc_attr( implode( ' ', get_post_class( '', $id ) ) ) . ' ' . esc_attr( $product->get_stock_status() ) . '" data-loop="' . ( $index + 1 ) . '" data-id="' . $id . '">'
			. '<div class="product-wrapper"><div class="product-element-top wd-quick-shop"><a href="' . esc_url( $url ) . '" class="product-image-link">' . $product->get_image( 'woocommerce_thumbnail' ) . '</a>' . $before_title . '</div>'
			. '<div class="product-element-bottom"><h3 class="wd-entities-title"><a href="' . esc_url( $url ) . '">' . esc_html( $product->get_name() ) . '</a></h3>' . $after_title
			. '<span class="price">' . $price . '</span>'
			. '<div class="wd-add-btn wd-add-btn-replace"><a href="' . esc_url( $variable ? $url : $product->add_to_cart_url() ) . '" data-quantity="1" class="button product_type_' . esc_attr( $product->get_type() ) . ( $variable ? '' : ' add_to_cart_button ajax_add_to_cart' ) . ' add-to-cart-loop" data-product_id="' . $id . '" rel="nofollow"><span>' . esc_html( $product->add_to_cart_text() ) . '</span></a></div>'
			. '</div></div></div>';
	}
	$html .= apply_filters( 'woocommerce_product_loop_end', '</div>' ) . '</div>';
	$GLOBALS['product'] = $previous;
	return $html;
}

/* ------------------------------------------------------------------ fixtures (as in beauty-qil-live.php) */
function qtb_term( $term ) {
	$t = new QTB_Term();
	foreach ( get_object_vars( $term ) as $k => $v ) { $t->$k = $v; }
	return $t;
}
function qtb_product( $id, array $props, array $cats = array() ) {
	$p = new QTB_Product( $id );
	foreach ( get_object_vars( qt_product( $id, $props, $cats ) ) as $k => $v ) { $p->$k = $v; }
	return $GLOBALS['qt']['products'][ $id ] = $p;
}
// The Beauty root category. Department children are not created (see beauty-qil-live.php).
qt_term( 'product_cat', 30, 'beauty', 'Beauty & Personal Care' );
qtb_product( 401, array( 'name' => 'Repair Shampoo 250ml', 'regular_price' => '6.500', 'total_sales' => 45, 'short' => 'Repairing shampoo for dry hair.' ), array( 30 ) );
update_post_meta( 401, '_qby_product_class', 'beauty' );
update_post_meta( 401, '_qby_cosmetic_details', array( 'size' => '250 ml', 'inci' => 'Aqua, Sodium Laureth Sulfate, Glycerin', 'hair_type' => 'Dry hair', 'hair_type_ar' => 'شعر جاف', 'directions_en' => 'Apply to wet hair.' ) );
update_post_meta( 401, '_qimia_ai_box_structured', array( 'title_en' => 'Shampoo guide', 'en' => array( 'serving_summary' => 'One scoop daily', 'faq' => array( array( 'q' => 'When to take?', 'a' => 'After training.' ) ), 'who_for' => array( 'Athletes' ) ) ) );
update_post_meta( 401, '_qimia_supplement_facts_structured', array( 'serving_size_en' => '1 scoop', 'servings_en' => '30', 'total_content_en' => '250 ml', 'ingredients_en' => 'Aqua', 'rows' => array( array( 'label_en' => 'Protein', 'value' => '24 g', 'basis' => 'per_serving' ) ) ) );
qtb_product( 402, array( 'name' => 'Hydrating Face Serum 30ml', 'regular_price' => '9.900', 'total_sales' => 30 ), array( 30 ) );
qtb_product( 403, array( 'name' => 'Curl Defining Cream 200ml', 'regular_price' => '7.200', 'total_sales' => 12, 'stock_status' => 'outofstock' ), array( 30 ) );
update_post_meta( 403, '_qby_product_class', 'beauty' );
update_post_meta( 102, '_qimia_ai_box_structured', array( 'title_en' => 'Creatine guide', 'en' => array( 'serving_summary' => 'Mix one scoop (5 g) with water daily.', 'faq' => array( array( 'q' => 'Loading phase?', 'a' => 'Not required.' ) ) ) ) );
update_post_meta( 102, '_qimia_supplement_facts_structured', array( 'serving_size_en' => '5 g', 'servings_en' => '60', 'ingredients_en' => 'Creatine monohydrate' ) );
// The WP_Query double compares tax_query terms with intval(), so a slug clause such as Beauty's
// product_cat 'shampoo' (or 'beauty') resolves to term 0: Beauty's real queries find 404-406.
qt_term( 'product_cat', 0, 'shampoo', 'Shampoo' );
qt_term( 'product_cat', 31, 'hair-nail-skin-health', 'Hair, Skin & Nails' );
qt_term( 'product_cat', 32, 'beauty-tools', 'Beauty tools', 30 );
qt_term( 'product_tag', 40, 'biotin', 'Biotin' );
qtb_product( 404, array( 'name' => 'Biotin Thickening Shampoo 250ml', 'regular_price' => '5.500', 'total_sales' => 2 ), array( 30, 0 ) );
update_post_meta( 404, '_qby_product_class', 'beauty' );
$GLOBALS['qt']['rel'][404]['product_tag'] = array( 40 );
qtb_product( 405, array( 'name' => 'Argan Repair Shampoo 400ml ', 'regular_price' => '8.900', 'total_sales' => 300 ), array( 30, 0 ) );
update_post_meta( 405, '_qby_product_class', 'beauty' );
qtb_product( 406, array( 'name' => 'Silk Gloss Shampoo', 'type' => 'variable', 'total_sales' => 5 ), array( 30, 0 ) );
update_post_meta( 406, '_qby_product_class', 'beauty' );
qt_variation( 4061, 406, '6.000', array( 'pa_size' => '250ml' ), array( 'stock_status' => 'outofstock' ) );
qt_variation( 4062, 406, '9.000', array( 'pa_size' => '500ml' ), array( 'stock_status' => 'outofstock' ) );
qtb_product( 407, array( 'name' => 'Detangling Paddle Brush', 'regular_price' => '3.500', 'total_sales' => 9 ), array( 32 ) );
update_post_meta( 407, '_qby_product_class', 'accessory' );
qt_product( 501, array( 'name' => 'Biotin Hair Gummies', 'regular_price' => '6.900', 'total_sales' => 80 ), array( 31 ) );
foreach ( $GLOBALS['qt']['terms'] as $qtb_tax => $qtb_rows ) {
	foreach ( $qtb_rows as $qtb_id => $qtb_row ) { $GLOBALS['qt']['terms'][ $qtb_tax ][ $qtb_id ] = qtb_term( $qtb_row ); }
}

/* ------------------------------------------------------------------ the plugin */
/** After QIL is required: Beauty, then WordPress's lifecycle hooks in core order. */
function qtb_load_plugin() {
	require realpath( __DIR__ . '/../../qimia-beauty/qimia-beauty.php' );
	do_action( 'plugins_loaded' );
	do_action( 'init' );
	do_action( 'wp' );
	// wp_localize_script() is a no-op double: print the object Beauty's storefront.php localizes
	// for 'qimia-beauty' (same keys and values, from Beauty's own functions), before its script.
	add_action( 'wp_enqueue_scripts', static function () {
		if ( ! wp_script_is( 'qimia-beauty' ) || ! function_exists( 'qby_browse_url' ) ) { return; }
		$data = array( 'brandCountLabel' => qby_t( 'brands', 'علامات' ), 'categoryLabel' => qby_t( 'Explore categories', 'اكتشف الفئات' ), 'searchLinks' => array( array( 'url' => qby_browse_url( 'hair-care' ), 'label' => qby_t( 'Hair care', 'العناية بالشعر' ) ), array( 'url' => qby_term_url( 'hair-nail-skin-health' ), 'label' => qby_t( 'Hair supplements', 'مكملات الشعر' ) ) ) );
		wp_add_inline_script( 'qimia-beauty', 'var QBYNavigation = ' . wp_json_encode( $data ) . ';' );
	}, 111 );
	// On the /beauty/ page Beauty enqueues its experience assets for is_page('beauty').
	add_action( 'wp_enqueue_scripts', static function () {
		if ( empty( $GLOBALS['qt']['is_beauty_page'] ) || wp_style_is( 'qimia-beauty-experience' ) || ! defined( 'QBY_URL' ) ) { return; }
		wp_enqueue_style( 'qimia-beauty-experience', QBY_URL . 'assets/experience.min.css', array( 'qimia-beauty' ), QBY_VERSION );
		wp_enqueue_script( 'qimia-beauty-experience', QBY_URL . 'assets/experience.min.js', array( 'qimia-beauty' ), QBY_VERSION, true );
	}, 111 );
}

/** The router's output rewrite for the staging host (the page links to https://qimialab.qimia.om). */
function qtb_rewrite_host( $html, $origin ) {
	return str_replace( array( 'https://' . QTB_HOST, 'https:\/\/' . QTB_HOST ), array( $origin, str_replace( '/', '\/', $origin ) ), $html );
}

/**
 * The Beauty page (/beauty/ or /ar/beauty/): a WoodMart page whose content is [qimia_beauty],
 * inside QIL's chrome (wp_body_open) and with wp_footer fired, where Beauty prints QIL's docks.
 */
function qtb_render_beauty_page() {
	$ar = ! empty( $GLOBALS['ar'] );
	?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo $ar ? 'الجمال والعناية – كيميا' : 'Beauty – Qimia'; ?></title><?php wp_head(); ?>
<style>body{margin:0;background:#fff}.main-page-wrapper{padding-top:30px}.wd-content-layout.container{width:100%;max-width:1222px;margin:0 auto;padding-inline:15px;box-sizing:border-box}.entry-content>*:last-child{margin-bottom:0}</style>
</head><body <?php body_class( 'page-template-default page wd-page qby-hub qby-beauty-page' ); ?>><?php wp_body_open(); ?>
<div class="wd-page-wrapper website-wrapper"><div class="wd-page-content main-page-wrapper"><main class="wd-content-layout content-layout-wrapper container" role="main"><div class="wd-content-area site-content"><article class="page type-page status-publish entry-content"><?php echo do_shortcode( '[qimia_beauty]' ); ?></article></div></main></div></div>
<div class="cart-widget-side wd-side-hidden wd-right"><div class="widget woocommerce widget_shopping_cart"><div class="widget_shopping_cart_content"><?php woocommerce_mini_cart(); ?></div></div></div><div class="wd-close-side wd-fill"></div>
<?php do_action( 'wp_footer' ); wp_footer(); ?></body></html>
<?php
}
