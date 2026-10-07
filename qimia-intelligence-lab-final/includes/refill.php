<?php
/**
 * Qimia Refill: opt-in refill plans for the supplements a shopper uses up.
 *
 * A signed-in shopper turns a plan on for something they bought (the order
 * received page, or My Account → Refills). The interval starts from the same
 * estimate as "Running low?" (the label's verified serving count × quantity ÷
 * servings a day), else 30 days, and the shopper can change it. Before the
 * product runs low, one email lists everything due with a signed one-tap
 * link: the exact product, flavour and size from the last order go into the
 * cart at today's WooCommerce price, then checkout. Nothing is charged and no
 * order is created automatically. A paid order that contains the product
 * starts the next cycle by itself; refill orders are counted on the Refills tab.
 *
 * Built on the verified Buy Again selection (qil_repeat_selection) and stored
 * in user meta only: no table, no schema change. QIL_REFILL_DISABLE in
 * wp-config.php, or the switch on the Refills tab, stops all of it.
 *
 * @package Qimia_Intelligence_Lab
 */
defined( 'ABSPATH' ) || exit;

final class QIL_Refill {
	const OPTION    = 'qil_refill';
	const META      = '_qil_refill_plans';
	const DUE       = '_qil_refill_due'; // Next time the reminder job has work for this account (indexed for the job).
	const STATS     = 'qil_refill_stats';
	const CRON      = 'qil_refill_tick';
	const ENDPOINT  = 'qimia-refills';
	const SESSION   = 'qil_refill';
	const MAX       = 12;
	const DELIVERY  = 2;  // Days between payment and the first serving, as in "Running low?".
	const FOLLOW_UP = 5;  // Days after the estimated run-out for the one follow-up.
	const ROLL      = 14; // Days after the run-out when an unanswered cycle moves on.
	const LINK_DAYS = 30;
	const INTERVALS = array( 14, 21, 30, 45, 60, 90 );

	public static function boot() {
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'ensure_rewrite' ), 30 );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu' ), 30 );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'account' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 8 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou' ), 25 );
		add_action( 'init', array( __CLASS__, 'private_request' ), -9995 );
		add_action( 'wc_ajax_qil_refill', array( __CLASS__, 'go' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attribute' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'attribute' ), 20 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'clear_session' ), 20 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'clear_session' ), 20 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'paid' ), 20 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'paid' ), 20 );
		add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
		add_action( self::CRON, array( __CLASS__, 'tick' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 70 );
		register_deactivation_hook( QIL_FILE, array( __CLASS__, 'unschedule' ) );
	}

	/* ------------------------------------------------------------------
	   Settings and switches
	   ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'enabled'     => 1,
			'email'       => 1,
			'lead'        => 4,
			'follow_up'   => 1,
			'thankyou'    => 1,
			'destination' => 'checkout',
		);
	}

	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$d   = self::defaults();
		return array(
			'enabled'     => empty( $raw['enabled'] ) ? 0 : 1,
			'email'       => empty( $raw['email'] ) ? 0 : 1,
			'lead'        => max( 1, min( 14, (int) ( $raw['lead'] ?? $d['lead'] ) ) ),
			'follow_up'   => empty( $raw['follow_up'] ) ? 0 : 1,
			'thankyou'    => empty( $raw['thankyou'] ) ? 0 : 1,
			'destination' => 'cart' === ( $raw['destination'] ?? '' ) ? 'cart' : 'checkout',
		);
	}

	public static function register_setting() {
		register_setting( 'qil_refill_settings', self::OPTION, array(
			'type'              => 'array',
			'default'           => self::defaults(),
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	public static function settings() {
		$stored = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/** The feature itself: QIL_DISABLE / QIL_REFILL_DISABLE and the Refills tab switch. */
	public static function enabled() {
		if ( ( defined( 'QIL_DISABLE' ) && QIL_DISABLE ) || ( defined( 'QIL_REFILL_DISABLE' ) && QIL_REFILL_DISABLE ) ) {
			return false;
		}
		return ! empty( self::settings()['enabled'] ) && function_exists( 'qil_repeat_selection' ) && function_exists( 'WC' );
	}

	/** Shopper-facing surfaces follow the storefront's host and on/off rules. */
	public static function visible() {
		return self::enabled() && function_exists( 'qil_experience_enabled' ) && qil_experience_enabled();
	}

	/**
	 * Reminder emails go out from the live store only. WP-Cron may run without a
	 * request host (system cron), so the site address decides, never the
	 * sandbox: staging copies must not email real customers.
	 */
	public static function mail_ready() {
		if ( ! self::enabled() || empty( self::settings()['email'] ) ) {
			return false;
		}
		$host  = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$hosts = array_map( 'strtolower', (array) apply_filters( 'qil_allowed_hosts', array( 'qimialab.qimia.om', 'qimia.om', 'www.qimia.om' ) ) );
		$live  = 'qimialab.qimia.om' !== $host && in_array( $host, $hosts, true ) && '1' === (string) get_option( 'qil_enabled', '0' );
		return (bool) apply_filters( 'qil_refill_send_email', $live );
	}

	private static function is_ar() {
		return class_exists( 'QIL_Boost' ) && QIL_Boost::is_ar();
	}

	private static function t( $en, $ar, $is_ar = null ) {
		return ( null === $is_ar ? self::is_ar() : $is_ar ) ? $ar : $en;
	}

	/* ------------------------------------------------------------------
	   Plans (user meta)
	   ------------------------------------------------------------------ */

	public static function plan_id( $product_id, $variation_id ) {
		return absint( $product_id ) . '-' . absint( $variation_id );
	}

	/** @return array<string,array> Plans keyed by plan id, oldest first. */
	public static function plans( $user_id ) {
		$plans = get_user_meta( (int) $user_id, self::META, true );
		$out   = array();
		foreach ( is_array( $plans ) ? $plans : array() as $id => $plan ) {
			if ( is_array( $plan ) && preg_match( '/^\d+-\d+$/', (string) $id ) && ! empty( $plan['productId'] ) ) {
				$out[ (string) $id ] = $plan;
			}
		}
		return $out;
	}

	private static function save( $user_id, array $plans ) {
		$user_id = (int) $user_id;
		if ( ! $plans ) {
			delete_user_meta( $user_id, self::META );
			delete_user_meta( $user_id, self::DUE );
			return;
		}
		update_user_meta( $user_id, self::META, $plans );
		$next = null;
		foreach ( $plans as $plan ) {
			$at = self::next_event( $plan );
			if ( null !== $at && ( null === $next || $at < $next ) ) {
				$next = $at;
			}
		}
		if ( null === $next ) {
			delete_user_meta( $user_id, self::DUE );
		} else {
			update_user_meta( $user_id, self::DUE, (int) $next );
		}
	}

	/** When the reminder job next has something to do for this plan (null: nothing while paused). */
	private static function next_event( array $plan ) {
		if ( 'active' !== ( $plan['status'] ?? '' ) ) {
			return null;
		}
		$lead = (int) self::settings()['lead'] * DAY_IN_SECONDS;
		$next = (int) $plan['nextAt'];
		if ( empty( $plan['remindedAt'] ) ) {
			$at = $next - $lead;
		} elseif ( empty( $plan['followedAt'] ) && ! empty( self::settings()['follow_up'] ) ) {
			$at = $next + self::FOLLOW_UP * DAY_IN_SECONDS;
		} else {
			$at = $next + self::ROLL * DAY_IN_SECONDS;
		}
		return max( $at, (int) ( $plan['waitUntil'] ?? 0 ) );
	}

	/** First serving + interval: the estimated day the product runs low. */
	private static function next_from( $paid_at, $interval ) {
		return (int) $paid_at + ( (int) $interval + self::DELIVERY ) * DAY_IN_SECONDS;
	}

	/**
	 * Whether an order line can carry a refill plan, and the suggested interval.
	 * Consumables only (the "Running low?" roles, or a verified serving count).
	 *
	 * @return array{selection:array,estimate:int,interval:int,role:string}|null
	 */
	public static function eligible( $order, $item ) {
		if ( ! function_exists( 'qil_repeat_selection' ) || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return null;
		}
		$selection = qil_repeat_selection( $order, $item );
		if ( ! $selection ) {
			return null;
		}
		$parent   = $selection['parent'];
		$product  = $selection['product'];
		$slugs    = array();
		$terms    = get_the_terms( $parent->get_id(), 'product_cat' );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$slugs[] = (string) $term->slug;
		}
		$role     = class_exists( 'QIL_Boost' ) ? (string) QIL_Boost::roles_for( $parent->get_id(), $parent->get_name(), $slugs )[0] : '';
		$servings = class_exists( 'QIL_Member' ) ? QIL_Member::servings( $product, $parent ) : 0;
		$quantity = (float) $item->get_quantity() + (float) $order->get_qty_refunded_for_item( $item->get_id() );
		$estimate = 0;
		if ( $servings > 0 && $quantity > 0 && class_exists( 'QIL_Member' ) ) {
			$days     = (int) floor( ( $servings * $quantity ) / QIL_Member::per_day( $role ) );
			$estimate = $days >= 7 && $days <= 180 ? $days : 0;
		}
		$replenish = class_exists( 'QIL_Member' ) && QIL_Member::replenishable( $role );
		if ( ! apply_filters( 'qil_refill_eligible', $replenish || $estimate > 0, $parent, $product, $role ) ) {
			return null;
		}
		return array(
			'selection' => $selection,
			'estimate'  => $estimate,
			'interval'  => $estimate ? max( 7, min( 120, $estimate ) ) : 30,
			'role'      => $role,
		);
	}

	private static function owns( $order, $user_id, $for_create = false ) {
		$statuses = $for_create ? array( 'processing', 'completed', 'on-hold' ) : array( 'processing', 'completed' );
		return is_a( $order, 'WC_Order' ) && (int) $user_id > 0 && (int) $order->get_customer_id() === (int) $user_id && $order->has_status( $statuses );
	}

	private static function order_time( $order ) {
		$date = $order->get_date_paid() ?: $order->get_date_created();
		return $date ? (int) $date->getTimestamp() : time();
	}

	/**
	 * Start (or restart) a plan from one of the account's order lines.
	 *
	 * @return array|WP_Error The plan.
	 */
	public static function create( $user_id, $order_id, $item_id, $interval = 0, $locale = null ) {
		$user_id = (int) $user_id;
		$order   = wc_get_order( absint( $order_id ) );
		if ( ! self::enabled() || ! self::owns( $order, $user_id, true ) ) {
			return new WP_Error( 'order', 'order' );
		}
		$item = $order->get_item( absint( $item_id ) );
		if ( ! $item || (int) $item->get_order_id() !== (int) $order->get_id() ) {
			return new WP_Error( 'item', 'item' );
		}
		$fit = self::eligible( $order, $item );
		if ( ! $fit ) {
			return new WP_Error( 'ineligible', 'ineligible' );
		}
		$plans = self::plans( $user_id );
		$id    = self::plan_id( $fit['selection']['parent']->get_id(), $fit['selection']['variationId'] );
		if ( ! isset( $plans[ $id ] ) && count( $plans ) >= self::MAX ) {
			return new WP_Error( 'limit', 'limit' );
		}
		$interval = (int) $interval ? max( 7, min( 120, (int) $interval ) ) : $fit['interval'];
		$paid_at  = self::order_time( $order );
		$now      = time();
		$plans[ $id ] = array(
			'productId'   => (int) $fit['selection']['parent']->get_id(),
			'variationId' => (int) $fit['selection']['variationId'],
			'orderId'     => (int) $order->get_id(),
			'itemId'      => (int) $item->get_id(),
			'quantity'    => max( 1, min( 10, (int) $item->get_quantity() ) ),
			'interval'    => $interval,
			'estimate'    => (int) $fit['estimate'],
			'status'      => 'active',
			'createdAt'   => (int) ( $plans[ $id ]['createdAt'] ?? $now ),
			'lastOrderAt' => $paid_at,
			'nextAt'      => self::next_from( $paid_at, $interval ),
			'remindedAt'  => 0,
			'followedAt'  => 0,
			'misses'      => 0,
			'skips'       => (int) ( $plans[ $id ]['skips'] ?? 0 ),
			'refills'     => (int) ( $plans[ $id ]['refills'] ?? 0 ),
			'locale'      => in_array( $locale, array( 'en', 'ar' ), true ) ? $locale : ( self::is_ar() ? 'ar' : 'en' ),
		);
		self::save( $user_id, $plans );
		self::count( 'started' );
		return $plans[ $id ];
	}

	/**
	 * Shopper's own changes: interval, skip, pause, resume, remove.
	 *
	 * @return bool
	 */
	public static function change( $user_id, $plan_id, $op, $value = 0 ) {
		$plans = self::plans( $user_id );
		if ( ! isset( $plans[ $plan_id ] ) ) {
			return false;
		}
		$plan = $plans[ $plan_id ];
		$now  = time();
		switch ( $op ) {
			case 'interval':
				$days = (int) $value;
				if ( $days < 7 || $days > 120 ) {
					return false;
				}
				$plan['interval'] = $days;
				$plan['nextAt']   = self::next_from( $plan['lastOrderAt'], $days );
				while ( $plan['nextAt'] < $now - self::ROLL * DAY_IN_SECONDS ) {
					$plan['nextAt'] += $days * DAY_IN_SECONDS;
				}
				$plan['remindedAt'] = 0;
				$plan['followedAt'] = 0;
				break;
			case 'skip':
				$plan['nextAt']     = max( (int) $plan['nextAt'], $now ) + (int) $plan['interval'] * DAY_IN_SECONDS;
				$plan['remindedAt'] = 0;
				$plan['followedAt'] = 0;
				$plan['skips']      = (int) $plan['skips'] + 1;
				break;
			case 'pause':
				$plan['status'] = 'paused';
				break;
			case 'resume':
				$plan['status'] = 'active';
				$plan['misses'] = 0;
				if ( (int) $plan['nextAt'] < $now ) {
					// Back after a break: the next reminder is one interval from today.
					$plan['nextAt']     = $now + (int) $plan['interval'] * DAY_IN_SECONDS;
					$plan['remindedAt'] = 0;
					$plan['followedAt'] = 0;
				}
				break;
			case 'remove':
				unset( $plans[ $plan_id ] );
				self::save( $user_id, $plans );
				return true;
			default:
				return false;
		}
		unset( $plan['waitUntil'] );
		$plans[ $plan_id ] = $plan;
		self::save( $user_id, $plans );
		return true;
	}

	/** The verified selection behind a plan, if its source order still belongs to the account. */
	private static function selection( array $plan, $user_id ) {
		$order = wc_get_order( (int) $plan['orderId'] );
		if ( ! self::owns( $order, $user_id, true ) ) {
			return null;
		}
		$item = $order->get_item( (int) $plan['itemId'] );
		return $item ? qil_repeat_selection( $order, $item ) : null;
	}

	/* ------------------------------------------------------------------
	   Into the cart
	   ------------------------------------------------------------------ */

	/**
	 * Add the plans' exact selections at today's price. A line already in the
	 * cart is left as it is, so a second tap or a reopened email never doubles it.
	 *
	 * @return array{added:string[],present:string[],unavailable:string[],names:string[]}
	 */
	private static function fill_cart( $user_id, array $plan_ids ) {
		$out = array( 'added' => array(), 'present' => array(), 'unavailable' => array(), 'names' => array() );
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			$out['unavailable'] = $plan_ids;
			return $out;
		}
		$plans   = self::plans( $user_id );
		$in_cart = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			$in_cart[ self::plan_id( $line['product_id'] ?? 0, $line['variation_id'] ?? 0 ) ] = true;
		}
		foreach ( $plan_ids as $id ) {
			$plan = $plans[ $id ] ?? null;
			$sel  = $plan ? self::selection( $plan, $user_id ) : null;
			if ( ! $sel || ! $sel['canAdd'] ) {
				$out['unavailable'][] = $id;
				continue;
			}
			$out['names'][ $id ] = qil_clean_text( $sel['parent']->get_name() ) . ( '' !== $sel['label'] ? ' (' . $sel['label'] . ')' : '' );
			if ( isset( $in_cart[ $id ] ) ) {
				$out['present'][] = $id;
				continue;
			}
			$parent_id = (int) $sel['parent']->get_id();
			$quantity  = max( 1, min( 10, (int) $plan['quantity'] ) );
			$cart_data = array();
			if ( ! apply_filters( 'woocommerce_add_to_cart_validation', true, $parent_id, $quantity, $sel['variationId'], $sel['attributes'], $cart_data ) ) {
				$out['unavailable'][] = $id;
				continue;
			}
			try {
				$key = WC()->cart->add_to_cart( $parent_id, $quantity, $sel['variationId'], $sel['attributes'], $cart_data );
			} catch ( Throwable $error ) {
				$key = false;
			}
			if ( $key ) {
				$out['added'][]  = $id;
				$in_cart[ $id ] = true;
			} else {
				$out['unavailable'][] = $id;
			}
		}
		if ( $out['added'] ) {
			WC()->cart->calculate_totals();
			if ( method_exists( WC()->cart, 'set_session' ) ) {
				WC()->cart->set_session();
			}
			if ( WC()->session ) {
				WC()->session->set_customer_session_cookie( true );
			}
		}
		if ( WC()->session && ( $out['added'] || $out['present'] ) ) {
			WC()->session->set( self::SESSION, array( 'user' => (int) $user_id, 'plans' => array_values( array_merge( $out['added'], $out['present'] ) ), 'at' => time() ) );
		}
		return $out;
	}

	private static function fill_notice( array $result, $ar ) {
		$names = array_values( array_intersect_key( $result['names'], array_flip( array_merge( $result['added'], $result['present'] ) ) ) );
		if ( $names ) {
			wc_add_notice( esc_html( sprintf( self::t( 'Your refill is ready: %s. Same flavour and size, at today’s price.', 'تجديدك جاهز: %s. نفس النكهة والحجم، بسعر اليوم.', $ar ), implode( $ar ? '، ' : ', ', $names ) ) ), 'success' );
		}
		if ( $result['unavailable'] ) {
			wc_add_notice( esc_html( self::t( 'Something from your refill is unavailable right now. Choose it on its product page, or try again later.', 'بعض منتجات التجديد غير متوفرة حالياً. اخترها من صفحة المنتج، أو حاول لاحقاً.', $ar ) ), $names ? 'notice' : 'error' );
		}
	}

	private static function destination( $ar ) {
		$url = 'cart' === self::settings()['destination'] ? wc_get_cart_url() : wc_get_checkout_url();
		return function_exists( 'qil_localized_url' ) ? qil_localized_url( $url, $ar ) : $url;
	}

	public static function account_url( $ar = null ) {
		$ar  = null === $ar ? self::is_ar() : (bool) $ar;
		$url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( self::ENDPOINT ) : home_url( '/my-account/' . self::ENDPOINT . '/' );
		return function_exists( 'qil_localized_url' ) ? qil_localized_url( $url, $ar ) : $url;
	}

	/* ------------------------------------------------------------------
	   Signed one-tap links (emails)
	   ------------------------------------------------------------------ */

	private static function sign( $payload ) {
		return substr( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) . '|qil-refill' ), 0, 32 );
	}

	/** Through wc-ajax: never served from a page cache, private headers on the way out. */
	public static function link( $user_id, array $plan_ids, $issued = null ) {
		$payload = (int) $user_id . '.' . implode( '~', array_map( 'strval', $plan_ids ) ) . '.' . (int) ( $issued ?? time() );
		return add_query_arg( array( 'wc-ajax' => 'qil_refill', 't' => $payload . '.' . self::sign( $payload ) ), home_url( '/' ) );
	}

	/** @return array{user:int,plans:string[],issued:int}|null */
	public static function verify( $token ) {
		if ( ! is_string( $token ) || strlen( $token ) > 600 || ! preg_match( '/^(\d+)\.(\d+-\d+(?:~\d+-\d+)*)\.(\d+)\.([a-f0-9]{32})$/D', $token, $m ) ) {
			return null;
		}
		if ( ! hash_equals( self::sign( $m[1] . '.' . $m[2] . '.' . $m[3] ), $m[4] ) ) {
			return null;
		}
		$issued = (int) $m[3];
		if ( $issued > time() + 300 || $issued < time() - self::LINK_DAYS * DAY_IN_SECONDS ) {
			return null;
		}
		return array( 'user' => (int) $m[1], 'plans' => array_slice( array_unique( explode( '~', $m[2] ) ), 0, self::MAX ), 'issued' => $issued );
	}

	public static function private_request() {
		if ( isset( $_GET['wc-ajax'] ) && 'qil_refill' === $_GET['wc-ajax'] && function_exists( 'qil_continuity_private_headers' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			qil_continuity_private_headers();
		}
	}

	/**
	 * The email's "Refill now". The signature proves the link came from us for
	 * this account; the order and item are re-verified as the account's own.
	 * Signed in as someone else, nothing is added. Signed out, the items go into
	 * this browser's cart and checkout asks who is buying, as usual.
	 */
	public static function go() {
		if ( function_exists( 'qil_continuity_private_headers' ) ) {
			qil_continuity_private_headers();
		}
		$token = isset( $_GET['t'] ) && is_string( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed link.
		$link  = self::verify( $token );
		$plans = $link ? self::plans( $link['user'] ) : array();
		$ar    = $link && $plans && 'ar' === ( reset( $plans )['locale'] ?? '' );
		if ( ! self::enabled() || ! $link ) {
			wc_add_notice( esc_html( self::t( 'This refill link has expired. Your refills are in your account.', 'انتهت صلاحية رابط التجديد. تجد تجديداتك في حسابك.', $ar ) ), 'notice' );
			self::redirect( self::account_url( $ar ) );
		}
		if ( is_user_logged_in() && (int) get_current_user_id() !== $link['user'] ) {
			wc_add_notice( esc_html( self::t( 'This refill link belongs to another account.', 'رابط التجديد هذا يخص حساباً آخر.', $ar ) ), 'error' );
			self::redirect( self::account_url( $ar ) );
		}
		$wanted = array();
		$done   = 0;
		foreach ( $link['plans'] as $id ) {
			if ( ! isset( $plans[ $id ] ) || 'active' !== $plans[ $id ]['status'] ) {
				continue;
			}
			if ( (int) $plans[ $id ]['lastOrderAt'] > $link['issued'] ) {
				++$done; // Already reordered after this email.
				continue;
			}
			$wanted[] = $id;
		}
		self::count( 'opened' );
		if ( ! $wanted ) {
			wc_add_notice( esc_html( $done ? self::t( 'You already reordered this. Your next reminder is set.', 'لقد أعدت الطلب بالفعل. تم ضبط تذكيرك القادم.', $ar ) : self::t( 'This refill is no longer active. Your refills are in your account.', 'هذا التجديد لم يعد مفعّلاً. تجد تجديداتك في حسابك.', $ar ) ), 'notice' );
			self::redirect( self::account_url( $ar ) );
		}
		$result = self::fill_cart( $link['user'], $wanted );
		self::fill_notice( $result, $ar );
		self::redirect( $result['added'] || $result['present'] ? self::destination( $ar ) : self::account_url( $ar ) );
	}

	private static function redirect( $url ) {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		wp_safe_redirect( $url );
		exit;
	}

	/* ------------------------------------------------------------------
	   Orders: attribution and the next cycle
	   ------------------------------------------------------------------ */

	/** Mark orders placed from a refill link or the Refills page. */
	public static function attribute( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$flag = WC()->session->get( self::SESSION );
		if ( ! is_array( $flag ) || empty( $flag['plans'] ) || (int) ( $flag['at'] ?? 0 ) < time() - 3 * DAY_IN_SECONDS ) {
			return;
		}
		$order->update_meta_data( '_qil_refill', implode( ',', array_map( 'sanitize_key', (array) $flag['plans'] ) ) );
		$order->update_meta_data( '_qil_refill_user', (int) $flag['user'] );
	}

	public static function clear_session() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION, null );
		}
	}

	/**
	 * A paid order restarts every plan it contains from its payment date, with
	 * the exact line as the new source. Runs once per order.
	 */
	public static function paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) || ! self::enabled() || $order->get_meta( '_qil_refill_seen' ) ) {
			return;
		}
		$user_id = (int) $order->get_customer_id();
		if ( ! $user_id && (int) $order->get_meta( '_qil_refill_user' ) ) {
			// A signed-out refill: the account from the link, when the billing email is that account's.
			$candidate = get_userdata( (int) $order->get_meta( '_qil_refill_user' ) );
			if ( $candidate && strtolower( (string) $candidate->user_email ) === strtolower( (string) $order->get_billing_email() ) ) {
				$user_id = (int) $candidate->ID;
			}
		}
		$plans = $user_id ? self::plans( $user_id ) : array();
		if ( ! $plans ) {
			return;
		}
		$from_refill = array_filter( explode( ',', (string) $order->get_meta( '_qil_refill' ) ) );
		$paid_at     = self::order_time( $order );
		$changed     = false;
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			$id = self::plan_id( $item->get_product_id(), $item->get_variation_id() );
			if ( ! isset( $plans[ $id ] ) || $paid_at < (int) $plans[ $id ]['lastOrderAt'] ) {
				continue;
			}
			$plan                = $plans[ $id ];
			$plan['lastOrderAt'] = $paid_at;
			$plan['nextAt']      = self::next_from( $paid_at, $plan['interval'] );
			$plan['remindedAt']  = 0;
			$plan['followedAt']  = 0;
			$plan['misses']      = 0;
			unset( $plan['waitUntil'] );
			if ( $user_id === (int) $order->get_customer_id() ) {
				$plan['orderId']  = (int) $order->get_id();
				$plan['itemId']   = (int) $item->get_id();
				$plan['quantity'] = max( 1, min( 10, (int) $item->get_quantity() ) );
			}
			if ( in_array( $id, $from_refill, true ) ) {
				$plan['refills'] = (int) $plan['refills'] + 1;
				self::count( 'items' );
				self::count( 'revenue', (float) $item->get_total(), strtoupper( (string) $order->get_currency() ) );
			}
			$plans[ $id ] = $plan;
			$changed      = true;
		}
		if ( $from_refill ) {
			self::count( 'orders' );
		}
		if ( $changed ) {
			self::save( $user_id, $plans );
		}
		$order->update_meta_data( '_qil_refill_seen', 1 );
		$order->save();
	}

	/* ------------------------------------------------------------------
	   Reminder job (WP-Cron, hourly)
	   ------------------------------------------------------------------ */

	public static function schedule() {
		if ( ! function_exists( 'wp_next_scheduled' ) || wp_next_scheduled( self::CRON ) ) {
			return;
		}
		if ( self::mail_ready() ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', self::CRON );
		}
	}

	public static function unschedule() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/** Up to 40 accounts per run, soonest first; one email per account per run. */
	public static function tick() {
		if ( ! self::mail_ready() ) {
			return array();
		}
		if ( get_transient( 'qil_refill_lock' ) ) {
			return array();
		}
		set_transient( 'qil_refill_lock', 1, 10 * MINUTE_IN_SECONDS );
		$sent = array();
		try {
			$now   = time();
			$users = get_users( array(
				'meta_key'     => self::DUE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed single key, bounded.
				'meta_value'   => $now, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare' => '<=',
				'meta_type'    => 'NUMERIC',
				'orderby'      => 'meta_value_num',
				'order'        => 'ASC',
				'number'       => 40,
				'fields'       => 'ID',
			) );
			foreach ( (array) $users as $user_id ) {
				$result = self::process( (int) $user_id, $now );
				if ( $result ) {
					$sent[ (int) $user_id ] = $result;
				}
			}
		} finally {
			delete_transient( 'qil_refill_lock' );
		}
		return $sent;
	}

	/**
	 * Move each due plan one step: the reminder (lead days before the run-out),
	 * one follow-up (five days after), then the cycle moves on (fourteen days
	 * after). Three unanswered cycles pause the plan, so nobody keeps getting
	 * emails they ignore. Unavailable products wait a day instead of emailing.
	 *
	 * @return string[] Plan ids in the email that was sent.
	 */
	public static function process( $user_id, $now = null ) {
		$now      = $now ?? time();
		$plans    = self::plans( $user_id );
		$settings = self::settings();
		$lead     = (int) $settings['lead'] * DAY_IN_SECONDS;
		$due      = array();
		$stage    = array();
		foreach ( $plans as $id => &$plan ) {
			if ( 'active' !== $plan['status'] || (int) ( $plan['waitUntil'] ?? 0 ) > $now ) {
				continue;
			}
			$next = (int) $plan['nextAt'];
			if ( $now >= $next + self::ROLL * DAY_IN_SECONDS ) {
				while ( $plan['nextAt'] <= $now ) {
					$plan['nextAt'] += (int) $plan['interval'] * DAY_IN_SECONDS;
				}
				$plan['remindedAt'] = 0;
				$plan['followedAt'] = 0;
				$plan['misses']     = (int) $plan['misses'] + 1;
				if ( $plan['misses'] >= 3 ) {
					$plan['status'] = 'paused';
					$plan['paused'] = 'idle';
				}
				continue;
			}
			$step = '';
			if ( empty( $plan['remindedAt'] ) && $now >= $next - $lead && (int) $plan['createdAt'] < $now - 2 * DAY_IN_SECONDS ) {
				$step = 'remind';
			} elseif ( ! empty( $plan['remindedAt'] ) && empty( $plan['followedAt'] ) && ! empty( $settings['follow_up'] ) && $now >= $next + self::FOLLOW_UP * DAY_IN_SECONDS ) {
				$step = 'follow';
			}
			if ( ! $step ) {
				if ( empty( $plan['remindedAt'] ) && $now >= $next - $lead ) {
					$plan['waitUntil'] = (int) $plan['createdAt'] + 2 * DAY_IN_SECONDS; // Just started: no email on day one.
				}
				continue;
			}
			$selection = self::selection( $plan, $user_id );
			if ( ! $selection || ! $selection['canAdd'] ) {
				$plan['waitUntil'] = $now + DAY_IN_SECONDS;
				continue;
			}
			$due[ $id ]   = $selection;
			$stage[ $id ] = $step;
		}
		unset( $plan );
		$sent = array();
		if ( $due && self::email( $user_id, $plans, $due, $stage, $now ) ) {
			foreach ( $stage as $id => $step ) {
				$plans[ $id ][ 'remind' === $step ? 'remindedAt' : 'followedAt' ] = $now;
				unset( $plans[ $id ]['waitUntil'] );
				$sent[] = $id;
			}
			self::count( 'sent' );
		} elseif ( $due ) {
			foreach ( array_keys( $due ) as $id ) {
				$plans[ $id ]['waitUntil'] = $now + 6 * HOUR_IN_SECONDS; // Mail failed: try again later, never in a loop.
			}
		}
		self::save( $user_id, $plans );
		return $sent;
	}

	private static function date_label( $time, $ar ) {
		$months = array( 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر' );
		$day    = function_exists( 'wp_date' ) ? (string) wp_date( 'j', $time ) : gmdate( 'j', $time );
		$month  = function_exists( 'wp_date' ) ? (int) wp_date( 'n', $time ) : (int) gmdate( 'n', $time );
		return $ar ? $day . ' ' . $months[ $month - 1 ] : ( function_exists( 'wp_date' ) ? (string) wp_date( 'j M', $time ) : gmdate( 'j M', $time ) );
	}

	private static function days_label( $days, $ar ) {
		if ( $ar ) {
			return 1 === $days ? 'يوم واحد' : ( 2 === $days ? 'يومان' : $days . ( $days <= 10 ? ' أيام' : ' يوماً' ) );
		}
		return 1 === $days ? '1 day' : $days . ' days';
	}

	private static function money( $product ) {
		$price = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product ) : (float) $product->get_price();
		// Plain text (escaped again where printed): WooCommerce's formatter, without its markup and entities.
		return function_exists( 'wc_price' ) ? trim( html_entity_decode( wp_strip_all_tags( wc_price( $price ) ), ENT_QUOTES, 'UTF-8' ) ) : number_format( (float) $price, 3 );
	}

	private static function email( $user_id, array $plans, array $due, array $stage, $now ) {
		$user = get_userdata( (int) $user_id );
		$to   = $user ? sanitize_email( (string) $user->user_email ) : '';
		if ( ! $to || ! is_email( $to ) ) {
			return false;
		}
		$ar    = 'ar' === ( $plans[ array_key_first( $due ) ]['locale'] ?? 'en' );
		$names = array();
		$rows  = '';
		foreach ( $due as $id => $selection ) {
			$plan    = $plans[ $id ];
			$name    = qil_clean_text( $selection['parent']->get_name() );
			$names[] = $name;
			$left    = (int) ceil( ( (int) $plan['nextAt'] - $now ) / DAY_IN_SECONDS );
			$when    = $left > 0
				? sprintf( self::t( 'Runs low around %1$s (in %2$s)', 'ينفد تقريباً في %1$s (بعد %2$s)', $ar ), self::date_label( (int) $plan['nextAt'], $ar ), self::days_label( $left, $ar ) )
				: self::t( 'Probably running low now', 'غالباً على وشك النفاد الآن', $ar );
			$rows   .= '<tr><td style="padding:12px 0;border-bottom:1px solid #e4efee">'
				. '<strong style="color:#12313a">' . esc_html( $name ) . '</strong>'
				. ( '' !== $selection['label'] ? '<br><span style="color:#4f6870">' . esc_html( $selection['label'] ) . '</span>' : '' )
				. '<br><span style="color:#4f6870">' . esc_html( sprintf( self::t( 'Qty %1$d · %2$s today · %3$s', 'الكمية %1$d · %2$s اليوم · %3$s', $ar ), (int) $plan['quantity'], self::money( $selection['product'] ), $when ) ) . '</span>'
				. '</td></tr>';
		}
		$follow  = ! in_array( 'remind', $stage, true );
		$subject = $follow
			? sprintf( self::t( 'Still need %s? Your refill is one tap away', 'هل ما زلت تحتاج %s؟ تجديدك بلمسة واحدة', $ar ), $names[0] )
			: ( 1 === count( $names )
				? sprintf( self::t( 'Your %s is running low', 'منتجك %s على وشك النفاد', $ar ), $names[0] )
				: sprintf( self::t( 'Your refill is due: %d products', 'حان وقت التجديد: %d منتجات', $ar ), count( $names ) ) );
		$link    = self::link( $user_id, array_keys( $due ), $now );
		$manage  = self::account_url( $ar );
		$dir     = $ar ? 'rtl' : 'ltr';
		$align   = $ar ? 'right' : 'left';
		$body    = '<div dir="' . $dir . '" style="text-align:' . $align . ';font-size:15px;line-height:1.6;color:#12313a">'
			. '<p>' . esc_html( $follow
				? self::t( 'A quick check before your routine runs out:', 'تذكير سريع قبل أن ينفد روتينك:', $ar )
				: self::t( 'You asked us to remind you before these run out. Here is your refill, ready to go:', 'طلبت منا تذكيرك قبل أن تنفد هذه المنتجات. تجديدك جاهز:', $ar ) ) . '</p>'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse">' . $rows . '</table>'
			. '<p style="margin:24px 0"><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:14px 24px;border-radius:999px;background:#0b6e75;color:#ffffff;font-weight:700;text-decoration:none">' . esc_html( self::t( 'Refill now', 'جدّد الآن', $ar ) ) . '</a></p>'
			. '<p style="color:#4f6870">' . esc_html( self::t( 'Same product, flavour and size as last time, at today’s price. You review everything at checkout; nothing is charged automatically.', 'نفس المنتج والنكهة والحجم كالمرة السابقة، بسعر اليوم. تراجع كل شيء عند الدفع، ولا يُخصم أي مبلغ تلقائياً.', $ar ) ) . '</p>'
			. '<p><a href="' . esc_url( $manage ) . '" style="color:#0b6e75">' . esc_html( self::t( 'Change the timing, skip or pause your refills', 'غيّر التوقيت أو تخطَّ أو أوقف التجديد مؤقتاً', $ar ) ) . '</a></p>'
			. '</div>';
		$heading = self::t( 'Time to refill', 'حان وقت التجديد', $ar );
		$sent    = false;
		if ( function_exists( 'WC' ) && WC() && method_exists( WC(), 'mailer' ) ) {
			$mailer = WC()->mailer();
			$sent   = (bool) $mailer->send( $to, $subject, $mailer->wrap_message( $heading, $body ), "Content-Type: text/html\r\n" );
		} elseif ( function_exists( 'wp_mail' ) ) {
			$sent = (bool) wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		}
		return $sent;
	}

	/* ------------------------------------------------------------------
	   Counters for the Refills tab (60 days, per day)
	   ------------------------------------------------------------------ */

	private static function count( $what, $amount = 1, $currency = '' ) {
		$stats = get_option( self::STATS, array() );
		$stats = is_array( $stats ) ? $stats : array();
		$day   = gmdate( 'Y-m-d' );
		if ( 'revenue' === $what ) {
			$stats[ $day ]['revenue'][ $currency ] = round( (float) ( $stats[ $day ]['revenue'][ $currency ] ?? 0 ) + (float) $amount, 3 );
		} else {
			$stats[ $day ][ $what ] = (int) ( $stats[ $day ][ $what ] ?? 0 ) + (int) $amount;
		}
		ksort( $stats );
		$stats = array_slice( $stats, -60, null, true );
		update_option( self::STATS, $stats, false );
	}

	public static function totals( $days = 30 ) {
		$stats = get_option( self::STATS, array() );
		$since = gmdate( 'Y-m-d', time() - ( (int) $days - 1 ) * DAY_IN_SECONDS );
		$out   = array( 'started' => 0, 'sent' => 0, 'opened' => 0, 'orders' => 0, 'items' => 0, 'revenue' => array() );
		foreach ( is_array( $stats ) ? $stats : array() as $day => $row ) {
			if ( $day < $since || ! is_array( $row ) ) {
				continue;
			}
			foreach ( array( 'started', 'sent', 'opened', 'orders', 'items' ) as $key ) {
				$out[ $key ] += (int) ( $row[ $key ] ?? 0 );
			}
			foreach ( (array) ( $row['revenue'] ?? array() ) as $currency => $amount ) {
				$out['revenue'][ $currency ] = ( $out['revenue'][ $currency ] ?? 0 ) + (float) $amount;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	   My Account → Refills
	   ------------------------------------------------------------------ */

	public static function query_vars( $vars ) {
		if ( self::enabled() ) {
			$vars[ self::ENDPOINT ] = self::ENDPOINT;
		}
		return $vars;
	}

	/** New endpoint after an in-place update (no activation hook runs): flush once if WooCommerce has no rule for it. */
	public static function ensure_rewrite() {
		if ( ! self::enabled() || is_admin() || wp_doing_ajax() || get_option( 'qil_refill_rewrite' ) === QIL_VERSION ) {
			return;
		}
		$rules = get_option( 'rewrite_rules' );
		if ( is_array( $rules ) && false === strpos( implode( ' ', array_keys( $rules ) ), self::ENDPOINT ) && function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
		update_option( 'qil_refill_rewrite', QIL_VERSION, false );
	}

	public static function menu( $items ) {
		if ( ! self::visible() || ! is_array( $items ) ) {
			return $items;
		}
		$label = self::t( 'Refills', 'التجديد التلقائي' );
		$out   = array();
		foreach ( $items as $key => $text ) {
			$out[ $key ] = $text;
			if ( 'orders' === $key ) {
				$out[ self::ENDPOINT ] = $label;
			}
		}
		if ( ! isset( $out[ self::ENDPOINT ] ) ) {
			$out = array_slice( $out, 0, 1, true ) + array( self::ENDPOINT => $label ) + array_slice( $out, 1, null, true );
		}
		return $out;
	}

	public static function title( $title ) {
		return self::t( 'Refills', 'التجديد التلقائي' );
	}

	/** The account's paid orders (newest 30), read once per request. */
	private static function history( $user_id ) {
		static $memo = array();
		if ( ! isset( $memo[ $user_id ] ) ) {
			$memo[ $user_id ] = function_exists( 'qil_repeat_orders' ) ? (array) qil_repeat_orders( (int) $user_id ) : array();
		}
		return $memo[ $user_id ];
	}

	/** Account lines that can start a plan, newest first, one per product selection. */
	public static function suggestions( $user_id, array $plans, $limit = 6 ) {
		$out = array();
		foreach ( self::history( $user_id ) as $order ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
					continue;
				}
				$id = self::plan_id( $item->get_product_id(), $item->get_variation_id() );
				if ( isset( $plans[ $id ] ) || isset( $out[ $id ] ) ) {
					continue;
				}
				$fit = self::eligible( $order, $item );
				if ( $fit ) {
					$out[ $id ] = $fit + array( 'orderId' => (int) $order->get_id(), 'itemId' => (int) $item->get_id(), 'paidAt' => self::order_time( $order ) );
				}
				if ( count( $out ) >= $limit ) {
					break 2;
				}
			}
		}
		return $out;
	}

	/** Median days between the account's paid orders of this exact selection (needs three orders). */
	public static function rhythm( $user_id, $plan_id ) {
		$times = array();
		foreach ( self::history( $user_id ) as $order ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( is_a( $item, 'WC_Order_Item_Product' ) && self::plan_id( $item->get_product_id(), $item->get_variation_id() ) === $plan_id ) {
					$times[] = self::order_time( $order );
					break;
				}
			}
		}
		if ( count( $times ) < 3 ) {
			return 0;
		}
		sort( $times );
		$gaps = array();
		for ( $i = 1, $n = count( $times ); $i < $n; $i++ ) {
			$gaps[] = ( $times[ $i ] - $times[ $i - 1 ] ) / DAY_IN_SECONDS;
		}
		sort( $gaps );
		$median = $gaps[ (int) floor( ( count( $gaps ) - 1 ) / 2 ) ];
		return $median >= 7 && $median <= 120 ? (int) round( $median ) : 0;
	}

	private static function intervals( $current, $extra = 0 ) {
		$list = array_merge( self::INTERVALS, array( (int) $current ), $extra ? array( (int) $extra ) : array() );
		$list = array_values( array_unique( array_filter( $list, static function ( $days ) {
			return $days >= 7 && $days <= 120;
		} ) ) );
		sort( $list );
		return $list;
	}

	private static function nonce_field( $user_id ) {
		return '<input type="hidden" name="qil_refill_nonce" value="' . esc_attr( wp_create_nonce( 'qil_refill_' . (int) $user_id ) ) . '">';
	}

	private static function action_button( $op, $label, $plan_id, $user_id, $class = '' ) {
		return '<form method="post" class="qil-refill-inline">' . self::nonce_field( $user_id )
			. '<input type="hidden" name="qil_refill_op" value="' . esc_attr( $op ) . '"><input type="hidden" name="plan" value="' . esc_attr( $plan_id ) . '">'
			. '<button type="submit" class="qil-refill-button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	private static function interval_form( $user_id, $plan_id, $current, $extra, $ar ) {
		$options = '';
		foreach ( self::intervals( $current, $extra ) as $days ) {
			$options .= '<option value="' . esc_attr( $days ) . '"' . selected( $days, (int) $current, false ) . '>' . esc_html( sprintf( self::t( 'Every %s', 'كل %s', $ar ), self::days_label( $days, $ar ) ) ) . '</option>';
		}
		$field = 'qil-refill-interval-' . str_replace( '-', '_', $plan_id );
		return '<form method="post" class="qil-refill-interval">' . self::nonce_field( $user_id )
			. '<input type="hidden" name="qil_refill_op" value="interval"><input type="hidden" name="plan" value="' . esc_attr( $plan_id ) . '">'
			. '<label class="screen-reader-text" for="' . esc_attr( $field ) . '">' . esc_html( self::t( 'Refill interval', 'فترة التجديد', $ar ) ) . '</label>'
			. '<select id="' . esc_attr( $field ) . '" name="days">' . $options . '</select>'
			. '<button type="submit" class="qil-refill-button is-quiet">' . esc_html( self::t( 'Save', 'حفظ', $ar ) ) . '</button></form>';
	}

	private static function thumb( $product, $parent ) {
		$id = $product->get_image_id() ?: $parent->get_image_id();
		if ( ! $id || ! function_exists( 'wp_get_attachment_image' ) ) {
			return '<span class="qil-refill-thumb" aria-hidden="true"></span>';
		}
		return '<span class="qil-refill-thumb">' . wp_get_attachment_image( $id, 'woocommerce_thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) ) . '</span>';
	}

	public static function account() {
		$user_id = (int) get_current_user_id();
		if ( ! self::visible() || ! $user_id ) {
			return;
		}
		echo self::account_markup( $user_id, time() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while built.
	}

	public static function account_markup( $user_id, $now ) {
		$ar    = self::is_ar();
		$plans = self::plans( $user_id );
		$lead  = (int) self::settings()['lead'] * DAY_IN_SECONDS;
		$rows  = '';
		$due   = array();
		foreach ( array_reverse( $plans, true ) as $id => $plan ) {
			$selection = self::selection( $plan, $user_id );
			if ( ! $selection ) {
				$rows .= '<li class="qil-refill-plan is-gone"><div class="qil-refill-main"><span class="qil-refill-thumb" aria-hidden="true"></span><div class="qil-refill-copy"><h3>' . esc_html( self::t( 'A product that is no longer available', 'منتج لم يعد متوفراً', $ar ) ) . '</h3><p class="qil-refill-when">' . esc_html( self::t( 'It cannot be refilled as it was. Remove it, or choose a replacement in the store.', 'لا يمكن تجديده كما كان. احذفه أو اختر بديلاً من المتجر.', $ar ) ) . '</p></div></div><div class="qil-refill-actions">' . self::action_button( 'remove', self::t( 'Remove', 'حذف', $ar ), $id, $user_id, 'is-quiet' ) . '</div></li>';
				continue;
			}
			$parent  = $selection['parent'];
			$active  = 'active' === $plan['status'];
			$left    = (int) ceil( ( (int) $plan['nextAt'] - $now ) / DAY_IN_SECONDS );
			$is_due  = $active && $now >= (int) $plan['nextAt'] - $lead;
			if ( $is_due && $selection['canAdd'] ) {
				$due[] = $id;
			}
			if ( ! $active ) {
				$when = 'idle' === ( $plan['paused'] ?? '' )
					? self::t( 'Paused after three reminders without a refill. Resume when you need it again.', 'متوقف بعد ثلاثة تذكيرات دون تجديد. استأنفه عندما تحتاجه مجدداً.', $ar )
					: self::t( 'Paused. No reminders until you resume.', 'متوقف مؤقتاً. لا تذكيرات حتى تستأنف.', $ar );
			} elseif ( $left > 0 ) {
				$when = sprintf( self::t( 'Runs low around %1$s · in %2$s', 'ينفد تقريباً في %1$s · بعد %2$s', $ar ), self::date_label( (int) $plan['nextAt'], $ar ), self::days_label( $left, $ar ) );
			} else {
				$when = self::t( 'Probably running low now', 'غالباً على وشك النفاد الآن', $ar );
			}
			$rhythm = $active ? self::rhythm( $user_id, $id ) : 0;
			$hint   = '';
			if ( $rhythm && abs( $rhythm - (int) $plan['interval'] ) >= max( 5, (int) round( $plan['interval'] * 0.2 ) ) ) {
				$hint = '<form method="post" class="qil-refill-hint">' . self::nonce_field( $user_id ) . '<input type="hidden" name="qil_refill_op" value="interval"><input type="hidden" name="plan" value="' . esc_attr( $id ) . '"><input type="hidden" name="days" value="' . esc_attr( $rhythm ) . '">'
					. '<span>' . esc_html( sprintf( self::t( 'You usually reorder every %s.', 'عادةً تعيد الطلب كل %s.', $ar ), self::days_label( $rhythm, $ar ) ) ) . '</span> '
					. '<button type="submit" class="qil-refill-link">' . esc_html( sprintf( self::t( 'Use %s', 'استخدم %s', $ar ), self::days_label( $rhythm, $ar ) ) ) . '</button></form>';
			}
			$facts = array( sprintf( self::t( 'Qty %d', 'الكمية %d', $ar ), (int) $plan['quantity'] ), sprintf( self::t( '%s today', '%s اليوم', $ar ), self::money( $selection['product'] ) ) );
			if ( (int) $plan['estimate'] ) {
				$facts[] = sprintf( self::t( 'about %s per pack', 'نحو %s للعبوة', $ar ), self::days_label( (int) $plan['estimate'], $ar ) );
			}
			if ( (int) $plan['refills'] ) {
				$facts[] = sprintf( self::t( 'refilled %d×', 'جُدّد %d مرة', $ar ), (int) $plan['refills'] );
			}
			$actions = '';
			if ( $active ) {
				$actions .= $selection['canAdd']
					? self::action_button( 'refill', self::t( 'Refill now', 'جدّد الآن', $ar ), $id, $user_id, $is_due ? 'is-primary' : '' )
					: '<span class="qil-refill-out">' . esc_html( self::t( 'Out of stock: we will remind you when it is back', 'غير متوفر: سنذكّرك عند توفره', $ar ) ) . '</span>';
				$actions .= self::action_button( 'skip', self::t( 'Skip next', 'تخطَّ القادم', $ar ), $id, $user_id, 'is-quiet' );
				$actions .= self::action_button( 'pause', self::t( 'Pause', 'إيقاف مؤقت', $ar ), $id, $user_id, 'is-quiet' );
			} else {
				$actions .= self::action_button( 'resume', self::t( 'Resume', 'استئناف', $ar ), $id, $user_id );
			}
			$actions .= self::action_button( 'remove', self::t( 'Remove', 'حذف', $ar ), $id, $user_id, 'is-quiet' );
			$rows    .= '<li class="qil-refill-plan' . ( $is_due ? ' is-due' : '' ) . ( $active ? '' : ' is-paused' ) . '">'
				. '<div class="qil-refill-main">' . self::thumb( $selection['product'], $parent )
				. '<div class="qil-refill-copy"><h3><a href="' . esc_url( qil_localized_url( $parent->get_permalink(), $ar ) ) . '">' . esc_html( qil_clean_text( $parent->get_name() ) ) . '</a></h3>'
				. ( '' !== $selection['label'] ? '<p class="qil-refill-selection">' . esc_html( $selection['label'] ) . '</p>' : '' )
				. '<p class="qil-refill-when">' . ( $is_due ? '<b>' . esc_html( self::t( 'Due', 'حان الوقت', $ar ) ) . '</b> ' : '' ) . esc_html( $when ) . '</p>'
				. '<p class="qil-refill-facts">' . esc_html( implode( ' · ', $facts ) ) . '</p>' . $hint . '</div></div>'
				. '<div class="qil-refill-controls">' . ( $active ? self::interval_form( $user_id, $id, (int) $plan['interval'], $rhythm, $ar ) : '' ) . '<div class="qil-refill-actions">' . $actions . '</div></div></li>';
		}
		$suggest = self::suggestions( $user_id, $plans );
		$offers  = '';
		foreach ( $suggest as $id => $fit ) {
			$selection = $fit['selection'];
			$options   = '';
			foreach ( self::intervals( $fit['interval'] ) as $days ) {
				$options .= '<option value="' . esc_attr( $days ) . '"' . selected( $days, (int) $fit['interval'], false ) . '>' . esc_html( sprintf( self::t( 'Every %s', 'كل %s', $ar ), self::days_label( $days, $ar ) ) ) . '</option>';
			}
			$field   = 'qil-refill-new-' . str_replace( '-', '_', $id );
			$offers .= '<li class="qil-refill-offer"><div class="qil-refill-main">' . self::thumb( $selection['product'], $selection['parent'] )
				. '<div class="qil-refill-copy"><h3>' . esc_html( qil_clean_text( $selection['parent']->get_name() ) ) . '</h3>'
				. ( '' !== $selection['label'] ? '<p class="qil-refill-selection">' . esc_html( $selection['label'] ) . '</p>' : '' )
				. '<p class="qil-refill-when">' . esc_html( $fit['estimate']
					? sprintf( self::t( 'One pack lasts about %s (label servings).', 'العبوة تكفي نحو %s (حسب الحصص على الملصق).', $ar ), self::days_label( (int) $fit['estimate'], $ar ) )
					: self::t( 'Choose how often you need it.', 'اختر كم مرة تحتاجه.', $ar ) ) . '</p></div></div>'
				. '<form method="post" class="qil-refill-start">' . self::nonce_field( $user_id )
				. '<input type="hidden" name="qil_refill_op" value="add"><input type="hidden" name="order" value="' . esc_attr( $fit['orderId'] ) . '"><input type="hidden" name="item" value="' . esc_attr( $fit['itemId'] ) . '">'
				. '<label class="screen-reader-text" for="' . esc_attr( $field ) . '">' . esc_html( self::t( 'Refill interval', 'فترة التجديد', $ar ) ) . '</label><select id="' . esc_attr( $field ) . '" name="days">' . $options . '</select>'
				. '<button type="submit" class="qil-refill-button is-primary">' . esc_html( self::t( 'Remind me', 'ذكّرني', $ar ) ) . '</button></form></li>';
		}
		$out  = '<div class="qil-refill" dir="' . ( $ar ? 'rtl' : 'ltr' ) . '" lang="' . ( $ar ? 'ar' : 'en' ) . '" data-qil-refill>';
		$out .= '<header class="qil-refill-head"><div><p class="qil-refill-kicker">' . esc_html( self::t( 'Qimia Refill', 'تجديد كيميا', $ar ) ) . '</p><h2>' . esc_html( self::t( 'Never run out of your routine', 'لا تدع روتينك ينفد', $ar ) ) . '</h2>'
			. '<p>' . esc_html( self::t( 'We email you a few days before a product runs low. One tap puts the same flavour and size in your cart at today’s price; you check out as usual. Nothing is charged automatically.', 'نرسل لك بريداً قبل نفاد المنتج بأيام. بلمسة واحدة يضاف نفس المنتج بنفس النكهة والحجم إلى سلتك بسعر اليوم، ثم تكمل الدفع كالمعتاد. لا يُخصم أي مبلغ تلقائياً.', $ar ) ) . '</p></div>';
		if ( count( $due ) > 1 ) {
			$out .= '<form method="post" class="qil-refill-all">' . self::nonce_field( $user_id ) . '<input type="hidden" name="qil_refill_op" value="refill"><input type="hidden" name="plan" value="due"><button type="submit" class="qil-refill-button is-primary">' . esc_html( sprintf( self::t( 'Refill all %d due', 'جدّد الكل (%d)', $ar ), count( $due ) ) ) . '</button></form>';
		}
		$out .= '</header>';
		if ( $rows ) {
			$out .= '<section aria-labelledby="qil-refill-yours"><h3 id="qil-refill-yours" class="qil-refill-title">' . esc_html( self::t( 'Your refills', 'تجديداتك', $ar ) ) . '</h3><ul class="qil-refill-list">' . $rows . '</ul></section>';
		}
		if ( $offers ) {
			$out .= '<section aria-labelledby="qil-refill-start"><h3 id="qil-refill-start" class="qil-refill-title">' . esc_html( $rows ? self::t( 'Add from your orders', 'أضف من طلباتك', $ar ) : self::t( 'Start with something you already use', 'ابدأ بمنتج تستخدمه بالفعل', $ar ) ) . '</h3><ul class="qil-refill-list">' . $offers . '</ul></section>';
		}
		if ( ! $rows && ! $offers ) {
			$out .= '<div class="qil-refill-empty"><p>' . esc_html( self::t( 'Refills appear here after you buy a supplement you use up, like protein, creatine or vitamins.', 'تظهر التجديدات هنا بعد شراء مكمل يُستهلك، مثل البروتين أو الكرياتين أو الفيتامينات.', $ar ) ) . '</p><a class="qil-refill-button is-primary" href="' . esc_url( qil_localized_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ), $ar ) ) . '">' . esc_html( self::t( 'Browse the store', 'تصفح المتجر', $ar ) ) . '</a></div>';
		}
		return $out . '</div>';
	}

	/* ------------------------------------------------------------------
	   Order received: start a plan where the routine begins
	   ------------------------------------------------------------------ */

	public static function thankyou( $order_id ) {
		$user_id = (int) get_current_user_id();
		$order   = wc_get_order( $order_id );
		if ( ! self::visible() || empty( self::settings()['thankyou'] ) || ! $user_id || ! self::owns( $order, $user_id, true ) ) {
			return;
		}
		echo self::thankyou_markup( $user_id, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while built.
	}

	public static function thankyou_markup( $user_id, $order ) {
		$ar    = self::is_ar();
		$plans = self::plans( $user_id );
		$items = '';
		$count = 0;
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$fit = is_a( $item, 'WC_Order_Item_Product' ) ? self::eligible( $order, $item ) : null;
			if ( ! $fit || ++$count > 4 ) {
				continue;
			}
			$selection = $fit['selection'];
			$id        = self::plan_id( $selection['parent']->get_id(), $selection['variationId'] );
			$name      = '<strong>' . esc_html( qil_clean_text( $selection['parent']->get_name() ) ) . '</strong>' . ( '' !== $selection['label'] ? ' <span>' . esc_html( $selection['label'] ) . '</span>' : '' );
			if ( isset( $plans[ $id ] ) && 'active' === $plans[ $id ]['status'] ) {
				$items .= '<li class="is-on"><p>' . $name . '</p><p class="qil-refill-when">' . esc_html( sprintf( self::t( 'Reminder on · every %s', 'التذكير مفعّل · كل %s', $ar ), self::days_label( (int) $plans[ $id ]['interval'], $ar ) ) ) . '</p></li>';
				continue;
			}
			$options = '';
			foreach ( self::intervals( $fit['interval'] ) as $days ) {
				$options .= '<option value="' . esc_attr( $days ) . '"' . selected( $days, (int) $fit['interval'], false ) . '>' . esc_html( sprintf( self::t( 'Every %s', 'كل %s', $ar ), self::days_label( $days, $ar ) ) ) . '</option>';
			}
			$field  = 'qil-refill-ty-' . str_replace( '-', '_', $id );
			$items .= '<li><p>' . $name . '</p>'
				. ( $fit['estimate'] ? '<p class="qil-refill-when">' . esc_html( sprintf( self::t( 'Lasts about %s', 'يكفي نحو %s', $ar ), self::days_label( (int) $fit['estimate'], $ar ) ) ) . '</p>' : '' )
				. '<form method="post" class="qil-refill-start">' . self::nonce_field( $user_id )
				. '<input type="hidden" name="qil_refill_op" value="add"><input type="hidden" name="order" value="' . esc_attr( $order->get_id() ) . '"><input type="hidden" name="item" value="' . esc_attr( $item->get_id() ) . '">'
				. '<label class="screen-reader-text" for="' . esc_attr( $field ) . '">' . esc_html( self::t( 'Refill interval', 'فترة التجديد', $ar ) ) . '</label><select id="' . esc_attr( $field ) . '" name="days">' . $options . '</select>'
				. '<button type="submit" class="qil-refill-button is-primary">' . esc_html( self::t( 'Remind me', 'ذكّرني', $ar ) ) . '</button></form></li>';
		}
		if ( ! $items ) {
			return '';
		}
		return '<section class="qil-refill qil-refill-thankyou" dir="' . ( $ar ? 'rtl' : 'ltr' ) . '" lang="' . ( $ar ? 'ar' : 'en' ) . '" aria-labelledby="qil-refill-ty-title" data-qil-refill>'
			. '<p class="qil-refill-kicker">' . esc_html( self::t( 'Qimia Refill', 'تجديد كيميا', $ar ) ) . '</p>'
			. '<h2 id="qil-refill-ty-title">' . esc_html( self::t( 'Want a reminder before it runs out?', 'هل تريد تذكيراً قبل أن ينفد؟', $ar ) ) . '</h2>'
			. '<p>' . esc_html( self::t( 'One email a few days before, with a one-tap refill of the same flavour and size. No subscription and nothing charged automatically; change or stop it any time in your account.', 'بريد واحد قبلها بأيام، مع تجديد بلمسة واحدة لنفس النكهة والحجم. لا اشتراك ولا خصم تلقائي؛ غيّره أو أوقفه متى شئت من حسابك.', $ar ) ) . '</p>'
			. '<ul class="qil-refill-quick">' . $items . '</ul>'
			. '<p class="qil-refill-manage"><a href="' . esc_url( self::account_url( $ar ) ) . '">' . esc_html( self::t( 'Manage your refills', 'إدارة التجديدات', $ar ) ) . '</a></p></section>';
	}

	/* ------------------------------------------------------------------
	   Form posts (account page and order received page)
	   ------------------------------------------------------------------ */

	public static function handle() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['qil_refill_op'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
			return;
		}
		$user_id = (int) get_current_user_id();
		$ar      = self::is_ar();
		if ( ! self::visible() || ! $user_id ) {
			return;
		}
		$back = wp_get_referer() ?: self::account_url( $ar );
		if ( ! isset( $_POST['qil_refill_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['qil_refill_nonce'] ) ), 'qil_refill_' . $user_id ) ) {
			wc_add_notice( esc_html( self::t( 'That took too long. Please try again.', 'انتهت مهلة الطلب. حاول مرة أخرى.', $ar ) ), 'error' );
			self::redirect( $back );
		}
		$op   = sanitize_key( wp_unslash( $_POST['qil_refill_op'] ) );
		$plan = isset( $_POST['plan'] ) ? sanitize_text_field( wp_unslash( $_POST['plan'] ) ) : '';
		$days = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 0;
		if ( 'add' === $op ) {
			$result = self::create( $user_id, absint( $_POST['order'] ?? 0 ), absint( $_POST['item'] ?? 0 ), $days, $ar ? 'ar' : 'en' );
			if ( is_wp_error( $result ) ) {
				wc_add_notice( esc_html( 'limit' === $result->get_error_code()
					? sprintf( self::t( 'You can keep up to %d refills. Remove one first.', 'يمكنك الاحتفاظ بـ %d تجديدات كحد أقصى. احذف واحداً أولاً.', $ar ), self::MAX )
					: self::t( 'This product cannot be set up for refills.', 'لا يمكن ضبط التجديد لهذا المنتج.', $ar ) ), 'error' );
			} else {
				wc_add_notice( esc_html( sprintf( self::t( 'Done. We will remind you every %s.', 'تم. سنذكّرك كل %s.', $ar ), self::days_label( (int) $result['interval'], $ar ) ) ), 'success' );
			}
			self::redirect( $back );
		}
		if ( 'refill' === $op ) {
			$plans = self::plans( $user_id );
			$ids   = 'due' === $plan ? self::due_ids( $plans, time() ) : ( isset( $plans[ $plan ] ) ? array( $plan ) : array() );
			$result = self::fill_cart( $user_id, $ids );
			self::fill_notice( $result, $ar );
			self::redirect( $result['added'] || $result['present'] ? self::destination( $ar ) : $back );
		}
		if ( in_array( $op, array( 'interval', 'skip', 'pause', 'resume', 'remove' ), true ) && preg_match( '/^\d+-\d+$/', $plan ) ) {
			$ok = self::change( $user_id, $plan, $op, $days );
			$messages = array(
				'interval' => sprintf( self::t( 'Saved. Every %s from your last order.', 'تم الحفظ. كل %s من آخر طلب.', $ar ), self::days_label( $days, $ar ) ),
				'skip'     => self::t( 'Skipped. Your next reminder moved one interval later.', 'تم التخطي. تأجل تذكيرك القادم فترة واحدة.', $ar ),
				'pause'    => self::t( 'Paused. No reminders until you resume.', 'تم الإيقاف المؤقت. لا تذكيرات حتى تستأنف.', $ar ),
				'resume'   => self::t( 'Resumed.', 'تم الاستئناف.', $ar ),
				'remove'   => self::t( 'Removed. You will not get reminders for it.', 'تم الحذف. لن تصلك تذكيرات له.', $ar ),
			);
			wc_add_notice( esc_html( $ok ? $messages[ $op ] : self::t( 'That change could not be saved.', 'تعذّر حفظ هذا التغيير.', $ar ) ), $ok ? 'success' : 'error' );
			self::redirect( $back );
		}
	}

	private static function due_ids( array $plans, $now ) {
		$lead = (int) self::settings()['lead'] * DAY_IN_SECONDS;
		$ids  = array();
		foreach ( $plans as $id => $plan ) {
			if ( 'active' === $plan['status'] && $now >= (int) $plan['nextAt'] - $lead ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/* ------------------------------------------------------------------
	   Assets: the account and order received pages only
	   ------------------------------------------------------------------ */

	public static function assets() {
		if ( is_admin() || ! self::visible() ) {
			return;
		}
		$account  = function_exists( 'is_account_page' ) && is_account_page();
		$received = function_exists( 'is_order_received_page' ) && is_order_received_page();
		if ( ! $account && ! $received ) {
			return;
		}
		$file = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ! file_exists( QIL_DIR . 'assets/qil-refill.min.css' ) ? 'qil-refill.css' : 'qil-refill.min.css';
		wp_enqueue_style( 'qil-refill', QIL_URL . 'assets/' . $file, array(), QIL_VERSION );
	}

	/* ------------------------------------------------------------------
	   Admin (Settings → Qimia Intelligence Lab → Refills)
	   ------------------------------------------------------------------ */

	public static function admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = self::settings();
		$name   = static function ( $key ) {
			return self::OPTION . '[' . $key . ']';
		};
		$check  = static function ( $key, $label ) use ( $s, $name ) {
			return '<input type="hidden" name="' . esc_attr( $name( $key ) ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $name( $key ) ) . '" value="1" ' . checked( 1, (int) $s[ $key ], false ) . '> ' . esc_html( $label ) . '</label>';
		};
		$t30    = self::totals( 30 );
		$active = 0;
		$paused = 0;
		$soon   = 0;
		$users  = get_users( array( 'meta_key' => self::META, 'fields' => 'ID', 'number' => 2000 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		foreach ( (array) $users as $user_id ) {
			foreach ( self::plans( (int) $user_id ) as $plan ) {
				if ( 'active' === $plan['status'] ) {
					++$active;
					$soon += (int) $plan['nextAt'] <= time() + 7 * DAY_IN_SECONDS ? 1 : 0;
				} else {
					++$paused;
				}
			}
		}
		$revenue = array();
		foreach ( $t30['revenue'] as $currency => $amount ) {
			$revenue[] = number_format( (float) $amount, 3 ) . ' ' . $currency;
		}
		echo '<section class="qimia-admin-panel"><h2>Qimia Refill: repeat purchases</h2>';
		echo '<p>Shoppers choose which supplements to refill and how often. A few days before each runs low they get one email; its button puts the exact product, flavour and size from their last order in the cart at today\'s WooCommerce price and opens checkout. No card is stored, nothing is charged and no order is created automatically. A paid order with the product starts the next cycle by itself.</p>';
		if ( defined( 'QIL_REFILL_DISABLE' ) && QIL_REFILL_DISABLE ) {
			echo '<div class="notice notice-warning inline"><p><code>QIL_REFILL_DISABLE</code> is set: refills are off.</p></div>';
		}
		if ( ! self::mail_ready() && self::enabled() && ! empty( $s['email'] ) ) {
			echo '<div class="notice notice-info inline"><p>Reminder emails are sent from the live store only (this site address is not one of the production hosts, or the storefront switch is off).</p></div>';
		}
		echo '<table class="widefat striped" style="max-width:720px"><tbody>';
		foreach ( array(
			'Active refills'               => $active,
			'Paused'                       => $paused,
			'Running low within 7 days'    => $soon,
			'Started (30 days)'            => $t30['started'],
			'Reminder emails (30 days)'    => $t30['sent'],
			'Refill links opened (30 days)' => $t30['opened'],
			'Refill orders (30 days)'      => $t30['orders'],
			'Refill revenue (30 days)'     => $revenue ? implode( ' · ', $revenue ) : '0',
		) as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'qil_refill_settings' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">Refills</th><td>' . $check( 'enabled', 'Offer refill reminders (My Account → Refills, order received page)' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<tr><th scope="row">Order received page</th><td>' . $check( 'thankyou', 'Invite the shopper to turn on a reminder for consumables they just bought' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<tr><th scope="row">Reminder emails</th><td>' . $check( 'email', 'Email the reminder' ) . ' <p>Send it <input type="number" class="small-text" min="1" max="14" name="' . esc_attr( $name( 'lead' ) ) . '" value="' . esc_attr( $s['lead'] ) . '"> days before the estimated run-out.</p><p>' . $check( 'follow_up', 'One follow-up five days after the run-out when there was no refill' ) . '</p><p class="description">Estimate = payment date + two days for delivery + the interval the shopper chose (suggested from the label\'s verified serving count × quantity ÷ servings a day). After 14 days the cycle moves on; three unanswered cycles pause the plan. Sent through WooCommerce\'s mailer, hourly, at most one email per shopper per run.</p></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<tr><th scope="row">"Refill now" opens</th><td><label><input type="radio" name="' . esc_attr( $name( 'destination' ) ) . '" value="checkout" ' . checked( 'checkout', $s['destination'], false ) . '> Checkout</label> &nbsp; <label><input type="radio" name="' . esc_attr( $name( 'destination' ) ) . '" value="cart" ' . checked( 'cart', $s['destination'], false ) . '> Cart</label></td></tr>';
		echo '</tbody></table>';
		submit_button( 'Save refill settings' );
		echo '</form></section>';
	}
}
QIL_Refill::boot();
