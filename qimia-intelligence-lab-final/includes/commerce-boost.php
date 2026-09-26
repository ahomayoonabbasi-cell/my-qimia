<?php
/**
 * Commerce Boost: the cart cashback ladder, one-tap stack completion and the
 * bounded public product pool behind them.
 *
 * Presentation adapter only. WooCommerce owns prices, stock, cart, coupons and
 * checkout; the cashback issuer owns its bands and rewards (read through
 * QIL_Cashback, never guessed). Nothing here writes a price, a product, an
 * order or a reward. The mini cart and cart blocks are printed inside
 * WooCommerce's own templates, so they travel with the existing cart fragments:
 * no additional request is made to show or refresh them.
 *
 * Emergency stop: define( 'QIL_BOOST_DISABLE', true ) in wp-config.php.
 *
 * @package Qimia_Intelligence_Lab
 */
defined( 'ABSPATH' ) || exit;

final class QIL_Boost {
	const OPTION     = 'qil_boost';
	const POOL_TTL   = 900;
	const POOL_LIMIT = 280;

	/** Mini-cart render counter: the ladder prints once per template render. */
	private static $mini_ladder_render = -1;
	private static $cart_panel_printed = false;

	public static function boot() {
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'woocommerce_before_mini_cart', array( __CLASS__, 'mini_ladder' ), 20 );
		add_action( 'woocommerce_mini_cart_contents', array( __CLASS__, 'mini_picks' ), 40 );
		add_action( 'woocommerce_before_cart_totals', array( __CLASS__, 'cart_panel' ), 5 );
		add_action( 'woocommerce_after_cart_table', array( __CLASS__, 'stack_band' ), 20 );
		add_filter( 'render_block_woocommerce/cart', array( __CLASS__, 'cart_block' ), 15, 2 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ), 30 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 60 );
	}

	/* ------------------------------------------------------------------
	   Settings
	   ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'ladder'           => 1,
			'ladder_picks'     => 3,
			'stack'            => 1,
			'flash'            => 1,
			'flash_size'       => 10,
			'flash_hours'      => 72,
			'flash_anchor'     => '',
			'flash_min_pct'    => 5,
			'flash_pins'       => '',
			'flash_exclude'    => '',
			'flash_exclusive'  => 0,
			'flash_campaign'   => 'flash-drop',
			'wallet'           => 1,
			'wallet_mode'      => 'auto',
			'reorder'          => 1,
			'reorder_lead'     => 7,
			'reorder_grace'    => 21,
			'stacks'           => 1,
			'stack_slugs'      => 'staks-offer, stacks-offer, stack-offers, stacks, stack, bundles',
			'stack_components' => '',
		);
	}

	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$d   = self::defaults();
		$ids = static function ( $value ) {
			$list = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $value ) ) );
			return implode( ', ', array_slice( array_values( array_unique( $list ) ), 0, 40 ) );
		};
		$anchor = trim( sanitize_text_field( (string) ( $raw['flash_anchor'] ?? '' ) ) );
		if ( '' !== $anchor && ! preg_match( '/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2})?$/', $anchor ) ) {
			$anchor = '';
		}
		$slugs = array_filter( array_map( 'sanitize_title', preg_split( '/[\s,]+/', (string) ( $raw['stack_slugs'] ?? $d['stack_slugs'] ) ) ) );
		$lines = array();
		foreach ( preg_split( '/\R/', (string) ( $raw['stack_components'] ?? '' ) ) as $line ) {
			if ( preg_match( '/^\s*(\d+)\s*[:=]\s*([\d\s,x*]+)$/i', $line, $match ) ) {
				$lines[] = (int) $match[1] . ': ' . trim( preg_replace( '/[^\dx*,\s]/i', '', $match[2] ) );
			}
		}
		return array(
			'ladder'           => empty( $raw['ladder'] ) ? 0 : 1,
			'ladder_picks'     => max( 0, min( 3, (int) ( $raw['ladder_picks'] ?? $d['ladder_picks'] ) ) ),
			'stack'            => empty( $raw['stack'] ) ? 0 : 1,
			'flash'            => empty( $raw['flash'] ) ? 0 : 1,
			'flash_size'       => max( 8, min( 12, (int) ( $raw['flash_size'] ?? $d['flash_size'] ) ) ),
			'flash_hours'      => max( 24, min( 168, (int) ( $raw['flash_hours'] ?? $d['flash_hours'] ) ) ),
			'flash_anchor'     => $anchor,
			'flash_min_pct'    => max( 1, min( 90, (int) ( $raw['flash_min_pct'] ?? $d['flash_min_pct'] ) ) ),
			'flash_pins'       => $ids( $raw['flash_pins'] ?? '' ),
			'flash_exclude'    => $ids( $raw['flash_exclude'] ?? '' ),
			'flash_exclusive'  => empty( $raw['flash_exclusive'] ) ? 0 : 1,
			'flash_campaign'   => sanitize_title( (string) ( $raw['flash_campaign'] ?? $d['flash_campaign'] ) ) ?: $d['flash_campaign'],
			'wallet'           => empty( $raw['wallet'] ) ? 0 : 1,
			'wallet_mode'      => 'strict' === ( $raw['wallet_mode'] ?? '' ) ? 'strict' : 'auto',
			'reorder'          => empty( $raw['reorder'] ) ? 0 : 1,
			'reorder_lead'     => max( 1, min( 30, (int) ( $raw['reorder_lead'] ?? $d['reorder_lead'] ) ) ),
			'reorder_grace'    => max( 3, min( 60, (int) ( $raw['reorder_grace'] ?? $d['reorder_grace'] ) ) ),
			'stacks'           => empty( $raw['stacks'] ) ? 0 : 1,
			'stack_slugs'      => implode( ', ', array_slice( array_values( array_unique( $slugs ) ), 0, 12 ) ),
			'stack_components' => implode( "\n", array_slice( $lines, 0, 40 ) ),
		);
	}

	public static function register_setting() {
		register_setting( 'qil_boost_settings', self::OPTION, array(
			'type'              => 'array',
			'default'           => self::defaults(),
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
		) );
	}

	/** One small autoloaded option; the array is read once per request. */
	public static function settings() {
		static $settings = null;
		if ( null === $settings ) {
			$stored   = get_option( self::OPTION, array() );
			$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return $settings;
	}

	public static function on( $feature ) {
		if ( defined( 'QIL_BOOST_DISABLE' ) && QIL_BOOST_DISABLE ) {
			return false;
		}
		if ( ! function_exists( 'qil_experience_enabled' ) || ! qil_experience_enabled() ) {
			return false;
		}
		$settings = self::settings();
		return ! empty( $settings[ $feature ] );
	}

	/* ------------------------------------------------------------------
	   Language and money
	   ------------------------------------------------------------------ */

	/**
	 * Page language. Woo's AJAX (fragments, add to cart) carries the page
	 * language in the X-Qimia-Language header that qil.js already sends.
	 */
	public static function is_ar() {
		static $ar = null;
		if ( null !== $ar ) {
			return $ar;
		}
		$context = function_exists( 'qil_language_context' ) ? qil_language_context() : array();
		$ar      = ! empty( $context['isArabic'] );
		$ajax    = wp_doing_ajax() || ( isset( $_GET['wc-ajax'] ) && is_string( $_GET['wc-ajax'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only language hint.
		$header  = isset( $_SERVER['HTTP_X_QIMIA_LANGUAGE'] ) ? strtolower( sanitize_key( wp_unslash( $_SERVER['HTTP_X_QIMIA_LANGUAGE'] ) ) ) : '';
		if ( $ajax && in_array( $header, array( 'ar', 'en' ), true ) ) {
			$ar = 'ar' === $header;
		} elseif ( $ajax && ! $ar ) {
			// No header (a refresh sent before qil.js loaded): the page it came from decides.
			$from = (string) wp_parse_url( (string) wp_get_referer(), PHP_URL_PATH );
			$home = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
			$ar   = '' !== $from && (bool) preg_match( '#^' . preg_quote( $home, '#' ) . '/ar(?:/|$)#i', $from );
		}
		return $ar;
	}

	public static function t( $en, $ar ) {
		return self::is_ar() ? $ar : $en;
	}

	public static function locale_attrs() {
		$ar = self::is_ar();
		return sprintf( ' dir="%1$s" lang="%2$s" translate="no" data-qaatm-no-rewrite data-no-translation', $ar ? 'rtl' : 'ltr', $ar ? 'ar' : 'en' );
	}

	/** Selected currency, its units per OMR (issuer/site FX only) and decimals. */
	public static function market() {
		static $market = null;
		if ( null === $market ) {
			$currency = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'OMR';
			$rate     = class_exists( 'QIL_Cashback' ) ? QIL_Cashback::rate( $currency ) : ( 'OMR' === $currency ? 1.0 : null );
			$market   = array(
				'currency' => $currency,
				'rate'     => $rate ? (float) $rate : null,
				'decimals' => function_exists( 'wc_get_price_decimals' ) ? max( 0, min( 4, (int) wc_get_price_decimals() ) ) : 3,
			);
		}
		return $market;
	}

	/** Cart-currency amount through WooCommerce's own formatter. */
	public static function price_html( $amount, $decimals = null ) {
		if ( ! function_exists( 'wc_price' ) ) {
			return esc_html( number_format( (float) $amount, 3 ) );
		}
		$args = null === $decimals ? array() : array( 'decimals' => (int) $decimals );
		return wp_kses_post( wc_price( (float) $amount, $args ) );
	}

	/** Cashback amount exactly as the public cashback section prints it. */
	public static function reward_text( $omr ) {
		$market = self::market();
		if ( ! class_exists( 'QIL_Cashback' ) ) {
			return number_format( (float) $omr, 3 ) . ' OMR';
		}
		return $market['rate']
			? QIL_Cashback::money( (float) $omr, $market['currency'], $market['rate'], self::is_ar() )
			: QIL_Cashback::money( (float) $omr, 'OMR', 1.0, self::is_ar() );
	}

	/* ------------------------------------------------------------------
	   Cashback ladder
	   ------------------------------------------------------------------ */

	public static function policy() {
		static $policy = false;
		if ( false === $policy ) {
			$policy = class_exists( 'QIL_Cashback' ) ? QIL_Cashback::policy() : null;
		}
		return $policy;
	}

	/** The issuer's six public bands with their current rewards, in order. */
	public static function bands() {
		$policy = self::policy();
		if ( ! $policy || ! class_exists( 'QIL_Cashback' ) ) {
			return array();
		}
		$bands = array();
		foreach ( QIL_Cashback::BANDS as $key => $bounds ) {
			$bands[] = array(
				'key'    => $key,
				'low'    => (float) $bounds[0],
				'high'   => null === $bounds[1] ? null : (float) $bounds[1],
				'reward' => (float) $policy['amounts'][ $key ],
			);
		}
		return $bands;
	}

	/** Reward (OMR) for an order value already expressed in OMR. */
	public static function reward_for_omr( $omr ) {
		foreach ( self::bands() as $band ) {
			if ( null === $band['high'] || (float) $omr <= $band['high'] + 1e-9 ) {
				return $band['reward'];
			}
		}
		return null;
	}

	/**
	 * Order value the bands are measured on, in the cart currency: items after
	 * discounts, fees and tax. Shipping is deliberately left out, so "add X"
	 * can only ever be pessimistic: whatever delivery the shopper picks later,
	 * adding X of products always reaches the promised band.
	 */
	public static function cart_value() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return null;
		}
		$cart  = WC()->cart;
		$value = (float) $cart->get_total( 'edit' ) - (float) $cart->get_shipping_total() - (float) $cart->get_shipping_tax();
		return $value > 0 ? $value : null;
	}

	/**
	 * Exact ladder state for a cart value. Bands include their upper limit, so
	 * the next band starts one smallest currency unit above it; the gap is
	 * computed in integer units of the store's price decimals to avoid float
	 * drift (27.600 OMR → add 2.401 OMR to exceed 30.000).
	 */
	public static function ladder( $value = null ) {
		$bands  = self::bands();
		$market = self::market();
		if ( ! $bands || ! $market['rate'] ) {
			return null;
		}
		$value = null === $value ? self::cart_value() : (float) $value;
		if ( null === $value || $value <= 0 ) {
			return null;
		}
		$omr   = $value / $market['rate'];
		$index = count( $bands ) - 1;
		foreach ( $bands as $i => $band ) {
			if ( null === $band['high'] || $omr <= $band['high'] + 1e-9 ) {
				$index = $i;
				break;
			}
		}
		$current = $bands[ $index ];
		$next    = $bands[ $index + 1 ] ?? null;
		$state   = array(
			'value'    => $value,
			'valueOmr' => round( $omr, 3 ),
			'index'    => $index,
			'reward'   => $current['reward'],
			'bands'    => $bands,
			'progress' => 1.0,
			'next'     => null,
			'top'      => null === $next,
		);
		if ( $next ) {
			$units     = (int) pow( 10, $market['decimals'] );
			$threshold = $current['high'] * $market['rate'] * $units;
			$need      = (int) floor( $threshold + 1e-6 ) + 1;
			$have      = (int) round( $value * $units );
			$gap       = max( 1, $need - $have ) / $units;
			$foreign   = 'OMR' !== $market['currency'];
			if ( $foreign ) {
				// Foreign amounts come from the display rate: whole units, rounded up.
				$gap = (float) ceil( $gap - 1e-9 );
			}
			$span              = max( 0.001, $current['high'] - $current['low'] );
			$state['progress'] = max( 0.0, min( 1.0, ( $omr - $current['low'] ) / $span ) );
			$state['next']     = array(
				'reward'    => $next['reward'],
				'threshold' => $current['high'],
				'gap'       => $gap,
				'foreign'   => $foreign,
			);
		}
		return $state;
	}

	/** "Add X" amount as cart-currency HTML; foreign currencies are marked approximate. */
	public static function gap_html( array $state ) {
		$next = $state['next'];
		if ( ! $next ) {
			return '';
		}
		return $next['foreign']
			? '≈ ' . self::price_html( $next['gap'], 0 )
			: self::price_html( $next['gap'] );
	}

	/* ------------------------------------------------------------------
	   Stack roles and rules
	   ------------------------------------------------------------------ */

	/**
	 * Complementary roles, strongest first. The first entries are the
	 * merchandising rules supplied by Qimia (Creatine → Whey / Pre-workout,
	 * Whey → Creatine, Fat burner → Multivitamin / Protein, Magnesium →
	 * Ashwagandha / Daily wellness). Filter qil_stack_rules to change them.
	 */
	public static function rules() {
		static $rules = null;
		if ( null === $rules ) {
			$rules = (array) apply_filters( 'qil_stack_rules', array(
				'protein'        => array( 'creatine', 'pre_workout', 'multivitamin' ),
				'creatine'       => array( 'protein', 'pre_workout', 'electrolytes' ),
				'pre_workout'    => array( 'creatine', 'protein', 'amino' ),
				'fat_burner'     => array( 'multivitamin', 'protein', 'electrolytes' ),
				'magnesium'      => array( 'ashwagandha', 'daily_wellness', 'omega3' ),
				'ashwagandha'    => array( 'magnesium', 'sleep', 'daily_wellness' ),
				'multivitamin'   => array( 'omega3', 'magnesium', 'vitamin_d' ),
				'omega3'         => array( 'multivitamin', 'magnesium', 'vitamin_d' ),
				'vitamin_d'      => array( 'magnesium', 'omega3', 'zinc' ),
				'zinc'           => array( 'magnesium', 'vitamin_d', 'multivitamin' ),
				'mass_gainer'    => array( 'creatine', 'multivitamin', 'protein' ),
				'amino'          => array( 'protein', 'creatine', 'electrolytes' ),
				'electrolytes'   => array( 'amino', 'creatine', 'multivitamin' ),
				'sleep'          => array( 'magnesium', 'ashwagandha', 'daily_wellness' ),
				'collagen'       => array( 'multivitamin', 'omega3', 'vitamin_d' ),
				'joint'          => array( 'omega3', 'collagen', 'multivitamin' ),
				'daily_wellness' => array( 'multivitamin', 'omega3', 'magnesium' ),
			) );
		}
		return $rules;
	}

	public static function role_label( $role ) {
		$labels = array(
			'protein'        => array( 'Protein', 'البروتين' ),
			'creatine'       => array( 'Creatine', 'الكرياتين' ),
			'pre_workout'    => array( 'Pre-workout', 'ما قبل التمرين' ),
			'fat_burner'     => array( 'Fat burner', 'حارق الدهون' ),
			'mass_gainer'    => array( 'Mass gainer', 'زيادة الوزن' ),
			'amino'          => array( 'Aminos', 'الأحماض الأمينية' ),
			'magnesium'      => array( 'Magnesium', 'المغنيسيوم' ),
			'ashwagandha'    => array( 'Ashwagandha', 'الأشواغاندا' ),
			'multivitamin'   => array( 'Multivitamin', 'الفيتامينات المتعددة' ),
			'omega3'         => array( 'Omega-3', 'أوميغا 3' ),
			'vitamin_d'      => array( 'Vitamin D', 'فيتامين د' ),
			'zinc'           => array( 'Zinc', 'الزنك' ),
			'collagen'       => array( 'Collagen', 'الكولاجين' ),
			'sleep'          => array( 'Sleep support', 'دعم النوم' ),
			'electrolytes'   => array( 'Electrolytes', 'الأملاح المعدنية' ),
			'joint'          => array( 'Joint support', 'دعم المفاصل' ),
			'daily_wellness' => array( 'Daily wellness', 'العافية اليومية' ),
		);
		$pair = $labels[ $role ] ?? array( ucfirst( str_replace( '_', ' ', (string) $role ) ), (string) $role );
		return self::is_ar() ? $pair[1] : $pair[0];
	}

	/**
	 * Role tags for one product. Core supplement types come only from the
	 * store's own taxonomy (the goal engine's evidence). Wellness ingredients
	 * that share one "daily wellness" category are told apart by their exact
	 * ingredient name in the category slug or title (merchandising only).
	 *
	 * @return array{0:string,1:string[]} Primary role and all tags.
	 */
	public static function roles_for( $product_id, $name = '', array $slugs = array() ) {
		$tags     = array();
		$purposes = array(
			'protein' => 'protein', 'creatine' => 'creatine', 'pre_workout' => 'pre_workout',
			'fat_burner' => 'fat_burner', 'mass_gainer' => 'mass_gainer', 'amino_recovery' => 'amino',
			'recovery_support' => 'amino', 'hydration' => 'electrolytes', 'joint_support' => 'joint',
			'sleep_support' => 'sleep', 'beauty_support' => 'collagen', 'omega_support' => 'omega3',
			'daily_wellness' => 'daily_wellness',
		);
		if ( function_exists( 'qil_goal_taxonomy_profile' ) ) {
			$profile = qil_goal_taxonomy_profile( $product_id );
			foreach ( (array) ( $profile['evidence'] ?? array() ) as $evidence ) {
				$purpose = (string) ( $evidence[0] ?? '' );
				if ( isset( $purposes[ $purpose ] ) ) {
					$tags[] = $purposes[ $purpose ];
				}
			}
		}
		$text = strtolower( remove_accents( $name . ' ' . implode( ' ', $slugs ) ) );
		$ingredients = array(
			'magnesium'    => '/magnesium|مغنيسيوم|ماغنيسيوم|مغنيزيوم/u',
			'ashwagandha'  => '/ashwagandha|ksm[\s-]*66|أشواغاندا|اشواغاندا|أشواجندا|اشواجندا/u',
			'multivitamin' => '/multi[\s-]*vit|multivitamin|animal[\s-]*pak|opti[\s-]*(?:men|women)|daily[\s-]*multi|ملتي[\s-]*فيتامين|فيتامينات[\s-]*متعددة/u',
			'omega3'       => '/omega[\s-]*3|fish[\s-]*oil|krill|أوميغا|اوميغا|أوميجا|اوميجا/u',
			'vitamin_d'    => '/vitamin[\s-]*d3?\b|\bd3\b|فيتامين[\s-]*د/u',
			'zinc'         => '/\bzinc\b|\bzma\b|زنك/u',
			'collagen'     => '/collagen|كولاجين/u',
			'electrolytes' => '/electrolyte|أملاح[\s-]*معدنية|إلكترولايت/u',
			'sleep'        => '/melatonin|ميلاتونين/u',
		);
		foreach ( $ingredients as $role => $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				$tags[] = $role;
			}
		}
		if ( array_intersect( $tags, array( 'magnesium', 'ashwagandha', 'multivitamin', 'omega3', 'vitamin_d', 'zinc' ) ) ) {
			$tags[] = 'daily_wellness';
		}
		$tags  = array_values( array_unique( $tags ) );
		$order = array( 'creatine', 'protein', 'pre_workout', 'fat_burner', 'mass_gainer', 'amino', 'magnesium', 'ashwagandha', 'multivitamin', 'omega3', 'vitamin_d', 'zinc', 'collagen', 'sleep', 'electrolytes', 'joint', 'daily_wellness' );
		$primary = '';
		foreach ( $order as $role ) {
			if ( in_array( $role, $tags, true ) ) {
				$primary = $role;
				break;
			}
		}
		return array( $primary, $tags );
	}

	/* ------------------------------------------------------------------
	   Public product pool
	   ------------------------------------------------------------------ */

	/**
	 * One compact, public list of purchasable in-stock products for the
	 * current market (currency, country, tax/role pricing) and language.
	 * Keyed by WooCommerce's product version, so a price or stock edit is
	 * picked up at once; otherwise rebuilt at most every 15 minutes. The cart
	 * path never waits more than a moment for another worker's build.
	 */
	public static function pool() {
		static $memo = array();
		$identity = function_exists( 'qil_perf_market_identity' ) ? qil_perf_market_identity() : array();
		unset( $identity['user'], $identity['session'] );
		$locale = self::is_ar() ? 'ar' : 'en';
		$key    = 'qil_boost_pool_v1_' . md5( (string) wp_json_encode( array(
			QIL_VERSION,
			function_exists( 'qil_perf_product_version' ) ? qil_perf_product_version() : '',
			function_exists( 'wp_cache_get_last_changed' ) ? (string) wp_cache_get_last_changed( 'terms' ) : '',
			$locale,
			$identity,
		) ) );
		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}
		$valid  = static function ( $value ) {
			return is_array( $value ) && isset( $value['rows'] ) && is_array( $value['rows'] );
		};
		$cached = qil_perf_cache_get( $key );
		if ( $valid( $cached ) ) {
			return $memo[ $key ] = $cached['rows'];
		}
		$lock = qil_perf_lock( 'boost-pool|' . $key, 45 );
		if ( '' === $lock ) {
			$cached = qil_perf_wait_for_cache( $key, 0.35, true, $valid );
			return $memo[ $key ] = $valid( $cached ) ? $cached['rows'] : array();
		}
		try {
			$rows = self::build_pool( $locale );
			qil_perf_cache_set( $key, array( 'rows' => $rows, 'at' => time() ), (int) apply_filters( 'qil_boost_pool_ttl', self::POOL_TTL ) );
		} finally {
			qil_perf_unlock( $lock );
		}
		return $memo[ $key ] = $rows;
	}

	private static function build_pool( $locale ) {
		if ( ! class_exists( 'WP_Query' ) || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$tax_query   = function_exists( 'qil_product_visibility_tax_query' ) ? qil_product_visibility_tax_query() : array();
		$tax_query[] = array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => array( 'simple', 'variable' ) );
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		$query = new WP_Query( array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'fields'                 => 'ids',
			'posts_per_page'         => (int) apply_filters( 'qil_boost_pool_limit', self::POOL_LIMIT ),
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_key'               => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'                => array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' ),
			'meta_query'             => array( array( 'key' => '_stock_status', 'value' => 'instock' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'tax_query'              => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'suppress_filters'       => false,
		) );
		$ids = array_values( array_unique( array_map( 'absint', (array) $query->posts ) ) );
		if ( ! $ids ) {
			return array();
		}
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, true, true );
		}
		$products  = array();
		$image_ids = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_type( array( 'simple', 'variable' ) ) || 'publish' !== $product->get_status()
				|| ! $product->is_visible() || ! $product->is_purchasable() || ! $product->is_in_stock() || post_password_required( $id ) ) {
				continue;
			}
			$image_id = (int) $product->get_image_id();
			if ( $image_id < 1 ) {
				continue;
			}
			$products[ $id ] = $product;
			$image_ids[]     = $image_id;
		}
		if ( $image_ids ) {
			update_meta_cache( 'post', array_values( array_unique( $image_ids ) ) );
		}
		$rows  = array();
		$names = array();
		foreach ( $products as $id => $product ) {
			$variable = $product->is_type( 'variable' );
			$min      = $variable ? (float) $product->get_variation_price( 'min', true ) : (float) wc_get_price_to_display( $product );
			if ( $min <= 0 ) {
				continue;
			}
			$max     = $variable ? (float) $product->get_variation_price( 'max', true ) : $min;
			$regular = $variable
				? (float) $product->get_variation_regular_price( 'min', true )
				: ( (float) $product->get_regular_price() > 0 ? (float) wc_get_price_to_display( $product, array( 'price' => (float) $product->get_regular_price() ) ) : $min );
			$image    = self::image( (int) $product->get_image_id(), 'woocommerce_gallery_thumbnail' );
			$image_2x = self::image( (int) $product->get_image_id(), 'woocommerce_thumbnail' );
			if ( ! $image ) {
				continue;
			}
			$terms = get_the_terms( $id, 'product_cat' );
			$leaf  = array();
			$slugs = array();
			$ancestors = array();
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$leaf[ (int) $term->term_id ] = true;
				$slugs[] = (string) $term->slug;
				foreach ( (array) get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $ancestor ) {
					$ancestors[ (int) $ancestor ] = true;
				}
			}
			$leaf  = array_values( array_diff( array_keys( $leaf ), array_keys( $ancestors ) ) );
			$name  = function_exists( 'qil_clean_text' ) ? qil_clean_text( $product->get_name() ) : wp_strip_all_tags( $product->get_name() );
			$brand = '';
			if ( taxonomy_exists( 'product_brand' ) ) {
				$brands = get_the_terms( $id, 'product_brand' );
				$brand  = is_array( $brands ) && isset( $brands[0] ) ? (string) $brands[0]->slug : '';
			}
			list( $primary, $tags ) = self::roles_for( $id, $name, $slugs );
			$names[] = $name;
			$rows[]  = array(
				'id' => $id,
				'n'  => $name,
				'u'  => esc_url_raw( function_exists( 'qil_localized_url' ) ? qil_localized_url( $product->get_permalink(), 'ar' === $locale ) : $product->get_permalink() ),
				'i'  => $image,
				'i2' => $image_2x ? $image_2x['src'] : '',
				'p'  => round( $min, 4 ),
				'x'  => round( $max, 4 ),
				'r'  => round( max( $min, $regular ), 4 ),
				't'  => $variable ? 'v' : 's',
				'a'  => ! $variable && $product->supports( 'ajax_add_to_cart' ),
				'k'  => function_exists( 'qil_clean_text' ) ? qil_clean_text( $product->get_sku() ) : (string) $product->get_sku(),
				'c'  => array_map( 'intval', $leaf ),
				'o'  => $primary,
				'g'  => $tags,
				's'  => max( 0, (int) $product->get_total_sales() ),
				'b'  => $brand,
			);
		}
		if ( 'ar' === $locale && $names && function_exists( 'qil_translate_batch' ) ) {
			$map = qil_translate_batch( $names, 'product_title', true );
			foreach ( $rows as &$row ) {
				$row['n'] = qil_translated( $row['n'], $map );
			}
			unset( $row );
		}
		return $rows;
	}

	private static function image( $attachment_id, $size ) {
		if ( $attachment_id < 1 ) {
			return null;
		}
		$source = wp_get_attachment_image_src( $attachment_id, $size );
		if ( ! $source || empty( $source[0] ) ) {
			return null;
		}
		return array( 'src' => esc_url_raw( $source[0] ), 'w' => max( 1, (int) $source[1] ), 'h' => max( 1, (int) $source[2] ) );
	}

	/* ------------------------------------------------------------------
	   Picks
	   ------------------------------------------------------------------ */

	/** Cart identity for request-local memos; changes whenever the cart does. */
	private static function cart_key() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		return WC()->cart->get_cart_hash() . '|' . (string) WC()->cart->get_total( 'edit' ) . '|' . count( WC()->cart->get_applied_coupons() );
	}

	/** Roles, categories and brands of what is in the cart right now. */
	public static function cart_context() {
		static $memo = array();
		$ctx = array( 'parents' => array(), 'roles' => array(), 'primary' => array(), 'cats' => array(), 'brands' => array() );
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $ctx;
		}
		$memo_key = self::cart_key() . '|' . ( self::is_ar() ? 'ar' : 'en' );
		if ( isset( $memo[ $memo_key ] ) ) {
			return $memo[ $memo_key ];
		}
		$by_id = array();
		foreach ( self::pool() as $row ) {
			$by_id[ $row['id'] ] = $row;
		}
		foreach ( array_slice( WC()->cart->get_cart(), 0, 24 ) as $line ) {
			$pid = (int) ( $line['product_id'] ?? 0 );
			if ( $pid < 1 || isset( $ctx['parents'][ $pid ] ) ) {
				continue;
			}
			$ctx['parents'][ $pid ] = true;
			if ( isset( $by_id[ $pid ] ) ) {
				$row = $by_id[ $pid ];
				$primary = $row['o'];
				$tags    = $row['g'];
				$cats    = $row['c'];
				$brand   = $row['b'];
			} else {
				$terms = get_the_terms( $pid, 'product_cat' );
				$cats  = array();
				$slugs = array();
				foreach ( is_array( $terms ) ? $terms : array() as $term ) {
					$cats[]  = (int) $term->term_id;
					$slugs[] = (string) $term->slug;
				}
				$product = isset( $line['data'] ) && is_object( $line['data'] ) ? $line['data'] : wc_get_product( $pid );
				$name    = $product ? $product->get_name() : '';
				list( $primary, $tags ) = self::roles_for( $pid, $name, $slugs );
				$brand = '';
			}
			if ( $primary ) {
				$ctx['primary'][] = $primary;
				$ctx['roles'][]   = $primary;
			}
			$ctx['cats'] = array_merge( $ctx['cats'], $cats );
			if ( $brand ) {
				$ctx['brands'][] = $brand;
			}
		}
		$ctx['roles']   = array_values( array_unique( $ctx['roles'] ) );
		$ctx['primary'] = array_values( array_unique( $ctx['primary'] ) );
		$ctx['cats']    = array_values( array_unique( array_map( 'intval', $ctx['cats'] ) ) );
		$ctx['brands']  = array_values( array_unique( $ctx['brands'] ) );
		return $memo[ $memo_key ] = $ctx;
	}

	/**
	 * Rank the pool for a cart. 'ladder' keeps only products that alone reach
	 * the next band, preferring related ones with the smallest overshoot.
	 * 'stack' keeps rule-based complements only.
	 */
	public static function picks( array $ctx, $mode, $gap, $limit, array $exclude = array() ) {
		$limit = max( 0, (int) $limit );
		if ( $limit < 1 ) {
			return array();
		}
		$market = self::market();
		$rules  = self::rules();
		$unit   = $market['rate'] ? (float) $market['rate'] : 1.0; // One OMR in the cart currency.
		$gap    = max( 0.0, (float) $gap );
		$slack  = max( $gap * 0.5, 6 * $unit );
		$ranked = array();
		foreach ( self::pool() as $row ) {
			$id = (int) $row['id'];
			if ( isset( $ctx['parents'][ $id ] ) || isset( $exclude[ $id ] ) ) {
				continue;
			}
			$complement = null;
			$reason     = '';
			foreach ( $ctx['roles'] as $cart_role ) {
				foreach ( array_values( (array) ( $rules[ $cart_role ] ?? array() ) ) as $rank => $wanted ) {
					if ( in_array( $wanted, $row['g'], true ) && ( null === $complement || $rank < $complement ) ) {
						$complement = $rank;
						$reason     = $cart_role;
					}
				}
			}
			$same_role  = '' !== $row['o'] && in_array( $row['o'], $ctx['primary'], true );
			$shared_cat = (bool) array_intersect( $row['c'], $ctx['cats'] );
			$reaches    = $gap > 0 && $row['p'] + 1e-9 >= $gap;
			$score      = 0.0;
			if ( null !== $complement ) {
				$score += 60 - 12 * $complement;
			} elseif ( $shared_cat && ! $same_role ) {
				$score += 18;
			}
			if ( $same_role ) {
				$score -= 40;
			}
			if ( '' !== $row['b'] && in_array( $row['b'], $ctx['brands'], true ) ) {
				$score += 4;
			}
			$score += min( 12.0, log( 1 + $row['s'], 2 ) * 1.5 );
			if ( $row['a'] ) {
				$score += 6;
			}
			if ( 'ladder' === $mode ) {
				if ( ! $reaches || $row['p'] - $gap > $slack ) {
					continue;
				}
				// Overshoot is judged against the size of the gap (at least 5 OMR),
				// so a tiny gap still favours a related item over any cheap filler.
				$score -= 20 * ( ( $row['p'] - $gap ) / max( $gap, 5 * $unit ) );
			} else {
				if ( null === $complement ) {
					continue;
				}
				if ( $reaches ) {
					$score += 10;
				}
			}
			$ranked[] = array( 'row' => $row, 'score' => $score, 'reaches' => $reaches, 'reason' => $reason, 'complement' => null !== $complement );
		}
		usort( $ranked, static function ( $a, $b ) {
			return ( $b['score'] <=> $a['score'] ) ?: ( $a['row']['p'] <=> $b['row']['p'] ) ?: ( $a['row']['id'] <=> $b['row']['id'] );
		} );
		// One product per role first, so three picks are three different ideas.
		$out   = array();
		$roles = array();
		$taken = array();
		foreach ( $ranked as $candidate ) {
			$role = $candidate['row']['o'] ?: 'id-' . $candidate['row']['id'];
			if ( isset( $roles[ $role ] ) ) {
				continue;
			}
			$roles[ $role ]                          = true;
			$taken[ (int) $candidate['row']['id'] ] = true;
			$out[]                                   = $candidate;
			if ( count( $out ) >= $limit ) {
				return $out;
			}
		}
		foreach ( $ranked as $candidate ) {
			if ( isset( $taken[ (int) $candidate['row']['id'] ] ) ) {
				continue;
			}
			$taken[ (int) $candidate['row']['id'] ] = true;
			$out[]                                   = $candidate;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	   Markup
	   ------------------------------------------------------------------ */

	private static function icon( $name ) {
		$paths = array(
			'coin'  => '<circle cx="12" cy="12" r="8.5"/><path d="M14.8 9.4c-.5-.8-1.5-1.3-2.8-1.3-1.7 0-2.8.9-2.8 2 0 2.8 5.8 1.4 5.8 4.1 0 1.2-1.2 2.1-3 2.1-1.4 0-2.5-.5-3-1.4M12 6.5v1.6M12 16.9v1.6"/>',
			'check' => '<path d="m5 12.5 4.2 4.2L19 7"/>',
			'plus'  => '<path d="M12 5v14M5 12h14"/>',
			'spark' => '<path d="M12 3l1.7 5.1L19 10l-5.3 1.9L12 17l-1.7-5.1L5 10l5.3-1.9L12 3Z"/>',
			'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			// The theme's own add-to-cart icon (qil-i-cart-plus), inline: the drawer lives outside the shell.
			'cart'  => '<path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M15 2v6M12 5h6"/>',
		);
		return '<svg class="qil-boost-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
	}

	/** Compact quick-view record so a variable pick opens the options sheet in place. */
	private static function quick_record( array $row ) {
		$currency = self::market()['currency'];
		$entry    = static function ( $value ) {
			return array( 'value' => (float) $value, 'min' => (float) $value, 'minimumFormattedHtml' => self::price_html( $value ) );
		};
		$sale = $row['r'] > $row['p'] + 1e-9;
		return array(
			'id'       => (int) $row['id'],
			'name'     => $row['n'],
			'url'      => $row['u'],
			'type'     => 'variable',
			'images'   => array( array( 'src' => $row['i2'] ?: $row['i']['src'], 'width' => 300, 'height' => 300, 'alt' => $row['n'] ) ),
			'price'    => array(
				'currency' => $currency,
				'current'  => $entry( $row['p'] ),
				'regular'  => $entry( $row['r'] ),
				'sale'     => $sale ? $entry( $row['p'] ) : null,
				'onSale'   => $sale,
			),
			'purchase' => array( 'action' => 'select', 'url' => $row['u'], 'purchasable' => true ),
			'stock'    => array( 'inStock' => true, 'status' => 'instock' ),
		);
	}

	private static function add_button( array $row, $label, $class = '' ) {
		$name = $row['n'];
		if ( 's' === $row['t'] && $row['a'] ) {
			$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
			$url      = add_query_arg( array( 'add-to-cart' => (int) $row['id'], 'quantity' => 1 ), $cart_url );
			return sprintf(
				'<a href="%1$s" data-quantity="1" data-product_id="%2$d" data-product_sku="%3$s" class="qil-boost-add add_to_cart_button ajax_add_to_cart product_type_simple%4$s" rel="nofollow" aria-label="%5$s"><span>%7$s</span>%6$s</a>',
				esc_url( $url ),
				(int) $row['id'],
				esc_attr( $row['k'] ),
				esc_attr( $class ? ' ' . $class : '' ),
				esc_attr( sprintf( self::t( 'Add %s to your order', 'أضف %s إلى طلبك' ), $name ) ),
				self::icon( 'cart' ),
				esc_html( $label )
			);
		}
		if ( 'v' === $row['t'] ) {
			return sprintf(
				'<a href="%1$s" class="qil-boost-add is-options%2$s" data-qil-quick-view-id="%3$d" data-qil-boost-record="%4$s" aria-haspopup="dialog" aria-label="%5$s"><span>%7$s</span>%6$s</a>',
				esc_url( $row['u'] ),
				esc_attr( $class ? ' ' . $class : '' ),
				(int) $row['id'],
				esc_attr( (string) wp_json_encode( self::quick_record( $row ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
				esc_attr( sprintf( self::t( 'Choose options for %s', 'اختر خيارات %s' ), $name ) ),
				self::icon( 'cart' ),
				esc_html( self::t( 'Options', 'الخيارات' ) )
			);
		}
		return sprintf( '<a href="%1$s" class="qil-boost-add is-link%2$s"><span>%3$s</span>%4$s</a>', esc_url( $row['u'] ), esc_attr( $class ? ' ' . $class : '' ), esc_html( self::t( 'View', 'عرض' ) ), self::icon( 'arrow' ) );
	}

	private static function pick_price( array $row ) {
		$prefix = 'v' === $row['t'] && $row['x'] > $row['p'] + 1e-9 ? '<small>' . esc_html( self::t( 'From', 'من' ) ) . '</small> ' : '';
		$was    = $row['r'] > $row['p'] + 1e-9 ? '<del>' . self::price_html( $row['r'] ) . '</del> ' : '';
		return '<span class="qil-boost-price">' . $prefix . $was . '<strong>' . self::price_html( $row['p'] ) . '</strong></span>';
	}

	/** One pick row (mini cart / cart totals). */
	private static function pick_row( array $pick, $state ) {
		$row  = $pick['row'];
		$note = '';
		if ( $pick['complement'] && '' !== $pick['reason'] ) {
			$note = sprintf( self::t( 'Pairs with your %s', 'يكمّل %s في سلتك' ), self::role_label( $pick['reason'] ) );
		} elseif ( $pick['reaches'] && $state && $state['next'] ) {
			$note = sprintf( self::t( 'Unlocks %s cashback', 'يفتح كاش باك %s' ), self::reward_text( $state['next']['reward'] ) );
		}
		$img = $row['i'];
		return sprintf(
			'<div class="qil-boost-pick" data-qil-boost-product="%1$d"><a class="qil-boost-pick-media" href="%2$s" tabindex="-1" aria-hidden="true"><img src="%3$s"%4$s width="%5$d" height="%6$d" alt="" loading="lazy" decoding="async"></a><div class="qil-boost-pick-body"><a class="qil-boost-pick-name" href="%2$s">%7$s</a>%8$s%9$s</div>%10$s</div>',
			(int) $row['id'],
			esc_url( $row['u'] ),
			esc_url( $img['src'] ),
			$row['i2'] ? ' srcset="' . esc_attr( esc_url( $img['src'] ) . ' 1x, ' . esc_url( $row['i2'] ) . ' 2x' ) . '"' : '',
			(int) min( 100, $img['w'] ),
			(int) min( 100, $img['h'] ),
			esc_html( $row['n'] ),
			self::pick_price( $row ),
			'' !== $note ? '<span class="qil-boost-pick-note">' . ( $pick['reaches'] && $state && $state['next'] ? self::icon( 'spark' ) : '' ) . esc_html( $note ) . '</span>' : '',
			self::add_button( $row, self::t( 'Add', 'أضف' ) )
		);
	}

	/** Ladder message + meter; $variant is 'compact' (mini cart) or 'full' (cart page). */
	public static function ladder_markup( $state, $variant = 'compact' ) {
		if ( ! $state ) {
			return '';
		}
		$full   = 'full' === $variant;
		$reward = self::reward_text( $state['reward'] );
		$out    = '<div class="qil-boost-ladder is-' . esc_attr( $full ? 'full' : 'compact' ) . ( $state['top'] ? ' is-top' : '' ) . '">';
		if ( $full ) {
			$out .= '<span class="qil-boost-kicker">' . esc_html( self::t( 'QIMIA CASHBACK LADDER', 'سلّم كاش باك كيميا' ) ) . '</span>';
		}
		$out .= '<div class="qil-boost-ladder-head"><span class="qil-boost-badge" aria-hidden="true">' . self::icon( $state['top'] ? 'check' : 'coin' ) . '</span>';
		if ( $state['top'] ) {
			$out .= '<p class="qil-boost-ladder-msg"><strong>' . esc_html( sprintf( self::t( 'Top cashback unlocked: %s', 'فتحت أعلى كاش باك: %s' ), $reward ) ) . '</strong></p>';
		} else {
			$out .= '<p class="qil-boost-ladder-msg"><strong>' . sprintf(
				/* translators: %s: amount to add. */
				esc_html( self::t( 'Add %s more', 'أضف %s فقط' ) ),
				'<bdi>' . self::gap_html( $state ) . '</bdi>'
			) . '</strong> <span class="qil-boost-arrow" aria-hidden="true">' . ( self::is_ar() ? '←' : '→' ) . '</span> <span>' . sprintf(
				esc_html( self::t( 'get %s cashback', 'واحصل على كاش باك %s' ) ),
				'<b><bdi>' . esc_html( self::reward_text( $state['next']['reward'] ) ) . '</bdi></b>'
			) . '</span></p>';
		}
		$out .= '</div>';
		$percent = (int) round( $state['progress'] * 100 );
		$label   = $state['top']
			? self::t( 'Highest cashback band reached', 'تم الوصول إلى أعلى فئة كاش باك' )
			: sprintf( self::t( '%d%% of the way to the next cashback band', 'قطعت %d%% نحو فئة الكاش باك التالية' ), $percent );
		if ( $full ) {
			$out .= '<ol class="qil-boost-steps" aria-label="' . esc_attr( self::t( 'Cashback bands', 'فئات الكاش باك' ) ) . '">';
			foreach ( $state['bands'] as $i => $band ) {
				$class = $i < $state['index'] ? 'is-done' : ( $i === $state['index'] ? 'is-current' : ( $i === $state['index'] + 1 ? 'is-next' : '' ) );
				$range = null === $band['high']
					? sprintf( self::t( 'over %s', 'أكثر من %s' ), self::band_edge( $band['low'] ) )
					: sprintf( self::t( 'to %s', 'حتى %s' ), self::band_edge( $band['high'] ) );
				$out  .= '<li class="' . esc_attr( $class ) . '"' . ( $i === $state['index'] ? ' aria-current="step"' : '' ) . '><b><bdi>' . esc_html( self::reward_text( $band['reward'] ) ) . '</bdi></b><small><bdi>' . esc_html( $range ) . '</bdi></small></li>';
			}
			$out .= '</ol>';
		}
		$out .= '<div class="qil-boost-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( $percent ) . '" aria-label="' . esc_attr( $label ) . '"><span style="width:' . esc_attr( $percent ) . '%"></span></div>';
		$note = $state['top']
			? self::t( 'On eligible paid orders · cashback is credit for your next order', 'للطلبات المدفوعة المؤهلة · الكاش باك رصيد لطلبك القادم' )
			: sprintf( self::t( 'This order earns %s cashback now · eligible paid orders', 'هذا الطلب يمنحك الآن كاش باك %s · للطلبات المدفوعة المؤهلة' ), $reward );
		$out .= '<p class="qil-boost-ladder-note">' . esc_html( $note ) . '</p></div>';
		return $out;
	}

	/** Band edge (OMR) in the shopper's currency, like the public cashback section. */
	private static function band_edge( $omr ) {
		$market = self::market();
		return class_exists( 'QIL_Cashback' ) && $market['rate']
			? QIL_Cashback::money( (float) $omr, $market['currency'], $market['rate'], self::is_ar() )
			: number_format( (float) $omr ) . ' OMR';
	}

	private static function picks_markup( array $picks, $state, $heading ) {
		if ( ! $picks ) {
			return '';
		}
		$out = '<div class="qil-boost-picks"><p class="qil-boost-picks-title">' . esc_html( $heading ) . '</p>';
		foreach ( $picks as $pick ) {
			$out .= self::pick_row( $pick, $state );
		}
		return $out . '</div>';
	}

	/**
	 * Picks for the ladder, or complements once the top band is reached.
	 * Memoized per cart state, so the cart page's totals panel and its stack
	 * band (rendered earlier by WooCommerce) agree on which products to avoid
	 * repeating, whatever order the hooks fire in.
	 */
	private static function ladder_picks( $state, array $ctx, $limit ) {
		static $memo = array();
		$key = self::cart_key() . '|' . (int) $limit . '|' . ( self::is_ar() ? 'ar' : 'en' ) . '|' . ( $state ? (int) $state['index'] : 'x' );
		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}
		if ( $state && $state['next'] ) {
			$picks = self::picks( $ctx, 'ladder', $state['next']['gap'], $limit );
			return $memo[ $key ] = array( $picks, sprintf( self::t( 'Reach %s cashback with one of these', 'اوصل إلى كاش باك %s بمنتج واحد من هذه' ), self::reward_text( $state['next']['reward'] ) ) );
		}
		$picks = self::picks( $ctx, 'stack', 0, $limit );
		return $memo[ $key ] = array( $picks, self::t( 'Complete your stack', 'أكمل مجموعتك' ) );
	}

	/** IDs the cart page's ladder panel shows, for the stack band to skip. */
	private static function ladder_pick_ids() {
		if ( ! self::on( 'ladder' ) ) {
			return array();
		}
		$state = self::ladder();
		if ( ! $state ) {
			return array();
		}
		list( $picks ) = self::ladder_picks( $state, self::cart_context(), (int) self::settings()['ladder_picks'] );
		$ids = array();
		foreach ( $picks as $pick ) {
			$ids[ (int) $pick['row']['id'] ] = true;
		}
		return $ids;
	}

	/* ------------------------------------------------------------------
	   Mini cart
	   ------------------------------------------------------------------ */

	private static function cart_ready() {
		return function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty();
	}

	/** Top of the mini cart: the one-line ladder. */
	public static function mini_ladder() {
		self::$mini_ladder_render = did_action( 'woocommerce_before_mini_cart' );
		if ( ! self::on( 'ladder' ) || ! self::cart_ready() ) {
			return;
		}
		$markup = self::ladder_markup( self::ladder(), 'compact' );
		if ( '' !== $markup ) {
			echo '<div class="qil-boost qil-boost-mini"' . self::locale_attrs() . '>' . $markup . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		}
	}

	/** End of the mini-cart list: the picks (and the ladder when a theme skipped the top hook). */
	public static function mini_picks() {
		if ( ! self::cart_ready() || ( ! self::on( 'ladder' ) && ! self::on( 'stack' ) ) ) {
			return;
		}
		$state = self::on( 'ladder' ) ? self::ladder() : null;
		$limit = (int) self::settings()['ladder_picks'];
		list( $picks, $heading ) = self::ladder_picks( $state, self::cart_context(), $limit );
		$ladder = self::$mini_ladder_render === did_action( 'woocommerce_before_mini_cart' ) && did_action( 'woocommerce_before_mini_cart' ) > 0
			? ''
			: self::ladder_markup( $state, 'compact' );
		$markup = self::picks_markup( $picks, $state, $heading );
		if ( '' === $ladder . $markup ) {
			return;
		}
		echo '<li class="qil-boost-li"><div class="qil-boost qil-boost-mini-picks"' . self::locale_attrs() . '>' . $ladder . $markup . '</div></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	}

	/* ------------------------------------------------------------------
	   Cart page
	   ------------------------------------------------------------------ */

	/** Ladder + gap picks inside .cart_totals, which Woo re-renders on every cart change. */
	public static function cart_panel_markup() {
		if ( ! self::cart_ready() || ! self::on( 'ladder' ) ) {
			return '';
		}
		$state = self::ladder();
		if ( ! $state ) {
			return '';
		}
		list( $picks, $heading ) = self::ladder_picks( $state, self::cart_context(), (int) self::settings()['ladder_picks'] );
		return '<div class="qil-boost qil-boost-cart-panel" data-qil-boost-cart-panel' . self::locale_attrs() . '>' . self::ladder_markup( $state, 'full' ) . self::picks_markup( $picks, $state, $heading ) . '</div>';
	}

	/** Printed once per request, even if a theme renders the totals twice. */
	public static function cart_panel() {
		if ( self::$cart_panel_printed ) {
			return;
		}
		self::$cart_panel_printed = true;
		echo self::cart_panel_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the builder.
	}

	/** "Complete your stack": rule-based complements with one-tap add. */
	public static function stack_band_markup() {
		if ( ! self::cart_ready() || ! self::on( 'stack' ) || ! function_exists( 'qil_get_catalogue' ) ) {
			return '';
		}
		$ctx = self::cart_context();
		if ( ! $ctx['roles'] ) {
			return '';
		}
		$state = self::on( 'ladder' ) ? self::ladder() : null;
		$gap   = $state && $state['next'] ? $state['next']['gap'] : 0;
		$picks = self::picks( $ctx, 'stack', $gap, 4, self::ladder_pick_ids() );
		if ( count( $picks ) < 2 ) {
			return '';
		}
		// The theme's own product card, from the shared catalogue record (cached per market).
		$ids   = array_map( static function ( $pick ) { return (int) $pick['row']['id']; }, $picks );
		$by_id = array();
		foreach ( qil_get_catalogue( array( 'include' => $ids, 'limit' => count( $ids ), 'orderby' => 'include' ) ) as $record ) {
			$by_id[ (int) $record['id'] ] = $record;
		}
		$records = array();
		foreach ( $ids as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$records[] = $by_id[ $id ];
			}
		}
		$ids = array_map( static function ( $record ) { return (int) $record['id']; }, $records );
		if ( count( $records ) < 2 ) {
			return '';
		}
		$labels = array();
		foreach ( $picks as $pick ) {
			$labels[ (int) $pick['row']['id'] ] = $pick['reaches'] && $state && $state['next']
				? '+' . self::reward_text( $state['next']['reward'] ) . ' ' . self::t( 'cashback', 'كاش باك' )
				: sprintf( self::t( 'Pairs with %s', 'يكمّل %s' ), self::role_label( $pick['reason'] ) );
		}
		$ar        = self::is_ar();
		$lead_role = $picks[0]['reason'] ?: ( $ctx['primary'][0] ?? '' );
		$title_id  = 'qil-boost-stack-title';
		$data      = array( 'records' => $records, 'labels' => array_intersect_key( $labels, array_flip( $ids ) ) );
		$nav       = '<div class="qil-rail-nav" data-qil-rail-nav="qil-boost-band"><button type="button" data-qil-rail-prev aria-label="' . esc_attr( self::t( 'Previous', 'السابق' ) ) . '"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button><button type="button" data-qil-rail-next aria-label="' . esc_attr( self::t( 'Next', 'التالي' ) ) . '"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button></div>';
		$out  = '<div class="qil-shell qil-boost-band-shell notranslate" data-qil-shell dir="' . ( $ar ? 'rtl' : 'ltr' ) . '" lang="' . ( $ar ? 'ar' : 'en' ) . '" data-qil-locale="' . ( $ar ? 'ar' : 'en' ) . '" translate="no" data-qaatm-no-rewrite data-no-translation>';
		$out .= '<section class="qil-section qil-commerce-collections qil-boost-band" data-qil-boost-band data-qil-boost-ids="' . esc_attr( implode( ',', $ids ) ) . '" aria-labelledby="' . esc_attr( $title_id ) . '"><div class="qil-container"><article class="qil-collection-block">';
		$out .= '<div class="qil-collection-head"><div><small>' . esc_html( self::t( 'COMPLETE YOUR STACK', 'أكمل مجموعتك' ) ) . '</small><h3 id="' . esc_attr( $title_id ) . '">' . esc_html( sprintf( self::t( 'Made to pair with your %s', 'مختارة لتكمّل %s' ), self::role_label( $lead_role ) ) ) . '</h3>';
		$out .= '<p>' . esc_html( self::t( 'One tap adds it to this order — no product page, same checkout.', 'بلمسة واحدة يُضاف إلى هذا الطلب — بدون صفحة المنتج وبنفس الدفع.' ) ) . '</p></div><div class="qil-collection-tools">' . $nav . '</div></div>';
		$out .= '<div class="qil-collection-grid qil-rail qil-boost-rail" data-qil-boost-band-grid data-qil-rail="qil-boost-band"><div class="qil-collection-skeleton" aria-hidden="true"><i></i><i></i><i></i><i></i></div></div>';
		$out .= '<script type="application/json" data-qil-boost-band-data>' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
		return $out . '</article></div></section></div>';
	}

	public static function stack_band() {
		echo self::stack_band_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the builder.
	}

	/** Block-based cart: print after the block; qil-boost.js refreshes it through fragments. */
	public static function cart_block( $content, $block = array() ) {
		if ( is_admin() || ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return $content;
		}
		if ( ! self::on( 'ladder' ) && ! self::on( 'stack' ) ) {
			return $content;
		}
		// Keep a hidden panel so a later fragment refresh has somewhere to land.
		$panel = self::cart_panel_markup();
		$panel = '' !== $panel ? $panel : '<div class="qil-boost-cart-panel" data-qil-boost-cart-panel hidden></div>';
		return $content . '<div class="qil-boost-block-cart" data-qil-boost-block-cart>' . $panel . self::stack_band_markup() . '</div>';
	}

	/**
	 * The cart-page panel as a fragment, only when the refresh comes from the
	 * cart page itself (Woo sends the page as Referer). Other pages pay nothing.
	 */
	public static function fragments( $fragments ) {
		if ( ! is_array( $fragments ) || ! self::on( 'ladder' ) || ! function_exists( 'wc_get_cart_url' ) ) {
			return $fragments;
		}
		$referer = wp_get_referer();
		if ( ! $referer ) {
			return $fragments;
		}
		$cart_path = untrailingslashit( (string) wp_parse_url( wc_get_cart_url(), PHP_URL_PATH ) );
		$from_path = untrailingslashit( (string) wp_parse_url( $referer, PHP_URL_PATH ) );
		$from_path = (string) preg_replace( '#^/ar(?=/|$)#', '', $from_path );
		if ( '' === $cart_path || $from_path !== $cart_path ) {
			return $fragments;
		}
		$panel = self::cart_panel_markup();
		$fragments['div.qil-boost-cart-panel'] = '' !== $panel ? $panel : '<div class="qil-boost-cart-panel" data-qil-boost-cart-panel hidden></div>';
		return $fragments;
	}

	/* ------------------------------------------------------------------
	   Admin (Settings → Qimia Intelligence Lab → Growth)
	   ------------------------------------------------------------------ */

	public static function admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = self::settings();
		$name  = static function ( $key ) {
			return self::OPTION . '[' . $key . ']';
		};
		$check = static function ( $key, $label ) use ( $s, $name ) {
			return '<input type="hidden" name="' . esc_attr( $name( $key ) ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $name( $key ) ) . '" value="1" ' . checked( 1, (int) $s[ $key ], false ) . '> ' . esc_html( $label ) . '</label>';
		};
		$number = static function ( $key, $min, $max, $step = 1 ) use ( $s, $name ) {
			return '<input type="number" class="small-text" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" name="' . esc_attr( $name( $key ) ) . '" value="' . esc_attr( $s[ $key ] ) . '">';
		};
		$text = static function ( $key, $class = 'regular-text', $placeholder = '' ) use ( $s, $name ) {
			return '<input type="text" class="' . esc_attr( $class ) . '" name="' . esc_attr( $name( $key ) ) . '" value="' . esc_attr( $s[ $key ] ) . '" placeholder="' . esc_attr( $placeholder ) . '">';
		};
		$policy = self::policy();
		echo '<section class="qimia-admin-panel"><h2>Growth: cashback ladder, flash drop, wallet, reorder, stacks</h2>';
		echo '<p>Presentation only. WooCommerce keeps every price, stock level, coupon and order; the cashback issuer keeps its bands and rewards. Nothing below changes a price or issues a reward.</p>';
		if ( defined( 'QIL_BOOST_DISABLE' ) && QIL_BOOST_DISABLE ) {
			echo '<div class="notice notice-warning inline"><p><code>QIL_BOOST_DISABLE</code> is set: every feature on this tab is off.</p></div>';
		}
		$bands = array();
		foreach ( self::bands() as $band ) {
			$edge    = null === $band['high'] ? '>' . $band['low'] : '≤' . $band['high'];
			$bands[] = $edge . ' → ' . rtrim( rtrim( number_format( $band['reward'], 3 ), '0' ), '.' );
		}
		$issuer = $policy ? 'active — bands ' . implode( ' / ', $bands ) . ' OMR' : 'not active — the ladder, stack tiers and wallet amounts stay hidden';
		echo '<p>Cashback issuer: <strong>' . esc_html( $issuer ) . '</strong></p>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'qil_boost_settings' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">Cart cashback ladder</th><td>' . $check( 'ladder', 'Show "Add X more → get Y cashback" in the mini cart and on the cart page' ) . '<p>Products that reach the next band: ' . $number( 'ladder_picks', 0, 3 ) . ' (0 hides them)</p><p class="description">Measured on items after discounts, fees and tax, excluding shipping, so "add X" can only ever be on the safe side. Rides on WooCommerce\'s existing cart fragments: no extra request.</p></td></tr>';
		echo '<tr><th scope="row">Complete your stack</th><td>' . $check( 'stack', 'Rule-based complements with one-tap "Add to my order" on the cart page' ) . '<p class="description">Creatine → Whey / Pre-workout · Whey → Creatine · Fat burner → Multivitamin / Protein · Magnesium → Ashwagandha / Daily wellness (plus sensible defaults). Developers can change the map with the <code>qil_stack_rules</code> filter.</p></td></tr>';
		echo '<tr><th scope="row">Flash drop</th><td>' . $check( 'flash', 'Feature a short, rotating flash drop on the homepage' ) . '<p>Products per drop ' . $number( 'flash_size', 8, 12 ) . ' · drop length ' . $number( 'flash_hours', 24, 168 ) . ' hours · minimum real discount ' . $number( 'flash_min_pct', 1, 90 ) . '%</p><p>First drop starts (Oman time, <code>YYYY-MM-DD HH:MM</code>): ' . $text( 'flash_anchor', 'regular-text', '2026-01-01 00:00' ) . '</p><p>Always include product IDs: ' . $text( 'flash_pins', 'regular-text', '123, 456' ) . '</p><p>Never include product IDs: ' . $text( 'flash_exclude', 'regular-text', '789' ) . '</p><p>Instagram campaign name: ' . $text( 'flash_campaign', 'regular-text' ) . '</p><p>' . $check( 'flash_exclusive', 'Show only the current drop on the Flash Sale category page' ) . '</p><p class="description">Candidates are in-stock flash-sale products that WooCommerce sells below their regular price right now. Products from the previous drop are skipped when possible, so every drop is new. The clock shows the real end of the drop. Each card leads with its real stock ("Only 3 left", or the option running low: "Only 2 left in Chocolate"); a product\'s own sale end appears only when it comes before the drop\'s end.</p></td></tr>';
		echo '<tr><th scope="row">Cashback wallet</th><td>' . $check( 'wallet', 'Show signed-in shoppers their unused cashback on the homepage and let them apply it in one tap' ) . '<p><label><input type="radio" name="' . esc_attr( $name( 'wallet_mode' ) ) . '" value="auto" ' . checked( 'auto', $s['wallet_mode'], false ) . '> Issuer-marked coupons, and single-use fixed-amount coupons restricted to the shopper\'s email with an expiry date</label><br><label><input type="radio" name="' . esc_attr( $name( 'wallet_mode' ) ) . '" value="strict" ' . checked( 'strict', $s['wallet_mode'], false ) . '> Only coupons the cashback issuer marked (meta starting with <code>_qcb2</code>, or "cashback" in the description)</label></p><p class="description">Coupon codes never reach the browser. The cashback plugin can supply its coupons directly through the <code>qil_cashback_wallet_coupons</code> filter.</p></td></tr>';
		echo '<tr><th scope="row">Running low? (reorder)</th><td>' . $check( 'reorder', 'Remind signed-in shoppers to restock the exact product, flavour and size' ) . '<p>Show from ' . $number( 'reorder_lead', 1, 30 ) . ' days before the estimated run-out until ' . $number( 'reorder_grace', 3, 60 ) . ' days after it.</p><p class="description">Estimate = the label\'s verified serving count × quantity bought ÷ servings a day (1; pre-workout, aminos and electrolytes 5 a week), from the payment date plus two days for delivery. Products without a verified serving count are never estimated. Filter: <code>qil_reorder_servings_per_day</code>.</p></td></tr>';
		echo '<tr><th scope="row">Cashback stacks</th><td>' . $check( 'stacks', 'Show stack products with their contents, real value and cashback tier on the homepage' ) . '<p>Stack category slugs: ' . $text( 'stack_slugs', 'large-text' ) . '</p><p>Stack contents when the product does not define them (one per line, <code>stackID: productID, productID x2</code>):</p><textarea class="large-text code" rows="4" name="' . esc_attr( $name( 'stack_components' ) ) . '">' . esc_textarea( $s['stack_components'] ) . '</textarea><p class="description">Grouped products, WPC Product Bundles, WooCommerce Product Bundles and YITH bundles are read automatically.</p></td></tr>';
		echo '</tbody></table>';
		submit_button( 'Save growth settings' );
		echo '</form>';
		if ( class_exists( 'QIL_Flash_Drop' ) ) {
			QIL_Flash_Drop::admin_preview();
		}
		if ( class_exists( 'QIL_Stacks' ) ) {
			QIL_Stacks::admin_assistant();
		}
		echo '<h3>Runtime boundaries</h3><table class="widefat striped"><tbody>';
		$rows = array(
			'Mini cart & cart'  => 'Printed inside WooCommerce\'s mini-cart and cart-totals templates, refreshed by the existing fragments. No new request.',
			'Product pool'      => 'Up to ' . self::POOL_LIMIT . ' in-stock products per market and language, public data only, rebuilt when WooCommerce\'s product version changes or after 15 minutes; one build at a time.',
			'Flash drop'        => 'Sent inside its own homepage section (no request); the choice is stored once per window, only the drop\'s own products are re-read, and a momentary lock never produces a homepage without the drop.',
			'Cached homepages'  => 'Purged (WP-Cron, at most once every five minutes) when a new drop starts, when a drop product\'s stock, price or sale changes, and once after a plugin upgrade.',
			'Wallet & reorder'  => 'One private, idle-time request for signed-in shoppers on the homepage or cart. Guests never trigger it; nothing is cached for them.',
			'Stacks'            => 'The theme\'s product cards, from records sent with the homepage; ten-minute public cache per market and language.',
			'Emergency stop'    => 'define( \'QIL_BOOST_DISABLE\', true ); in wp-config.php',
		);
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	/* ------------------------------------------------------------------
	   Assets
	   ------------------------------------------------------------------ */

	public static function asset( $extension ) {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$min   = 'assets/qil-boost.min.' . $extension;
		return ! $debug && is_readable( QIL_DIR . $min ) ? $min : 'assets/qil-boost.' . $extension;
	}

	/**
	 * The stylesheet goes wherever WooCommerce may print a mini cart (every
	 * storefront page, also with the Qimia header switched off). The script
	 * only rides along with the Qimia storefront script it builds on.
	 */
	public static function assets() {
		if ( is_admin() || ( defined( 'QIL_BOOST_DISABLE' ) && QIL_BOOST_DISABLE ) || ! function_exists( 'qil_experience_enabled' ) || ! qil_experience_enabled() || qil_is_elementor_context() ) {
			return;
		}
		$bot = ( function_exists( 'qil_perf_noninteractive_bot' ) && qil_perf_noninteractive_bot() ) || ( function_exists( 'qil_perf_crawler_family' ) && '' !== qil_perf_crawler_family() );
		wp_enqueue_style( 'qil-boost', QIL_URL . self::asset( 'css' ), array(), QIL_VERSION );
		if ( $bot || ! wp_script_is( 'qimia-intelligence-lab', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script( 'qil-boost', QIL_URL . self::asset( 'js' ), array( 'qimia-intelligence-lab' ), QIL_VERSION, true );
		$data = (array) apply_filters( 'qil_boost_page_data', array(
			'version'  => QIL_VERSION,
			'locale'   => self::is_ar() ? 'ar' : 'en',
			'isCart'   => function_exists( 'is_cart' ) && is_cart(),
			'signedIn' => is_user_logged_in(),
		) );
		wp_add_inline_script( 'qil-boost', 'window.QIL_BOOST = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
	}
}
QIL_Boost::boot();
