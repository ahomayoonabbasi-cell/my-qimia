<?php
/**
 * Member hub for signed-in shoppers: the cashback wallet, "Running low?"
 * replenishment and the best ways to spend a cashback balance.
 *
 * Everything here is private and read live for the signed-in account only:
 * one idle-time request from the homepage or cart, never a page-cache entry,
 * never a guest request. Coupon codes never leave the server; "Shop with
 * cashback" applies the shopper's own coupon through WooCommerce's cart and
 * validation. Replenishment estimates use the exact serving count on the
 * product label and the purchased quantity; without a verified count no
 * estimate is shown.
 *
 * @package Qimia_Intelligence_Lab
 */
defined( 'ABSPATH' ) || exit;

final class QIL_Member {
	const SCHEMA  = 'qil-member/1';
	const PENDING = 'qil_wallet_pending';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'private_request' ), -9996 );
		add_action( 'wc_ajax_qil_member', array( __CLASS__, 'ajax' ) );
		add_action( 'wc_ajax_qil_wallet_apply', array( __CLASS__, 'apply' ) );
		add_action( 'woocommerce_after_calculate_totals', array( __CLASS__, 'apply_pending' ), 30 );
		add_filter( 'qil_boost_page_data', array( __CLASS__, 'page_data' ) );
	}

	public static function enabled() {
		return QIL_Boost::on( 'wallet' ) || QIL_Boost::on( 'reorder' );
	}

	public static function private_request() {
		$endpoint = isset( $_GET['wc-ajax'] ) && is_string( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $endpoint, array( 'qil_member', 'qil_wallet_apply' ), true ) && function_exists( 'qil_continuity_private_headers' ) ) {
			qil_continuity_private_headers();
		}
	}

	/** Same-origin POST with the storefront's request marker. */
	private static function allowed_request() {
		if ( class_exists( 'QIL_Personalization' ) && method_exists( 'QIL_Personalization', 'same_origin' ) ) {
			return QIL_Personalization::same_origin();
		}
		return function_exists( 'qil_continuity_request_allowed' ) && qil_continuity_request_allowed();
	}

	public static function page_data( $data ) {
		if ( ! self::enabled() || ! is_user_logged_in() ) {
			return $data;
		}
		$home = function_exists( 'qil_render_mode' ) && 'full' === qil_render_mode();
		$cart = function_exists( 'is_cart' ) && is_cart();
		if ( $home || $cart ) {
			$data['member'] = array( 'surface' => $home ? 'home' : 'cart' );
		}
		return $data;
	}

	/* ------------------------------------------------------------------
	   Cashback wallet
	   ------------------------------------------------------------------ */

	private static function emails( $user_id ) {
		$user   = get_userdata( $user_id );
		$emails = array();
		if ( $user ) {
			$emails[] = (string) $user->user_email;
		}
		$emails[] = (string) get_user_meta( $user_id, 'billing_email', true );
		$emails   = array_filter( array_map( static function ( $email ) {
			$email = strtolower( trim( $email ) );
			return is_email( $email ) ? $email : '';
		}, $emails ) );
		return array_values( array_unique( $emails ) );
	}

	/** Issuer marker on the coupon, when the cashback plugin sets one. */
	private static function issuer_marked( $coupon_id, $coupon ) {
		foreach ( array_keys( (array) get_post_meta( $coupon_id ) ) as $key ) {
			if ( preg_match( '/^_?qcb2/i', (string) $key ) ) {
				return true;
			}
		}
		return (bool) preg_match( '/cash\s*back|كاش\s*باك/iu', (string) $coupon->get_description() );
	}

	private static function coupon_currency( $coupon_id ) {
		foreach ( array( '_qcb2_currency', 'qcb2_currency', '_coupon_currency', 'coupon_currency' ) as $key ) {
			$value = strtoupper( trim( (string) get_post_meta( $coupon_id, $key, true ) ) );
			if ( preg_match( '/^[A-Z]{3}$/', $value ) ) {
				return $value;
			}
		}
		return strtoupper( (string) get_option( 'woocommerce_currency', 'OMR' ) );
	}

	/**
	 * Unused, unexpired cashback coupons issued to this account's email.
	 * The cashback issuer (or My Qimia) can supply them directly through the
	 * qil_cashback_wallet_coupons filter; otherwise WooCommerce's own coupon
	 * records are read. A coupon counts as cashback when the issuer marked it,
	 * or, in the default mode, when it has the cashback shape: fixed amount,
	 * restricted to this email, single use and a real expiry date.
	 *
	 * @return array<int,array{id:int,code:string,amount:float,currency:string,omr:float,expires:int,minimum:float}>
	 */
	public static function coupons( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id < 1 || $user_id !== (int) get_current_user_id() || ! class_exists( 'WC_Coupon' ) ) {
			return array();
		}
		$external = apply_filters( 'qil_cashback_wallet_coupons', null, $user_id );
		$ids      = array();
		if ( is_array( $external ) ) {
			foreach ( $external as $row ) {
				$id = is_array( $row ) ? absint( $row['id'] ?? 0 ) : absint( $row );
				if ( $id ) {
					$ids[] = $id;
				}
			}
		} else {
			global $wpdb;
			$emails = self::emails( $user_id );
			if ( ! $emails ) {
				return array();
			}
			$likes = array();
			foreach ( $emails as $email ) {
				$likes[] = $wpdb->prepare( 'pm.meta_value LIKE %s', '%' . $wpdb->esc_like( '"' . $email . '"' ) . '%' );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Each LIKE clause is prepared above.
			$ids = $wpdb->get_col( "SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'customer_email' WHERE p.post_type = 'shop_coupon' AND p.post_status = 'publish' AND (" . implode( ' OR ', $likes ) . ') ORDER BY p.ID DESC LIMIT 30' );
		}
		$strict = 'strict' === QIL_Boost::settings()['wallet_mode'];
		$emails = self::emails( $user_id );
		$now    = time();
		$rows   = array();
		foreach ( array_slice( array_values( array_unique( array_map( 'absint', (array) $ids ) ) ), 0, 30 ) as $id ) {
			$coupon = new WC_Coupon( $id );
			if ( ! $coupon->get_id() || 'fixed_cart' !== $coupon->get_discount_type() || (float) $coupon->get_amount() <= 0 ) {
				continue;
			}
			$restricted = array_map( 'strtolower', (array) $coupon->get_email_restrictions() );
			if ( ! array_intersect( $restricted, $emails ) ) {
				continue; // Never someone else's coupon, whatever a filter returned.
			}
			$expires = $coupon->get_date_expires();
			$expires = $expires ? (int) $expires->getTimestamp() : 0;
			if ( $expires && $expires <= $now ) {
				continue;
			}
			$limit = (int) $coupon->get_usage_limit();
			if ( $limit > 0 && (int) $coupon->get_usage_count() >= $limit ) {
				continue;
			}
			$used_by = array_map( 'strtolower', array_map( 'strval', (array) $coupon->get_used_by() ) );
			$uses    = count( array_filter( $used_by, static function ( $who ) use ( $user_id, $emails ) {
				return (string) $user_id === $who || in_array( $who, $emails, true );
			} ) );
			$per_user = (int) $coupon->get_usage_limit_per_user();
			if ( ( $per_user > 0 && $uses >= $per_user ) || ( $limit <= 1 && $uses > 0 ) ) {
				continue;
			}
			$marked = self::issuer_marked( $id, $coupon );
			$shaped = $expires > 0 && ( 1 === $limit || 1 === $per_user );
			if ( ! $marked && ( $strict || ! $shaped ) && ! is_array( $external ) ) {
				continue;
			}
			$currency = self::coupon_currency( $id );
			$rate     = 'OMR' === $currency ? 1.0 : ( class_exists( 'QIL_Cashback' ) ? QIL_Cashback::rate( $currency ) : null );
			if ( ! $rate ) {
				continue; // An amount that cannot be stated truthfully is not shown.
			}
			$rows[] = array(
				'id'       => $id,
				'code'     => (string) $coupon->get_code(),
				'amount'   => (float) $coupon->get_amount(),
				'currency' => $currency,
				'omr'      => round( (float) $coupon->get_amount() / $rate, 3 ),
				'expires'  => $expires,
				'minimum'  => (float) $coupon->get_minimum_amount(),
			);
		}
		// Use the one that expires first; ties go to the larger amount.
		usort( $rows, static function ( $a, $b ) {
			$ea = $a['expires'] ?: PHP_INT_MAX;
			$eb = $b['expires'] ?: PHP_INT_MAX;
			return ( $ea <=> $eb ) ?: ( $b['omr'] <=> $a['omr'] ) ?: ( $a['id'] <=> $b['id'] );
		} );
		return $rows;
	}

	/** Money in the shopper's currency, formatted like the public cashback section. */
	private static function money( $omr ) {
		return QIL_Boost::reward_text( $omr );
	}

	/**
	 * A coupon amount. When the coupon is already in the shopper's currency it
	 * is exact (the cashback section's wording, the prices' Western digits);
	 * otherwise it is converted with the site rate and marked approximate by
	 * QIL_Cashback::money().
	 */
	private static function exact_money( $amount, $currency ) {
		$ar     = QIL_Boost::is_ar();
		$number = rtrim( rtrim( number_format( (float) $amount, 3, '.', ',' ), '0' ), '.' );
		$units  = array( 'OMR' => 'ر.ع', 'AED' => 'د.إ', 'SAR' => 'ر.س', 'QAR' => 'ر.ق', 'KWD' => 'د.ك', 'BHD' => 'د.ب' );
		return $number . ' ' . ( $ar && isset( $units[ $currency ] ) ? $units[ $currency ] : $currency );
	}

	private static function coupon_money( array $row ) {
		$market = QIL_Boost::market();
		return $row['currency'] === $market['currency'] ? self::exact_money( $row['amount'], $row['currency'] ) : self::money( $row['omr'] );
	}

	private static function minimum_money( array $row ) {
		if ( $row['minimum'] <= 0 ) {
			return '';
		}
		$market = QIL_Boost::market();
		if ( $row['currency'] === $market['currency'] ) {
			return self::exact_money( $row['minimum'], $row['currency'] );
		}
		$rate = 'OMR' === $row['currency'] ? 1.0 : ( class_exists( 'QIL_Cashback' ) ? QIL_Cashback::rate( $row['currency'] ) : null );
		return $rate ? self::money( $row['minimum'] / $rate ) : '';
	}

	/** Public-safe wallet summary: amounts and dates, never a coupon code. */
	public static function wallet( $user_id ) {
		if ( ! QIL_Boost::on( 'wallet' ) ) {
			return null;
		}
		$rows = self::coupons( $user_id );
		if ( ! $rows ) {
			return null;
		}
		$best    = $rows[0];
		$total   = array_sum( array_column( $rows, 'omr' ) );
		$applied = array_map( 'strtolower', function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_applied_coupons() : array() );
		$status  = 'ready';
		foreach ( $rows as $row ) {
			if ( in_array( strtolower( $row['code'] ), $applied, true ) ) {
				$status = 'applied';
				$best   = $row;
				break;
			}
		}
		$pending = function_exists( 'WC' ) && WC()->session ? WC()->session->get( self::PENDING ) : null;
		if ( 'ready' === $status && is_array( $pending ) && (int) ( $pending['id'] ?? 0 ) === $best['id'] ) {
			$status = 'pending';
		}
		$days = $best['expires'] ? max( 0, (int) ceil( ( $best['expires'] - time() ) / DAY_IN_SECONDS ) ) : null;
		$same  = 1 === count( array_unique( array_column( $rows, 'currency' ) ) ) && $rows[0]['currency'] === QIL_Boost::market()['currency'];
		return array(
			'count'      => count( $rows ),
			'total'      => $same ? self::exact_money( array_sum( array_column( $rows, 'amount' ) ), $rows[0]['currency'] ) : self::money( $total ),
			'totalOmr'   => round( $total, 3 ),
			'best'       => array(
				'id'        => (int) $best['id'],
				'amount'    => self::coupon_money( $best ),
				'amountOmr' => (float) $best['omr'],
				'expiresAt' => (int) $best['expires'],
				'daysLeft'  => $days,
				'minimum'   => self::minimum_money( $best ),
			),
			'status'     => $status,
			'oneAtATime' => count( $rows ) > 1,
			// Each credit is used on its own order: show them (never their codes).
			'credits'    => array_map(
				static function ( $row ) {
					return array(
						'amount'   => self::coupon_money( $row ),
						'daysLeft' => $row['expires'] ? max( 0, (int) ceil( ( $row['expires'] - time() ) / DAY_IN_SECONDS ) ) : null,
						'minimum'  => self::minimum_money( $row ),
					);
				},
				array_slice( $rows, 0, 4 )
			),
		);
	}

	/* ------------------------------------------------------------------
	   Shop with cashback
	   ------------------------------------------------------------------ */

	private static function respond( array $data, $status = 200 ) {
		$data['schema'] = self::SCHEMA;
		wp_send_json( $data, $status );
	}

	/** Mini-cart fragments after a cart change, like Buy Again's receipt. */
	private static function fragments() {
		if ( ! function_exists( 'woocommerce_mini_cart' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}
		$level = ob_get_level();
		try {
			ob_start();
			woocommerce_mini_cart();
			$mini = ob_get_clean();
			return array(
				'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini . '</div>' ) ),
				'cart_hash' => WC()->cart->get_cart_hash(),
			);
		} catch ( Throwable $error ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			return array();
		}
	}

	public static function apply() {
		$ar = QIL_Boost::is_ar();
		if ( ! QIL_Boost::on( 'wallet' ) || ! self::allowed_request() || ! is_user_logged_in() ) {
			self::respond( array( 'error' => true, 'message' => $ar ? 'سجّل الدخول لاستخدام الكاش باك.' : 'Sign in to use your cashback.' ), 403 );
		}
		if ( ! check_ajax_referer( 'qil_wallet', 'nonce', false ) ) {
			self::respond( array( 'error' => true, 'code' => 'nonce', 'message' => $ar ? 'حدّث الصفحة ثم حاول مرة أخرى.' : 'Refresh the page and try again.' ), 403 );
		}
		$id  = isset( $_POST['coupon'] ) && is_scalar( $_POST['coupon'] ) ? absint( $_POST['coupon'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$own = null;
		foreach ( self::coupons( get_current_user_id() ) as $row ) {
			if ( $row['id'] === $id ) {
				$own = $row;
				break;
			}
		}
		if ( ! $own ) {
			self::respond( array( 'error' => true, 'message' => $ar ? 'هذا الكاش باك غير متاح الآن.' : 'This cashback is not available any more.' ), 409 );
		}
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart || ! WC()->session ) {
			self::respond( array( 'error' => true, 'message' => $ar ? 'تعذّر فتح السلة.' : 'Your cart is unavailable.' ), 503 );
		}
		$amount = self::coupon_money( $own );
		if ( WC()->cart->has_discount( $own['code'] ) ) {
			WC()->session->__unset( self::PENDING );
			self::respond( array( 'status' => 'applied', 'message' => sprintf( $ar ? 'كاش باك %s مطبّق على سلتك.' : '%s cashback is applied to your cart.', $amount ) ) + self::fragments() );
		}
		$minimum = self::minimum_money( $own );
		if ( WC()->cart->is_empty() ) {
			WC()->session->set( self::PENDING, array( 'id' => $own['id'], 'at' => time() ) );
			self::respond( array(
				'status'  => 'pending',
				'message' => $minimum
					? sprintf( $ar ? 'جاهز. سيُطبَّق كاش باك %1$s تلقائياً عندما تصل سلتك إلى %2$s.' : 'Ready. Your %1$s cashback applies automatically once your cart reaches %2$s.', $amount, $minimum )
					: sprintf( $ar ? 'جاهز. سيُطبَّق كاش باك %s تلقائياً عند إضافة منتج.' : 'Ready. Your %s cashback applies automatically when you add a product.', $amount ),
			) );
		}
		$coupon = new WC_Coupon( $own['id'] );
		$valid  = ( new WC_Discounts( WC()->cart ) )->is_coupon_valid( $coupon );
		if ( is_wp_error( $valid ) ) {
			if ( 108 === (int) $valid->get_error_code() || ( $minimum && false !== stripos( (string) $valid->get_error_message(), 'minimum' ) ) ) {
				WC()->session->set( self::PENDING, array( 'id' => $own['id'], 'at' => time() ) );
				self::respond( array(
					'status'  => 'pending',
					'message' => sprintf( $ar ? 'سيُطبَّق كاش باك %1$s تلقائياً عندما تصل سلتك إلى %2$s.' : 'Your %1$s cashback applies automatically once your cart reaches %2$s.', $amount, $minimum ?: '—' ),
				) );
			}
			self::respond( array( 'error' => true, 'message' => wp_strip_all_tags( (string) $valid->get_error_message() ) ), 409 );
		}
		// This response states the outcome itself; keep only notices that were
		// already waiting for the shopper, not the ones apply_coupon() queues.
		$before  = WC()->session->get( 'wc_notices', array() );
		$applied = WC()->cart->apply_coupon( $own['code'] );
		$errors  = function_exists( 'wc_get_notices' ) ? wc_get_notices( 'error' ) : array();
		WC()->session->set( 'wc_notices', is_array( $before ) ? $before : array() );
		if ( ! $applied ) {
			$previous = is_array( $before['error'] ?? null ) ? count( $before['error'] ) : 0;
			$new      = array_slice( (array) $errors, $previous );
			$first    = $new ? ( is_array( $new[0] ) ? ( $new[0]['notice'] ?? '' ) : $new[0] ) : '';
			self::respond( array( 'error' => true, 'message' => $first ? wp_strip_all_tags( (string) $first ) : ( $ar ? 'تعذّر تطبيق الكاش باك.' : 'Your cashback could not be applied.' ) ), 409 );
		}
		WC()->session->__unset( self::PENDING );
		WC()->cart->calculate_totals();
		self::respond( array( 'status' => 'applied', 'message' => sprintf( $ar ? 'تم! كاش باك %s مطبّق على هذا الطلب.' : 'Done — %s cashback is applied to this order.', $amount ) ) + self::fragments() );
	}

	/**
	 * A cashback the shopper asked to use is applied the moment the cart
	 * qualifies (first product, or its minimum spend). Cheap no-op otherwise.
	 */
	public static function apply_pending( $cart ) {
		static $running = false;
		if ( $running || ! function_exists( 'WC' ) || ! WC()->session || ! is_object( $cart ) || $cart->is_empty() ) {
			return;
		}
		$pending = WC()->session->get( self::PENDING );
		if ( ! is_array( $pending ) || empty( $pending['id'] ) ) {
			return;
		}
		$running = true;
		try {
			if ( ! is_user_logged_in() || ! QIL_Boost::on( 'wallet' ) || (int) ( $pending['at'] ?? 0 ) < time() - 14 * DAY_IN_SECONDS ) {
				WC()->session->__unset( self::PENDING );
				return;
			}
			$own = null;
			foreach ( self::coupons( get_current_user_id() ) as $row ) {
				if ( $row['id'] === (int) $pending['id'] ) {
					$own = $row;
					break;
				}
			}
			if ( ! $own || $cart->has_discount( $own['code'] ) ) {
				WC()->session->__unset( self::PENDING );
				return;
			}
			$valid = ( new WC_Discounts( $cart ) )->is_coupon_valid( new WC_Coupon( $own['id'] ) );
			if ( true === $valid && $cart->apply_coupon( $own['code'] ) ) {
				WC()->session->__unset( self::PENDING );
			}
		} catch ( Throwable $error ) {
			// Optional convenience: never block the cart.
		} finally {
			$running = false;
		}
	}

	/* ------------------------------------------------------------------
	   Running low? (replenishment)
	   ------------------------------------------------------------------ */

	/** Servings a day by product role; label counts only, never a dose claim. */
	public static function per_day( $role ) {
		$rates = (array) apply_filters( 'qil_reorder_servings_per_day', array(
			'pre_workout' => 5 / 7, // Training days.
			'fat_burner'  => 1.0,
			'protein'     => 1.0,
			'mass_gainer' => 1.0,
			'creatine'    => 1.0,
			'amino'       => 5 / 7,
			'electrolytes'=> 5 / 7,
		) );
		return max( 0.1, (float) ( $rates[ $role ] ?? 1.0 ) );
	}

	private static function replenishable( $role ) {
		return in_array( $role, (array) apply_filters( 'qil_reorder_roles', array( 'protein', 'creatine', 'pre_workout', 'fat_burner', 'mass_gainer', 'amino', 'magnesium', 'ashwagandha', 'multivitamin', 'omega3', 'vitamin_d', 'zinc', 'collagen', 'sleep', 'electrolytes', 'joint', 'daily_wellness' ) ), true );
	}

	/** Verified serving count for the exact bought item (flavour-only variations inherit). */
	private static function servings( $product, $parent ) {
		if ( ! function_exists( 'qil_filter_product_servings' ) ) {
			return 0;
		}
		$count = qil_filter_product_servings( $product );
		if ( null === $count && $product->is_type( 'variation' ) && $parent ) {
			foreach ( array_keys( (array) $parent->get_variation_attributes() ) as $name ) {
				if ( ! in_array( qil_goal_token( preg_replace( '/^pa_/i', '', (string) $name ) ), array( 'flavor', 'flavour', 'flavors', 'flavours', 'taste', 'نكهة', 'النكهة' ), true ) ) {
					return 0;
				}
			}
			$count = qil_filter_product_servings( $parent );
		}
		return $count ? (int) $count : 0;
	}

	/**
	 * Exact previous selections that are about to run out. Estimate = verified
	 * servings × quantity ÷ servings a day, from the payment date plus two days
	 * for delivery. Shown from $lead days before that date until $grace days
	 * after it, unless the product was bought again or is already in the cart.
	 */
	public static function running_low( $user_id, $locale ) {
		if ( ! QIL_Boost::on( 'reorder' ) || ! class_exists( 'QIL_Personalization' ) || ! function_exists( 'qil_repeat_selection' ) ) {
			return array();
		}
		$settings = QIL_Boost::settings();
		$lead     = (int) $settings['reorder_lead'] * DAY_IN_SECONDS;
		$grace    = (int) $settings['reorder_grace'] * DAY_IN_SECONDS;
		$now      = time();
		$in_cart  = QIL_Boost::cart_context()['parents'];
		$seen     = array();
		$rows     = array();
		$loaded   = 0;
		foreach ( QIL_Personalization::purchase_refs( (int) $user_id ) as $ref ) {
			$pid = (int) $ref['productId'];
			if ( isset( $seen[ $pid ] ) ) {
				continue; // A newer order of the same product already decided.
			}
			$seen[ $pid ] = true;
			$at           = (int) $ref['at'];
			if ( isset( $in_cart[ $pid ] ) || $at < $now - 400 * DAY_IN_SECONDS || $at > $now ) {
				continue;
			}
			$parent  = wc_get_product( $pid );
			$product = (int) $ref['variationId'] ? wc_get_product( (int) $ref['variationId'] ) : $parent;
			if ( ! $parent || ! $product ) {
				continue;
			}
			$slugs = array();
			$terms = get_the_terms( $pid, 'product_cat' );
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$slugs[] = (string) $term->slug;
			}
			list( $role ) = QIL_Boost::roles_for( $pid, $parent->get_name(), $slugs );
			$servings     = self::servings( $product, $parent );
			if ( ! self::replenishable( $role ) || $servings < 1 ) {
				continue;
			}
			if ( ++$loaded > 8 ) {
				break; // Bounded: at most eight order reads per response.
			}
			$order = wc_get_order( (int) $ref['orderId'] );
			if ( ! qil_repeat_order_owned( $order, (int) $user_id ) ) {
				continue;
			}
			$item = $order->get_item( (int) $ref['itemId'] );
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}
			$quantity = (float) $item->get_quantity() + (float) $order->get_qty_refunded_for_item( $item->get_id() );
			if ( $quantity < 1 ) {
				continue;
			}
			$per_day = self::per_day( $role );
			$days    = (int) floor( ( $servings * $quantity ) / $per_day );
			if ( $days < 7 || $days > 400 ) {
				continue;
			}
			$runs_out = $at + ( $days + 2 ) * DAY_IN_SECONDS;
			if ( $now < $runs_out - $lead || $now > $runs_out + $grace ) {
				continue;
			}
			$selection = qil_repeat_selection( $order, $item );
			if ( ! $selection || ! $selection['canAdd'] ) {
				continue;
			}
			$exact = $selection['product'];
			$image = function_exists( 'qil_image_data' ) ? qil_image_data( $exact->get_image_id() ?: $parent->get_image_id(), $parent->get_name() ) : array();
			$rows[] = array(
				'key'         => $order->get_id() . ':' . $item->get_id(),
				'orderId'     => (int) $order->get_id(),
				'itemId'      => (int) $item->get_id(),
				'productId'   => $pid,
				'variationId' => (int) $selection['variationId'],
				'name'        => qil_clean_text( $parent->get_name() ),
				'selection'   => (string) $selection['label'],
				'url'         => esc_url_raw( qil_localized_url( $parent->get_permalink(), 'ar' === $locale ) ),
				'image'       => $image ? array( 'src' => $image['src'], 'srcset' => $image['srcset'], 'width' => $image['width'], 'height' => $image['height'] ) : null,
				'price'       => qil_product_price_schema( $exact, QIL_Boost::market()['currency'], 0 )['current']['formattedHtml'] ?? '',
				'servings'    => (int) round( $servings * $quantity ),
				'perDay'      => round( $per_day, 2 ),
				'role'        => $role,
				'orderedAt'   => $at,
				'runsOutAt'   => $runs_out,
				'daysLeft'    => (int) floor( ( $runs_out - $now ) / DAY_IN_SECONDS ),
			);
			if ( count( $rows ) >= 3 ) {
				break;
			}
		}
		if ( 'ar' === $locale && $rows && function_exists( 'qil_translate_batch' ) ) {
			$names  = qil_translate_batch( array_column( $rows, 'name' ), 'product_title', true );
			$labels = qil_translate_batch( array_filter( array_column( $rows, 'selection' ) ), 'general', false );
			foreach ( $rows as &$row ) {
				$row['name']      = qil_translated( $row['name'], $names );
				$row['selection'] = qil_translated( $row['selection'], $labels );
			}
			unset( $row );
		}
		usort( $rows, static function ( $a, $b ) {
			return $a['runsOutAt'] <=> $b['runsOutAt'];
		} );
		return $rows;
	}

	/* ------------------------------------------------------------------
	   Best ways to use the balance
	   ------------------------------------------------------------------ */

	/**
	 * Products the shopper actually viewed or compared (verified live), then
	 * complements to what they bought (stack rules). Items whose price covers
	 * the coupon come first, so the credit can be used in full.
	 */
	private static function wallet_picks( $user_id, $locale, array $hints, $wallet ) {
		$pool  = QIL_Boost::pool();
		$by_id = array();
		foreach ( $pool as $row ) {
			$by_id[ (int) $row['id'] ] = $row;
		}
		$reasons = array();
		$order   = array();
		$add     = static function ( $id, $reason ) use ( &$reasons, &$order, $by_id ) {
			$id = (int) $id;
			if ( $id > 0 && isset( $by_id[ $id ] ) && ! isset( $reasons[ $id ] ) ) {
				$reasons[ $id ] = $reason;
				$order[]        = $id;
			}
		};
		if ( QIL_Personalization::allowed() ) {
			try {
				$views = apply_filters( 'qimia_my_qimia_recent_views_v1', array() );
			} catch ( Throwable $error ) {
				$views = array();
			}
			foreach ( array_slice( is_array( $views ) ? $views : array(), 0, 12 ) as $view ) {
				$add( is_array( $view ) ? ( $view['product_id'] ?? 0 ) : 0, array( 'kind' => 'viewed' ) );
			}
		}
		foreach ( $hints['compare'] as $id ) {
			$add( $id, array( 'kind' => 'compared' ) );
		}
		foreach ( $hints['recent'] as $id ) {
			$add( $id, array( 'kind' => 'viewed' ) );
		}
		$bought = array();
		$roles  = array();
		foreach ( array_slice( QIL_Personalization::purchase_refs( (int) $user_id ), 0, 12 ) as $ref ) {
			$pid            = (int) $ref['productId'];
			$bought[ $pid ] = true;
			if ( isset( $by_id[ $pid ] ) && $by_id[ $pid ]['o'] ) {
				$roles[ $by_id[ $pid ]['o'] ] = true;
			}
		}
		$rules = QIL_Boost::rules();
		foreach ( array_keys( $roles ) as $role ) {
			foreach ( (array) ( $rules[ $role ] ?? array() ) as $wanted ) {
				foreach ( $pool as $row ) {
					if ( in_array( $wanted, $row['g'], true ) && ! isset( $bought[ (int) $row['id'] ] ) ) {
						$add( $row['id'], array( 'kind' => 'pairs', 'role' => $role ) );
						break;
					}
				}
			}
		}
		$in_cart = QIL_Boost::cart_context()['parents'];
		$credit  = $wallet ? (float) $wallet['best']['amountOmr'] * ( QIL_Boost::market()['rate'] ?: 1.0 ) : 0.0;
		$ids     = array_values( array_filter( $order, static function ( $id ) use ( $in_cart ) {
			return ! isset( $in_cart[ $id ] );
		} ) );
		// Stable: covers-the-credit first, original relevance order within.
		$rank = array_flip( $ids );
		usort( $ids, static function ( $a, $b ) use ( $by_id, $credit, $rank ) {
			$ca = $by_id[ $a ]['p'] + 1e-9 >= $credit ? 0 : 1;
			$cb = $by_id[ $b ]['p'] + 1e-9 >= $credit ? 0 : 1;
			return ( $ca <=> $cb ) ?: ( $rank[ $a ] <=> $rank[ $b ] );
		} );
		$ids = array_slice( $ids, 0, 6 );
		if ( ! $ids ) {
			return array( 'records' => array(), 'reasons' => array() );
		}
		$records = qil_get_catalogue( array( 'include' => $ids, 'limit' => count( $ids ), 'orderby' => 'include', '_qil_locale' => $locale ) );
		$by_rec  = array();
		foreach ( $records as $record ) {
			$by_rec[ (int) $record['id'] ] = $record;
		}
		$out  = array();
		$why  = array();
		$ar   = 'ar' === $locale;
		foreach ( $ids as $id ) {
			if ( ! isset( $by_rec[ $id ] ) ) {
				continue;
			}
			$out[]   = $by_rec[ $id ];
			$reason  = $reasons[ $id ];
			$why[ $id ] = 'pairs' === $reason['kind']
				? sprintf( $ar ? 'يكمّل %s' : 'Pairs with %s', QIL_Boost::role_label( $reason['role'] ) )
				: ( 'compared' === $reason['kind'] ? ( $ar ? 'قارنته' : 'You compared' ) : ( $ar ? 'شاهدته' : 'You viewed' ) );
		}
		return array( 'records' => $out, 'reasons' => $why );
	}

	/* ------------------------------------------------------------------
	   Endpoint
	   ------------------------------------------------------------------ */

	private static function hints() {
		$raw   = isset( $_POST['hints'] ) && is_string( $_POST['hints'] ) ? wp_unslash( $_POST['hints'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only hints; ownership is never inferred from them.
		$input = strlen( $raw ) <= 2000 ? json_decode( $raw, true ) : null;
		$ids   = static function ( $list ) {
			return class_exists( 'QIL_Personalization' ) ? QIL_Personalization::ids( is_array( $list ) ? $list : array(), 12 ) : array();
		};
		return array(
			'recent'  => $ids( $input['recent'] ?? array() ),
			'compare' => $ids( $input['compare'] ?? array() ),
		);
	}

	public static function ajax() {
		if ( function_exists( 'qil_perf_noninteractive_bot' ) && qil_perf_noninteractive_bot() ) {
			self::respond( array( 'bot' => true ) );
		}
		if ( ! self::enabled() || ! self::allowed_request() || ! function_exists( 'wc_get_product' ) ) {
			self::respond( array( 'error' => true ), 403 );
		}
		$user_id = (int) get_current_user_id();
		if ( $user_id < 1 ) {
			self::respond( array( 'authenticated' => false ) );
		}
		$locale  = isset( $_POST['locale'] ) && 'ar' === $_POST['locale'] ? 'ar' : 'en'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$surface = isset( $_POST['surface'] ) && 'cart' === $_POST['surface'] ? 'cart' : 'home'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		try {
			$wallet  = self::wallet( $user_id );
			$running = 'home' === $surface ? self::running_low( $user_id, $locale ) : array();
			$picks   = $wallet && 'home' === $surface && class_exists( 'QIL_Personalization' ) ? self::wallet_picks( $user_id, $locale, self::hints(), $wallet ) : array( 'records' => array(), 'reasons' => array() );
		} catch ( Throwable $error ) {
			self::respond( array( 'error' => true ), 503 );
		}
		self::respond( array(
			'authenticated' => true,
			'currency'      => QIL_Boost::market()['currency'],
			'wallet'        => $wallet,
			'running'       => $running,
			'picks'         => $picks,
			'nonce'         => $running ? wp_create_nonce( 'qil_buy_again' ) : '',
			'walletNonce'   => $wallet ? wp_create_nonce( 'qil_wallet' ) : '',
			'serverTime'    => time(),
		) );
	}

	/* ------------------------------------------------------------------
	   Homepage shell (empty and cache-safe until the private response)
	   ------------------------------------------------------------------ */

	public static function section() {
		if ( ! self::enabled() ) {
			return '';
		}
		$ar = QIL_Boost::is_ar();
		ob_start();
		?>
		<section id="qil-member" class="qil-section qil-member" data-qil-member hidden aria-labelledby="qil-member-title">
			<div class="qil-container">
				<div class="qil-member-grid">
					<article class="qil-wallet" data-qil-wallet hidden>
						<span class="qil-wallet-glow" aria-hidden="true"></span>
						<span class="qil-kicker"><?php echo esc_html( $ar ? 'محفظة الكاش باك' : 'YOUR CASHBACK' ); ?></span>
						<h2 id="qil-member-title"><span data-qil-wallet-title></span></h2>
						<p class="qil-wallet-sub" data-qil-wallet-sub></p>
						<div class="qil-wallet-actions">
							<button type="button" class="qil-button qil-button-light" data-qil-wallet-apply><span data-qil-wallet-cta></span><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button>
							<p class="qil-wallet-status" data-qil-wallet-status role="status" aria-live="polite"></p>
						</div>
						<ul class="qil-wallet-credits" data-qil-wallet-credits hidden aria-label="<?php echo esc_attr( $ar ? 'أرصدتك' : 'Your credits' ); ?>"></ul>
					</article>
					<article class="qil-running" data-qil-running hidden>
						<span class="qil-kicker"><?php echo esc_html( $ar ? 'على وشك النفاد؟' : 'RUNNING LOW?' ); ?></span>
						<h2 data-qil-running-title><?php echo esc_html( $ar ? 'جدّد روتينك بلمسة واحدة' : 'Restock your routine in one tap' ); ?></h2>
						<div class="qil-running-list" data-qil-running-list></div>
						<p class="qil-running-note"><?php echo esc_html( $ar ? 'تقدير من عدد الحصص على الملصق والكمية التي اشتريتها. نفس المنتج والنكهة والحجم، بسعر اليوم.' : 'Estimated from the label’s serving count and the quantity you bought. Same product, flavour and size, at today’s price.' ); ?></p>
					</article>
				</div>
				<article class="qil-collection-block qil-wallet-picks" data-qil-wallet-picks hidden>
					<div class="qil-collection-head"><div><small data-qil-wallet-picks-kicker><?php echo esc_html( $ar ? 'أفضل طرق استخدام رصيدك' : 'BEST WAYS TO USE IT' ); ?></small><h3 data-qil-wallet-picks-title></h3></div><div class="qil-collection-tools"><div class="qil-rail-nav" data-qil-rail-nav="wallet-picks"><button type="button" data-qil-rail-prev aria-label="<?php echo esc_attr( $ar ? 'السابق' : 'Previous' ); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button><button type="button" data-qil-rail-next aria-label="<?php echo esc_attr( $ar ? 'التالي' : 'Next' ); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button></div></div></div>
					<div class="qil-collection-grid qil-rail qil-boost-rail" data-qil-wallet-grid data-qil-rail="wallet-picks"><div class="qil-collection-skeleton" aria-hidden="true"><i></i><i></i><i></i><i></i></div></div>
				</article>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
QIL_Member::boot();
