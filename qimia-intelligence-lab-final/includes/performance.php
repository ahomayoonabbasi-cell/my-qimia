<?php
/**
 * Burst protection for storefront traffic spikes (Meta ad crawler waves,
 * landing-page surges). Presentation-neutral: nothing here changes markup,
 * prices, stock, cart, checkout, AI or account behaviour. It only decides how
 * often the expensive work runs.
 *
 *  - Link-preview crawlers (facebookexternalhit, meta-externalagent, …) get a
 *    short-lived copy of the same guest HTML, keyed without ad tracking
 *    parameters, with one render per URL and a crawler-only render budget.
 *  - Crawler requests and the Lab's own AJAX calls never spawn WP-Cron.
 *  - Optional server-cron mode with an automatic fail-safe, and an overlap
 *    lock so two WP-Cron runs never execute the same queue at once.
 *  - One shared cache/lock helper: the persistent object cache when present,
 *    transients / options otherwise.
 *
 * Emergency stop: define( 'QIL_PERFORMANCE_DISABLE', true ) in wp-config.php.
 */
defined( 'ABSPATH' ) || exit;

/* --------------------------------------------------------------------------
   Settings
   -------------------------------------------------------------------------- */

function qil_perf_defaults() {
	return array(
		'crawler_cache'  => 1,   // Serve link-preview crawlers a cached guest copy.
		'crawler_ttl'    => 5,   // Minutes.
		'crawler_rate'   => 30,  // Uncached crawler renders per minute; 0 = unlimited.
		'fragments_gate' => 1,   // Skip Woo's first-load fragment refresh for empty carts.
		'cron_mode'      => 'request',
		'cron_lock'      => 1,   // One WP-Cron run at a time.
	);
}

function qil_perf_sanitize( $raw ) {
	$raw      = is_array( $raw ) ? $raw : array();
	$defaults = qil_perf_defaults();
	return array(
		'crawler_cache'  => empty( $raw['crawler_cache'] ) ? 0 : 1,
		'crawler_ttl'    => max( 1, min( 60, (int) ( $raw['crawler_ttl'] ?? $defaults['crawler_ttl'] ) ) ),
		'crawler_rate'   => max( 0, min( 600, (int) ( $raw['crawler_rate'] ?? $defaults['crawler_rate'] ) ) ),
		'fragments_gate' => empty( $raw['fragments_gate'] ) ? 0 : 1,
		'cron_mode'      => 'server' === ( $raw['cron_mode'] ?? '' ) ? 'server' : 'request',
		'cron_lock'      => empty( $raw['cron_lock'] ) ? 0 : 1,
	);
}

/** One autoloaded option, so reading it never costs a query. */
function qil_perf_settings() {
	static $settings = null;
	if ( null !== $settings ) {
		return $settings;
	}
	$stored = get_option( 'qil_performance', false );
	if ( false === $stored ) {
		// Create it once, autoloaded, so later requests do not look it up.
		add_option( 'qil_performance', qil_perf_defaults(), '', true );
		$stored = array();
	}
	$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), qil_perf_defaults() );
	return $settings;
}

function qil_perf_enabled() {
	return ! ( defined( 'QIL_PERFORMANCE_DISABLE' ) && QIL_PERFORMANCE_DISABLE );
}

/* --------------------------------------------------------------------------
   Shared cache and lock helpers
   -------------------------------------------------------------------------- */

function qil_perf_ext_cache() {
	return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
}

/**
 * Read a shared cache entry. $fresh bypasses the request-local copy so a
 * waiting request can see what another PHP worker has just written.
 */
function qil_perf_cache_get( $key, $fresh = false, $persist = true ) {
	if ( ! $persist && ! qil_perf_ext_cache() ) {
		return false;
	}
	if ( qil_perf_ext_cache() ) {
		$found = false;
		$value = wp_cache_get( $key, 'qil_perf', $fresh, $found );
		return $found ? $value : false;
	}
	if ( $fresh ) {
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( '_transient_' . $key, 'options' );
		wp_cache_delete( '_transient_timeout_' . $key, 'options' );
	}
	return get_transient( $key );
}

/**
 * $persist = false: private (account / session) data is kept only in a
 * memory object cache (Redis, Memcached) and never written to the database.
 */
function qil_perf_cache_set( $key, $value, $ttl, $persist = true ) {
	$ttl = max( 1, (int) $ttl );
	if ( qil_perf_ext_cache() ) {
		return wp_cache_set( $key, $value, 'qil_perf', $ttl );
	}
	if ( ! $persist ) {
		return false;
	}
	return set_transient( $key, $value, $ttl );
}

function qil_perf_cache_delete( $key ) {
	if ( qil_perf_ext_cache() ) {
		return wp_cache_delete( $key, 'qil_perf' );
	}
	return delete_transient( $key );
}

/**
 * Wait for another worker to publish a cache entry without doing the same
 * expensive work in parallel. Sleeping workers consume virtually no CPU. The
 * validator is optional; when supplied, only a value it accepts is returned.
 */
function qil_perf_wait_for_cache( $key, $seconds, $persist = true, $validator = null ) {
	$seconds  = max( 0.0, (float) $seconds );
	$deadline = microtime( true ) + $seconds;
	$pause    = 60000;
	do {
		if ( $seconds > 0 ) {
			usleep( $pause );
		}
		$value = qil_perf_cache_get( $key, true, $persist );
		if ( false !== $value && ( ! is_callable( $validator ) || call_user_func( $validator, $value ) ) ) {
			return $value;
		}
		$pause = min( 250000, (int) round( $pause * 1.45 ) );
	} while ( microtime( true ) < $deadline );
	return false;
}

/**
 * Non-blocking named lock. Returns a token to release, or '' when another
 * worker holds it. Stale locks expire after $ttl seconds.
 */
function qil_perf_lock( $name, $ttl ) {
	$ttl = max( 1, (int) $ttl );
	$key = 'qil_lock_' . md5( (string) $name );
	if ( qil_perf_ext_cache() ) {
		return wp_cache_add( $key, time(), 'qil_perf_lock', $ttl ) ? $key : '';
	}
	wp_cache_delete( 'notoptions', 'options' );
	wp_cache_delete( $key, 'options' );
	$held = get_option( $key, false );
	if ( false !== $held && (int) $held < time() - $ttl ) {
		delete_option( $key );
	}
	return add_option( $key, time(), '', false ) ? $key : '';
}

/** True while another worker holds the named lock (does not take it). */
function qil_perf_lock_held( $name, $ttl ) {
	$key = 'qil_lock_' . md5( (string) $name );
	if ( qil_perf_ext_cache() ) {
		return false !== wp_cache_get( $key, 'qil_perf_lock', true );
	}
	wp_cache_delete( 'notoptions', 'options' );
	wp_cache_delete( $key, 'options' );
	$held = get_option( $key, false );
	return false !== $held && (int) $held >= time() - max( 1, (int) $ttl );
}

function qil_perf_unlock( $token ) {
	if ( ! is_string( $token ) || '' === $token ) {
		return;
	}
	if ( qil_perf_ext_cache() ) {
		wp_cache_delete( $token, 'qil_perf_lock' );
		return;
	}
	delete_option( $token );
}

/**
 * Public product-data version. Changes whenever WooCommerce clears product
 * transients (product save, stock status change, scheduled sale start/end).
 */
function qil_perf_product_version() {
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		return (string) WC_Cache_Helper::get_transient_version( 'product' );
	}
	// Before WooCommerce loads, read the same transient it maintains.
	return (string) get_transient( 'product-transient-version' );
}

/**
 * Everything that can change a price, a currency or what may be sold to this
 * visitor. Every Lab cache that holds prices or availability includes it, so
 * a visitor in Saudi Arabia (SAR) never receives Oman's (OMR) copy, a changed
 * exchange rate or tax rule invalidates at once, and a shopper with a Woo
 * session or account only ever reuses their own entries.
 */
function qil_perf_market_identity() {
	$currency = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : '';
	$country  = function_exists( 'qil_market_context' ) ? (string) ( qil_market_context()['country'] ?? '' ) : '';
	$session  = function_exists( 'WC' ) && WC()->session && method_exists( WC()->session, 'has_session' ) && WC()->session->has_session() ? (string) WC()->session->get_customer_id() : '';
	$rates    = '';
	if ( class_exists( 'Qimia_Unlimited_Geo_Currency' ) && defined( 'Qimia_Unlimited_Geo_Currency::OPTION_KEY' ) ) {
		// Exchange rates and per-market rules live in the currency engine's settings.
		$rates = md5( (string) maybe_serialize( get_option( Qimia_Unlimited_Geo_Currency::OPTION_KEY ) ) );
	}
	$identity = array(
		'currency' => $currency,
		'country'  => strtoupper( $country ),
		'decimals' => function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2,
		'pricing'  => function_exists( 'qil_pricing_cache_context' ) ? qil_pricing_cache_context() : array(),
		'rates'    => $rates,
		'user'     => (int) get_current_user_id(),
		'session'  => $session,
	);
	return (array) apply_filters( 'qil_cache_market_identity', $identity );
}

/** Anonymous visitor without a Woo session: the result may be shared. */
function qil_perf_identity_public( array $identity ) {
	return 0 === (int) ( $identity['user'] ?? 0 ) && '' === (string) ( $identity['session'] ?? '' );
}

/* --------------------------------------------------------------------------
   Link-preview and search crawlers
   -------------------------------------------------------------------------- */

/** Host helper safe to call while this plugin file is still loading. */
function qil_perf_current_host_early() {
	$host = isset( $_SERVER['HTTP_HOST'] ) && is_string( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
	return (string) preg_replace( '/:\\d+$/', '', $host );
}

/**
 * Same production/sandbox enable decision as qil_experience_enabled(), but it
 * has no dependency on functions declared later in the main plugin file.
 */
function qil_perf_experience_enabled_early() {
	if ( defined( 'QIL_DISABLE' ) && QIL_DISABLE ) {
		return false;
	}
	$host  = qil_perf_current_host_early();
	$hosts = (array) apply_filters( 'qil_allowed_hosts', array( 'qimialab.qimia.om', 'qimia.om', 'www.qimia.om' ) );
	if ( ! in_array( $host, array_map( 'strtolower', $hosts ), true ) ) {
		return false;
	}
	if ( 'qimialab.qimia.om' === $host ) {
		return true;
	}
	return '1' === (string) get_option( 'qil_enabled', '0' );
}

/** Crawler family name, or '' for everything else (including in-app browsers). */
function qil_perf_crawler_family() {
	static $family = null;
	if ( null !== $family ) {
		return $family;
	}
	$family = '';
	$agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 512 ) : '';
	if ( '' === $agent ) {
		return $family;
	}
	// Preview fetchers only. Facebook / Instagram in-app browsers (FBAN,
	// FB_IAB, Instagram) are real shoppers and deliberately do not match.
	$pattern = '/(facebookexternalhit|facebookcatalog|meta-externalagent|meta-externalfetcher|facebot|whatsapp\\/|telegrambot|twitterbot|linkedinbot|pinterestbot|slackbot-linkexpanding|discordbot|skypeuripreview)/i';
	$pattern = (string) apply_filters( 'qil_crawler_user_agent_pattern', $pattern );
	if ( @preg_match( $pattern, $agent, $match ) && ! empty( $match[1] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$family = strtolower( rtrim( $match[1], '/' ) );
	}
	return $family;
}

/** Search/index crawlers never need shopper-only JavaScript or private Woo AJAX. */
function qil_perf_noninteractive_bot() {
	static $bot = null;
	if ( null !== $bot ) {
		return $bot;
	}
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 512 ) : '';
	if ( '' === $agent ) {
		$bot = false;
		return $bot;
	}
	$pattern = '/(googlebot|googleother|google-inspectiontool|adsbot-google|bingbot|bingpreview|applebot|duckduckbot|baiduspider|yandex(?:bot|images)|oai-searchbot|chatgpt-user|gptbot|claudebot|anthropic-ai|perplexitybot|youbot|semrushbot|ahrefsbot|petalbot|bytespider)/i';
	$pattern = (string) apply_filters( 'qil_noninteractive_bot_user_agent_pattern', $pattern );
	$bot = (bool) @preg_match( $pattern, $agent ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	return $bot;
}

/**
 * If an index bot replays a shopper-only AJAX call, end it before WooCommerce,
 * the theme or Qimia callbacks do work. Ordinary document/REST crawling is not
 * blocked and keeps the same server-rendered HTML for SEO.
 */
function qil_perf_noninteractive_dynamic_short_circuit() {
	if ( ! qil_perf_noninteractive_bot() ) {
		return;
	}
	$uri      = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	$wc_ajax  = isset( $_GET['wc-ajax'] ) && is_string( $_GET['wc-ajax'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$admin_ax = wp_doing_ajax() || false !== stripos( $uri, '/wp-admin/admin-ajax.php' );
	if ( ! $wc_ajax && ! $admin_ax ) {
		return;
	}
	if ( ! headers_sent() ) {
		http_response_code( 204 );
		header( 'Cache-Control: no-store' );
		header( 'X-QIL-Bot-Dynamic: SKIP' );
	}
	exit;
}

/** Ad and analytics parameters that never change the page a crawler receives. */
function qil_perf_tracking_param( $name ) {
	$name = strtolower( (string) $name );
	if ( 0 === strpos( $name, 'utm_' ) ) {
		return true;
	}
	$names = array( 'fbclid', 'gclid', 'gbraid', 'wbraid', 'dclid', 'msclkid', 'ttclid', 'twclid', 'li_fat_id', 'igshid', 'igsh', 'mc_cid', 'mc_eid', '_ga', '_gl', 'gad_source', 'gad_campaignid', 'srsltid', 'yclid', '_hsenc', '_hsmi', 'mkt_tok', 'fb_action_ids', 'fb_action_types', 'fb_source', 'campaign_id', 'ad_id', 'adset_id', 'placement', 'site_source_name' );
	return in_array( $name, (array) apply_filters( 'qil_crawler_tracking_params', $names ), true );
}

/**
 * Cache identity: scheme, host and normalized path only. Tracking parameters
 * are dropped; any functional query argument makes the request uncacheable.
 */
function qil_perf_crawler_url_key() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
	if ( '' === $uri || '/' !== $uri[0] || strlen( $uri ) > 2048 ) {
		return '';
	}
	$path  = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
	if ( '' === $path || false !== stripos( $path, 'wp-json' ) || preg_match( '#/(?:wp-admin|wp-login\\.php|wp-cron\\.php|xmlrpc\\.php|feed)(?:/|$)#i', $path ) ) {
		return '';
	}
	if ( '' !== $query ) {
		foreach ( explode( '&', $query ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$name = urldecode( (string) strtok( $pair, '=' ) );
			if ( ! qil_perf_tracking_param( $name ) ) {
				return '';
			}
		}
	}
	// /page and /page/ are one public document for crawler collapse purposes.
	$path = '/' === $path ? '/' : untrailingslashit( $path );
	return ( is_ssl() ? 'https' : 'http' ) . '://' . qil_perf_current_host_early() . $path;
}

/** Edge country header (Cloudflare) when present. */
function qil_perf_edge_country() {
	$edge = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) && is_string( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? strtoupper( trim( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) : '';
	return preg_match( '/^[A-Z]{2}$/D', $edge ) ? $edge : 'ZZ';
}

/** One render lock per normalized URL/country, available before Woo boots. */
function qil_perf_crawler_render_lock_name( $url_key ) {
	return 'crawl-url|' . $url_key . '|' . qil_perf_edge_country();
}

/** /48 for IPv6, /24 for IPv4: one crawler datacenter block. */
function qil_perf_client_prefix() {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
	$bin = '' !== $ip ? @inet_pton( $ip ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! is_string( $bin ) ) {
		return '';
	}
	return bin2hex( 16 === strlen( $bin ) ? substr( $bin, 0, 6 ) : substr( $bin, 0, 3 ) );
}

/** Early alias to the last fully market-resolved copy for this crawler block. */
function qil_perf_crawler_alias_key( $url_key ) {
	$prefix = qil_perf_client_prefix();
	return '' === $prefix ? '' : 'qil_crawl_alias_' . md5( $url_key . '|' . qil_perf_edge_country() . '|' . $prefix . '|' . QIL_VERSION );
}

/** Market identity for a cookie-less crawler; a fresh Woo session is ignored. */
function qil_perf_crawler_identity() {
	$identity = qil_perf_market_identity();
	unset( $identity['session'] );
	return $identity;
}

function qil_perf_crawler_identity_hash() {
	return md5( (string) wp_json_encode( qil_perf_crawler_identity() ) );
}

/** Stable full key after the currency/country engine has resolved this request. */
function qil_perf_crawler_cache_key( $url_key ) {
	return 'qil_crawl_v4_' . md5( wp_json_encode( array( $url_key, qil_perf_edge_country(), qil_perf_crawler_identity(), qil_perf_product_version(), QIL_VERSION ) ) );
}

/** Cap simultaneous cold preview renders across DIFFERENT URLs. */
function qil_perf_crawler_slot_acquire( $ttl = 90 ) {
	$max = max( 1, min( 8, (int) apply_filters( 'qil_crawler_max_concurrent_renders', 2 ) ) );
	for ( $i = 0; $i < $max; ++$i ) {
		$slot = qil_perf_lock( 'crawl-slot|' . $i, $ttl );
		if ( '' !== $slot ) {
			return $slot;
		}
	}
	return '';
}

/** Crawler render budget for the current minute. Cache hits are never counted. */
function qil_perf_crawler_over_budget() {
	$limit = (int) apply_filters( 'qil_crawler_rate_limit', (int) qil_perf_settings()['crawler_rate'] );
	if ( $limit < 1 ) {
		return false;
	}
	$key = 'qil_crawl_rate_' . gmdate( 'YmdHi' );
	if ( qil_perf_ext_cache() ) {
		wp_cache_add( $key, 0, 'qil_perf', 120 );
		$count = (int) wp_cache_incr( $key, 1, 'qil_perf' );
		$count = $count > 0 ? $count : 1;
	} else {
		$count = qil_perf_db_counter( $key );
	}
	return $count > $limit;
}

/** Atomic fallback counter for simultaneous bursts without Redis/Memcached. */
function qil_perf_db_counter( $name ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = option_value + 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( 1 === $count ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name <> %s", $wpdb->esc_like( 'qil_crawl_rate_' ) . '%', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	return max( 1, $count );
}

/** Small rolling log of why crawler copies could not be stored (admin only). */
function qil_perf_crawler_log( $path, $reason ) {
	$log = get_option( 'qil_perf_crawler_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, array( time(), substr( (string) $path, 0, 160 ), substr( (string) $reason, 0, 120 ) ) );
	update_option( 'qil_perf_crawler_log', array_slice( $log, 0, 25 ), false );
}

function qil_perf_crawler_header( $state ) {
	if ( ! headers_sent() ) {
		header( 'X-QIL-Crawler-Cache: ' . $state );
	}
}

function qil_perf_send_crawler_copy( array $entry, $state = 'HIT' ) {
	if ( ! headers_sent() ) {
		http_response_code( 200 );
		header( 'Content-Type: ' . ( ! empty( $entry['type'] ) ? $entry['type'] : 'text/html; charset=UTF-8' ) );
		header( 'Cache-Control: private, max-age=300' );
		header( 'X-QIL-Crawler-Cache: ' . $state );
		if ( ! empty( $entry['at'] ) ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) $entry['at'] ) . ' GMT' );
		}
	}
	if ( 'HEAD' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
		echo $entry['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	exit;
}

function qil_perf_send_crawler_busy() {
	if ( ! headers_sent() ) {
		http_response_code( 429 );
		header( 'Retry-After: 60' );
		header( 'Cache-Control: no-store' );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-QIL-Crawler-Cache: BUSY' );
	}
	echo 'Too Many Requests';
	exit;
}

function qil_perf_send_crawler_head_ok() {
	if ( ! headers_sent() ) {
		http_response_code( 200 );
		header( 'Cache-Control: no-store' );
		header( 'X-QIL-Crawler-Cache: HEAD' );
	}
	exit;
}

/** Release the URL owner and the global cold-render slot. */
function qil_perf_crawler_owner_release( $lock = '', $slot = '' ) {
	$lock = (string) $lock;
	$slot = (string) $slot;
	if ( '' !== $lock ) {
		qil_perf_unlock( $lock );
	}
	if ( '' !== $slot ) {
		qil_perf_unlock( $slot );
	}
	if ( isset( $GLOBALS['qil_perf_early_crawler_lock'] ) && (string) $GLOBALS['qil_perf_early_crawler_lock'] === $lock ) {
		$GLOBALS['qil_perf_early_crawler_lock'] = '';
	}
	if ( isset( $GLOBALS['qil_perf_early_crawler_slot'] ) && (string) $GLOBALS['qil_perf_early_crawler_slot'] === $slot ) {
		$GLOBALS['qil_perf_early_crawler_slot'] = '';
	}
}

function qil_perf_crawler_early_release() {
	qil_perf_crawler_owner_release(
		(string) ( $GLOBALS['qil_perf_early_crawler_lock'] ?? '' ),
		(string) ( $GLOBALS['qil_perf_early_crawler_slot'] ?? '' )
	);
}

/**
 * TRUE single-flight gate while QIL itself is loading. One Meta/preview worker
 * may continue for a URL; followers are answered from the last safe alias or
 * get crawler-only 429 before WooCommerce, the theme and the page builder boot.
 */
function qil_perf_crawler_early_gate() {
	if ( '' === qil_perf_crawler_family() || ! qil_perf_experience_enabled_early() ) {
		return;
	}
	qil_perf_stop_request_cron();
	$settings = qil_perf_settings();
	$method   = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
	if ( empty( $settings['crawler_cache'] ) || ! in_array( $method, array( 'GET', 'HEAD' ), true ) || ! empty( $_COOKIE ) ) {
		return;
	}
	$url_key = qil_perf_crawler_url_key();
	if ( '' === $url_key ) {
		return;
	}

	// Fastest path: a previous request from this crawler block already resolved
	// the market-specific full cache key.
	$alias = qil_perf_crawler_alias_key( $url_key );
	$known = '' === $alias ? false : qil_perf_cache_get( $alias );
	if ( is_string( $known ) && 0 === strpos( $known, 'qil_crawl_v4_' ) ) {
		$entry = qil_perf_cache_get( $known );
		if ( is_array( $entry ) && isset( $entry['body'] ) && is_string( $entry['body'] ) && (string) qil_perf_product_version() === (string) ( $entry['pv'] ?? '' ) ) {
			qil_perf_send_crawler_copy( $entry, 'HIT-EARLY' );
		}
		if ( is_array( $entry ) && ! empty( $entry['skip'] ) && (int) ( $entry['r'] ?? 0 ) >= time() - (int) apply_filters( 'qil_crawler_skip_cooldown_seconds', 10 ) ) {
			qil_perf_send_crawler_busy();
		}
	}

	$lock = qil_perf_lock( qil_perf_crawler_render_lock_name( $url_key ), (int) apply_filters( 'qil_crawler_early_lock_seconds', 90 ) );
	if ( '' === $lock ) {
		qil_perf_send_crawler_busy();
	}
	$slot = qil_perf_crawler_slot_acquire( (int) apply_filters( 'qil_crawler_early_lock_seconds', 90 ) );
	if ( '' === $slot ) {
		qil_perf_unlock( $lock );
		qil_perf_send_crawler_busy();
	}

	$GLOBALS['qil_perf_early_crawler_url']  = $url_key;
	$GLOBALS['qil_perf_early_crawler_lock'] = $lock;
	$GLOBALS['qil_perf_early_crawler_slot'] = $slot;
	register_shutdown_function( 'qil_perf_crawler_early_release' );
	add_action( 'wp_loaded', 'qil_perf_crawler_serve', PHP_INT_MAX );
}

/** Back-compat entry point. */
function qil_perf_crawler_guard() {
	qil_perf_crawler_early_gate();
}

function qil_perf_crawler_serve() {
	$method   = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
	$settings = qil_perf_settings();
	$url_key  = (string) ( $GLOBALS['qil_perf_early_crawler_url'] ?? '' );
	if ( '' === $url_key ) {
		$url_key = empty( $settings['crawler_cache'] ) ? '' : qil_perf_crawler_url_key();
	}
	$lock = (string) ( $GLOBALS['qil_perf_early_crawler_lock'] ?? '' );
	$slot = (string) ( $GLOBALS['qil_perf_early_crawler_slot'] ?? '' );

	if ( '' === $url_key || ! qil_perf_experience_enabled_early() ) {
		qil_perf_crawler_owner_release( $lock, $slot );
		return;
	}
	$identity = qil_perf_crawler_identity();
	if ( 0 !== (int) ( $identity['user'] ?? 0 ) ) {
		qil_perf_crawler_owner_release( $lock, $slot );
		return;
	}

	$key   = qil_perf_crawler_cache_key( $url_key );
	$alias = qil_perf_crawler_alias_key( $url_key );
	if ( '' !== $alias && qil_perf_cache_get( $alias ) !== $key ) {
		qil_perf_cache_set( $alias, $key, (int) $settings['crawler_ttl'] * MINUTE_IN_SECONDS );
	}
	$entry = qil_perf_cache_get( $key );
	if ( is_array( $entry ) && isset( $entry['body'] ) && is_string( $entry['body'] ) && (string) qil_perf_product_version() === (string) ( $entry['pv'] ?? '' ) ) {
		qil_perf_crawler_owner_release( $lock, $slot );
		qil_perf_send_crawler_copy( $entry );
	}
	$skip = is_array( $entry ) && ! empty( $entry['skip'] );
	if ( 'HEAD' === $method ) {
		qil_perf_crawler_owner_release( $lock, $slot );
		qil_perf_send_crawler_head_ok();
	}

	// Fallback for calls that reached here without the early owner (for example
	// another plugin calling this function directly). Never render in parallel.
	if ( '' === $lock ) {
		$lock = qil_perf_lock( qil_perf_crawler_render_lock_name( $url_key ), 90 );
		if ( '' === $lock ) {
			qil_perf_send_crawler_busy();
		}
		$slot = qil_perf_crawler_slot_acquire( 90 );
		if ( '' === $slot ) {
			qil_perf_unlock( $lock );
			qil_perf_send_crawler_busy();
		}
	}

	if ( qil_perf_crawler_over_budget() ) {
		qil_perf_crawler_owner_release( $lock, $slot );
		qil_perf_send_crawler_busy();
	}
	ignore_user_abort( true );

	if ( $skip ) {
		if ( (int) ( $entry['r'] ?? 0 ) >= time() - (int) apply_filters( 'qil_crawler_skip_cooldown_seconds', 10 ) ) {
			qil_perf_crawler_owner_release( $lock, $slot );
			qil_perf_send_crawler_busy();
		}
		$entry['r'] = time();
		qil_perf_cache_set( $key, $entry, max( 5, (int) ( $entry['skip'] ?? time() ) + MINUTE_IN_SECONDS - time() ) );
		qil_perf_crawler_header( 'SKIP' );
		add_action( 'shutdown', static function () use ( $lock, $slot ) { qil_perf_crawler_owner_release( $lock, $slot ); }, 1000 );
		return;
	}

	qil_perf_crawler_header( 'MISS' );
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
	$GLOBALS['qil_perf_capture'] = array(
		'key'        => $key,
		'lock'       => $lock,
		'extra_lock' => $slot,
		'path'       => $path,
		'identity'   => $identity,
		'chunks'     => array(),
		'complete'   => false,
		'dirty'      => false,
		'started'    => false,
	);
	add_action( 'template_redirect', 'qil_perf_crawler_capture_start', PHP_INT_MAX );
	add_action( 'shutdown', 'qil_perf_crawler_capture_finish', 1000 );
}

/** Only ordinary public storefront documents are copied. */
function qil_perf_crawler_page_cacheable() {
	if ( is_user_logged_in() || is_404() || is_search() || is_feed() || is_preview() || is_trackback() || is_robots() || is_embed() ) {
		return false;
	}
	foreach ( array( 'is_cart', 'is_checkout', 'is_account_page', 'is_wc_endpoint_url' ) as $woo_check ) {
		if ( function_exists( $woo_check ) && call_user_func( $woo_check ) ) {
			return false;
		}
	}
	if ( is_singular() && post_password_required() ) {
		return false;
	}
	$public = is_front_page() || is_home() || is_singular() || is_archive() || ( function_exists( 'is_shop' ) && is_shop() );
	return (bool) apply_filters( 'qil_crawler_page_cacheable', $public );
}

function qil_perf_crawler_capture_start() {
	$capture = $GLOBALS['qil_perf_capture'] ?? null;
	if ( ! is_array( $capture ) || ! empty( $capture['started'] ) ) {
		return;
	}
	if ( ! qil_perf_crawler_page_cacheable() ) {
		return;
	}
	$GLOBALS['qil_perf_capture']['started'] = true;
	ob_start( 'qil_perf_crawler_buffer' );
}

/** Output handler: passes every byte through unchanged and keeps a copy. */
function qil_perf_crawler_buffer( $buffer, $phase ) {
	if ( ! isset( $GLOBALS['qil_perf_capture'] ) || ! is_array( $GLOBALS['qil_perf_capture'] ) ) {
		return $buffer;
	}
	if ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) {
		$GLOBALS['qil_perf_capture']['dirty'] = true;
	}
	$GLOBALS['qil_perf_capture']['chunks'][] = (string) $buffer;
	if ( $phase & PHP_OUTPUT_HANDLER_FINAL ) {
		$GLOBALS['qil_perf_capture']['complete'] = true;
	}
	return $buffer;
}

function qil_perf_crawler_capture_finish() {
	$capture = $GLOBALS['qil_perf_capture'] ?? null;
	if ( ! is_array( $capture ) ) {
		return;
	}
	unset( $GLOBALS['qil_perf_capture'] );
	$why = 'error';
	try {
		$why = qil_perf_crawler_store( $capture );
	} finally {
		if ( '' !== $why ) {
			$ttl = in_array( $why, array( 'incomplete', 'dirty', 'error' ), true ) ? 20 : MINUTE_IN_SECONDS;
			qil_perf_cache_set( $capture['key'], array( 'skip' => time(), 'why' => $why, 'r' => time(), 'pv' => (string) qil_perf_product_version() ), $ttl );
			if ( 'not-cacheable' !== $why ) {
				qil_perf_crawler_log( $capture['path'] ?? '', $why );
			}
		}
		qil_perf_crawler_owner_release( (string) ( $capture['lock'] ?? '' ), (string) ( $capture['extra_lock'] ?? '' ) );
	}
}

/** Store a complete ordinary 200 HTML document. Empty string means success. */
function qil_perf_crawler_store( array $capture ) {
	if ( empty( $capture['started'] ) ) {
		return 'not-cacheable';
	}
	if ( 200 !== (int) http_response_code() ) {
		return 'status-' . (int) http_response_code();
	}
	if ( empty( $capture['complete'] ) ) {
		return 'incomplete';
	}
	if ( ! empty( $capture['dirty'] ) ) {
		return 'dirty';
	}
	$before = (array) ( $capture['identity'] ?? array() );
	$after  = qil_perf_crawler_identity();
	if ( md5( (string) wp_json_encode( $after ) ) !== md5( (string) wp_json_encode( $before ) ) ) {
		$changed = array();
		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $field ) {
			if ( wp_json_encode( $before[ $field ] ?? null ) !== wp_json_encode( $after[ $field ] ?? null ) ) {
				$changed[] = $field;
			}
		}
		return 'market-changed:' . implode( ',', $changed );
	}
	$type = 'text/html; charset=UTF-8';
	foreach ( headers_list() as $header ) {
		if ( 0 === stripos( $header, 'Location:' ) ) {
			return 'redirect';
		}
		if ( 0 === stripos( $header, 'Content-Type:' ) ) {
			$type = trim( substr( $header, 13 ) );
		}
	}
	$body = implode( '', $capture['chunks'] );
	if ( 0 !== stripos( $type, 'text/html' ) ) {
		return 'not-html';
	}
	if ( strlen( $body ) < 512 || strlen( $body ) > 3 * MB_IN_BYTES || false === stripos( $body, '</html>' ) ) {
		return 'size-' . strlen( $body );
	}
	$ttl = (int) apply_filters( 'qil_crawler_cache_ttl', (int) qil_perf_settings()['crawler_ttl'] * MINUTE_IN_SECONDS );
	return qil_perf_cache_set( $capture['key'], array( 'body' => $body, 'type' => $type, 'at' => time(), 'pv' => (string) qil_perf_product_version() ), $ttl ) ? '' : 'cache-write';
}

/* --------------------------------------------------------------------------
   WP-Cron
   -------------------------------------------------------------------------- */

/** Stop request-triggered cron without touching direct/server wp-cron.php runs. */
function qil_perf_stop_request_cron() {
	// WordPress 6.9+ registers wp_cron() on init and _wp_cron() on shutdown;
	// older/alternate-cron paths may still use wp_loaded. Remove every request
	// spawn path, while direct wp-cron.php (DOING_CRON) remains untouched.
	remove_action( 'init', 'wp_cron' );
	remove_action( 'shutdown', '_wp_cron' );
	remove_action( 'wp_loaded', '_wp_cron', 20 );
	add_filter( 'pre_spawn_cron', '__return_true', PHP_INT_MAX );
}

/** Seconds since a direct server/CLI cron run was last seen. */
function qil_perf_cron_heartbeat_age() {
	$seen = (int) get_option( 'qil_cron_heartbeat', 0 );
	return $seen > 0 ? max( 0, time() - $seen ) : PHP_INT_MAX;
}

function qil_perf_cron_guard() {
	$settings = qil_perf_settings();
	if ( wp_doing_cron() ) {
		// Hostinger/server cron calls wp-cron.php without doing_wp_cron. Spawned
		// visitor cron includes that token and must not overwrite the heartbeat.
		if ( empty( $_GET['doing_wp_cron'] ) && qil_perf_cron_heartbeat_age() >= MINUTE_IN_SECONDS ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option( 'qil_cron_heartbeat', time(), true );
		}
		if ( ! empty( $settings['cron_lock'] ) ) {
			$ttl  = max( 60, (int) apply_filters( 'qil_cron_lock_ttl', 5 * MINUTE_IN_SECONDS ) );
			$lock = qil_perf_lock( 'wp-cron-run', $ttl );
			if ( '' === $lock ) {
				exit; // Another cron worker is already processing the queue.
			}
			register_shutdown_function( 'qil_perf_unlock', $lock );
		}
		return;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	// Search/index/preview bots and Qimia's own background AJAX never get to
	// start unrelated scheduled jobs while serving a request.
	if ( qil_perf_noninteractive_bot() || '' !== qil_perf_crawler_family() ) {
		qil_perf_stop_request_cron();
		return;
	}
	$endpoint = isset( $_GET['wc-ajax'] ) && is_string( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 0 === strpos( $endpoint, 'qil_' ) ) {
		qil_perf_stop_request_cron();
		return;
	}

	// A recent direct server-cron heartbeat is authoritative even if an older
	// saved setting still says "request". If the external cron disappears, the
	// 20-minute fail-safe restores ordinary WordPress spawning automatically.
	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		return;
	}
	$server_alive = qil_perf_cron_heartbeat_age() < (int) apply_filters( 'qil_cron_failsafe_seconds', 20 * MINUTE_IN_SECONDS );
	if ( $server_alive ) {
		qil_perf_stop_request_cron();
		return;
	}
	// "server" mode is intentionally fail-safe: it suppresses request cron only
	// after a real server run has been observed. Until then WordPress continues
	// to schedule jobs normally, preventing emails/sales/actions from stopping.
	if ( 'server' === $settings['cron_mode'] ) {
		return;
	}
}

/* --------------------------------------------------------------------------
   WooCommerce cart fragments
   -------------------------------------------------------------------------- */

/** Remove shopper-only scripts from search and preview crawlers. */
function qil_perf_dequeue_bot_interactivity() {
	if ( ! qil_perf_noninteractive_bot() && '' === qil_perf_crawler_family() ) {
		return;
	}
	foreach ( array(
		'wc-cart-fragments', 'wc-add-to-cart', 'wd-cart-widget', 'wd-action-after-add-to-cart', 'wd-on-remove-from-cart',
		'qimia-intelligence-lab', 'qimia-lab-navigation', 'qil-personalization', 'qimia-shopping-context',
		'qh-site-chrome', 'qimia-experience', 'qimia-lab-product-tools', 'qil-brain-signals'
	) as $handle ) {
		wp_dequeue_script( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'qil_perf_dequeue_bot_interactivity', PHP_INT_MAX - 5 );

/**
 * WooCommerce refreshes cart fragments on the first page of every browser
 * session, even when the visitor has never had a cart. That is one full
 * WordPress + WooCommerce request per ad visitor. When no cart cookie exists
 * the cached mini-cart is already correct, so that single automatic request is
 * answered in the browser. Explicit refreshes (bag open, currency change, add
 * or remove) and every visitor with a cart are untouched.
 */
function qil_perf_fragments_gate_script() {
	return '(function(){try{var $=window.jQuery;if(!$||!$.ajaxTransport||window.__qilFragmentsGate)return;window.__qilFragmentsGate=1;'
		. 'var armed=true;$(function(){setTimeout(function(){armed=false;},2500);});'
		. 'var hasCart=function(){return /(?:^|;\\s*)(?:woocommerce_items_in_cart|woocommerce_cart_hash)=[^;]+/.test(document.cookie||"");};'
		. '$.ajaxTransport("+*",function(o){if(!armed||String(o.url||"").indexOf("get_refreshed_fragments")<0||hasCart())return;armed=false;'
		. 'return{send:function(h,done){setTimeout(function(){done(200,"success",{text:JSON.stringify({fragments:{},cart_hash:""})},"Content-Type: application/json\\r\\n");},0);},abort:function(){}};});'
		. '}catch(e){}})();';
}

function qil_perf_fragments_gate() {
	if ( ! qil_perf_enabled() || empty( qil_perf_settings()['fragments_gate'] ) || is_admin() || qil_perf_noninteractive_bot() || '' !== qil_perf_crawler_family() || ! wp_script_is( 'wc-cart-fragments', 'enqueued' ) ) {
		return;
	}
	if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
		return;
	}
	static $added = false;
	if ( $added ) {
		return;
	}
	$added = true;
	wp_add_inline_script( 'wc-cart-fragments', qil_perf_fragments_gate_script(), 'before' );
}
add_action( 'wp_enqueue_scripts', 'qil_perf_fragments_gate', PHP_INT_MAX - 1 );

/* --------------------------------------------------------------------------
   Settings screen
   -------------------------------------------------------------------------- */

add_action( 'admin_init', static function () {
	register_setting( 'qil_performance_settings', 'qil_performance', array(
		'type'              => 'array',
		'default'           => qil_perf_defaults(),
		'sanitize_callback' => 'qil_perf_sanitize',
	) );
} );

/** LiteSpeed Cache's drop-query-string list, when that plugin is active. */
function qil_perf_litespeed_drop_qs() {
	if ( ! defined( 'LSCWP_V' ) ) {
		return null;
	}
	$value = get_option( 'litespeed.conf.cache-drop_qs', null );
	if ( null === $value ) {
		return null;
	}
	$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
	return array_values( array_filter( array_map( 'trim', (array) $items ) ) );
}

function qil_perf_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s        = qil_perf_settings();
	$age      = qil_perf_cron_heartbeat_age();
	$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$root     = untrailingslashit( ABSPATH );
	$cron_url = site_url( 'wp-cron.php' );
	$drop_qs  = qil_perf_litespeed_drop_qs();
	$field    = static function ( $key ) { return 'qil_performance[' . $key . ']'; };

	echo '<section class="qimia-admin-panel"><h2>Traffic bursts &amp; server load</h2>';
	echo '<p>These controls change how often expensive work runs. They never change the storefront design, prices, stock, cart, checkout or AI behaviour.</p>';
	if ( ! qil_perf_enabled() ) {
		echo '<div class="notice notice-warning inline"><p><code>QIL_PERFORMANCE_DISABLE</code> is set: crawler protection, the cron guard and the fragments gate are all off.</p></div>';
	}
	echo '<form method="post" action="options.php">';
	settings_fields( 'qil_performance_settings' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">Link-preview crawlers</th><td><input type="hidden" name="' . esc_attr( $field( 'crawler_cache' ) ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $field( 'crawler_cache' ) ) . '" value="1" ' . checked( 1, (int) $s['crawler_cache'], false ) . '> Serve Meta / WhatsApp / Telegram preview crawlers a cached copy of the same guest page</label>';
	echo '<p class="description">Ad links such as <code>?fbclid=…&amp;utm_source=…</code> share one copy per page. Only one crawler request renders a URL; cached followers are served immediately and duplicate cold followers receive crawler-only 429 instead of consuming PHP/MySQL. At most two different cold crawler URLs render at once.</p>';
	echo '<p><label>Keep the copy for <input type="number" class="small-text" min="1" max="60" name="' . esc_attr( $field( 'crawler_ttl' ) ) . '" value="' . esc_attr( $s['crawler_ttl'] ) . '"> minutes</label> (a product edit or stock change refreshes it sooner)</p>';
	echo '<p><label>Uncached crawler renders per minute <input type="number" class="small-text" min="0" max="600" name="' . esc_attr( $field( 'crawler_rate' ) ) . '" value="' . esc_attr( $s['crawler_rate'] ) . '"></label> — above this, crawlers receive <code>429 Retry-After: 60</code>. 0 = no limit.</p></td></tr>';
	echo '<tr><th scope="row">Cart fragments</th><td><input type="hidden" name="' . esc_attr( $field( 'fragments_gate' ) ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $field( 'fragments_gate' ) ) . '" value="1" ' . checked( 1, (int) $s['fragments_gate'], false ) . '> Skip WooCommerce\'s automatic <code>get_refreshed_fragments</code> call on first page load when the visitor has no cart</label>';
	echo '<p class="description">Visitors with a cart, and every explicit refresh (opening the bag, adding, removing, changing currency), still refresh from WooCommerce.</p></td></tr>';
	echo '<tr><th scope="row">WP-Cron</th><td><label><input type="radio" name="' . esc_attr( $field( 'cron_mode' ) ) . '" value="request" ' . checked( 'request', $s['cron_mode'], false ) . '> Visitors may trigger cron (WordPress default)</label><br>';
	echo '<label><input type="radio" name="' . esc_attr( $field( 'cron_mode' ) ) . '" value="server" ' . checked( 'server', $s['cron_mode'], false ) . '> A server cron job runs WP-Cron; visitors do not</label>';
	echo '<p class="description">Fail-safe: if no server run has been seen for 20 minutes, visitors trigger cron again automatically, so scheduled sales, emails and Action Scheduler never stop.</p>';
	echo '<p><input type="hidden" name="' . esc_attr( $field( 'cron_lock' ) ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $field( 'cron_lock' ) ) . '" value="1" ' . checked( 1, (int) $s['cron_lock'], false ) . '> Never run two WP-Cron passes at the same time</label></p></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Save load settings' );
	echo '</form>';

	echo '<h3>Status</h3><table class="widefat striped"><tbody>';
	$rows = array(
		'Persistent object cache' => qil_perf_ext_cache() ? 'Active — locks and caches are shared in memory' : 'Not detected — caches use transients (works; Redis/Memcached is faster)',
		'DISABLE_WP_CRON'         => $disabled ? 'Defined — visitors never trigger cron' : 'Not defined',
		'Last server cron run'    => PHP_INT_MAX === $age ? 'Never seen' : human_time_diff( time() - $age ) . ' ago',
		'Request-triggered cron'  => $disabled ? 'Off (wp-config.php)' : ( $age < 20 * MINUTE_IN_SECONDS ? 'Off (server cron heartbeat detected)' : 'On (fail-safe)' ),
	);
	if ( null !== $drop_qs ) {
		$missing = array_diff( array( 'fbclid', 'gclid', 'utm*' ), $drop_qs );
		$rows['LiteSpeed "Drop Query String"'] = $missing ? 'Missing: ' . implode( ', ', $missing ) . ' — add them so ad links share the page cache' : 'OK — ad tracking parameters share the page cache';
	}
	foreach ( $rows as $label => $value ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h3>Server cron (recommended)</h3><ol>';
	echo '<li>Add to <code>wp-config.php</code>, above “stop editing”: <code>define( \'DISABLE_WP_CRON\', true );</code> — or choose “A server cron job runs WP-Cron” above.</li>';
	echo '<li>Add one of these to the hosting control panel’s Cron Jobs (every 5 minutes). <code>flock -n</code> skips a run while the previous one is still working:</li></ol>';
	echo '<p><code>*/5 * * * * cd ' . esc_html( $root ) . ' &amp;&amp; flock -n /tmp/qimia-wp-cron.lock php wp-cron.php &gt;/dev/null 2&gt;&amp;1</code></p>';
	echo '<p><code>*/5 * * * * flock -n /tmp/qimia-wp-cron.lock wget -q -O /dev/null "' . esc_html( $cron_url ) . '"</code></p>';
	echo '<h3>Page cache for ad links</h3><p>In LiteSpeed Cache → Cache → Advanced → <strong>Drop Query String</strong>, keep <code>fbclid</code>, <code>gclid</code>, <code>utm*</code>, <code>gad_source</code>, <code>srsltid</code> and <code>_ga</code>. Each Meta ad click otherwise carries a unique <code>fbclid</code> and bypasses the page cache.</p>';
	qil_perf_admin_diagnostics();
	echo '<p class="description">Emergency stop for this panel only: <code>define( \'QIL_PERFORMANCE_DISABLE\', true );</code></p></section>';
}

/* --------------------------------------------------------------------------
   admin-ajax diagnostics
   -------------------------------------------------------------------------- */

/**
 * Samples which admin-ajax actions storefront visitors trigger (1 in 25
 * requests), so the Performance tab can name the plugin behind a POST that
 * runs on every page view. Diagnostic only: nothing is blocked or changed.
 */
function qil_perf_ajax_sample() {
	if ( ! wp_doing_ajax() || 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || wp_rand( 1, 25 ) !== 1 ) {
		return;
	}
	$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? substr( sanitize_key( wp_unslash( $_REQUEST['action'] ) ), 0, 80 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$action = '' === $action ? '(no action)' : $action;
	$day    = gmdate( 'Ymd' );
	$stats  = get_option( 'qil_perf_ajax_actions', array() );
	if ( ! is_array( $stats ) || ( $stats['day'] ?? '' ) !== $day ) {
		$stats = array( 'day' => $day, 'actions' => array() );
	}
	$total = 0; foreach ( (array) ( $stats['actions'] ?? array() ) as $r ) { $total += (int) ( $r[0] ?? 0 ); }
	if ( $total >= 200 ) { return; }
	$row = $stats['actions'][ $action ] ?? array( 0, 0, 0 );
	$row[0] = (int) $row[0] + 1;
	$row[ is_user_logged_in() ? 2 : 1 ] = (int) $row[ is_user_logged_in() ? 2 : 1 ] + 1;
	$stats['actions'][ $action ] = $row;
	if ( count( $stats['actions'] ) > 40 ) {
		uasort( $stats['actions'], static function ( $a, $b ) { return $b[0] <=> $a[0]; } );
		$stats['actions'] = array_slice( $stats['actions'], 0, 40, true );
	}
	update_option( 'qil_perf_ajax_actions', $stats, false );
}

function qil_perf_admin_diagnostics() {
	echo '<h3>Why crawler copies were not stored (latest first)</h3>';
	$log = get_option( 'qil_perf_crawler_log', array() );
	if ( ! is_array( $log ) || ! $log ) {
		echo '<p>Nothing recorded — every crawler render that could be stored was stored.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>When</th><th>Path</th><th>Reason</th></tr></thead><tbody>';
		foreach ( $log as $row ) {
			echo '<tr><td>' . esc_html( human_time_diff( (int) $row[0] ) ) . ' ago</td><td><code>' . esc_html( $row[1] ) . '</code></td><td><code>' . esc_html( $row[2] ) . '</code></td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '<h3>admin-ajax.php actions today (sampled 1 in 25, max 200/day)</h3>';
	$stats = get_option( 'qil_perf_ajax_actions', array() );
	if ( ! is_array( $stats ) || empty( $stats['actions'] ) ) {
		echo '<p>No samples yet.</p>';
		return;
	}
	$actions = $stats['actions'];
	uasort( $actions, static function ( $a, $b ) { return $b[0] <=> $a[0]; } );
	echo '<p class="description">Every admin-ajax request loads all of WordPress. An action that appears for almost every guest page view belongs to a theme or plugin that calls it on page load; its setting (view counter, tracking, live stock…) is usually where it can be turned off or delayed.</p>';
	echo '<table class="widefat striped"><thead><tr><th>Action</th><th>Samples</th><th>Guests</th><th>Signed in</th></tr></thead><tbody>';
	foreach ( array_slice( $actions, 0, 15, true ) as $name => $row ) {
		echo '<tr><td><code>' . esc_html( $name ) . '</code></td><td>' . (int) $row[0] . '</td><td>' . (int) $row[1] . '</td><td>' . (int) $row[2] . '</td></tr>';
	}
	echo '</tbody></table>';
}

/* --------------------------------------------------------------------------
   Bootstrap (runs while plugins load)
   -------------------------------------------------------------------------- */

function qil_perf_bootstrap() {
	if ( ! qil_perf_enabled() ) {
		return;
	}
	if ( ! wp_doing_cron() ) {
		// Cheapest exits first: stale bot AJAX and duplicate preview workers stop
		// before the rest of Qimia/Woo/theme code is required.
		qil_perf_noninteractive_dynamic_short_circuit();
		qil_perf_crawler_guard();
	}
	qil_perf_cron_guard();
	if ( wp_doing_ajax() ) {
		// Diagnostic only, heavily sampled so it cannot become a load source.
		add_action( 'admin_init', 'qil_perf_ajax_sample', 1 );
	}
}
qil_perf_bootstrap();
