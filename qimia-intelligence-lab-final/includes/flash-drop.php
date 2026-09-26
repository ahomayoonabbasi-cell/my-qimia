<?php
/**
 * Flash Drop: a short, rotating selection from the store's real flash-sale
 * products.
 *
 * Every price, previous price, percentage and stock figure is WooCommerce's
 * own, read at build time. Nothing here changes a price or a sale schedule:
 * the drop only decides which 8–12 genuinely reduced products are featured
 * during one fixed window (72 hours by default). The clock counts down to the
 * real end of that window; a per-product "sale ends" time is shown only when
 * WooCommerce (or the flash-sale plugin) has a real end date.
 *
 * @package Qimia_Intelligence_Lab
 */
defined( 'ABSPATH' ) || exit;

final class QIL_Flash_Drop {
	const STATE        = 'qil_flash_drop_state';
	const CRON         = 'qil_flash_drop_rotate';
	const TIMEZONE     = '+04:00';
	const MIN_PRODUCTS = 4;

	public static function boot() {
		add_action( self::CRON, array( __CLASS__, 'rotate' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'init', array( __CLASS__, 'no_edge_cache' ), -9999 );
		add_action( 'woocommerce_product_query', array( __CLASS__, 'exclusive_archive' ), 25 );
		add_action( 'update_option_' . QIL_Boost::OPTION, array( __CLASS__, 'settings_changed' ), 10, 2 );
		add_filter( 'qil_boost_page_data', array( __CLASS__, 'page_data' ) );
		add_filter( 'qimia_customer_source_projection_v2', array( __CLASS__, 'ai_context' ), 30 );
		add_shortcode( 'qimia_flash_drop', array( __CLASS__, 'shortcode' ) );
	}

	/* ------------------------------------------------------------------
	   Windows
	   ------------------------------------------------------------------ */

	/** First window start: the configured Oman time, else 1 Jan 2026 00:00 Oman. */
	public static function anchor() {
		$raw = trim( (string) QIL_Boost::settings()['flash_anchor'] );
		$raw = '' === $raw ? '2026-01-01 00:00' : ( 10 === strlen( $raw ) ? $raw . ' 00:00' : $raw );
		$ts  = strtotime( $raw . ':00 ' . self::TIMEZONE );
		return false === $ts ? 1767211200 : (int) $ts;
	}

	/** @return array{index:int,start:int,end:int,hours:int} */
	public static function window( $now = null ) {
		$now    = null === $now ? time() : (int) $now;
		$hours  = (int) QIL_Boost::settings()['flash_hours'];
		$length = max( 1, $hours ) * HOUR_IN_SECONDS;
		$index  = (int) floor( ( $now - self::anchor() ) / $length );
		$start  = self::anchor() + $index * $length;
		return array( 'index' => $index, 'start' => $start, 'end' => $start + $length, 'hours' => $hours );
	}

	/* ------------------------------------------------------------------
	   Candidates: real reductions only
	   ------------------------------------------------------------------ */

	public static function category_term_ids() {
		$slugs = (array) apply_filters( 'qil_promotion_category_slugs', array( 'flash-sale', 'flash-sales' ), 'flash' );
		$ids   = array();
		foreach ( $slugs as $slug ) {
			if ( ! is_scalar( $slug ) ) {
				continue;
			}
			$term = get_term_by( 'slug', sanitize_title( (string) $slug ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** A real end date from WooCommerce's schedule or the flash-sale plugin's own expiry. */
	private static function sale_end( $product ) {
		$date = method_exists( $product, 'get_date_on_sale_to' ) ? $product->get_date_on_sale_to() : null;
		if ( $date ) {
			return (int) $date->getTimestamp();
		}
		foreach ( array( 'qimia_flash_sale_expiry', '_qimia_flash_sale_expiry', 'expiry_date' ) as $key ) {
			$raw = get_post_meta( $product->get_id(), $key, true );
			if ( is_scalar( $raw ) && '' !== (string) $raw && function_exists( 'qil_variation_contract_expiry' ) ) {
				$day = qil_variation_contract_expiry( (string) $raw );
				if ( '' !== $day ) {
					$ts = strtotime( $day . ' 23:59:59 ' . self::TIMEZONE );
					if ( false !== $ts ) {
						return (int) $ts;
					}
				}
			}
		}
		return 0;
	}

	/** Variations inspected per candidate build; bounds the cold build's cost. */
	private static $variation_budget = 900;

	/**
	 * Facts for one product at today's WooCommerce prices, or null when it is
	 * not a genuine, available reduction right now.
	 */
	private static function facts( $product, $min_pct ) {
		if ( ! $product || ! $product->is_type( array( 'simple', 'variable' ) ) || 'publish' !== $product->get_status()
			|| ! $product->is_visible() || ! $product->is_purchasable() || ! $product->is_in_stock() || ! $product->get_image_id()
			|| post_password_required( $product->get_id() ) ) {
			return null;
		}
		$now = time();
		if ( $product->is_type( 'simple' ) ) {
			if ( ! $product->is_on_sale() || ! $product->has_enough_stock( 1 ) ) {
				return null;
			}
			$current = (float) wc_get_price_to_display( $product );
			$regular = (float) $product->get_regular_price() > 0 ? (float) wc_get_price_to_display( $product, array( 'price' => (float) $product->get_regular_price() ) ) : 0.0;
			if ( $current <= 0 || $regular <= $current ) {
				return null;
			}
			$pct   = (int) round( ( ( $regular - $current ) / $regular ) * 100 );
			$stock = $product->managing_stock() ? max( 0, (int) $product->get_stock_quantity() ) : null;
			$ends  = self::sale_end( $product );
			return $pct >= $min_pct ? array( 'pct' => $pct, 'upTo' => false, 'stock' => $stock, 'endsAt' => $ends > $now ? $ends : 0 ) : null;
		}
		// Variable: only in-stock, visible, reduced variations count; a mixed
		// set is shown as "up to", never as the largest figure alone.
		$children = array_slice( array_map( 'absint', (array) $product->get_visible_children() ), 0, 60 );
		if ( ! $children || self::$variation_budget < count( $children ) ) {
			return null;
		}
		self::$variation_budget -= count( $children );
		update_meta_cache( 'post', $children );
		$pcts     = array();
		$stock    = 0;
		$managed  = true;
		$ends     = 0;
		foreach ( $children as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || 'publish' !== $variation->get_status() || ! $variation->is_in_stock() || ! $variation->is_purchasable() || ! $variation->has_enough_stock( 1 ) ) {
				continue;
			}
			if ( $variation->managing_stock() ) {
				$stock += max( 0, (int) $variation->get_stock_quantity() );
			} else {
				$managed = false;
			}
			if ( ! $variation->is_on_sale() ) {
				$pcts[] = 0;
				continue;
			}
			$current = (float) wc_get_price_to_display( $variation );
			$regular = (float) $variation->get_regular_price() > 0 ? (float) wc_get_price_to_display( $variation, array( 'price' => (float) $variation->get_regular_price() ) ) : 0.0;
			if ( $current <= 0 || $regular <= $current ) {
				$pcts[] = 0;
				continue;
			}
			$pcts[] = (int) round( ( ( $regular - $current ) / $regular ) * 100 );
			$end    = self::sale_end( $variation );
			if ( $end > $now ) {
				$ends = $ends ? min( $ends, $end ) : $end;
			}
		}
		if ( ! $pcts || max( $pcts ) < $min_pct ) {
			return null;
		}
		$product_end = self::sale_end( $product );
		if ( $product_end > $now ) {
			$ends = $ends ? min( $ends, $product_end ) : $product_end;
		}
		return array(
			'pct'    => max( $pcts ),
			'upTo'   => count( array_unique( $pcts ) ) > 1,
			'stock'  => $managed ? $stock : null,
			'endsAt' => $ends,
		);
	}

	/**
	 * Eligible flash products with their facts. Public and market-scoped
	 * (prices differ by currency; percentages do not), keyed by the product
	 * version so a stock or price edit is reflected on the next read.
	 */
	public static function candidates() {
		static $memo = array();
		$identity = function_exists( 'qil_perf_market_identity' ) ? qil_perf_market_identity() : array();
		unset( $identity['user'], $identity['session'] );
		$settings = QIL_Boost::settings();
		$key      = 'qil_flash_candidates_v1_' . md5( (string) wp_json_encode( array( QIL_VERSION, qil_perf_product_version(), $identity, (int) $settings['flash_min_pct'], $settings['flash_exclude'] ) ) );
		// Keyed like the cache itself, so a stock or price change is seen even
		// inside one long-running process (WP-CLI, a cron batch).
		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}
		$cached = qil_perf_cache_get( $key );
		if ( is_array( $cached ) ) {
			return $memo[ $key ] = $cached;
		}
		$lock = qil_perf_lock( 'flash-candidates|' . $key, 45 );
		if ( '' === $lock ) {
			$cached = qil_perf_wait_for_cache( $key, 3.0, true, 'is_array' );
			return $memo[ $key ] = is_array( $cached ) ? $cached : array();
		}
		try {
			$rows  = array();
			$terms = self::category_term_ids();
			if ( $terms && class_exists( 'WP_Query' ) ) {
				$excluded = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $settings['flash_exclude'] ) ) );
				$query    = new WP_Query( array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'posts_per_page'         => 160,
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'post__not_in'           => $excluded, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
					'meta_key'               => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'orderby'                => array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' ),
					'meta_query'             => array( array( 'key' => '_stock_status', 'value' => 'instock' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'tax_query'              => qil_product_visibility_tax_query( $terms ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'suppress_filters'       => false,
				) );
				$ids = array_values( array_map( 'absint', (array) $query->posts ) );
				if ( $ids && function_exists( '_prime_post_caches' ) ) {
					_prime_post_caches( $ids, true, true );
				}
				foreach ( $ids as $rank => $id ) {
					$facts = self::facts( wc_get_product( $id ), (int) $settings['flash_min_pct'] );
					if ( ! $facts ) {
						continue;
					}
					$facts['id']    = $id;
					$facts['sales'] = count( $ids ) - $rank;
					$rows[ $id ]    = $facts;
				}
			}
			qil_perf_cache_set( $key, $rows, 10 * MINUTE_IN_SECONDS );
		} finally {
			qil_perf_unlock( $lock );
		}
		return $memo[ $key ] = $rows;
	}

	/* ------------------------------------------------------------------
	   Selection and rotation
	   ------------------------------------------------------------------ */

	/** Deterministic order for a window: discount, demand, stock health, rotation jitter. */
	private static function rank( array $candidates, $index ) {
		uasort( $candidates, static function ( $a, $b ) use ( $index ) {
			$score = static function ( $row ) use ( $index ) {
				$jitter = ( crc32( $index . '|' . $row['id'] ) % 1000 ) / 1000;
				$stock  = null === $row['stock'] ? 4 : min( 6, $row['stock'] / 3 );
				return $row['pct'] * 0.9 + min( 20, $row['sales'] ) * 0.6 + $stock + $jitter * 14;
			};
			return ( $score( $b ) <=> $score( $a ) ) ?: ( $a['id'] <=> $b['id'] );
		} );
		return array_keys( $candidates );
	}

	/** Persisted selection for the current window; a new window gets a fresh drop. */
	public static function state() {
		$window = self::window();
		$state  = get_option( self::STATE, array() );
		if ( is_array( $state ) && (int) ( $state['index'] ?? PHP_INT_MIN ) === $window['index'] && (int) ( $state['hours'] ?? 0 ) === $window['hours'] && ! empty( $state['ids'] ) ) {
			return $state;
		}
		$lock = qil_perf_lock( 'flash-rotate|' . $window['index'], 60 );
		if ( '' === $lock ) {
			// Another worker is choosing this window's drop; show the last one meanwhile.
			return is_array( $state ) && ! empty( $state['ids'] ) ? $state : array( 'index' => $window['index'], 'hours' => $window['hours'], 'ids' => array(), 'previous' => array() );
		}
		try {
			$settings   = QIL_Boost::settings();
			$candidates = self::candidates();
			$previous   = is_array( $state ) && (int) ( $state['index'] ?? PHP_INT_MIN ) === $window['index'] - 1 ? array_map( 'absint', (array) $state['ids'] ) : array();
			$ids        = array();
			foreach ( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $settings['flash_pins'] ) ) ) as $pin ) {
				if ( isset( $candidates[ $pin ] ) ) {
					$ids[] = $pin;
				}
			}
			$ranked = self::rank( $candidates, $window['index'] );
			foreach ( array( true, false ) as $fresh_only ) {
				foreach ( $ranked as $id ) {
					if ( count( $ids ) >= (int) $settings['flash_size'] ) {
						break 2;
					}
					if ( in_array( $id, $ids, true ) || ( $fresh_only && in_array( $id, $previous, true ) ) ) {
						continue;
					}
					$ids[] = $id;
				}
			}
			$state = array(
				'index'    => $window['index'],
				'hours'    => $window['hours'],
				'start'    => $window['start'],
				'end'      => $window['end'],
				'ids'      => array_values( $ids ),
				'previous' => $previous,
				'chosenAt' => time(),
			);
			if ( $ids ) {
				update_option( self::STATE, $state, false );
				self::schedule( $window['end'] );
			}
		} finally {
			qil_perf_unlock( $lock );
		}
		return $state;
	}

	private static function schedule( $end ) {
		if ( ! wp_next_scheduled( self::CRON ) && function_exists( 'wp_schedule_single_event' ) ) {
			wp_schedule_single_event( (int) $end + 20, self::CRON );
		}
	}

	/** Cron at the end of a window: choose the next drop and refresh cached homepages. */
	public static function rotate() {
		if ( ! QIL_Boost::on( 'flash' ) && ! ( function_exists( 'qil_continuity_enabled' ) && qil_continuity_enabled() && ! empty( QIL_Boost::settings()['flash'] ) ) ) {
			return;
		}
		self::state();
		self::purge();
	}

	/**
	 * Re-choose the drop only when a flash setting really changed; saving an
	 * unrelated Growth setting must never reshuffle a live drop mid-window.
	 */
	public static function settings_changed( $old = array(), $new = array() ) {
		$old     = is_array( $old ) ? $old : array();
		$new     = is_array( $new ) ? $new : array();
		$changed = false;
		foreach ( array( 'flash', 'flash_size', 'flash_hours', 'flash_anchor', 'flash_min_pct', 'flash_pins', 'flash_exclude', 'flash_exclusive' ) as $key ) {
			if ( (string) ( $old[ $key ] ?? '' ) !== (string) ( $new[ $key ] ?? '' ) ) {
				$changed = true;
				break;
			}
		}
		if ( ! $changed ) {
			return;
		}
		delete_option( self::STATE );
		wp_clear_scheduled_hook( self::CRON );
		self::purge( ! empty( $new['flash_exclusive'] ) || ! empty( $old['flash_exclusive'] ) );
	}

	/** Only the two homepages (and the flash archive when it follows the drop). */
	private static function purge( $archive = null ) {
		$home = get_option( 'home', '' );
		if ( ! is_string( $home ) || '' === $home ) {
			return;
		}
		$home = rtrim( $home, '/' );
		do_action( 'litespeed_purge_url', $home . '/' );
		do_action( 'litespeed_purge_url', $home . '/ar/' );
		$archive = null === $archive ? ! empty( QIL_Boost::settings()['flash_exclusive'] ) : (bool) $archive;
		if ( $archive && function_exists( 'qil_promotion_url' ) ) {
			do_action( 'litespeed_purge_url', qil_promotion_url( 'flash', false ) );
			do_action( 'litespeed_purge_url', qil_promotion_url( 'flash', true ) );
		}
	}

	/** Drop IDs still genuinely available now, topped up from the same window's order. */
	public static function live_ids() {
		$state      = self::state();
		$candidates = self::candidates();
		$size       = (int) QIL_Boost::settings()['flash_size'];
		$ids        = array();
		foreach ( (array) ( $state['ids'] ?? array() ) as $id ) {
			if ( isset( $candidates[ (int) $id ] ) ) {
				$ids[] = (int) $id;
			}
		}
		if ( count( $ids ) < $size ) {
			$previous = array_map( 'absint', (array) ( $state['previous'] ?? array() ) );
			foreach ( self::rank( $candidates, (int) ( $state['index'] ?? 0 ) ) as $id ) {
				if ( count( $ids ) >= $size ) {
					break;
				}
				if ( ! in_array( $id, $ids, true ) && ! in_array( $id, $previous, true ) ) {
					$ids[] = $id;
				}
			}
		}
		return $ids;
	}

	/* ------------------------------------------------------------------
	   Payload
	   ------------------------------------------------------------------ */

	/**
	 * Card records (shared catalogue renderer) plus flash facts, for this
	 * market and language. Short public cache; the window is part of the key.
	 */
	public static function payload( $locale = null ) {
		static $memo = array();
		if ( ! QIL_Boost::on( 'flash' ) ) {
			return null;
		}
		$locale = in_array( $locale, array( 'en', 'ar' ), true ) ? $locale : ( QIL_Boost::is_ar() ? 'ar' : 'en' );
		if ( array_key_exists( $locale, $memo ) ) {
			return $memo[ $locale ];
		}
		return $memo[ $locale ] = self::build_payload( $locale );
	}

	private static function build_payload( $locale ) {
		$window   = self::window();
		$identity = qil_perf_market_identity();
		unset( $identity['user'], $identity['session'] );
		$key    = 'qil_flash_payload_v1_' . md5( (string) wp_json_encode( array( QIL_VERSION, qil_perf_product_version(), $identity, $locale, $window, QIL_Boost::settings() ) ) );
		$cached = qil_perf_cache_get( $key );
		if ( is_array( $cached ) && isset( $cached['records'] ) ) {
			return $cached['records'] ? $cached : null;
		}
		$ids     = self::live_ids();
		$records = $ids ? qil_get_catalogue( array( 'include' => $ids, 'limit' => count( $ids ), 'orderby' => 'include', '_qil_locale' => $locale ) ) : array();
		$facts   = self::candidates();
		$meta    = array();
		$by_id   = array();
		foreach ( $records as $record ) {
			$by_id[ (int) $record['id'] ] = $record;
		}
		$ordered = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $by_id[ $id ], $facts[ $id ] ) ) {
				continue;
			}
			$ordered[]  = $by_id[ $id ];
			$meta[ $id ] = array(
				'pct'    => (int) $facts[ $id ]['pct'],
				'upTo'   => (bool) $facts[ $id ]['upTo'],
				'stock'  => null === $facts[ $id ]['stock'] ? null : (int) $facts[ $id ]['stock'],
				'endsAt' => (int) $facts[ $id ]['endsAt'],
			);
		}
		$payload = array(
			'records'     => count( $ordered ) >= self::MIN_PRODUCTS ? $ordered : array(),
			'meta'        => $meta,
			'window'      => $window,
			'generatedAt' => time(),
			'url'         => function_exists( 'qil_promotion_url' ) ? qil_promotion_url( 'flash', 'ar' === $locale ) : '',
		);
		qil_perf_cache_set( $key, $payload, 5 * MINUTE_IN_SECONDS );
		return $payload['records'] ? $payload : null;
	}

	/** Whether the current singular page carries the [qimia_flash_drop] shortcode. */
	private static function shortcode_on_page() {
		global $post;
		return is_singular() && is_a( $post, 'WP_Post' ) && has_shortcode( (string) $post->post_content, 'qimia_flash_drop' );
	}

	/**
	 * Homepage (or shortcode) pages receive the drop with the page, not by
	 * AJAX. Records the homepage already embeds for its own collections are
	 * sent as IDs only, so the same card data is never shipped twice.
	 */
	public static function page_data( $data ) {
		$home = function_exists( 'qil_render_mode' ) && 'full' === qil_render_mode();
		if ( ! $home && ! self::shortcode_on_page() ) {
			return $data;
		}
		$payload = self::payload();
		if ( $payload ) {
			$known = array();
			if ( $home && function_exists( 'qil_initial_catalogue_payload' ) ) {
				$initial = qil_initial_catalogue_payload();
				foreach ( (array) ( $initial['products'] ?? array() ) as $record ) {
					$known[ (int) ( $record['id'] ?? 0 ) ] = true;
				}
			}
			$records = array();
			foreach ( $payload['records'] as $record ) {
				$records[] = isset( $known[ (int) $record['id'] ] ) ? array( 'id' => (int) $record['id'], 'ref' => true ) : $record;
			}
			$data['flash'] = array(
				'records'     => $records,
				'meta'        => $payload['meta'],
				'start'       => (int) $payload['window']['start'],
				'end'         => (int) $payload['window']['end'],
				'hours'       => (int) $payload['window']['hours'],
				'generatedAt' => (int) $payload['generatedAt'],
			);
		}
		return $data;
	}

	/* ------------------------------------------------------------------
	   Homepage section
	   ------------------------------------------------------------------ */

	private static function oman_time( $ts, $ar ) {
		try {
			$date = new DateTimeImmutable( '@' . (int) $ts );
			$date = $date->setTimezone( new DateTimeZone( self::TIMEZONE ) );
		} catch ( Exception $error ) {
			return '';
		}
		if ( $ar ) {
			$days   = array( 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت' );
			$months = array( 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر' );
			$hour   = (int) $date->format( 'G' );
			$suffix = $hour < 12 ? 'ص' : 'م';
			$hour12 = 0 === $hour % 12 ? 12 : $hour % 12;
			return $days[ (int) $date->format( 'w' ) ] . ' ' . (int) $date->format( 'j' ) . ' ' . $months[ (int) $date->format( 'n' ) - 1 ] . '، ' . $hour12 . ':' . $date->format( 'i' ) . ' ' . $suffix . ' (بتوقيت عُمان)';
		}
		return $date->format( 'D j M, g:i A' ) . ' (Oman)';
	}

	public static function section( array $args = array() ) {
		$payload = self::payload();
		if ( ! $payload ) {
			return '';
		}
		$ar      = QIL_Boost::is_ar();
		$window  = $payload['window'];
		$count   = count( $payload['records'] );
		$hours   = (int) $window['hours'];
		$remain  = max( 0, (int) $window['end'] - time() );
		$days    = (int) floor( $remain / DAY_IN_SECONDS );
		$clock   = array( 'd' => $days, 'h' => (int) floor( ( $remain % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ), 'm' => (int) floor( ( $remain % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ), 's' => $remain % MINUTE_IN_SECONDS );
		$title   = $ar ? 'تخفيضات سريعة' : 'FLASH SALE';
		$span    = $ar ? '— ' . $hours . ' ساعة' : '— ' . $hours . ' HOURS';
		$lead    = $ar
			? sprintf( '%d تخفيضات حقيقية مختارة لمدة %d ساعة: مخزون حقيقي، ونسبة خصم حقيقية، وسعر سابق حقيقي. ثم تتغيّر المجموعة.', $count, $hours )
			: sprintf( '%d real reductions, chosen for %d hours. Real stock, real discount, real previous price — then the drop changes.', $count, $hours );
		$ends_at = self::oman_time( $window['end'], $ar );
		$units   = $ar ? array( 'd' => 'يوم', 'h' => 'ساعة', 'm' => 'دقيقة', 's' => 'ثانية' ) : array( 'd' => 'days', 'h' => 'hrs', 'm' => 'min', 's' => 'sec' );
		ob_start();
		?>
		<section id="qil-flash-drop" class="qil-section qil-flash-drop" data-qil-flash-drop data-qil-flash-end="<?php echo esc_attr( $window['end'] ); ?>" aria-labelledby="qil-flash-title">
			<div class="qil-container">
				<div class="qil-flash-stage">
					<span class="qil-flash-aurora" aria-hidden="true"></span>
					<header class="qil-flash-head">
						<div class="qil-flash-titles">
							<span class="qil-flash-kicker"><i class="qil-flash-pulse" aria-hidden="true"></i><?php echo esc_html( $ar ? sprintf( 'مجموعة مباشرة · %d منتجات', $count ) : sprintf( 'LIVE DROP · %d PRODUCTS', $count ) ); ?></span>
							<h2 id="qil-flash-title"><span class="qil-flash-word"><?php echo esc_html( $title ); ?></span> <em><?php echo esc_html( $span ); ?></em></h2>
							<p><?php echo esc_html( $lead ); ?></p>
						</div>
						<div class="qil-flash-clock" data-qil-flash-clock>
							<small><?php echo esc_html( $ar ? 'المجموعة التالية بعد' : 'NEXT DROP IN' ); ?></small>
							<div class="qil-flash-digits" role="timer" aria-live="off" dir="ltr">
								<?php foreach ( $clock as $unit => $value ) : ?>
									<span class="qil-flash-unit"><b data-qil-flash-unit="<?php echo esc_attr( $unit ); ?>"><?php echo esc_html( 'd' === $unit ? (string) $value : str_pad( (string) $value, 2, '0', STR_PAD_LEFT ) ); ?></b><small><?php echo esc_html( $units[ $unit ] ); ?></small></span>
								<?php endforeach; ?>
							</div>
							<small class="qil-flash-ends" data-qil-flash-ends><?php echo esc_html( ( $ar ? 'تنتهي: ' : 'Ends ' ) . $ends_at ); ?></small>
						</div>
					</header>
					<div class="qil-collection-grid qil-rail qil-flash-rail" data-qil-flash-grid data-qil-rail="flash-drop" aria-live="polite"><div class="qil-collection-skeleton" aria-hidden="true"><i></i><i></i><i></i><i></i></div></div>
					<footer class="qil-flash-foot">
						<div class="qil-rail-nav" data-qil-rail-nav="flash-drop"><button type="button" data-qil-rail-prev aria-label="<?php echo esc_attr( $ar ? 'السابق' : 'Previous' ); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button><button type="button" data-qil-rail-next aria-label="<?php echo esc_attr( $ar ? 'التالي' : 'Next' ); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button></div>
						<p class="qil-flash-truth"><svg aria-hidden="true"><use href="#qil-i-shield"/></svg><span><?php echo esc_html( $ar ? 'أسعار ومخزون ووكومرس المباشر. لا عدّاد وهمي: الوقت هو نهاية هذه المجموعة فعلاً.' : 'Live WooCommerce prices and stock. No fake timer: the clock is the real end of this drop.' ); ?></span></p>
						<div class="qil-flash-actions">
							<button type="button" class="qil-button qil-button-light qil-button-small" data-qil-flash-ai data-qimia-ai-open><svg aria-hidden="true"><use href="#qil-i-spark"/></svg><span><?php echo esc_html( $ar ? 'اسأل ذكاء كيميا عن هذه المجموعة' : 'Ask Qimia AI about this drop' ); ?></span></button>
							<?php if ( ! empty( $payload['url'] ) ) : ?>
								<a class="qil-flash-all" href="<?php echo esc_url( $payload['url'] ); ?>"><span><?php echo esc_html( $ar ? 'كل عروض التخفيضات' : 'All flash offers' ); ?></span><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></a>
							<?php endif; ?>
						</div>
					</footer>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function shortcode() {
		return self::section();
	}

	/* ------------------------------------------------------------------
	   Flash archive, REST feed, AI context
	   ------------------------------------------------------------------ */

	/** Optional: the flash-sale category page lists only the current drop. */
	public static function exclusive_archive( $query ) {
		if ( ! QIL_Boost::on( 'flash' ) || empty( QIL_Boost::settings()['flash_exclusive'] ) || is_admin() || ! is_object( $query ) ) {
			return;
		}
		if ( method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return;
		}
		$terms = self::category_term_ids();
		if ( ! $terms || ! function_exists( 'is_product_category' ) || ! is_product_category( $terms ) ) {
			return;
		}
		$ids = self::live_ids();
		if ( count( $ids ) >= self::MIN_PRODUCTS ) {
			$query->set( 'post__in', $ids );
			$query->set( 'orderby', 'post__in' );
		}
	}

	public static function no_edge_cache() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $uri && preg_match( '#(?:/wp-json|rest_route=)/?qimia-lab/v1/flash-drop#i', $uri ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			do_action( 'litespeed_control_set_nocache', 'Qimia flash drop feed' );
		}
	}

	public static function routes() {
		register_rest_route( 'qimia-lab/v1', '/flash-drop', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'rest' ),
			'permission_callback' => static function () {
				return QIL_Boost::on( 'flash' ) && function_exists( 'wc_get_products' );
			},
			'args'                => array(
				'qil_locale' => array( 'type' => 'string', 'default' => 'en', 'enum' => array( 'en', 'ar' ), 'sanitize_callback' => 'sanitize_key' ),
			),
		) );
	}

	/** UTM-tagged link so Instagram sales are attributable in analytics. */
	public static function campaign_url( $url, $window, $source = 'instagram' ) {
		$campaign = QIL_Boost::settings()['flash_campaign'] . '-' . gmdate( 'Ymd', (int) $window['start'] + 4 * HOUR_IN_SECONDS );
		return add_query_arg( array( 'utm_source' => $source, 'utm_medium' => 'social', 'utm_campaign' => $campaign ), $url );
	}

	/** Plain-text summary, one line per product (Instagram caption / AI). */
	public static function lines( array $payload, $ar ) {
		$lines = array();
		foreach ( $payload['records'] as $record ) {
			$id    = (int) $record['id'];
			$meta  = $payload['meta'][ $id ] ?? array();
			$price = $record['price'] ?? array();
			$now   = wp_strip_all_tags( (string) ( $price['current']['minimumFormatted'] ?? $price['formatted'] ?? '' ) );
			$was   = wp_strip_all_tags( (string) ( $price['regular']['minimumFormatted'] ?? '' ) );
			$pct   = (int) ( $meta['pct'] ?? 0 );
			$off   = $pct > 0 ? ( ! empty( $meta['upTo'] ) ? ( $ar ? 'حتى −' : 'up to −' ) : '−' ) . $pct . '%' : '';
			$line  = '• ' . $record['name'] . ' — ' . trim( $now . ( $was && $was !== $now ? ( $ar ? ' بدلاً من ' : ' (was ' ) . $was . ( $ar ? '' : ')' ) : '' ) ) . ( $off ? ' ' . $off : '' );
			$lines[] = html_entity_decode( $line, ENT_QUOTES, 'UTF-8' );
		}
		return $lines;
	}

	public static function caption( array $payload, $ar ) {
		$hours = (int) $payload['window']['hours'];
		$head  = $ar
			? '⚡ تخفيضات كيميا السريعة — ' . $hours . ' ساعة فقط'
			: '⚡ Qimia FLASH SALE — ' . $hours . ' hours only';
		$tail  = $ar
			? 'أسعار ومخزون حقيقيان. تنتهي المجموعة: ' . self::oman_time( $payload['window']['end'], true ) . "\n" . 'الرابط في البايو 🔗'
			: 'Real prices, real stock. This drop ends ' . self::oman_time( $payload['window']['end'], false ) . "\n" . 'Link in bio 🔗';
		return $head . "\n\n" . implode( "\n", self::lines( $payload, $ar ) ) . "\n\n" . $tail;
	}

	public static function rest( WP_REST_Request $request ) {
		$locale  = 'ar' === $request->get_param( 'qil_locale' ) ? 'ar' : 'en';
		$payload = self::payload( $locale );
		$out     = array( 'window' => null, 'products' => array(), 'currency' => QIL_Boost::market()['currency'] );
		if ( $payload ) {
			$out['window'] = array( 'start' => (int) $payload['window']['start'], 'end' => (int) $payload['window']['end'], 'hours' => (int) $payload['window']['hours'] );
			foreach ( $payload['records'] as $record ) {
				$id   = (int) $record['id'];
				$meta = $payload['meta'][ $id ] ?? array();
				$out['products'][] = array(
					'id'           => $id,
					'name'         => $record['name'],
					'url'          => self::campaign_url( $record['url'], $payload['window'] ),
					'image'        => $record['images'][0]['src'] ?? '',
					'price'        => (float) ( $record['price']['current']['min'] ?? $record['price']['value'] ?? 0 ),
					'regularPrice' => (float) ( $record['price']['regular']['min'] ?? 0 ),
					'discountPct'  => (int) ( $meta['pct'] ?? 0 ),
					'upTo'         => ! empty( $meta['upTo'] ),
					'stock'        => $meta['stock'] ?? null,
					'saleEndsAt'   => (int) ( $meta['endsAt'] ?? 0 ) ?: null,
				);
			}
			$out['caption'] = self::caption( $payload, 'ar' === $locale );
		}
		return function_exists( 'qil_private_live_rest_response' ) ? qil_private_live_rest_response( $out ) : rest_ensure_response( $out );
	}

	/** Let Qimia AI know which products are in the current drop. */
	public static function ai_context( $context ) {
		if ( ! is_array( $context ) || ! QIL_Boost::on( 'flash' ) ) {
			return $context;
		}
		$state = get_option( self::STATE, array() );
		if ( is_array( $state ) && ! empty( $state['ids'] ) && (int) ( $state['end'] ?? 0 ) > time() ) {
			$context['intelligence_lab']['flash_drop'] = array(
				'product_ids' => array_values( array_map( 'absint', (array) $state['ids'] ) ),
				'ends_at'     => (int) $state['end'],
				'hours'       => (int) ( $state['hours'] ?? 72 ),
			);
		}
		return $context;
	}

	/* ------------------------------------------------------------------
	   Admin preview (Growth tab)
	   ------------------------------------------------------------------ */

	public static function admin_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<h3>Current drop</h3>';
		if ( ! QIL_Boost::on( 'flash' ) ) {
			echo '<p>The Flash Drop is switched off.</p>';
			return;
		}
		$payload = self::payload( 'en' );
		$window  = self::window();
		echo '<p>Window: <strong>' . esc_html( self::oman_time( $window['start'], false ) ) . '</strong> → <strong>' . esc_html( self::oman_time( $window['end'], false ) ) . '</strong>. Candidates with a real reduction right now: <strong>' . (int) count( self::candidates() ) . '</strong>.</p>';
		if ( ! $payload ) {
			echo '<p>Fewer than ' . (int) self::MIN_PRODUCTS . ' flash-sale products currently qualify (in stock, on sale by at least ' . (int) QIL_Boost::settings()['flash_min_pct'] . '%, with an image), so the homepage section is hidden.</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Product</th><th>Now</th><th>Was</th><th>Off</th><th>Stock</th><th>Sale ends</th></tr></thead><tbody>';
		foreach ( $payload['records'] as $record ) {
			$meta = $payload['meta'][ (int) $record['id'] ] ?? array();
			echo '<tr><td><a href="' . esc_url( $record['url'] ) . '">' . esc_html( $record['name'] ) . '</a> <code>#' . (int) $record['id'] . '</code></td><td>' . wp_kses_post( $record['price']['current']['minimumFormattedHtml'] ?? '' ) . '</td><td>' . wp_kses_post( $record['price']['regular']['minimumFormattedHtml'] ?? '' ) . '</td><td>' . ( ! empty( $meta['upTo'] ) ? 'up to ' : '' ) . (int) ( $meta['pct'] ?? 0 ) . '%</td><td>' . esc_html( null === ( $meta['stock'] ?? null ) ? 'In stock' : (string) $meta['stock'] ) . '</td><td>' . esc_html( ! empty( $meta['endsAt'] ) ? self::oman_time( $meta['endsAt'], false ) : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$feed = add_query_arg( 'qil_locale', 'en', rest_url( 'qimia-lab/v1/flash-drop' ) );
		echo '<h3>Instagram &amp; automation</h3><p>Links carry <code>utm_source=instagram</code> and the campaign <code>' . esc_html( QIL_Boost::settings()['flash_campaign'] ) . '-YYYYMMDD</code>. JSON feed for automations: <a href="' . esc_url( $feed ) . '"><code>' . esc_html( $feed ) . '</code></a> (add <code>qil_locale=ar</code> for Arabic).</p>';
		foreach ( array( 'en' => 'English caption', 'ar' => 'Arabic caption' ) as $locale => $label ) {
			$localized = 'ar' === $locale ? self::payload( 'ar' ) : $payload;
			if ( ! $localized ) {
				continue;
			}
			$text = self::caption( $localized, 'ar' === $locale );
			echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><textarea readonly rows="9" class="large-text" dir="' . ( 'ar' === $locale ? 'rtl' : 'ltr' ) . '" onclick="this.select()">' . esc_textarea( $text ) . '</textarea></label></p>';
		}
	}
}
QIL_Flash_Drop::boot();
