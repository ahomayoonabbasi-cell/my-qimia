<?php
/**
 * WooCommerce + cashback issuer doubles (see wp-doubles.php).
 */

class WC_DateTime extends DateTimeImmutable {}
function qt_date( $ts ) { return $ts ? ( new WC_DateTime( '@' . (int) $ts ) ) : null; }

class WC_Product {
	public $id; public $name = 'Product'; public $type = 'simple'; public $status = 'publish'; public $visible = true;
	public $purchasable = true; public $stock_status = 'instock'; public $manage_stock = false; public $stock_qty = null;
	public $image_id = 0; public $regular_price = ''; public $sale_price = ''; public $sale_to = null; public $sku = '';
	public $total_sales = 0; public $parent_id = 0; public $children = array(); public $attrs = array();
	public $default_attrs = array(); public $short = ''; public $desc = ''; public $featured = false; public $individual = false;
	public $cross = array(); public $product_attributes = array();
	public function __construct( $id = 0 ) { $this->id = (int) $id; }
	public function get_id() { return $this->id; }
	public function get_name() { return $this->name; }
	public function get_title() { return $this->name; }
	public function get_type() { return $this->type; }
	public function is_type( $t ) { return in_array( $this->type, (array) $t, true ); }
	public function get_status() { return $this->status; }
	public function exists() { return true; }
	public function is_visible() { return $this->visible && 'publish' === $this->status; }
	public function get_parent_id() { return $this->parent_id; }
	public function get_sku() { return $this->sku; }
	public function get_image_id() { return $this->image_id; }
	public function get_total_sales() { return $this->total_sales; }
	public function get_short_description() { return $this->short; }
	public function get_description() { return $this->desc; }
	public function is_featured() { return $this->featured; }
	public function is_sold_individually() { return $this->individual; }
	public function get_cross_sell_ids() { return $this->cross; }
	public function get_average_rating() { return 0; }
	public function get_rating_count() { return 0; }
	public function backorders_allowed() { return false; }
	public function get_max_purchase_quantity() { return -1; }
	public function get_meta( $k, $single = true ) { return get_post_meta( $this->id, $k, true ); }
	public function get_permalink() { return get_permalink( $this->parent_id ?: $this->id ); }
	public function supports( $f ) { return 'ajax_add_to_cart' === $f && 'simple' === $this->type; }
	public function add_to_cart_text() { return 'variable' === $this->type ? 'Select options' : 'Add to cart'; }
	public function add_to_cart_description() { return 'Add “' . $this->name . '” to your cart'; }
	public function add_to_cart_url() { return '?add-to-cart=' . $this->id; }
	public function managing_stock() { return $this->manage_stock; }
	public function get_stock_quantity() { return $this->manage_stock ? $this->stock_qty : null; }
	public function get_stock_status() {
		if ( 'variable' === $this->type ) { foreach ( $this->kids() as $k ) { if ( 'instock' === $k->stock_status ) { return 'instock'; } } return 'outofstock'; }
		return $this->stock_status;
	}
	public function is_in_stock() { return 'instock' === $this->get_stock_status(); }
	public function has_enough_stock( $q ) { return ! $this->manage_stock || null === $this->stock_qty || $this->stock_qty >= $q; }
	public function is_purchasable() { return $this->purchasable && ( 'variable' !== $this->type || (bool) $this->kids() ); }
	public function get_date_on_sale_to( $c = 'view' ) { return qt_date( $this->sale_to ); }
	public function is_on_sale( $c = 'view' ) {
		if ( 'variable' === $this->type ) { foreach ( $this->kids() as $k ) { if ( $k->is_on_sale() ) { return true; } } return false; }
		return '' !== (string) $this->sale_price && (float) $this->sale_price < (float) $this->regular_price && ( ! $this->sale_to || $this->sale_to > time() );
	}
	public function get_regular_price( $c = 'view' ) { return (string) $this->regular_price; }
	public function get_sale_price( $c = 'view' ) { return $this->is_on_sale() ? (string) $this->sale_price : ''; }
	public function get_price( $c = 'view' ) {
		if ( 'variable' === $this->type ) { $p = $this->prices( 'price' ); return $p ? (string) min( $p ) : ''; }
		return $this->is_on_sale() ? (string) $this->sale_price : (string) $this->regular_price;
	}
	/** Visible, published children as objects. */
	public function kids() { return array_values( array_filter( array_map( 'wc_get_product', $this->children ), static function ( $k ) { return $k && 'publish' === $k->status; } ) ); }
	private function prices( $which ) { $out = array(); foreach ( $this->kids() as $k ) { if ( 'instock' !== $k->stock_status ) { continue; } $out[] = (float) ( 'regular' === $which ? $k->get_regular_price() : $k->get_price() ); } return $out; }
	public function get_variation_price( $m = 'min', $display = false ) { $p = $this->prices( 'price' ); return $p ? ( 'min' === $m ? min( $p ) : max( $p ) ) : 0; }
	public function get_variation_regular_price( $m = 'min', $display = false ) { $p = $this->prices( 'regular' ); return $p ? ( 'min' === $m ? min( $p ) : max( $p ) ) : 0; }
	public function get_children() { return $this->children; }
	public function get_visible_children() { return array_map( static function ( $k ) { return $k->id; }, $this->kids() ); }
	public function get_variation_attributes( $with_prefix = true ) {
		if ( 'variation' === $this->type ) { $out = array(); foreach ( $this->attrs as $k => $v ) { $out[ 'attribute_' . $k ] = $v; } return $out; }
		$out = array();
		foreach ( $this->kids() as $k ) { foreach ( $k->attrs as $name => $value ) { $out[ $name ][] = $value; } }
		return array_map( 'array_values', array_map( 'array_unique', $out ) );
	}
	public function get_default_attributes() { return $this->default_attrs; }
	public function variation_is_active() { return true; }
	public function variation_is_visible() { return 'publish' === $this->status; }
	public function get_attributes() { return array(); }
	public function get_attribute( $name ) { return (string) ( $this->product_attributes[ $name ] ?? '' ); }
}

function wc_get_product( $id = false ) {
	if ( is_object( $id ) ) { return $id; }
	return $GLOBALS['qt']['products'][ (int) $id ] ?? false;
}
function wc_get_products( $args = array() ) {
	$ids = array();
	foreach ( $GLOBALS['qt']['products'] as $id => $p ) {
		if ( 'variation' === $p->type ) { continue; }
		if ( ! empty( $args['include'] ) && ! in_array( $id, array_map( 'intval', (array) $args['include'] ), true ) ) { continue; }
		if ( ( $args['status'] ?? 'publish' ) !== $p->status ) { continue; }
		if ( ! empty( $args['stock_status'] ) && $p->get_stock_status() !== $args['stock_status'] ) { continue; }
		if ( 'visible' === ( $args['visibility'] ?? '' ) && ! $p->visible ) { continue; }
		if ( ! empty( $args['category'] ) ) {
			$slugs = array_map( static function ( $t ) { return $GLOBALS['qt']['terms']['product_cat'][ $t ]->slug; }, (array) ( $GLOBALS['qt']['rel'][ $id ]['product_cat'] ?? array() ) );
			if ( ! array_intersect( $slugs, (array) $args['category'] ) ) { continue; }
		}
		$ids[] = $id;
	}
	if ( 'include' === ( $args['orderby'] ?? '' ) && ! empty( $args['include'] ) ) { $order = array_flip( array_map( 'intval', $args['include'] ) ); usort( $ids, static function ( $a, $b ) use ( $order ) { return $order[ $a ] <=> $order[ $b ]; } ); }
	else { rsort( $ids ); }
	$limit = (int) ( $args['limit'] ?? 10 );
	if ( $limit > 0 ) { $ids = array_slice( $ids, 0, $limit ); }
	if ( ! empty( $args['paginate'] ) ) { return (object) array( 'products' => $ids, 'total' => count( $ids ) ); }
	return 'ids' === ( $args['return'] ?? 'objects' ) ? $ids : array_map( 'wc_get_product', $ids );
}
function wc_get_price_to_display( $product, $args = array() ) { return isset( $args['price'] ) ? (float) $args['price'] : (float) $product->get_price(); }
function wc_get_price_decimals() { return (int) $GLOBALS['qt']['decimals']; }
function wc_prices_include_tax() { return true; }
function get_woocommerce_currency() { return $GLOBALS['qt']['currency']; }
function get_woocommerce_currency_symbol( $c = '' ) { $m = array( 'OMR' => 'ر.ع.', 'SAR' => 'ر.س', 'AED' => 'د.إ', 'USD' => '$' ); return $m[ $c ?: get_woocommerce_currency() ] ?? $c; }
function wc_price( $amount, $args = array() ) {
	$currency = $args['currency'] ?? get_woocommerce_currency();
	$decimals = $args['decimals'] ?? wc_get_price_decimals();
	return '<span class="woocommerce-Price-amount amount"><bdi>' . number_format( (float) $amount, (int) $decimals, '.', ',' ) . '&nbsp;<span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol( $currency ) . '</span></bdi></span>';
}
function wc_format_price_range( $a, $b ) { return wc_price( $a ) . ' – ' . wc_price( $b ); }
function wc_get_cart_url() { return 'https://qimia.om/cart/'; }
function wc_get_checkout_url() { return 'https://qimia.om/checkout/'; }
function wc_get_page_permalink( $p ) { return 'https://qimia.om/' . $p . '/'; }
function wc_get_account_endpoint_url( $endpoint ) { return 'https://qimia.om/my-account/' . $endpoint . '/'; }
function wc_get_page_id( $p ) { return 5; }
function wc_variation_attribute_name( $n ) { return 'attribute_' . sanitize_title( $n ); }
function wc_attribute_label( $n, $p = null ) { return ucfirst( str_replace( array( 'pa_', 'attribute_' ), '', $n ) ); }
function wc_placeholder_img_src( $s = '' ) { return 'https://qimia.om/placeholder.png'; }
function wc_get_product_visibility_term_ids() { return array( 'exclude-from-catalog' => 901, 'exclude-from-search' => 902, 'outofstock' => 903 ); }
function wc_load_cart() {}
function wc_add_notice( $m, $type = 'success' ) { $GLOBALS['qt_session']->data['wc_notices'][ $type ][] = array( 'notice' => $m ); }
function wc_get_notices( $type = '' ) { $n = $GLOBALS['qt_session']->get( 'wc_notices', array() ); return '' === $type ? $n : ( $n[ $type ] ?? array() ); }
function wc_clear_notices() { $GLOBALS['qt_session']->__unset( 'wc_notices' ); }
function woocommerce_mini_cart() {
	do_action( 'woocommerce_before_mini_cart' );
	echo '<div class="shopping-cart-widget-body wd-scroll"><div class="wd-scroll-content">';
	if ( ! WC()->cart->is_empty() ) {
		echo '<ul class="cart_list product_list_widget woocommerce-mini-cart">';
		do_action( 'woocommerce_before_mini_cart_contents' );
		foreach ( WC()->cart->get_cart() as $key => $line ) {
			echo '<li class="woocommerce-mini-cart-item mini_cart_item"><a href="#" class="remove" data-cart_item_key="' . esc_attr( $key ) . '">×</a><span class="wd-entities-title">' . esc_html( $line['data']->get_name() ) . '</span><span class="quantity">' . (int) $line['quantity'] . ' × ' . wc_price( $line['data']->get_price() ) . '</span></li>';
		}
		do_action( 'woocommerce_mini_cart_contents' );
		echo '</ul>';
	} else {
		echo '<p class="woocommerce-mini-cart__empty-message">No products in the cart.</p>';
	}
	echo '</div></div><div class="shopping-cart-widget-footer">';
	if ( ! WC()->cart->is_empty() ) {
		echo '<p class="woocommerce-mini-cart__total total"><strong>Subtotal:</strong> ' . wc_price( WC()->cart->subtotal() ) . '</p>';
		do_action( 'woocommerce_widget_shopping_cart_before_buttons' );
		echo '<p class="woocommerce-mini-cart__buttons buttons"><a href="#" class="button">View cart</a><a href="#" class="button checkout">Checkout</a></p>';
	}
	do_action( 'woocommerce_after_mini_cart' );
	echo '</div>';
}

class WC_Cart {
	public $items = array(); public $applied = array(); public $total = 0.0; public $shipping = 0.0; public $shipping_tax = 0.0;
	public function __construct() {}
	public function add( $product_id, $qty = 1, $variation_id = 0 ) {
		$p   = wc_get_product( $variation_id ?: $product_id );
		$key = md5( $product_id . '|' . $variation_id );
		$this->items[ $key ] = array( 'key' => $key, 'product_id' => $product_id, 'variation_id' => $variation_id, 'quantity' => $qty, 'data' => $p );
		$this->calculate_totals();
		return $key;
	}
	public function add_to_cart( $product_id, $qty = 1, $variation_id = 0, $attributes = array(), $data = array() ) {
		$key = md5( $product_id . '|' . $variation_id );
		$qty = (int) $qty + (int) ( $this->items[ $key ]['quantity'] ?? 0 );
		return $this->add( $product_id, $qty, $variation_id );
	}
	public function get_cart() { return $this->items; }
	public function is_empty() { return ! $this->items; }
	public function get_cart_contents_count() { return array_sum( array_column( $this->items, 'quantity' ) ); }
	public function get_removed_cart_contents() { return array(); }
	public function subtotal() { $s = 0; foreach ( $this->items as $l ) { $s += (float) $l['data']->get_price() * $l['quantity']; } return $s; }
	public function discount() { $d = 0; foreach ( $this->applied as $code ) { $c = new WC_Coupon( $code ); $d += (float) $c->get_amount(); } return min( $d, $this->subtotal() ); }
	public function calculate_totals() { $this->total = max( 0, $this->subtotal() - $this->discount() ) + $this->shipping + $this->shipping_tax; do_action( 'woocommerce_after_calculate_totals', $this ); }
	public function get_total( $c = 'view' ) { return 'edit' === $c ? $this->total : wc_price( $this->total ); }
	public function get_shipping_total() { return $this->shipping; }
	public function get_shipping_tax() { return $this->shipping_tax; }
	public function get_cart_hash() { return md5( wp_json_encode( array_map( static function ( $l ) { return array( $l['product_id'], $l['variation_id'], $l['quantity'] ); }, $this->items ) ) ); }
	public function get_applied_coupons() { return $this->applied; }
	public function has_discount( $code ) { return in_array( strtolower( $code ), array_map( 'strtolower', $this->applied ), true ); }
	public function apply_coupon( $code ) {
		$coupon = new WC_Coupon( $code );
		$valid  = ( new WC_Discounts( $this ) )->is_coupon_valid( $coupon );
		if ( true !== $valid ) { wc_add_notice( $valid->get_error_message(), 'error' ); return false; }
		$this->applied[] = strtolower( $code );
		wc_add_notice( 'Coupon code applied successfully.', 'success' );
		do_action( 'woocommerce_applied_coupon', $code );
		$this->calculate_totals();
		return true;
	}
}
class QT_Session {
	public $data = array();
	public function get( $k, $d = null ) { return $this->data[ $k ] ?? $d; }
	public function set( $k, $v ) { $this->data[ $k ] = $v; }
	public function __unset( $k ) { unset( $this->data[ $k ] ); }
	public function has_session() { return false; }
	public function get_customer_id() { return 'guest'; }
	public function set_customer_session_cookie( $s ) {}
	public function save_data() {}
}
class QT_Customer {
	public function get_shipping_country() { return 'OM'; }
	public function get_billing_country() { return 'OM'; }
	public function get_is_vat_exempt() { return false; }
	public function get_taxable_address() { return array( 'OM', '', '', '' ); }
}
/** WooCommerce's mailer: records what would be sent. */
class QT_Mailer {
	public function wrap_message( $heading, $message ) { return '<h1>' . $heading . '</h1>' . $message; }
	public function send( $to, $subject, $message, $headers = '', $attachments = '' ) { $GLOBALS['qt']['mail'][] = array( 'to' => $to, 'subject' => $subject, 'message' => $message, 'via' => 'wc' ); return empty( $GLOBALS['qt']['mail_fails'] ); }
}
class QT_WC { public $customer; public $cart; public $session; public function mailer() { return new QT_Mailer(); } }
function WC() {
	static $wc = null;
	if ( null === $wc ) { $wc = new QT_WC(); $wc->customer = new QT_Customer(); }
	$wc->cart    = $GLOBALS['qt_cart'];
	$wc->session = $GLOBALS['qt_session'];
	return $wc;
}

class WC_Coupon {
	const E_WC_COUPON_MIN_SPEND_LIMIT_NOT_MET = 108;
	public $row;
	public function __construct( $id_or_code = 0 ) {
		$this->row = null;
		foreach ( $GLOBALS['qt']['coupons'] as $id => $row ) {
			if ( (int) $id === (int) $id_or_code || ( is_string( $id_or_code ) && strtolower( $row['code'] ) === strtolower( $id_or_code ) ) ) { $this->row = $row + array( 'id' => $id ); break; }
		}
	}
	public function get_id() { return $this->row ? (int) $this->row['id'] : 0; }
	public function get_code() { return $this->row['code'] ?? ''; }
	public function get_discount_type() { return $this->row['type'] ?? 'fixed_cart'; }
	public function get_amount() { return (float) ( $this->row['amount'] ?? 0 ); }
	public function get_email_restrictions() { return $this->row['emails'] ?? array(); }
	public function get_date_expires() { return qt_date( $this->row['expires'] ?? 0 ); }
	public function get_usage_limit() { return (int) ( $this->row['limit'] ?? 0 ); }
	public function get_usage_count() { return (int) ( $this->row['count'] ?? 0 ); }
	public function get_usage_limit_per_user() { return (int) ( $this->row['per_user'] ?? 0 ); }
	public function get_used_by() { return $this->row['used_by'] ?? array(); }
	public function get_minimum_amount() { return (float) ( $this->row['minimum'] ?? 0 ); }
	public function get_description() { return $this->row['description'] ?? ''; }
}
class WC_Discounts {
	private $cart;
	public function __construct( $cart = null ) { $this->cart = $cart; }
	public function is_coupon_valid( $coupon ) {
		if ( ! $coupon->get_id() ) { return new WP_Error( 105, 'Coupon does not exist!' ); }
		$exp = $coupon->get_date_expires();
		if ( $exp && $exp->getTimestamp() < time() ) { return new WP_Error( 107, 'This coupon has expired.' ); }
		if ( $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) { return new WP_Error( 106, 'Coupon usage limit has been reached.' ); }
		if ( $coupon->get_minimum_amount() > 0 && $this->cart && $this->cart->subtotal() < $coupon->get_minimum_amount() ) { return new WP_Error( 108, 'The minimum spend for this coupon is ' . $coupon->get_minimum_amount() . '.' ); }
		return true;
	}
}

class WC_Order_Item_Product {
	public $id; public $order_id; public $product_id; public $variation_id; public $quantity; public $total; public $meta = array();
	public function get_id() { return $this->id; }
	public function get_order_id() { return $this->order_id; }
	public function get_product_id() { return $this->product_id; }
	public function get_variation_id() { return $this->variation_id; }
	public function get_quantity() { return $this->quantity; }
	public function get_total() { return $this->total; }
	public function get_meta( $k, $single = true ) { return $this->meta[ $k ] ?? ''; }
}
class WC_Order {
	public $id; public $customer_id; public $status = 'completed'; public $items = array(); public $paid = 0; public $meta = array();
	public function get_id() { return $this->id; }
	public function get_customer_id() { return $this->customer_id; }
	public function has_status( $s ) { return in_array( $this->status, (array) $s, true ); }
	public function get_items( $t = 'line_item' ) { return $this->items; }
	public function get_item( $id ) { return $this->items[ $id ] ?? false; }
	public function get_qty_refunded_for_item( $id ) { return 0; }
	public function get_total_refunded_for_item( $id ) { return 0; }
	public function get_date_paid() { return qt_date( $this->paid ); }
	public function get_date_created() { return qt_date( $this->paid ); }
	public function get_meta( $k, $s = true ) { return $this->meta[ $k ] ?? ''; }
	public $billing_email = ''; public $currency = 'OMR'; public $saved = 0;
	public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
	public function save() { $this->saved++; return $this->id; }
	public function get_billing_email() { return $this->billing_email; }
	public function get_currency() { return $this->currency; }
}
function wc_get_order( $id ) { return $GLOBALS['qt']['orders'][ (int) $id ] ?? false; }
function wc_get_orders( $args ) {
	$rows = array_values( array_filter( $GLOBALS['qt']['orders'], static function ( $o ) use ( $args ) {
		return (int) $o->customer_id === (int) ( $args['customer_id'] ?? 0 ) && in_array( 'wc-' . $o->status, (array) ( $args['status'] ?? array() ), true );
	} ) );
	usort( $rows, static function ( $a, $b ) { return $b->paid <=> $a->paid; } );
	return array_slice( $rows, 0, (int) ( $args['limit'] ?? 10 ) );
}
class WC_Cache_Helper { public static function get_transient_version( $g ) { return (string) ( $GLOBALS['qt']['version'] ?? '1' ); } }

/** $wpdb double: only the wallet's coupon lookup is interpreted. */
class QT_WPDB {
	public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta'; public $prefix = 'wp_'; public $options = 'wp_options';
	public function prepare( $sql, ...$args ) { $args = is_array( $args[0] ?? null ) ? $args[0] : $args; foreach ( $args as $a ) { $sql = preg_replace( '/%[sd]/', is_int( $a ) ? (string) $a : "'" . addslashes( (string) $a ) . "'", $sql, 1 ); } return $sql; }
	public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }
	public function get_col( $sql ) {
		if ( false === strpos( $sql, "shop_coupon" ) ) { return array(); }
		preg_match_all( "/LIKE '%(.*?)%'/", $sql, $m );
		$needles = array_map( static function ( $n ) { return trim( stripcslashes( stripslashes( $n ) ), '"' ); }, $m[1] );
		$ids = array();
		foreach ( $GLOBALS['qt']['coupons'] as $id => $row ) {
			if ( array_intersect( array_map( 'strtolower', $row['emails'] ?? array() ), $needles ) ) { $ids[] = $id; }
		}
		rsort( $ids );
		return $ids;
	}
	public function get_var( $sql ) { return null; }
	public function query( $sql ) { return 1; }
}
$GLOBALS['wpdb'] = new QT_WPDB();

/** Cashback issuer double with Qimia's live bands: 2 / 3 / 4 / 5 / 6 / 8 OMR. */
class QCB2_Core {
	public static function public_policy() {
		return array( 'amounts' => array( 'upto20' => 2, 'upto30' => 3, 'upto40' => 4, 'upto50' => 5, 'upto60' => 6, 'above60' => 8 ), 'minimum_omr' => 0, 'expiry' => array( 'days_after_issue' => 40 ), 'program' => 'instant_paid_order', 'enabled' => true );
	}
	public static function rates() { return array( 'SAR' => array( 'rate' => 9.75 ), 'AED' => array( 'rate' => 9.55 ) ); }
}
