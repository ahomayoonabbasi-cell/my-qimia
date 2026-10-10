<?php
/**
 * Plugin Name: Qimia Intelligence Lab
 * Description: The bilingual intelligent Qimia storefront, with isolated staging protections.
 * Version: 1.18.22
 * Author: Qimia
 * Text Domain: qimia-intelligence-lab
 * Requires at least: 6.2
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'QIL_VERSION', '1.18.22' );
define( 'QIL_SCHEMA_VERSION', 15 );
define( 'QIL_FILE', __FILE__ );
define( 'QIL_DIR', plugin_dir_path( __FILE__ ) );
define( 'QIL_URL', plugin_dir_url( __FILE__ ) );

// First: link-preview crawlers can be answered here, before anything else loads.
require_once QIL_DIR . 'includes/performance.php';
require_once QIL_DIR . 'includes/sections.php';
require_once QIL_DIR . 'includes/goal-engine.php';
require_once QIL_DIR . 'includes/product.php';
require_once QIL_DIR . 'includes/elementor.php';
require_once QIL_DIR . 'includes/storefront.php';
require_once QIL_DIR . 'includes/product-integration.php';
require_once QIL_DIR . 'includes/hero-studio.php';
require_once QIL_DIR . 'includes/navigation.php';
require_once QIL_DIR . 'includes/promotions.php';
require_once QIL_DIR . 'includes/cashback.php';
require_once QIL_DIR . 'includes/product-knowledge.php';
require_once QIL_DIR . 'includes/site-chrome.php';
require_once QIL_DIR . 'includes/customer-experience.php';
require_once QIL_DIR . 'includes/commerce-continuity.php';
require_once QIL_DIR . 'includes/personalization.php';
require_once QIL_DIR . 'includes/customer-brain.php';
// 1.18.0 sales features: cart cashback ladder + stack completion, flash drop,
// member wallet / replenishment and cashback stacks. QIL_BOOST_DISABLE stops all four.
require_once QIL_DIR . 'includes/commerce-boost.php';
require_once QIL_DIR . 'includes/flash-drop.php';
require_once QIL_DIR . 'includes/member-hub.php';
require_once QIL_DIR . 'includes/cashback-stacks.php';

/**
 * Normalize the request host for storefront eligibility and sandbox isolation.
 * This deliberately does not rely on WP_ENVIRONMENT_TYPE, which can be mis-set.
 */
function qil_current_host() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
	return (string) preg_replace( '/:\d+$/', '', $host );
}

/**
 * The sandbox: the one host that may have commerce disabled underneath it.
 *
 * This is deliberately NOT the same question as "should the Qimia experience
 * render here". Every destructive guard in this plugin — no email, no payment
 * gateways, no order creation, no cron, no webhooks, no outbound HTTP, noindex —
 * hangs off this function alone. It is an exact match on one hostname and it
 * must never be widened: those guards on a live store would stop it selling and
 * drop it out of search.
 */
function qil_is_staging_sandbox() {
	$host    = qil_current_host();
	$allowed = 'qimialab.qimia.om' === $host;

	/**
	 * Allows a developer to make the sandbox check stricter. It cannot widen it.
	 */
	return $allowed && (bool) apply_filters( 'qil_is_staging_host', true, $host );
}

/**
 * Back-compat name. Everything that meant "is this the sandbox" still works.
 */
function qil_is_staging_host() {
	return qil_is_staging_sandbox();
}

/**
 * Whether the Qimia storefront experience may render on this host.
 *
 * Separate from the sandbox question, so production can have the experience
 * without inheriting a single one of the sandbox guards. Three switches, in the
 * order they are checked:
 *
 *   1. QIL_DISABLE in wp-config.php — an emergency stop that works even when
 *      wp-admin is unreachable, and does not require deactivating the plugin.
 *   2. The host allow-list. Anything not on it gets nothing.
 *   3. The qil_enabled option. The sandbox is always on; explicit plugin
 *      activation enables the experience on the two production hosts. A saved
 *      Settings off switch remains off until activation or another saved choice.
 */
function qil_experience_enabled() {
	if ( defined( 'QIL_DISABLE' ) && QIL_DISABLE ) {
		return false;
	}

	$host  = qil_current_host();
	$hosts = (array) apply_filters(
		'qil_allowed_hosts',
		array( 'qimialab.qimia.om', 'qimia.om', 'www.qimia.om' )
	);
	if ( ! in_array( $host, array_map( 'strtolower', $hosts ), true ) ) {
		return false;
	}

	if ( qil_is_staging_sandbox() ) {
		return (bool) apply_filters( 'qil_experience_enabled', true, $host );
	}

	$enabled = '1' === (string) get_option( 'qil_enabled', '0' );
	return (bool) apply_filters( 'qil_experience_enabled', $enabled, $host );
}

/**
 * Keep this staging environment private and incapable of commerce side effects.
 */
function qil_staging_safety_guards() {
	if ( ! qil_is_staging_host() ) {
		return;
	}

	add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
	add_filter( 'pre_spawn_cron', '__return_true', PHP_INT_MAX );
	add_filter( 'woocommerce_webhook_should_deliver', '__return_false', PHP_INT_MAX );
	add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', PHP_INT_MAX );
	add_filter(
		'pre_http_request',
		static function ( $preempt, $args, $url ) {
			$parts  = wp_parse_url( $url );
			$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
			$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
			$port   = isset( $parts['port'] ) ? (int) $parts['port'] : 443;

            // Exact same-host Worker egress; no redirects, query tricks or alternate domains.
            $worker_path = isset($parts['path']) ? (string)$parts['path'] : '';
            $headers = $args['headers'] ?? array();
            $secret = is_array($headers) ? ($headers['X-Qimia-Gateway-Secret'] ?? '') : '';
            if ('https' === $scheme && 'qimialab.qimia.om' === $host && 443 === $port
                && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
                && 'POST' === strtoupper((string)($args['method'] ?? 'GET'))
                && in_array($worker_path,array('/ai-gateway/internal/provider/responses','/ai-gateway/internal/provider/audio/speech','/ai-gateway/internal/provider/audio/transcriptions'),true)
                && (int)($args['redirection'] ?? 5) === 0 && class_exists('AAICE_Settings')
                && is_string($secret) && strlen($secret)>=32 && hash_equals(AAICE_Settings::gateway_secret(),$secret)) {
                return $preempt;
            }
			// Preserve the pre-existing direct-provider exception; all other staging egress stays blocked.
			if ( 'https' === $scheme && 'api.openai.com' === $host && 443 === $port ) {
				return $preempt;
			}

			return new WP_Error(
				'qil_staging_outbound_blocked',
				'Outbound HTTP is disabled on the Qimia Lab staging host.',
				array( 'host' => $host )
			);
		},
		PHP_INT_MAX,
		3
	);
	add_filter(
		'wp_robots',
		static function ( $robots ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
			return $robots;
		},
		PHP_INT_MAX
	);

	add_action(
		'send_headers',
		static function () {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		}
	);

	add_action(
		'template_redirect',
		static function () {
			if ( function_exists( 'is_checkout' ) && is_checkout() ) {
				wp_safe_redirect( home_url( '/' ), 302, 'Qimia Intelligence Lab' );
				exit;
			}
		},
		0
	);

	// Classic checkout is rejected before WooCommerce can persist an order.
	add_action(
		'woocommerce_after_checkout_validation',
		static function ( $data, $errors ) {
			if ( is_a( $errors, 'WP_Error' ) ) {
				$errors->add( 'qil_staging_checkout_disabled', 'Checkout is disabled on this private staging site.' );
			}
		},
		PHP_INT_MAX,
		2
	);
	add_action(
		'woocommerce_checkout_create_order',
		static function ( $order, $data ) {
			throw new Exception( 'Order creation is disabled on this private staging site.' );
		},
		PHP_INT_MAX,
		2
	);

	// Block Store API and REST order writes while leaving cart endpoints native.
	add_filter(
		'rest_pre_dispatch',
		static function ( $result, $server, $request ) {
			if ( ! is_a( $request, 'WP_REST_Request' ) ) {
				return $result;
			}

			$method = strtoupper( (string) $request->get_method() );
			$route  = (string) $request->get_route();
			$write  = in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true );
			$orders = (bool) preg_match( '#^/wc/v[1-3]/orders(?:/|$)#', $route );
			$checkout = (bool) preg_match( '#^/wc/store/v[0-9]+/checkout(?:/|$)#', $route );

			if ( $write && ( $orders || $checkout ) ) {
				return new WP_Error(
					'qil_staging_checkout_disabled',
					'Order creation is disabled on this private staging site.',
					array( 'status' => 403 )
				);
			}

			return $result;
		},
		PHP_INT_MAX,
		3
	);
}
add_action( 'plugins_loaded', 'qil_staging_safety_guards', 20 );


/* ==========================================================================
   Production switch
   ========================================================================== */

/**
 * An explicit plugin activation enables the complete storefront on Qimia live.
 * Updates do not run this hook, so a merchant's saved off switch stays off.
 * CLI activation has no HTTP host; use WordPress's configured home URL then.
 * Sandbox controls and commerce/indexing options are deliberately not changed.
 */
function qil_activate_storefront() {
	$host = qil_current_host();
	if ( '' === $host ) {
		$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	}
	if ( in_array( $host, array( 'qimia.om', 'www.qimia.om' ), true ) ) {
		update_option( 'qil_enabled', '1' );
	}
}
register_activation_hook( QIL_FILE, 'qil_activate_storefront' );

/**
 * A Settings screen with one control, and an emergency stop that does not need
 * one.
 *
 * On the sandbox the experience is always on and this screen only reports that.
 * Activating the plugin on production enables the experience. This screen can
 * switch it off again without changing commerce, content or plugin activation.
 */
function qil_register_settings() {
	register_setting(
		'qil_settings',
		'qil_enabled',
		array(
			'type'              => 'string',
			'sanitize_callback' => static function ( $value ) {
				return '1' === (string) $value ? '1' : '0';
			},
			'default'           => '0',
		)
	);
}
add_action( 'admin_init', 'qil_register_settings' );

function qil_register_settings_page() {
	add_options_page(
		'Qimia Intelligence Lab',
		'Qimia Lab',
		'manage_options',
		'qimia-intelligence-lab',
		'qil_render_settings_page'
	);
}
add_action( 'admin_menu', 'qil_register_settings_page' );

function qil_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$host      = qil_current_host();
	$sandbox   = qil_is_staging_sandbox();
	$disabled  = defined( 'QIL_DISABLE' ) && QIL_DISABLE;
	$enabled   = qil_experience_enabled();
	?>
	<div class="wrap">
		<h1>Qimia Intelligence Lab</h1>

		<?php if ( $disabled ) : ?>
			<div class="notice notice-warning"><p>
				<strong>QIL_DISABLE is set in wp-config.php.</strong> The storefront experience is off everywhere on this install, whatever this page says. Remove that line to use the switch below.
			</p></div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">This host</th>
				<td><code><?php echo esc_html( $host ); ?></code>
					<?php if ( $sandbox ) : ?>
						<p class="description"><strong>Sandbox.</strong> Email, payment gateways, order creation, cron, webhooks and outbound HTTP are all disabled here, and the site is set to noindex. The experience is always on and cannot be switched off from this page. None of those guards exist on any other host.</p>
					<?php else : ?>
						<p class="description"><strong>Live host.</strong> None of the sandbox guards apply: email, payments, orders, cron and webhooks all behave normally.</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">Status</th>
				<td><?php echo $enabled ? '<span style="color:#146c43;font-weight:600">Rendering</span>' : '<span style="color:#8a6d3b;font-weight:600">Not rendering — the site is exactly as it was without this plugin</span>'; ?></td>
			</tr>
		</table>

		<?php if ( ! $sandbox ) : ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'qil_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Storefront experience</th>
						<td>
							<label>
								<input type="checkbox" name="qil_enabled" value="1" <?php checked( '1', (string) get_option( 'qil_enabled', '0' ) ); ?>>
								Replace the homepage, header and product pages with the Qimia experience
							</label>
							<p class="description">Turning this off returns every page to the theme immediately. It does not deactivate the plugin and it changes no content, products or orders.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		<?php endif; ?>

		<?php qil_hero_studio_settings_form(); ?>
        <?php qil_continuity_settings_form(); ?>
		<h2>Emergency stop</h2>
		<p>If this screen is unreachable, add this line to <code>wp-config.php</code> above the “stop editing” comment:</p>
		<p><code>define( 'QIL_DISABLE', true );</code></p>
		<p class="description">It overrides everything, on every host, without touching the database or the plugin list.</p>
	</div>
	<?php
}

/**
 * Whether this request should receive the Qimia Lab homepage.
 */
function qil_should_render() {
	if ( is_admin() || wp_doing_ajax() || ! qil_experience_enabled() ) {
		return false;
	}

	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$request_path = untrailingslashit( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
	$arabic_path  = untrailingslashit( (string) wp_parse_url( home_url( '/ar/' ), PHP_URL_PATH ) );

	return is_front_page() || ( '' !== $arabic_path && $request_path === $arabic_path );
}

/**
 * What this request gets from the plugin.
 *
 * 'full'   — the lab homepage: its own document, every section.
 * 'chrome' — any other enabled storefront page: the topbar, header and cart drawer only,
 *            so the shop, product and checkout pages carry the same navigation
 *            as the homepage instead of the theme's.
 * 'none'   — admin, AJAX, REST, an ineligible host, or chrome switched off.
 *
 * Memoised: this is asked on several hooks per request and is_front_page() is
 * not free.
 */
function qil_render_mode() {
	static $mode = null;
	if ( null !== $mode ) {
		return $mode;
	}
	// is_front_page() is meaningless before the main query is parsed, so an
	// early caller gets an answer without it being cached as the answer.
	$settled = did_action( 'template_redirect' ) > 0;

	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! qil_experience_enabled() || qil_is_elementor_context() ) {
		if ( $settled ) {
			$mode = 'none';
		}
		return 'none';
	}
	if ( ! $settled ) {
		return 'none';
	}

	if ( qil_should_render() ) {
		$mode = 'full';
		return $mode;
	}

	// An escape hatch for comparing against the theme's own page while testing.
	// Checked before anything else so it can always get the theme back.
	$requested = isset( $_GET['qil_chrome'] ) ? sanitize_key( wp_unslash( $_GET['qil_chrome'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle.
	if ( '0' === $requested || 'off' === $requested ) {
		$mode = 'none';
		return $mode;
	}

	/**
	 * Filter whether the Qimia chrome replaces the theme header off the homepage.
	 *
	 * @param bool $enabled Default true when the storefront experience is enabled.
	 */
	$mode = apply_filters( 'qil_global_chrome_enabled', true ) ? 'chrome' : 'none';
	return $mode;
}

/**
 * Whether the Elementor editor is driving this request.
 *
 * The storefront's product page is a WoodMart layout built in Elementor, and
 * the shop pages are editable the same way. Nothing this plugin prints may end
 * up inside the editor's canvas or its preview iframe, or the merchant cannot
 * edit the page it is printed on.
 */
function qil_is_elementor_context() {
	if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['preview_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor detection.
		return true;
	}
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( in_array( $action, array( 'elementor', 'elementor_ajax', 'elementor_inline_editor' ), true ) ) {
		return true;
	}
	if ( class_exists( '\Elementor\Plugin' ) ) {
		$elementor = \Elementor\Plugin::$instance;
		if ( isset( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
			return true;
		}
		if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether this request is a single product, so the script gets the product and
 * its rail in the payload.
 *
 * The layout itself is NOT printed by PHP. WoodMart renders this store's
 * product page as an Elementor document (a wd-builder-on layout containing
 * .elementor-475), so no WooCommerce summary hook fires and nothing this plugin
 * could hook would land in the right place — v0.27 proved that the hard way by
 * returning two complete documents. The layout is an Elementor widget instead,
 * which is also how the merchant edits every other part of this theme.
 */
function qil_is_product_view() {
	static $is_product = null;
	if ( null !== $is_product ) {
		return $is_product;
	}
	if ( did_action( 'template_redirect' ) < 1 ) {
		return false;
	}
	$is_product = 'none' !== qil_render_mode() && function_exists( 'is_product' ) && is_product();
	return $is_product;
}

/**
 * The product this request is showing, as a catalogue record, read from the
 * queried object so it never depends on render order.
 */
function qil_current_product_payload() {
	static $payload = null;
	if ( null !== $payload ) {
		return $payload;
	}
	$payload    = array( 'product' => null, 'related' => array() );
	$product_id = (int) get_queried_object_id();
	if ( $product_id && function_exists( 'wc_get_product' ) ) {
		$payload = qil_product_page_data( $product_id );
	}
	return $payload;
}

/**
 * Render the shared navigation on every enabled page that is not the lab
 * homepage, so the header, locale menu, search and cart are identical
 * everywhere. The homepage template prints its own; nothing is printed twice.
 */
function qil_render_global_chrome() {
	if ( 'chrome' !== qil_render_mode() ) {
		return;
	}
	if ( ! function_exists( 'qil_section_header' ) ) {
		return;
	}

	$context = qil_view_context();
	printf(
		'<div id="qimia-lab" class="qil-shell qil-chrome-shell qaatm-no-translate notranslate%1$s" dir="%2$s" lang="%3$s" translate="no" data-qil-locale="%3$s" data-qil-chrome data-qaatm-no-rewrite data-no-translation>',
		$context['isArabic'] ? ' is-arabic' : '',
		esc_attr( $context['direction'] ),
		esc_attr( $context['locale'] )
	);
	echo qil_sprite(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted section markup.
	echo qil_section_topbar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo qil_section_header(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';
	// The chrome is fixed, so the theme's own page needs the height back. The
	// script keeps this in step with the measured chrome; the inline value is
	// only what the first paint uses.
	echo '<div class="qil-chrome-spacer" aria-hidden="true"></div>';
}
add_action( 'wp_body_open', 'qil_render_global_chrome', 5 );

/**
 * Mark the document so the shared stylesheet can style the theme's own page
 * around the Qimia chrome, and stand the theme header down.
 */
function qil_chrome_body_class( $classes ) {
	$render_mode = qil_render_mode();

	if ( 'chrome' === $render_mode ) {
		$classes[] = 'qil-body';
		$classes[] = 'qil-chrome-active';
	}
	if ( 'chrome' === $render_mode ) {
		$classes[] = 'qil-storefront';
		if ( function_exists( 'is_product' ) && is_product() ) { $classes[] = 'qil-product-experience'; }
	}
	if ( 'none' !== $render_mode && qil_language_context()['isArabic'] ) {
		$classes[] = 'qil-arabic';
	}

	return array_values( array_unique( $classes ) );
}
add_filter( 'body_class', 'qil_chrome_body_class' );

/**
 * Convert catalogue text to one decoded, single-line, JSON-safe value.
 */
function qil_clean_text( $value ) {
	if ( is_array( $value ) ) {
		$parts = array();
		foreach ( $value as $part ) {
			$part = qil_clean_text( $part );
			if ( '' !== $part ) {
				$parts[] = $part;
			}
		}
		return implode( ', ', $parts );
	}

	if ( is_object( $value ) || is_resource( $value ) ) {
		return '';
	}

	$charset = get_bloginfo( 'charset' );
	$charset = $charset ? $charset : 'UTF-8';
	$text    = preg_replace( '/<[^>]*>/', ' ', (string) $value );
	$text    = wp_strip_all_tags( (string) $text, true );
	$text    = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, $charset );
	$text    = preg_replace( '/\s+/u', ' ', $text );

	return trim( (string) $text );
}

/**
 * Locate the first useful product attribute or metadata value.
 */
function qil_product_fact( $product, array $attribute_names, array $meta_names = array() ) {
	foreach ( $attribute_names as $attribute_name ) {
		$value = qil_clean_text( $product->get_attribute( $attribute_name ) );
		if ( '' !== $value ) {
			return $value;
		}
	}

	foreach ( $meta_names as $meta_name ) {
		$value = qil_clean_text( $product->get_meta( $meta_name, true ) );
		if ( '' !== $value ) {
			return $value;
		}
	}

	return '';
}

/**
 * Preserve table rows and sentences as bounded fact-extraction units.
 */
function qil_fact_segments( $markup ) {
	$table_rows = array();
	if ( preg_match_all( '/<tr\b[^>]*>(.*?)<\/tr>/isu', (string) $markup, $row_matches ) ) {
		foreach ( $row_matches[1] as $row_markup ) {
			if ( ! preg_match_all( '/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/isu', (string) $row_markup, $cell_matches ) ) {
				continue;
			}

			$cells = array_values( array_filter( array_map( 'qil_clean_text', $cell_matches[1] ), static function ( $cell ) { return '' !== $cell; } ) );
			if ( $cells ) {
				$table_rows[] = implode( ' · ', $cells );
			}
		}

		// Rows have already been normalized above. Removing them here prevents
		// line breaks between individual table cells from fragmenting one fact.
		$markup = preg_replace( '/<tr\b[^>]*>.*?<\/tr>/isu', "\n", (string) $markup );
	}

	$markup = preg_replace( '/<\s*br\s*\/?\s*>/i', "\n", (string) $markup );
	$markup = preg_replace( '/<\/\s*(?:td|th)\s*>/i', ' · ', (string) $markup );
	$markup = preg_replace( '/<\/\s*(?:tr|p|li|h[1-6]|div)\s*>/i', "\n", (string) $markup );
	$markup = preg_replace( '/<[^>]*>/', ' ', (string) $markup );
	$markup = html_entity_decode( wp_strip_all_tags( (string) $markup, true ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8' );
	$rows   = preg_split( '/\R+/u', (string) $markup );
	$result = array();

	foreach ( $rows as $row ) {
		$sentences = preg_split( '/(?<=[.!?;])\s+/u', (string) $row );
		foreach ( $sentences as $sentence ) {
			$sentence = qil_clean_text( $sentence );
			if ( '' !== $sentence ) {
				$result[] = $sentence;
			}
		}
	}

	return array_values( array_unique( array_merge( $table_rows, $result ) ) );
}

/**
 * Return labelled product attributes without exposing internal metadata.
 */
function qil_product_attributes( $product ) {
	$attributes = array();

	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! is_a( $attribute, 'WC_Product_Attribute' ) ) {
			continue;
		}

		$name   = $attribute->get_name();
		$label  = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name, $product ) : $name;
		$values = array();

		if ( $attribute->is_taxonomy() ) {
			$terms  = wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'names' ) );
			$values = is_wp_error( $terms ) ? array() : $terms;
		} else {
			$values = $attribute->get_options();
		}

		$clean_values = array_values( array_filter( array_map( 'qil_clean_text', $values ) ) );
		if ( $clean_values ) {
			$attributes[] = array(
				'name'   => sanitize_key( str_replace( 'pa_', '', $name ) ),
				'label'  => qil_clean_text( $label ),
				'values' => array_slice( $clean_values, 0, 24 ),
				'value'  => implode( ', ', array_slice( $clean_values, 0, 24 ) ),
			);
		}
	}

	return $attributes;
}

/**
 * Extract an evidence-backed serving count label.
 */
function qil_extract_servings( $product, array $segments ) {
	$count = qil_filter_product_servings( $product );
	return $count > 0 ? $count . ( 1 === $count ? ' serving' : ' servings' ) : '';
}

/**
 * Extract a serving size only when it is explicitly labelled.
 */
function qil_extract_serving_size( $product, array $segments ) {
    $facts = get_post_meta( $product->get_id(), '_qimia_supplement_facts_structured', true );
    if ( is_string( $facts ) ) $facts = json_decode( $facts, true );
    if ( is_array( $facts ) && is_scalar( $facts['serving_size_en'] ?? null ) && '' !== trim( (string) $facts['serving_size_en'] ) ) {
        return qil_clean_text( $facts['serving_size_en'] );
    }
	$value = qil_product_fact(
		$product,
		array( 'pa_serving-size', 'serving-size', 'pa_serving_size', 'serving_size' ),
		array( 'serving_size', '_serving_size', 'qimia_serving_size', '_qimia_serving_size' )
	);

	if ( '' !== $value ) {
		return $value;
	}

	foreach ( $segments as $segment ) {
		$cells = array_values( array_map( 'trim', explode( '·', (string) $segment ) ) );
		if ( 2 <= count( $cells ) && preg_match( '/\bserving\s*size\b/i', $cells[0] ) ) {
			if ( preg_match( '/^([0-9]+(?:[.,][0-9]+)?\s*(?:scoops?|capsules?|tablets?|softgels?|g|grams?|ml|oz)(?:\s*\([^)]{1,32}\))?)/i', $cells[1], $matches ) ) {
				return qil_clean_text( $matches[1] );
			}
		}

		if ( preg_match( '/\bserving\s*size\s*[:·\-–—]?\s*([0-9]+(?:[.,][0-9]+)?\s*(?:scoops?|capsules?|tablets?|softgels?|g|grams?|ml|oz)(?:\s*\([^)]{1,32}\))?)/i', $segment, $matches ) ) {
			return qil_clean_text( $matches[1] );
		}
	}

	return '';
}

/**
 * Convert a labelled caffeine amount to milligrams and reject implausible data.
 */
function qil_caffeine_to_mg( $number, $unit ) {
	$number = trim( (string) $number );
	if ( preg_match( '/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/', $number ) ) {
		$number = str_replace( ',', '', $number );
	} elseif ( false !== strpos( $number, ',' ) && false === strpos( $number, '.' ) ) {
		$number = str_replace( ',', '.', $number );
	}

	$value = (float) $number;
	$unit  = strtolower( (string) $unit );
	if ( 'g' === $unit ) {
		$value *= 1000;
	} elseif ( 'mcg' === $unit || 'µg' === $unit ) {
		$value /= 1000;
	}

	return $value >= 0 && $value <= 2000 ? $value : null;
}

/**
 * Match each caffeine occurrence to its closest value, preferring a value in
 * the adjacent table cell. One compact HTML table can otherwise collapse into
 * a single segment and hide multiple distinct caffeine sources.
 */
function qil_caffeine_candidates( $segment ) {
	$caffeine_matches = array();
	$amount_matches   = array();
	preg_match_all( '/\bcaffeine(?:\s+(?:anhydrous|citrate))?\b/i', $segment, $caffeine_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
	preg_match_all( '/\b(\d+(?:[.,]\d+)?)\s*(mcg|µg|mg|g)\b/i', $segment, $amount_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

	$candidates = array();
	foreach ( $caffeine_matches as $caffeine_match ) {
		$caffeine_start = (int) $caffeine_match[0][1];
		$caffeine_end   = $caffeine_start + strlen( $caffeine_match[0][0] );
		$best_table     = null;
		$best_nearby    = null;
		foreach ( $amount_matches as $amount_match ) {
			$amount_start = (int) $amount_match[0][1];
			$amount_end   = $amount_start + strlen( $amount_match[0][0] );
			$is_after      = $amount_start >= $caffeine_end;
			$distance      = $amount_end <= $caffeine_start
				? $caffeine_start - $amount_end
				: ( $is_after ? $amount_start - $caffeine_end : 0 );

			if ( $distance > 80 ) {
				continue;
			}
			$milligrams = qil_caffeine_to_mg( $amount_match[1][0], $amount_match[2][0] );
			if ( null === $milligrams ) {
				continue;
			}
			$between_start = $is_after ? $caffeine_end : $amount_end;
			$between_end   = $is_after ? $amount_start : $caffeine_start;
			$between       = substr( $segment, $between_start, max( 0, $between_end - $between_start ) );
			$candidate     = array(
				'mg'       => $milligrams,
				'distance' => $distance,
				'table'    => false !== strpos( $between, '·' ),
			);

			if ( $candidate['table'] && ( null === $best_table || $distance < $best_table['distance'] ) ) {
				$best_table = $candidate;
			}
			if ( null === $best_nearby || $distance < $best_nearby['distance'] ) {
				$best_nearby = $candidate;
			}
		}

		$best = $best_table ? $best_table : $best_nearby;
		if ( ! $best ) {
			continue;
		}
		$component = 'generic';
		if ( false !== stripos( $caffeine_match[0][0], 'anhydrous' ) ) {
			$component = 'anhydrous';
		} else {
			$before_caffeine = substr( $segment, max( 0, $caffeine_start - 42 ), min( 42, $caffeine_start ) );
			if ( preg_match( '/\b(?:natural|innovatea|tea|coffee|guarana)\b/i', $before_caffeine ) ) {
				$component = 'natural';
			}
		}
		$best['component'] = $component;
		$key = $component . ':' . number_format( $best['mg'], 3, '.', '' );
		$candidates[ $key ] = $best;
	}

	return array_values( $candidates );
}

/**
 * Return the strongest single candidate for prose fallback.
 */
function qil_nearest_caffeine_amount( $segment ) {
	$candidates = qil_caffeine_candidates( $segment );
	if ( ! $candidates ) {
		return null;
	}
	usort(
		$candidates,
		static function ( $left, $right ) {
			if ( $left['table'] !== $right['table'] ) {
				return $left['table'] ? -1 : 1;
			}
			return $left['distance'] - $right['distance'];
		}
	);
	return $candidates[0];
}

/**
 * Format a normalized caffeine value without fake precision.
 */
function qil_caffeine_label( $milligrams ) {
	$rounded = round( (float) $milligrams, 2 );
	$number  = abs( $rounded - round( $rounded ) ) < 0.001
		? number_format( $rounded, 0, '.', '' )
		: rtrim( rtrim( number_format( $rounded, 2, '.', '' ), '0' ), '.' );
	return $number . ' mg';
}

/**
 * Caffeine is tri-state: verified amount, explicit none, or unknown.
 * Table components are deduplicated and distinct sources are summed.
 */
function qil_extract_caffeine( $product, array $segments ) {
	$explicit = qil_product_fact(
		$product,
		array( 'pa_caffeine', 'caffeine', 'pa_caffeine-content', 'caffeine-content' ),
		array( 'caffeine', '_caffeine', 'caffeine_content', '_caffeine_content', 'qimia_caffeine', '_qimia_caffeine' )
	);

	if ( '' !== $explicit ) {
		if ( preg_match( '/^(?:unknown|not\s+(?:listed|available|specified)|n\/?a|[-–—])$/i', $explicit ) ) {
			return array( 'state' => 'unknown', 'label' => '', 'parts' => array() );
		}
		if ( preg_match( '/(?:\bcaffeine[\s-]*free\b|\bno\s+caffeine\b|\bwithout\s+caffeine\b|\bzero\s+caffeine\b|^0(?:[.,]0+)?\s*(?:mcg|mg|g)?$)/i', $explicit ) ) {
			return array( 'state' => 'none', 'label' => '0 mg', 'parts' => array() );
		}
		if ( preg_match( '/(\d+(?:[.,]\d+)?)\s*(mcg|µg|mg|g)\b/i', $explicit, $matches ) ) {
			$milligrams = qil_caffeine_to_mg( $matches[1], $matches[2] );
			if ( null !== $milligrams ) {
				return array(
					'state' => 0.0 === $milligrams ? 'none' : 'known',
					'label' => qil_caffeine_label( $milligrams ),
					'parts' => array( array( 'source' => 'product_field', 'mg' => $milligrams ) ),
				);
			}
		}
		return array( 'state' => 'unknown', 'label' => '', 'parts' => array() );
	}

	$table_parts = array();
	$fallback    = null;
	foreach ( $segments as $segment ) {
		if ( false === stripos( $segment, 'caffeine' ) ) {
			continue;
		}
		if ( preg_match( '/(?:\bcaffeine[\s-]*free\b|\bno\s+caffeine\b|\bwithout\s+caffeine\b|\bzero\s+caffeine\b)/i', $segment ) ) {
			return array( 'state' => 'none', 'label' => '0 mg', 'parts' => array() );
		}

		// Prefer a direct ingredient-cell → amount-cell relationship. This is
		// stronger evidence than proximity and remains correct even if several
		// table rows were compressed into one text segment.
		$direct_rows = array();
		preg_match_all(
			'/(?:^|·)\s*([^·]{0,180}\bcaffeine\b[^·]{0,180})\s*·\s*(\d+(?:[.,]\d+)?)\s*(mcg|µg|mg|g)\b/iu',
			$segment,
			$direct_rows,
			PREG_SET_ORDER
		);
		foreach ( $direct_rows as $direct_row ) {
			$milligrams = qil_caffeine_to_mg( $direct_row[2], $direct_row[3] );
			if ( null === $milligrams ) {
				continue;
			}
			$component = 'generic';
			if ( preg_match( '/\b(?:natural|innovatea|tea|coffee|guarana)\b/i', $direct_row[1] ) ) {
				$component = 'natural';
			} elseif ( preg_match( '/\banhydrous\b/i', $direct_row[1] ) ) {
				$component = 'anhydrous';
			}
			$table_parts[ $component ] = $milligrams;
		}

		$candidates = qil_caffeine_candidates( $segment );
		if ( ! $candidates ) {
			continue;
		}
		foreach ( $candidates as $candidate ) {
			if ( null === $fallback || $candidate['distance'] < $fallback['distance'] ) {
				$fallback = $candidate;
			}
			if ( ! $candidate['table'] ) {
				continue;
			}
			$component = $candidate['component'];
			if ( ! isset( $table_parts[ $component ] ) ) {
				$table_parts[ $component ] = $candidate['mg'];
			}
		}
	}

	if ( $table_parts ) {
		// A generic total is often duplicated beside a more specific source row.
		$specific = array_diff_key( $table_parts, array( 'generic' => true ) );
		if ( $specific ) {
			$table_parts = $specific;
		}
		$total = array_sum( $table_parts );
		return array(
			'state' => 0.0 === $total ? 'none' : 'known',
			'label' => qil_caffeine_label( $total ),
			'parts' => array_map(
				static function ( $source, $mg ) {
					return array( 'source' => $source, 'mg' => $mg );
				},
				array_keys( $table_parts ),
				array_values( $table_parts )
			),
		);
	}

	if ( $fallback ) {
		return array(
			'state' => 0.0 === $fallback['mg'] ? 'none' : 'known',
			'label' => qil_caffeine_label( $fallback['mg'] ),
			'parts' => array( array( 'source' => 'label_text', 'mg' => $fallback['mg'] ) ),
		);
	}

	return array( 'state' => 'unknown', 'label' => '', 'parts' => array() );
}

/**
 * Map catalogue language to broad, non-medical shopping goals.
 */
function qil_product_goals( $corpus ) {
	$corpus = strtolower( remove_accents( $corpus ) );
	$rules  = array(
		'muscle'   => array( 'protein', 'whey', 'isolate', 'creatine', 'mass gainer', 'amino', 'bcaa', 'muscle' ),
		'energy'   => array( 'pre workout', 'pre-workout', 'caffeine', 'energy', 'focus', 'nootropic', 'pump' ),
		'recovery' => array( 'recovery', 'electrolyte', 'hydration', 'glutamine', 'bcaa', 'magnesium', 'collagen', 'casein' ),
		'sleep'    => array( 'sleep', 'melatonin', 'calm', 'stress', 'ashwagandha', 'gaba', 'night' ),
		'wellness' => array( 'vitamin', 'multivitamin', 'mineral', 'omega', 'greens', 'probiotic', 'wellness', 'health', 'zinc', 'd3' ),
		'beauty'   => array( 'collagen', 'biotin', 'hair', 'skin', 'nail', 'hyaluronic', 'beauty' ),
	);

	$matches = array();
	foreach ( $rules as $goal => $keywords ) {
		foreach ( $keywords as $keyword ) {
			if ( false !== strpos( $corpus, $keyword ) ) {
				$matches[] = $goal;
				break;
			}
		}
	}

	return $matches ? array_values( array_unique( $matches ) ) : array( 'wellness' );
}

/**
 * Describe the practical shopping purpose of a product without making a
 * diagnosis or inventing a benefit from a missing label. The browser localises
 * these stable keys; live ingredient/serving evidence stays separate.
 */
function qil_product_purpose_key( $corpus ) {
	$corpus = strtolower( remove_accents( (string) $corpus ) );
	$rules  = array(
		'mass_gainer'    => '/\b(?:(?:mass|weight)[\s-]+gainers?|gainers?|serious[\s-]+mass)\b/',
		'pre_workout'    => '/\b(?:pre[\s-]*workouts?|pump[\s-]+(?:formulas?|products?|nitric[\s-]+oxide)|nitric[\s-]+oxide)\b/',
		'fat_burner'     => '/\b(?:fat[\s-]*burners?|thermogenics?|weight[\s-]*loss|lipo[\s-]*6)\b/',
		'protein'        => '/\b(?:proteins?|wheys?|caseins?|isolates?|protein[\s-]+(?:powders?|isolates?|concentrates?|blends?)|isolate[\s-]+proteins?)\b/',
		'creatine'       => '/\bcreatines?(?:[\s-]+monohydrates?)?\b/',
		'amino_recovery' => '/\b(?:bcaas?|eaas?|amino[\s-]+acids?|glutamines?|recovery|intra[\s-]*workouts?|post[\s-]*workouts?)\b/',
		'hydration'      => '/\b(?:electrolytes?|hydrations?|isotonics?)\b/',
		'joint_support'  => '/\b(?:joint[\s-]+supports?|glucosamines?|chondroitins?|msm)\b/',
		'sleep_support'  => '/\b(?:melatonins?|sleep[\s-]+supports?|night[\s-]+formulas?|gaba)\b/',
		'beauty_support' => '/\b(?:collagens?|biotins?|hair[\s,\/&-]+skin|skin[\s,\/&-]+nails?)\b/',
		'omega_support'  => '/\b(?:omega[\s-]*3s?|fish[\s-]+oils?|epa|dha)\b/',
		'daily_wellness' => '/\b(?:multi[\s-]*vitamins?|vitamins?|minerals?|magnesium|zinc|probiotics?|greens|ashwagandha)\b/',
	);

	foreach ( $rules as $key => $pattern ) {
		if ( preg_match( $pattern, $corpus ) ) {
			return $key;
		}
	}

	return 'goal_support';
}

/**
 * Extract up to three recognised active ingredients from their own fact row.
 */
function qil_extract_primary_actives( array $segments, array $goals, $product_name ) {
	$definitions = array(
		'protein'      => array( 'label' => 'Protein', 'pattern' => '/\b(?:whey\s+protein(?:\s+(?:isolate|concentrate|hydrolysate))?|protein\s+isolate|protein)\b/i' ),
		'creatine'     => array( 'label' => 'Creatine', 'pattern' => '/\bcreatine(?:\s+monohydrate)?\b/i' ),
		'citrulline'   => array( 'label' => 'L-Citrulline', 'pattern' => '/\b(?:l[\s-]?)?citrulline(?:\s+malate)?\b/i' ),
		'beta_alanine' => array( 'label' => 'Beta-Alanine', 'pattern' => '/\bbeta[\s-]alanine\b/i' ),
		'bcaa'         => array( 'label' => 'BCAA', 'pattern' => '/\bBCAA(?:s)?\b/i' ),
		'eaa'          => array( 'label' => 'EAA', 'pattern' => '/\bEAA(?:s)?\b/i' ),
		'glutamine'    => array( 'label' => 'Glutamine', 'pattern' => '/\b(?:l[\s-]?)?glutamine\b/i' ),
		'collagen'     => array( 'label' => 'Collagen', 'pattern' => '/\bcollagen(?:\s+peptides?)?\b/i' ),
		'omega_3'      => array( 'label' => 'Omega-3', 'pattern' => '/\bomega[\s-]?3\b/i' ),
		'epa'          => array( 'label' => 'EPA', 'pattern' => '/\bEPA\b/i' ),
		'dha'          => array( 'label' => 'DHA', 'pattern' => '/\bDHA\b/i' ),
		'vitamin_d3'   => array( 'label' => 'Vitamin D3', 'pattern' => '/\bvitamin\s+d3\b/i' ),
		'vitamin_c'    => array( 'label' => 'Vitamin C', 'pattern' => '/\bvitamin\s+c\b/i' ),
		'magnesium'    => array( 'label' => 'Magnesium', 'pattern' => '/\bmagnesium\b/i' ),
		'zinc'         => array( 'label' => 'Zinc', 'pattern' => '/\bzinc\b/i' ),
		'biotin'       => array( 'label' => 'Biotin', 'pattern' => '/\bbiotin\b/i' ),
		'melatonin'    => array( 'label' => 'Melatonin', 'pattern' => '/\bmelatonin\b/i' ),
		'ashwagandha'  => array( 'label' => 'Ashwagandha', 'pattern' => '/\bashwagandha\b/i' ),
		'probiotics'   => array( 'label' => 'Probiotics', 'pattern' => '/\bprobiotic(?:s)?\b/i' ),
		'caffeine'     => array( 'label' => 'Caffeine', 'pattern' => '/\bcaffeine(?:\s+anhydrous)?\b/i' ),
	);
	$priorities  = array(
		'muscle'   => array( 'protein', 'creatine', 'bcaa', 'eaa', 'glutamine' ),
		'energy'   => array( 'caffeine', 'citrulline', 'beta_alanine', 'creatine' ),
		'recovery' => array( 'glutamine', 'bcaa', 'eaa', 'magnesium', 'collagen' ),
		'sleep'    => array( 'melatonin', 'magnesium', 'ashwagandha' ),
		'wellness' => array( 'vitamin_d3', 'vitamin_c', 'omega_3', 'epa', 'dha', 'magnesium', 'zinc', 'probiotics' ),
		'beauty'   => array( 'collagen', 'biotin', 'vitamin_c' ),
	);
	$order       = array();

	foreach ( $goals as $goal ) {
		if ( isset( $priorities[ $goal ] ) ) {
			$order = array_merge( $order, $priorities[ $goal ] );
		}
	}
	$order = array_values( array_unique( array_merge( $order, array_keys( $definitions ) ) ) );

	$facts          = array();
	$explicit_rows  = array_values(
		array_filter(
			$segments,
			static function ( $segment ) {
				return (bool) preg_match( '/\b(?:per\s+serving|each\s+(?:prepared\s+)?serving|serving\s+provides?|amount\s+per\s+serving|supplement\s+facts?)\b/i', $segment );
			}
		)
	);
	// Prefer rows that explicitly tie an amount to one serving. Product titles
	// often contain a tub weight (for example "Creatine 250G"), which must not
	// outrank the real "5g per serving" statement later in the label.
	$ordered_segments = array_merge( $explicit_rows, array_values( array_diff( $segments, $explicit_rows ) ) );
	$amount_body    = '\d+(?:[.,]\d+)?\s*(?:mcg|µg|mg|g|grams?|kg|ml|iu|cfu|billion\s+cfu|million\s+cfu)';
	$after_pattern  = '/^\s*(?:(?:[:·\-–—()]|per\s+serving)\s*){0,3}(' . $amount_body . ')\b/i';
	$before_pattern = '/\b(' . $amount_body . ')\s*(?:[:·\-–—()]\s*){0,3}$/i';
	foreach ( $order as $key ) {
		$definition = $definitions[ $key ];
		foreach ( $ordered_segments as $segment ) {
			if ( ! preg_match( $definition['pattern'], $segment, $ingredient, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$offset = (int) $ingredient[0][1];
			$length = strlen( $ingredient[0][0] );
			$after  = substr( $segment, $offset + $length, 48 );
			$before = substr( $segment, max( 0, $offset - 36 ), min( 36, $offset ) );
			$amount = '';
			$ingredient_end = $offset + $length;
			$delimiter      = strpos( $segment, '·', $ingredient_end );
			$is_explicit_row = in_array( $segment, $explicit_rows, true );

			// In a preserved table row, the ingredient may have descriptive text in
			// its own cell (for example "Best Creatine Blend (Proprietary)"). Read
			// only the immediately following cell and never scan beyond its delimiter.
			if ( false !== $delimiter && $delimiter - $ingredient_end <= 96 ) {
				$cell_start = $delimiter + strlen( '·' );
				$cell_end   = strpos( $segment, '·', $cell_start );
				$cell       = false === $cell_end
					? substr( $segment, $cell_start, 48 )
					: substr( $segment, $cell_start, min( 48, $cell_end - $cell_start ) );
				if ( preg_match( '/^\s*(?:[:\-–—()]\s*){0,2}(' . $amount_body . ')\b/i', $cell, $matches ) ) {
					$amount = qil_clean_text( $matches[1] );
				}
			}

			if ( '' === $amount && false === $delimiter && $is_explicit_row && ( preg_match( $after_pattern, $after, $matches ) || preg_match( $before_pattern, $before, $matches ) ) ) {
				$amount = qil_clean_text( $matches[1] );
			}

			// Description prose without an adjacent amount is not a supplement fact.
			if ( '' === $amount ) {
				continue;
			}

			$facts[] = array(
				'key'    => $key,
				'name'   => $definition['label'],
				'amount' => $amount,
				'label'  => $definition['label'] . ' · ' . $amount,
				'formatted' => $definition['label'] . ' · ' . $amount,
			);
			break;
		}

		if ( 3 <= count( $facts ) ) {
			break;
		}
	}

	// A product title is a safe fallback for identity, but never for dosage.
	if ( ! $facts ) {
		foreach ( $order as $key ) {
			$definition = $definitions[ $key ];
			if ( preg_match( $definition['pattern'], $product_name ) ) {
				$facts[] = array(
					'key'    => $key,
					'name'   => $definition['label'],
					'amount' => '',
					'label'  => $definition['label'],
					'formatted' => $definition['label'],
				);
				break;
			}
		}
	}

	return $facts;
}

/** Keep usable intermediate images without rewriting attachment metadata. */
function qil_valid_image_metadata( $metadata ) {
	if ( ! is_array( $metadata ) || ! array_key_exists( 'sizes', $metadata ) ) {
		return $metadata;
	}
	$metadata['sizes'] = is_array( $metadata['sizes'] ) ? array_filter(
		$metadata['sizes'],
		static function ( $size ) {
			return is_array( $size ) && isset( $size['file'], $size['width'], $size['height'] )
				&& is_string( $size['file'] ) && '' !== trim( $size['file'] )
				&& is_numeric( $size['width'] ) && (float) $size['width'] > 0
				&& is_numeric( $size['height'] ) && (float) $size['height'] > 0;
		}
	) : array();
	return $metadata;
}

/**
 * Build responsive attachment data for a primary or gallery image.
 */
function qil_image_data( $attachment_id, $fallback_alt = '', $include_srcset = true ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id < 1 ) {
		return array();
	}

	// Only filter metadata during this Lab image read. WooCommerce can ask core
	// for an array-sized fallback, which visits every stored intermediate entry.
	// Failed thumbnail records (false/null) must never reach that core loop.
	$metadata_filter = static function ( $metadata, $id ) use ( $attachment_id ) {
		return (int) $id === $attachment_id ? qil_valid_image_metadata( $metadata ) : $metadata;
	};
	add_filter( 'wp_get_attachment_metadata', $metadata_filter, PHP_INT_MAX, 2 );
	try {
		$alt    = qil_clean_text( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		$srcset = '';
		$source = null;
		$candidates = array();

		// One primary attachment only: expose a compact responsive set and choose the
		// largest generated source up to 1600px. This normally gives product cards a
		// 1024px source without shipping a gallery or forcing a multi-megabyte original.
		foreach ( array( 'medium', 'woocommerce_thumbnail', 'woocommerce_single', 'medium_large', 'large', '1536x1536', 'full' ) as $image_size ) {
			$candidate = wp_get_attachment_image_src( $attachment_id, $image_size );
			if ( ! $candidate || empty( $candidate[0] ) || empty( $candidate[1] ) ) {
				continue;
			}
			$width = (int) $candidate[1];
			if ( $width < 240 || $width > 1600 ) {
				continue;
			}
			$candidates[ $width ] = $candidate;
		}

		if ( $candidates ) {
			ksort( $candidates, SORT_NUMERIC );
			$source = end( $candidates );
		}
		if ( ! $source ) {
			$source = wp_get_attachment_image_src( $attachment_id, 'large' );
		}
		if ( ! $source ) {
			$source = wp_get_attachment_image_src( $attachment_id, 'full' );
		}
		if ( ! $source ) {
			return array();
		}

		if ( $include_srcset && $candidates ) {
			$parts = array();
			foreach ( $candidates as $width => $candidate ) {
				$parts[] = esc_url_raw( $candidate[0] ) . ' ' . (int) $width . 'w';
			}
			$srcset = implode( ', ', $parts );
		}

		return array(
			'id'     => $attachment_id,
			'src'    => esc_url_raw( $source[0] ),
			'srcset' => $srcset,
			'sizes'  => '(max-width: 680px) 46vw, (max-width: 1180px) 46vw, 320px',
			'width'  => (int) $source[1],
			'height' => (int) $source[2],
			'alt'    => $alt ? $alt : qil_clean_text( $fallback_alt ),
		);
	} finally {
		remove_filter( 'wp_get_attachment_metadata', $metadata_filter, PHP_INT_MAX );
	}
}

/**
 * Return only option combinations backed by a real, visible, purchasable and
 * currently in-stock WooCommerce variation. The compact matrix lets the QIL
 * picker remove impossible values before Woo's get_variation endpoint performs
 * the final authoritative match.
 */
function qil_product_options( $product, $locale_override = '' ) {
	$empty = array( 'fallback' => true, 'unavailable' => false, 'attributes' => array(), 'defaults' => array(), 'combinations' => array() );
	if ( ! $product->is_type( 'variable' ) || ! method_exists( $product, 'get_variation_attributes' ) ) {
		return $empty;
	}

	$variation_attributes = (array) $product->get_variation_attributes();
	if ( empty( $variation_attributes ) || count( $variation_attributes ) > 4 ) {
		return $empty;
	}

	$definitions    = array();
	$default_values = method_exists( $product, 'get_default_attributes' ) ? (array) $product->get_default_attributes() : array();
	$total_values   = 0;
	$language       = qil_language_context( $locale_override );
	foreach ( $variation_attributes as $attribute_name => $values ) {
		$values   = array_values( array_unique( (array) $values ) );
		$taxonomy = str_replace( 'attribute_', '', (string) $attribute_name );
		$key      = function_exists( 'wc_variation_attribute_name' )
			? wc_variation_attribute_name( $taxonomy )
			: 'attribute_' . sanitize_title( $taxonomy );
		$key      = sanitize_key( $key );
		$label    = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $taxonomy, $product ) : $taxonomy;
		if ( $language['isArabic'] ) {
			$label_key = sanitize_title( str_replace( array( 'pa_', 'attribute_' ), '', $taxonomy ) );
			$arabic_labels = array(
				'flavour' => 'النكهة', 'flavor' => 'النكهة', 'taste' => 'النكهة',
				'size' => 'الحجم', 'weight' => 'الوزن', 'servings' => 'عدد الحصص',
				'color' => 'اللون', 'colour' => 'اللون', 'pack' => 'العبوة',
			);
			if ( isset( $arabic_labels[ $label_key ] ) ) {
				$label = $arabic_labels[ $label_key ];
			}
		}
		$value_rows = array();

		foreach ( $values as $value ) {
			$raw_value = qil_clean_text( $value );
			if ( '' === $raw_value ) {
				continue;
			}
			$stored_value = taxonomy_exists( $taxonomy ) ? sanitize_title( $raw_value ) : $raw_value;
			$display_name = $raw_value;
			$term         = null;
			if ( taxonomy_exists( $taxonomy ) ) {
				$term = get_term_by( 'slug', $stored_value, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$display_name = $term->name;
				}
			}
			$display_name = apply_filters( 'woocommerce_variation_option_name', $display_name, $term, $taxonomy, $product );
			$value_rows[] = array( 'value' => $stored_value, 'label' => qil_clean_text( $display_name ) );
		}

		$value_rows    = array_values( array_filter( $value_rows, static function ( $row ) { return '' !== $row['value'] && '' !== $row['label']; } ) );
		$total_values += count( $value_rows );
		if ( empty( $value_rows ) || count( $value_rows ) > 32 || $total_values > 128 ) {
			return $empty;
		}
		$definitions[ $key ] = array(
			'key'      => $key,
			'taxonomy' => $taxonomy,
			'label'    => qil_clean_text( $label ),
			'options'  => $value_rows,
		);
	}

	$children = method_exists( $product, 'get_children' ) ? array_values( array_filter( array_map( 'absint', (array) $product->get_children() ) ) ) : array();
	if ( empty( $children ) ) {
		$empty['fallback']    = false;
		$empty['unavailable'] = true;
		return $empty;
	}
	if ( count( $children ) > 256 ) {
		return $empty;
	}
	// Warm variation metadata in one request so the first option picker does not
	// trigger a separate uncached post-meta query for every child variation.
	if ( function_exists( 'update_meta_cache' ) ) {
		update_meta_cache( 'post', $children );
	}

	$combinations = array();
	$signatures   = array();
	foreach ( $children as $variation_id ) {
		$variation = wc_get_product( $variation_id );
		if (
			! $variation
			|| ! $variation->is_type( 'variation' )
			|| 'publish' !== $variation->get_status()
			|| ! $variation->exists()
			|| ( method_exists( $variation, 'variation_is_visible' ) && ! $variation->variation_is_visible() )
			|| ( method_exists( $variation, 'variation_is_active' ) && ! $variation->variation_is_active() )
			|| ! $variation->is_purchasable()
			|| 'instock' !== $variation->get_stock_status()
		) {
			continue;
		}

		$variation_values = method_exists( $variation, 'get_variation_attributes' ) ? (array) $variation->get_variation_attributes( true ) : array();
		$combination      = array();
		$valid            = true;
		foreach ( $definitions as $key => $definition ) {
			$value = isset( $variation_values[ $key ] ) ? qil_clean_text( $variation_values[ $key ] ) : '';
			if ( '' !== $value && taxonomy_exists( $definition['taxonomy'] ) ) {
				$value = sanitize_title( $value );
			}
			$known_values = wp_list_pluck( $definition['options'], 'value' );
			if ( '' !== $value && ! in_array( $value, $known_values, true ) ) {
				$valid = false;
				break;
			}
			$combination[ $key ] = $value;
		}
		if ( ! $valid ) {
			continue;
		}
		$signature = wp_json_encode( $combination );
		if ( isset( $signatures[ $signature ] ) ) {
			continue;
		}
		$signatures[ $signature ] = true;
		$combinations[]           = $combination;
		if ( count( $combinations ) > 128 ) {
			return $empty;
		}
	}

	if ( empty( $combinations ) ) {
		$empty['fallback']    = false;
		$empty['unavailable'] = true;
		return $empty;
	}

	$attributes = array();
	$defaults   = array();
	foreach ( $definitions as $key => $definition ) {
		$allowed = array();
		foreach ( $combinations as $combination ) {
			$value = isset( $combination[ $key ] ) ? $combination[ $key ] : '';
			if ( '' === $value ) {
				$allowed = wp_list_pluck( $definition['options'], 'value' );
				break;
			}
			$allowed[] = $value;
		}
		$allowed = array_values( array_unique( $allowed ) );
		$rows    = array_values( array_filter( $definition['options'], static function ( $row ) use ( $allowed ) { return in_array( $row['value'], $allowed, true ); } ) );
		if ( empty( $rows ) ) {
			return $empty;
		}
		$attributes[] = array( 'key' => $key, 'label' => $definition['label'], 'options' => $rows );
		$taxonomy     = $definition['taxonomy'];
		$default      = isset( $default_values[ $taxonomy ] ) ? qil_clean_text( $default_values[ $taxonomy ] ) : '';
		$default      = taxonomy_exists( $taxonomy ) ? sanitize_title( $default ) : $default;
		if ( '' !== $default && in_array( $default, $allowed, true ) ) {
			$defaults[ $key ] = $default;
		}
	}

	return array(
		'fallback'     => false,
		'unavailable'  => false,
		'attributes'   => $attributes,
		'defaults'     => $defaults,
		'combinations' => $combinations,
	);
}

/**
 * Return native WooCommerce purchase behaviour for the product type.
 */
function qil_product_purchase( $product, $is_arabic = false ) {
	$type        = sanitize_key( $product->get_type() );
	$purchasable = (bool) $product->is_purchasable();
	$in_stock    = (bool) $product->is_in_stock();
	$url         = $product->get_permalink();
	$label       = qil_clean_text( $product->add_to_cart_text() );
	$action      = 'view';
	$ajax        = false;

	if ( 'simple' === $type && $purchasable && $in_stock ) {
		$cart_base = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
		$url    = add_query_arg(
			array(
				'add-to-cart' => (int) $product->get_id(),
				'quantity'    => 1,
			),
			$cart_base
		);
		$action = 'add';
		$ajax   = method_exists( $product, 'supports' ) && $product->supports( 'ajax_add_to_cart' );
	} elseif ( 'variable' === $type && $purchasable && $in_stock ) {
		$action = 'select';
	} elseif ( 'external' === $type && $purchasable && $in_stock ) {
		$url    = $product->add_to_cart_url();
		$action = 'external';
	}

	return array(
		'url'         => 'add' === $action ? esc_url_raw( $url ) : esc_url_raw( qil_localized_url( $url, $is_arabic ) ),
		'text'        => $label,
		'action'      => $action,
		'ajax'        => $ajax,
		'sku'         => qil_clean_text( $product->get_sku() ),
		'purchasable' => $purchasable,
		'description' => qil_clean_text( $product->add_to_cart_description() ),
	);
}

/**
 * Format one storefront price or range through WooCommerce. Keeping the
 * sanitized HTML preserves the installed currency-symbol font/class, while the
 * text form remains available to Qimia AI and non-HTML consumers.
 */
function qil_price_display( $minimum, $maximum, $currency_code ) {
	$minimum = max( 0, (float) $minimum );
	$maximum = max( $minimum, (float) $maximum );
	if ( $minimum <= 0 || ! function_exists( 'wc_price' ) ) {
		return array(
			'value'                => $minimum,
			'min'                  => $minimum,
			'max'                  => $maximum,
			'formatted'            => '',
			'formattedHtml'        => '',
			'minimumFormatted'     => '',
			'minimumFormattedHtml' => '',
		);
	}

	$minimum_html = wp_kses_post( wc_price( $minimum, array( 'currency' => $currency_code ) ) );
	if ( $maximum > $minimum && function_exists( 'wc_format_price_range' ) ) {
		$html = wc_format_price_range( $minimum, $maximum );
	} else {
		$html = $minimum_html;
	}
	$html = wp_kses_post( $html );

	return array(
		'value'                => $minimum,
		'min'                  => $minimum,
		'max'                  => $maximum,
		'formatted'            => qil_clean_text( $html ),
		'formattedHtml'        => $html,
		'minimumFormatted'     => qil_clean_text( $minimum_html ),
		'minimumFormattedHtml' => $minimum_html,
	);
}

/**
 * Return current, regular and sale prices using WooCommerce's display-tax
 * values exactly once. Variable ranges are already display-adjusted by Woo.
 */
function qil_product_price_schema( $product, $currency_code, $serving_count ) {
	$is_variable = $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' );

	if ( $is_variable ) {
		$current_min = (float) $product->get_variation_price( 'min', true );
		$current_max = (float) $product->get_variation_price( 'max', true );
		$regular_min = method_exists( $product, 'get_variation_regular_price' ) ? (float) $product->get_variation_regular_price( 'min', true ) : $current_min;
		$regular_max = method_exists( $product, 'get_variation_regular_price' ) ? (float) $product->get_variation_regular_price( 'max', true ) : $current_max;
	} else {
		$current_min = (float) wc_get_price_to_display( $product );
		$current_max = $current_min;
		$regular_raw = (float) $product->get_regular_price();
		$regular_min = $regular_raw > 0 ? (float) wc_get_price_to_display( $product, array( 'price' => $regular_raw ) ) : $current_min;
		$regular_max = $regular_min;
	}

	$current = qil_price_display( $current_min, $current_max, $currency_code );
	$regular = qil_price_display( $regular_min, $regular_max, $currency_code );
	$on_sale              = (bool) $product->is_on_sale();
	$has_visible_reduction = $on_sale
		&& $current_min > 0
		&& ( $regular_min > $current_min || $regular_max > $current_max );
	$sale                 = $has_visible_reduction ? $current : null;
	$savings = 0;
	if ( $has_visible_reduction && $regular_min > 0 && ( ! $is_variable || ( $current_min === $current_max && $regular_min === $regular_max ) ) ) {
		$savings = max( 0, min( 100, (int) round( ( ( $regular_min - $current_min ) / $regular_min ) * 100 ) ) );
	}

	$per_serving = null;
	if ( $serving_count > 0 && $current_min > 0 ) {
		$per_serving = qil_price_display( $current_min / $serving_count, $current_min / $serving_count, $currency_code );
	}

	return array(
		'value'               => $current['value'],
		'formatted'           => $current['formatted'],
		'formattedHtml'       => $current['formattedHtml'],
		'currency'            => $currency_code,
		'symbol'              => qil_clean_text( get_woocommerce_currency_symbol( $currency_code ) ),
		'current'             => $current,
		'regular'             => $regular,
		'sale'                => $sale,
		'onSale'              => $on_sale,
		'savingsPct'          => $savings,
		'perServing'          => $per_serving ? $per_serving['value'] : 0.0,
		'perServingFormatted' => $per_serving ? $per_serving['formatted'] : '',
		'perServingHtml'      => $per_serving ? $per_serving['formattedHtml'] : '',
	);
}

/**
 * Read the installed Qimia flash-sale plugin's public post classes. A normal
 * Woo sale never becomes a "Flash Sale" unless every plugin signal is present.
 */
function qil_product_promotion( $product, array $category_slugs ) {
	$classes       = get_post_class( '', $product->get_id() );
	$has_category  = in_array( 'flash-sale', $category_slugs, true ) || in_array( 'product_cat-flash-sale', $classes, true );
	$has_expiry    = in_array( 'qimia-discount-expiry', $classes, true );
	$plugin_pct    = 0;

	foreach ( $classes as $class_name ) {
		if ( preg_match( '/^qimia-total-discount-(\d{1,3})$/', (string) $class_name, $matches ) ) {
			$plugin_pct = max( 1, min( 100, (int) $matches[1] ) );
			break;
		}
	}

	$is_flash = (bool) $product->is_on_sale() && $has_category && $has_expiry && $plugin_pct > 0;

	return array(
		'type'         => $is_flash ? 'flash' : ( $product->is_on_sale() ? 'sale' : 'none' ),
		'isFlash'      => $is_flash,
		'discountPct' => $is_flash ? $plugin_pct : 0,
		'source'      => $is_flash ? 'qimia-flash-sale-plugin' : '',
	);
}

/** Convert the Flash Sale plugin's bounded scalar flags to a real boolean. */
function qil_variation_contract_boolean( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}
	if ( is_int( $value ) || is_float( $value ) ) {
		return 1 === (int) $value;
	}
	return in_array( strtolower( qil_clean_text( $value ) ), array( '1', 'yes', 'true', 'on' ), true );
}

/** Normalize the only supported expiry representations to YYYY-MM-DD. */
function qil_variation_contract_expiry( $value ) {
	$raw   = qil_clean_text( $value );
	$year  = 0;
	$month = 0;
	$day   = 0;
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches ) ) {
		$year  = (int) $matches[1];
		$month = (int) $matches[2];
		$day   = (int) $matches[3];
	} elseif ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $matches ) ) {
		$day   = (int) $matches[1];
		$month = (int) $matches[2];
		$year  = (int) $matches[3];
	}
	if ( $year < 2020 || $year > 2100 || ! checkdate( $month, $day, $year ) ) {
		return '';
	}
	return sprintf( '%04d-%02d-%02d', $year, $month, $day );
}

/**
 * Append a small, typed QIL contract to Woo's authoritative variation result.
 * The Flash Sale plugin may add fields before this callback; only explicitly
 * allowlisted scalars are copied, and arbitrary HTML such as price_html is
 * never placed inside the QIL namespace.
 */
function qil_available_variation_contract( $data, $parent_product, $variation ) {
	if ( ! qil_experience_enabled() || ! is_array( $data ) || ! is_object( $variation ) ) {
		return $data;
	}

	$requested_locale = isset( $_REQUEST['qil_locale'] ) ? strtolower( sanitize_key( wp_unslash( $_REQUEST['qil_locale'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Woo variation lookup.
	if ( '' !== $requested_locale && ! in_array( $requested_locale, array( 'en', 'ar' ), true ) ) {
		return $data;
	}

	$current = isset( $data['display_price'] ) && is_numeric( $data['display_price'] ) ? (float) $data['display_price'] : (float) wc_get_price_to_display( $variation );
	$regular = isset( $data['display_regular_price'] ) && is_numeric( $data['display_regular_price'] ) ? (float) $data['display_regular_price'] : $current;
	$is_sale = $current >= 0 && $regular > $current;

	$eligible = isset( $data['qimia_flash_sale_eligible'] ) && qil_variation_contract_boolean( $data['qimia_flash_sale_eligible'] );
	$discount = isset( $data['qimia_flash_sale_total_percent'] ) && is_numeric( $data['qimia_flash_sale_total_percent'] )
		? max( 0, min( 100, (int) round( (float) $data['qimia_flash_sale_total_percent'] ) ) )
		: 0;
	if ( $is_sale && 0 === $discount && $regular > 0 ) {
		$discount = max( 0, min( 100, (int) round( ( ( $regular - $current ) / $regular ) * 100 ) ) );
	}
	$is_flash = $is_sale && $eligible && $discount > 0;

	$expiry_source = isset( $data['qimia_flash_sale_expiry'] ) ? $data['qimia_flash_sale_expiry'] : ( isset( $data['expiry_date'] ) ? $data['expiry_date'] : '' );
	$expiry        = $is_flash ? qil_variation_contract_expiry( $expiry_source ) : '';
	$days_left     = isset( $data['qimia_flash_sale_days_left'] ) && is_numeric( $data['qimia_flash_sale_days_left'] )
		? (int) $data['qimia_flash_sale_days_left']
		: null;
	if ( $is_flash && null === $days_left && '' !== $expiry ) {
		$today_timestamp  = strtotime( current_time( 'Y-m-d' ) . ' UTC' );
		$expiry_timestamp = strtotime( $expiry . ' UTC' );
		if ( false !== $today_timestamp && false !== $expiry_timestamp ) {
			$days_left = (int) floor( ( $expiry_timestamp - $today_timestamp ) / DAY_IN_SECONDS );
		}
	}
	$days_left     = $is_flash && null !== $days_left && $days_left >= 0 && $days_left <= 3650 ? $days_left : null;

	$stock_quantity = method_exists( $variation, 'managing_stock' ) && $variation->managing_stock() && method_exists( $variation, 'get_stock_quantity' )
		? $variation->get_stock_quantity()
		: null;
	$stock_quantity = is_numeric( $stock_quantity ) ? max( 0, (int) $stock_quantity ) : null;
	$max_quantity   = isset( $data['max_qty'] ) && is_numeric( $data['max_qty'] ) ? (int) $data['max_qty'] : null;
	if ( ( null === $max_quantity || $max_quantity < 1 ) && method_exists( $variation, 'get_max_purchase_quantity' ) ) {
		$candidate    = $variation->get_max_purchase_quantity();
		$max_quantity = is_numeric( $candidate ) && (int) $candidate > 0 ? (int) $candidate : null;
	}
	$in_stock   = isset( $data['is_in_stock'] ) ? qil_variation_contract_boolean( $data['is_in_stock'] ) : (bool) $variation->is_in_stock();
	$purchasable = isset( $data['is_purchasable'] ) ? qil_variation_contract_boolean( $data['is_purchasable'] ) : (bool) $variation->is_purchasable();

	$data['qil'] = array(
		'schema'    => 1,
		'locale'    => '' !== $requested_locale ? $requested_locale : qil_language_context()['locale'],
		'promotion' => array(
			'type'        => $is_flash ? 'flash' : ( $is_sale ? 'sale' : 'none' ),
			'discountPct' => $is_sale ? $discount : 0,
			'bestDeal'    => $is_flash && isset( $data['qimia_flash_sale_best_deal'] ) && qil_variation_contract_boolean( $data['qimia_flash_sale_best_deal'] ),
			'expiry'      => $expiry,
			'daysLeft'    => $days_left,
		),
		'inventory' => array(
			'inStock'       => $in_stock,
			'purchasable'    => $purchasable,
			'stockQuantity'  => $stock_quantity,
			'maxQuantity'    => $max_quantity,
		),
	);
	return $data;
}
add_filter( 'woocommerce_available_variation', 'qil_available_variation_contract', PHP_INT_MAX, 3 );

/**
 * Ask the installed Qimia AI Translator for the Arabic wording of a batch of
 * strings, using its own Translation Memory.
 *
 * On a normal /ar/ page load the translator rewrites the rendered HTML inside
 * an output buffer, and its woocommerce_product_get_name filter only runs for
 * AJAX/REST requests (QAATM_Dynamic::is_safe_context()). Anything this plugin
 * hands to the browser as JSON therefore left PHP in English, which is why the
 * product cards on /ar/ showed English names while the rest of the page was
 * Arabic. Reading the same Memory the page buffer reads keeps both on exactly
 * the same wording, with one batched query and no AI call on the visitor's
 * request.
 *
 * @param array  $sources       Raw English strings.
 * @param string $context       Translator context: product_title, heading, general…
 * @param bool   $queue_missing Register unseen strings so the translator picks
 *                              them up on its next run. Only ever true for
 *                              product titles, which are the catalogue's own
 *                              vocabulary; everything else uses what exists.
 * @return array<string,string> Source string => Arabic string, ready rows only.
 */
function qil_translate_batch( array $sources, $context = 'product_title', $queue_missing = false ) {
	$map = array();
	if ( ! class_exists( 'QAATM_Memory' ) || ! method_exists( 'QAATM_Memory', 'bulk_resolve' ) ) {
		return $map;
	}

	$context = sanitize_key( (string) $context );
	if ( '' === $context ) {
		$context = 'general';
	}

	$unique = array();
	foreach ( $sources as $source ) {
		$source = trim( (string) $source );
		// A string with no letters (a size, a SKU, a bare number) has nothing to
		// translate and would only cost a Memory row.
		if ( '' === $source || ! preg_match( '/\p{L}/u', $source ) ) {
			continue;
		}
		$unique[ $source ] = true;
	}
	if ( ! $unique ) {
		return $map;
	}
	$unique = array_slice( array_keys( $unique ), 0, 240 );

	$segments = array();
	foreach ( $unique as $source ) {
		$segments[] = array( 'source' => $source, 'context' => $context );
	}

	try {
		$resolved = (array) QAATM_Memory::bulk_resolve( $segments, (bool) $queue_missing );
	} catch ( Exception $error ) {
		return $map;
	} catch ( Error $error ) {
		return $map;
	}

	foreach ( $unique as $index => $source ) {
		$row = isset( $resolved[ $index ] ) && is_array( $resolved[ $index ] ) ? $resolved[ $index ] : null;
		if ( ! $row || 'ready' !== (string) ( isset( $row['status'] ) ? $row['status'] : '' ) ) {
			continue;
		}
		$target = trim( (string) ( isset( $row['target'] ) ? $row['target'] : '' ) );
		if ( '' === $target || $target === $source ) {
			continue;
		}
		// The translator strips store-name leaks out of catalogue titles before
		// printing them; the JSON payload has to get the same cleaned string or
		// the card and the product page would disagree.
		if ( 'product_title' === $context && class_exists( 'QAATM_Product_Title' ) && method_exists( 'QAATM_Product_Title', 'clean_target' ) ) {
			$cleaned = trim( (string) QAATM_Product_Title::clean_target( $source, $target ) );
			if ( '' !== $cleaned ) {
				$target = $cleaned;
			}
		}
		$map[ $source ] = qil_clean_text( $target );
	}

	return $map;
}

/**
 * Translate one string through qil_translate_batch()'s map, unchanged when the
 * translator has no ready row for it.
 */
function qil_translated( $value, array $map ) {
	$value = (string) $value;
	return isset( $map[ $value ] ) ? $map[ $value ] : $value;
}

/**
 * Arabic names for the active ingredients this plugin recognises.
 *
 * These come from a fixed table in qil_extract_primary_actives(), not from the
 * merchant's copy, so they are translated here rather than sent through the
 * Translation Memory: deterministic, correct and free.
 */
function qil_active_labels_ar() {
	return array(
		'protein'      => 'بروتين',
		'creatine'     => 'كرياتين',
		'citrulline'   => 'إل-سيترولين',
		'beta_alanine' => 'بيتا-ألانين',
		'bcaa'         => 'أحماض أمينية متشعبة (BCAA)',
		'eaa'          => 'أحماض أمينية أساسية (EAA)',
		'glutamine'    => 'جلوتامين',
		'collagen'     => 'كولاجين',
		'omega_3'      => 'أوميغا-3',
		'epa'          => 'EPA',
		'dha'          => 'DHA',
		'vitamin_d3'   => 'فيتامين D3',
		'vitamin_c'    => 'فيتامين C',
		'magnesium'    => 'مغنيسيوم',
		'zinc'         => 'زنك',
		'biotin'       => 'بيوتين',
		'melatonin'    => 'ميلاتونين',
		'ashwagandha'  => 'أشواغاندا',
		'probiotics'   => 'بروبيوتيك',
		'caffeine'     => 'كافيين',
	);
}

/**
 * Rewrite a built catalogue into Arabic in one batched pass.
 *
 * Product titles are the merchant's own vocabulary, so unseen ones are queued
 * for the translator. Category, tag and attribute wording reuses whatever the
 * translator already holds and never queues new work.
 */
function qil_localize_catalogue( array $catalogue ) {
	if ( ! $catalogue ) {
		return $catalogue;
	}

	$names = array();
	$terms = array();
	foreach ( $catalogue as $item ) {
		$names[] = isset( $item['name'] ) ? $item['name'] : '';
		foreach ( array( 'categories', 'tags' ) as $list_key ) {
			if ( empty( $item[ $list_key ] ) || ! is_array( $item[ $list_key ] ) ) {
				continue;
			}
			foreach ( $item[ $list_key ] as $term ) {
				$terms[] = $term;
			}
		}
		if ( ! empty( $item['purchase']['options'] ) && is_array( $item['purchase']['options'] ) ) {
			foreach ( $item['purchase']['options'] as $option ) {
				if ( isset( $option['label'] ) ) {
					$terms[] = $option['label'];
				}
			}
		}
	}

	$name_map   = qil_translate_batch( $names, 'product_title', true );
	$term_map   = qil_translate_batch( $terms, 'general', false );
	$active_map = qil_active_labels_ar();
	if ( ! $name_map && ! $term_map ) {
		$name_map = array();
	}

	foreach ( $catalogue as &$item ) {
		if ( isset( $item['name'] ) ) {
			$item['name'] = qil_translated( $item['name'], $name_map );
		}
		foreach ( array( 'categories', 'tags' ) as $list_key ) {
			if ( empty( $item[ $list_key ] ) || ! is_array( $item[ $list_key ] ) ) {
				continue;
			}
			foreach ( $item[ $list_key ] as $term_index => $term ) {
				$item[ $list_key ][ $term_index ] = qil_translated( $term, $term_map );
			}
		}
		if ( ! empty( $item['purchase']['options'] ) && is_array( $item['purchase']['options'] ) ) {
			foreach ( $item['purchase']['options'] as $option_index => $option ) {
				if ( isset( $option['label'] ) ) {
					$item['purchase']['options'][ $option_index ]['label'] = qil_translated( $option['label'], $term_map );
				}
			}
		}
		if ( ! empty( $item['facts']['primaryActives'] ) && is_array( $item['facts']['primaryActives'] ) ) {
			foreach ( $item['facts']['primaryActives'] as $active_index => $active ) {
				$key = isset( $active['key'] ) ? (string) $active['key'] : '';
				if ( '' !== $key && isset( $active_map[ $key ] ) ) {
					$item['facts']['primaryActives'][ $active_index ]['name'] = $active_map[ $key ];
				}
			}
		}
	}
	unset( $item );

	return $catalogue;
}

/**
 * Stable representation of catalogue query arguments for cache keys.
 */
function qil_runtime_cache_value( $value ) {
	if ( ! is_array( $value ) ) {
		return is_scalar( $value ) || null === $value ? $value : (string) $value;
	}
	if ( function_exists( 'array_is_list' ) && array_is_list( $value ) ) {
		return array_map( 'qil_runtime_cache_value', $value );
	}
	ksort( $value, SORT_STRING );
	foreach ( $value as $key => $item ) {
		$value[ $key ] = qil_runtime_cache_value( $item );
	}
	return $value;
}

/**
 * Return a concise, typed and safe view of the real WooCommerce catalogue.
 *
 * The same cards are requested by product pages, search, goals, shelves and
 * crawlers. A short versioned cache stops a burst rebuilding them in parallel.
 * The key carries the full market identity (currency, country, tax/role
 * context, exchange-rate settings, user and Woo session), product and term
 * versions, locale and the query; only anonymous session-less results are
 * ever written to the database.
 */
function qil_get_catalogue( array $query_args = array() ) {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$locale_override = isset( $query_args['_qil_locale'] ) ? sanitize_key( (string) $query_args['_qil_locale'] ) : '';
	if ( ! in_array( $locale_override, array( 'en', 'ar' ), true ) ) {
		$locale_override = '';
	}
	unset( $query_args['_qil_locale'] );
	$detail_view = ! empty( $query_args['_qil_include_unavailable'] ) && ! empty( $query_args['include'] ) && 1 === count( (array) $query_args['include'] );
	// A bounded merchant-selected shelf may include unavailable catalogue items.
	// Woo still owns publication, visibility, prices and purchase actions.
	$selected_view = ! empty( $query_args['_qil_catalogue_selection'] ) && ! empty( $query_args['include'] ) && count( (array) $query_args['include'] ) <= 48;
	unset( $query_args['_qil_include_unavailable'], $query_args['_qil_catalogue_selection'] );

	static $request_cache = array();
	$market_identity = qil_perf_market_identity();
	$cache_key = 'qil_catalogue_v2_' . md5( (string) wp_json_encode( array(
		QIL_VERSION, QIL_SCHEMA_VERSION, qil_perf_product_version(),
		function_exists( 'wp_cache_get_last_changed' ) ? (string) wp_cache_get_last_changed( 'terms' ) : '',
		$locale_override ? $locale_override : ( qil_language_context()['locale'] ?? 'en' ),
		qil_language_context( $locale_override )['siteLocale'] ?? '',
		$market_identity, $detail_view, $selected_view, qil_runtime_cache_value( $query_args ),
	) ) );
	if ( array_key_exists( $cache_key, $request_cache ) ) {
		return $request_cache[ $cache_key ];
	}
	$public_cache = qil_perf_identity_public( $market_identity );
	$cached = qil_perf_cache_get( $cache_key, false, $public_cache );
	if ( is_array( $cached ) ) {
		return $request_cache[ $cache_key ] = $cached;
	}
	// One public cold build at a time. The cache key already contains product,
	// term, market, currency and pricing versions, so a longer TTL reduces CPU
	// without allowing an old product version to survive a stock/price edit.
	$catalogue_lock = $public_cache ? qil_perf_lock( 'catalogue|' . $cache_key, 30 ) : '';
	if ( $public_cache && '' === $catalogue_lock ) {
		$cached = qil_perf_wait_for_cache( $cache_key, (float) apply_filters( 'qil_catalogue_collapse_wait_seconds', 5.0 ), true, 'is_array' );
		if ( is_array( $cached ) ) {
			return $request_cache[ $cache_key ] = $cached;
		}
		// The first owner may have failed before publishing. Only take over after
		// the wait and only when the lock is actually free; do not duplicate a
		// healthy build merely because it needs more than a few hundred ms.
		$catalogue_lock = qil_perf_lock( 'catalogue|' . $cache_key, 30 );
		if ( '' === $catalogue_lock ) {
			$cached = qil_perf_wait_for_cache( $cache_key, (float) apply_filters( 'qil_catalogue_failover_wait_seconds', 5.0 ), true, 'is_array' );
			if ( is_array( $cached ) ) {
				return $request_cache[ $cache_key ] = $cached;
			}
			$catalogue_lock = qil_perf_lock( 'catalogue|' . $cache_key, 30 );
			if ( '' === $catalogue_lock ) {
				$cached = qil_perf_wait_for_cache( $cache_key, (float) apply_filters( 'qil_catalogue_emergency_wait_seconds', 5.0 ), true, 'is_array' );
				if ( is_array( $cached ) ) {
					return $request_cache[ $cache_key ] = $cached;
				}
				// Absolute availability fail-safe only. Reaching this means a previous
				// worker has held the build lock for ~15 seconds without publishing.
				// Preserve storefront output rather than returning an empty catalogue.
			}
		}
	}
	try {
		$catalogue = qil_build_catalogue( $query_args, $locale_override, $detail_view, $selected_view );
	} finally {
		qil_perf_unlock( $catalogue_lock );
	}
	$request_cache[ $cache_key ] = $catalogue;
	qil_perf_cache_set( $cache_key, $catalogue, (int) apply_filters( 'qil_catalogue_cache_ttl', $public_cache ? 90 : 60 ), $public_cache );
	return $catalogue;
}

/** Uncached catalogue builder behind qil_get_catalogue(). */
function qil_build_catalogue( array $query_args, $locale_override, $detail_view, $selected_view = false ) {

	$product_query = wp_parse_args(
		$query_args,
		array(
			'limit'        => 96,
			'status'       => 'publish',
			'stock_status' => 'instock',
			'visibility'   => 'visible',
			'orderby'      => 'date',
			'order'        => 'DESC',
			'return'       => 'objects',
		)
	);
	$product_query['limit']        = max( 1, min( 96, (int) $product_query['limit'] ) );
	$product_query['status']       = 'publish';
	$product_query['stock_status'] = 'instock';
	$product_query['visibility']   = 'visible';
	$product_query['return']       = 'objects';
	if ( $detail_view || $selected_view ) { unset( $product_query['stock_status'] ); }
	if ( $selected_view ) { $product_query['visibility'] = 'catalog'; }
	$products = wc_get_products( $product_query );

	$currency_code = strtoupper( (string) get_woocommerce_currency() );
	$catalogue     = array();
	$source_index  = 0;
	$language      = qil_language_context( $locale_override );

	foreach ( $products as $product ) {
		if ( ! is_a( $product, 'WC_Product' ) || ( ! $detail_view && ! $selected_view && ! $product->is_visible() ) || post_password_required( $product->get_id() ) ) {
			continue;
		}

		$product_id = $product->get_id();
		$name       = qil_clean_text( $product->get_name() );
		$category_terms = wp_get_post_terms( $product_id, 'product_cat' );
		$tag_terms      = wp_get_post_terms( $product_id, 'product_tag' );
		$brand_terms    = taxonomy_exists( 'product_brand' ) ? wp_get_post_terms( $product_id, 'product_brand' ) : array();
		$category_terms = is_wp_error( $category_terms ) ? array() : $category_terms;
		$tag_terms      = is_wp_error( $tag_terms ) ? array() : $tag_terms;
		$brand_terms    = is_wp_error( $brand_terms ) ? array() : $brand_terms;
		$categories     = array_values( array_filter( array_map( static function ( $term ) { return qil_clean_text( $term->name ); }, $category_terms ) ) );
		$category_slugs = array_values( array_filter( array_map( static function ( $term ) { return sanitize_title( $term->slug ); }, $category_terms ) ) );
		$tags           = array_values( array_filter( array_map( static function ( $term ) { return qil_clean_text( $term->name ); }, $tag_terms ) ) );
		$brand          = isset( $brand_terms[0] ) ? qil_clean_text( $brand_terms[0]->name ) : '';

		if ( '' === $brand ) {
			$brand = qil_product_fact( $product, array( 'pa_brand', 'brand' ) );
		}

		$short_markup = $product->get_short_description();
		$full_markup  = $product->get_description();
		$attributes   = qil_product_attributes( $product );
		$attribute_text = '';
		foreach ( $attributes as $attribute ) {
			$attribute_text .= "\n" . $attribute['label'] . ': ' . $attribute['value'];
		}
		$segments      = qil_fact_segments( $full_markup . "\n" . $short_markup . $attribute_text );
		$description   = qil_clean_text( $short_markup );
		if ( '' === $description ) {
			$description = qil_clean_text( $full_markup );
		}
		$description = wp_trim_words( $description, 34 );

		$corpus_source = implode(
			' ',
			array_filter(
				array(
					$name,
					$brand,
					implode( ' ', $categories ),
					implode( ' ', $category_slugs ),
					implode( ' ', $tags ),
					$attribute_text,
					implode( ' ', array_slice( $segments, 0, 24 ) ),
				)
			)
		);
		$corpus = function_exists( 'mb_strtolower' ) ? mb_strtolower( remove_accents( $corpus_source ), 'UTF-8' ) : strtolower( remove_accents( $corpus_source ) );
		$corpus = function_exists( 'mb_substr' ) ? mb_substr( $corpus, 0, 760, 'UTF-8' ) : substr( $corpus, 0, 760 );
		$taxonomy_profile = qil_goal_taxonomy_profile( $product_id, $category_terms, $tag_terms );
		$goals = $taxonomy_profile['goals'];
		$payload_corpus = function_exists( 'mb_substr' ) ? mb_substr( $corpus, 0, 320, 'UTF-8' ) : substr( $corpus, 0, 320 );
		$purpose_identity = implode( ' ', array_filter( array( $name, implode( ' ', $categories ), implode( ' ', $category_slugs ), implode( ' ', $tags ) ) ) );
		$purpose_key = $taxonomy_profile['purposeKey'];
		$servings     = qil_extract_servings( $product, $segments );
		$serving_size = qil_extract_serving_size( $product, $segments );
		$caffeine     = qil_extract_caffeine( $product, $segments );
		$caffeine['formatted'] = $caffeine['label'];
		$actives      = qil_extract_primary_actives( $segments, $goals, $name );
		foreach ( $actives as $active_index => $active ) {
			if ( 'caffeine' !== $active['key'] ) {
				continue;
			}
			if ( 'known' !== $caffeine['state'] || '' === $caffeine['label'] ) {
				unset( $actives[ $active_index ] );
				continue;
			}
			$actives[ $active_index ] = array(
				'key'       => 'caffeine',
				'name'      => 'Caffeine',
				'amount'    => $caffeine['label'],
				'label'     => 'Caffeine · ' . $caffeine['label'],
				'formatted' => 'Caffeine · ' . $caffeine['label'],
			);
		}
		$actives       = array_values( $actives );
		$active_labels = array_values( array_map( static function ( $active ) { return $active['label']; }, $actives ) );
		$typed_actives = array(
			'protein'  => null,
			'creatine' => null,
			'collagen' => null,
		);
		foreach ( $actives as $active ) {
			if ( array_key_exists( $active['key'], $typed_actives ) ) {
				$typed_actives[ $active['key'] ] = $active;
			}
		}

		$serving_count = 0; // Unit price is verified against the exact available SKU below.
		$price_schema = qil_product_price_schema( $product, $currency_code, $serving_count );
		$promotion    = qil_product_promotion( $product, $category_slugs );

		$primary_image = qil_image_data( $product->get_image_id(), $name );
		if ( ! $primary_image && function_exists( 'wc_placeholder_img_src' ) ) {
			$primary_image = array(
				'id'     => 0,
				'src'    => esc_url_raw( wc_placeholder_img_src( 'woocommerce_single' ) ),
				'srcset' => '',
				'sizes'  => '',
				'width'  => 600,
				'height' => 600,
				'alt'    => $name,
			);
		}

		// Homepage cards deliberately use only the primary packshot. This keeps the
		// visual identity stable on hover and avoids downloading unused galleries.
		$images = array_values( array_filter( array( $primary_image ) ) );
		$purchase = qil_product_purchase( $product, $language['isArabic'] );
		$dietary = qil_goal_verified_dietary( $product, $taxonomy_profile['dietary'], $segments, $caffeine );

		$match_reasons = array();
		if ( isset( $actives[0]['label'] ) && '' !== $actives[0]['label'] ) {
			$match_reasons[] = $actives[0]['label'];
		}
		if ( '' !== $servings ) {
			$match_reasons[] = $servings;
		}
		if ( '' === $servings && '' !== $serving_size ) {
			$match_reasons[] = $serving_size . ' serving size';
		}
		$match_sources = array( 'woocommerce' );
		if ( $actives || '' !== $servings || '' !== $serving_size || 'unknown' !== $caffeine['state'] ) {
			$match_sources[] = 'product_label';
		}

		$rank_score  = $product->is_featured() ? 30 : 0;
		$rank_score += $primary_image ? 8 : 0;
		$rank_score += '' !== $servings ? 4 : 0;
		$rank_score += $actives ? 4 : 0;
		$rank_score += '' !== $description ? 2 : 0;
		$rank_score += min( 18, (int) round( log( max( 1, (int) $product->get_total_sales() + 1 ), 2 ) ) );
		if ( $product->get_rating_count() > 0 ) {
			$rank_score += min( 12, (int) round( (float) $product->get_average_rating() * 2 ) );
		}

		$catalogue[] = array(
			'id'              => $product_id,
			'name'            => $name,
			'brand'           => $brand,
			'sku'             => qil_clean_text( $product->get_sku() ),
			'categories'      => $categories,
			'categorySlugs'   => $category_slugs,
			'tags'            => $tags,
			'images'          => $images,
			'url'             => esc_url_raw( qil_localized_url( $product->get_permalink(), $language['isArabic'] ) ),
			'type'            => sanitize_key( $product->get_type() ),
			'purchase'        => $purchase,
			'price'           => $price_schema,
			'promotion'       => $promotion,
            'inventory'       => qil_inventory_state( $product ),
			'salesCount'      => max( 0, (int) $product->get_total_sales() ),
			'rating'          => (float) $product->get_average_rating(),
			'facts'           => array(
				'servings'       => $servings,
				'servingsPerContainer' => $servings,
				'servingSize'    => $serving_size,
				'caffeine'       => $caffeine,
				'primaryActives' => $actives,
				'protein'        => $typed_actives['protein'],
				'creatine'       => $typed_actives['creatine'],
				'collagen'       => $typed_actives['collagen'],
			),
			'dietary'         => $dietary,
			'stock'           => array(
				'status'     => sanitize_key( $product->get_stock_status() ),
				'label'      => $product->is_in_stock() ? ( $language['isArabic'] ? 'متوفر' : 'In stock' ) : ( $language['isArabic'] ? 'غير متوفر' : 'Out of stock' ),
				'inStock'    => (bool) $product->is_in_stock(),
				'quantity'   => null !== $product->get_stock_quantity() ? (int) $product->get_stock_quantity() : null,
				'backorders' => (bool) $product->backorders_allowed(),
			),
			'match'           => array(
				'goals'        => $goals,
				'purposeKey'   => $purpose_key,
				'evidence'     => $taxonomy_profile['evidence'],
				'reasons'      => array_slice( array_values( array_unique( $match_reasons ) ), 0, 2 ),
				'sources'      => $match_sources,
				'searchTokens' => array_values( array_unique( array_merge( $category_slugs, $tags, $active_labels ) ) ),
			),
			'corpus'          => $payload_corpus,
			'_rank'           => $rank_score,
			'_sourceIndex'    => $source_index,
		);
		$last_index = count( $catalogue ) - 1;
		$catalogue[ $last_index ] = qil_goal_value_record( $catalogue[ $last_index ], $product );
		$catalogue[ $last_index ] = apply_filters( 'qil_catalogue_product_record', $catalogue[ $last_index ], $product, $language );
		++$source_index;
	}

	usort(
		$catalogue,
		static function ( $left, $right ) {
			if ( $left['_rank'] === $right['_rank'] ) {
				return $left['_sourceIndex'] - $right['_sourceIndex'];
			}
			return $right['_rank'] - $left['_rank'];
		}
	);

	foreach ( $catalogue as &$item ) {
		unset( $item['_rank'], $item['_sourceIndex'] );
	}
	unset( $item );

	// The catalogue is built from WooCommerce getters, which stay English on a
	// normal /ar/ request; translate it before it is cached under the ar key.
	if ( $language['isArabic'] ) {
		$catalogue = qil_localize_catalogue( $catalogue );
	}

	return $catalogue;
}

/**
 * Exclude products WooCommerce marks as hidden or unavailable from public lab
 * queries. The returned clause is reusable by collection and search queries.
 */
function qil_product_visibility_tax_query( array $include_term_ids = array() ) {
	$tax_query = array();
	if ( $include_term_ids ) {
		$tax_query[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => array_values( array_unique( array_map( 'absint', $include_term_ids ) ) ),
			'operator' => 'IN',
		);
	}

	if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
		$visibility = wc_get_product_visibility_term_ids();
		$excluded   = array_filter(
			array(
				isset( $visibility['exclude-from-catalog'] ) ? (int) $visibility['exclude-from-catalog'] : 0,
				isset( $visibility['exclude-from-search'] ) ? (int) $visibility['exclude-from-search'] : 0,
				isset( $visibility['outofstock'] ) ? (int) $visibility['outofstock'] : 0,
			)
		);
		if ( $excluded ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array_values( array_unique( $excluded ) ),
				'operator' => 'NOT IN',
			);
		}
	}

	if ( 1 < count( $tax_query ) ) {
		$tax_query['relation'] = 'AND';
	}

	return $tax_query;
}

/**
 * Query compact, real collection IDs. Best sellers require total_sales > 0;
 * no rating, recency or invented popularity signal can earn that label.
 */
function qil_collection_product_ids( array $term_ids = array(), $limit = 8, $require_sales = false ) {
	if ( ! class_exists( 'WP_Query' ) ) {
		return array();
	}

	$meta_query = array(
		array(
			'key'     => '_stock_status',
			'value'   => 'instock',
			'compare' => '=',
		),
	);
	if ( $require_sales ) {
		$meta_query[] = array(
			'key'     => 'total_sales',
			'value'   => 0,
			'compare' => '>',
			'type'    => 'NUMERIC',
		);
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => max( 1, min( 12, (int) $limit ) ),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_key'               => 'total_sales',
			'meta_query'             => $meta_query,
			'tax_query'              => qil_product_visibility_tax_query( $term_ids ),
			'orderby'                => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
			'order'                  => 'DESC',
			'suppress_filters'       => false,
		)
	);

	return array_values( array_unique( array_map( 'absint', $query->posts ) ) );
}

/**
 * Read the last seven days of completed WooCommerce demand from the analytics
 * lookup tables. The result is cached briefly and falls back to Woo's lifetime
 * sales ordering if the lookup tables are unavailable or the week is sparse.
 */
function qil_weekly_best_seller_ids( $limit = 8 ) {
	global $wpdb;

	$limit           = max( 3, min( 8, (int) $limit ) );
	$product_version = class_exists( 'WC_Cache_Helper' ) ? WC_Cache_Helper::get_transient_version( 'product' ) : '0';
	$time_bucket     = gmdate( 'YmdHi', (int) floor( time() / ( 15 * MINUTE_IN_SECONDS ) ) * ( 15 * MINUTE_IN_SECONDS ) );
	$cache_key       = 'qil_weekly_sellers_v1_' . md5( QIL_SCHEMA_VERSION . '|' . (string) $product_version . '|' . $time_bucket . '|' . $limit );
	$cached          = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return array_values( array_map( 'absint', $cached ) );
	}

	$ids          = array();
	$lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
	$stats_table  = $wpdb->prefix . 'wc_order_stats';
	$lookup_ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $lookup_table ) ) ) === $lookup_table;
	$stats_ready  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $stats_table ) ) ) === $stats_table;
	if ( $lookup_ready && $stats_ready ) {
		$from = gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are built only from the verified WordPress prefix.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT opl.product_id
				FROM {$lookup_table} opl
				INNER JOIN {$stats_table} os ON os.order_id = opl.order_id
				WHERE opl.date_created >= %s
				AND os.status IN ('wc-processing','wc-completed','wc-on-hold')
				AND opl.product_id > 0
				GROUP BY opl.product_id
				ORDER BY SUM(opl.product_qty) DESC, MAX(opl.date_created) DESC
				LIMIT %d",
				$from,
				$limit * 3
			)
		);
		foreach ( array_map( 'absint', (array) $rows ) as $product_id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
			if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_visible() || ! $product->is_in_stock() ) {
				continue;
			}
			$ids[] = $product_id;
			if ( count( $ids ) >= $limit ) {
				break;
			}
		}
	}

	if ( count( $ids ) < $limit ) {
		$ids = array_values(
			array_unique(
				array_merge( $ids, qil_collection_product_ids( array(), $limit, true ) )
			)
		);
	}
	$ids = array_slice( array_values( array_map( 'absint', $ids ) ), 0, $limit );
	set_transient( $cache_key, $ids, 45 * MINUTE_IN_SECONDS );

	return $ids;
}

/**
 * Resolve collection sources from the store's real taxonomy and sales data.
 */
function qil_curated_source_ids() {
	$product_version = class_exists( 'WC_Cache_Helper' ) ? WC_Cache_Helper::get_transient_version( 'product' ) : '0';
	$cache_key = 'qil_curated_sources_v4_' . md5( QIL_SCHEMA_VERSION . '|' . (string) $product_version );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$term_groups = array( 'protein' => array(), 'creatine' => array(), 'fat_burner' => array(), 'mass_gainer' => array() );
	$terms       = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$identity = qil_clean_text( $term->name . ' ' . $term->slug );
			$key      = qil_product_purpose_key( $identity );
			if ( isset( $term_groups[ $key ] ) ) {
				$term_groups[ $key ][] = (int) $term->term_id;
			}
		}
	}

	$sources = array(
		'protein'     => $term_groups['protein'] ? qil_collection_product_ids( $term_groups['protein'], 8, false ) : array(),
		'creatine'    => $term_groups['creatine'] ? qil_collection_product_ids( $term_groups['creatine'], 8, false ) : array(),
		'fat-burner'  => $term_groups['fat_burner'] ? qil_collection_product_ids( $term_groups['fat_burner'], 8, false ) : array(),
		'mass-gainer' => $term_groups['mass_gainer'] ? qil_collection_product_ids( $term_groups['mass_gainer'], 8, false ) : array(),
		'best-sellers' => qil_collection_product_ids( array(), 8, true ),
		'weekly-best-sellers' => qil_weekly_best_seller_ids( 8 ),
	);
	set_transient( $cache_key, $sources, 20 * MINUTE_IN_SECONDS );

	return $sources;
}

/**
 * Build one bounded initial index containing recent products and every curated
 * product, then expose collection membership as ID arrays instead of duplicates.
 */
function qil_initial_catalogue_payload() {
	$identity        = qil_perf_market_identity();
	$public_cache    = qil_perf_identity_public( $identity );
	$language        = qil_language_context();
	$market          = qil_market_context( $language['isArabic'] );
	$product_version = class_exists( 'WC_Cache_Helper' ) ? WC_Cache_Helper::get_transient_version( 'product' ) : '0';
	$user            = wp_get_current_user();
	$roles           = $user instanceof WP_User ? array_values( (array) $user->roles ) : array();
	sort( $roles, SORT_STRING );
	$vat_exempt = function_exists( 'WC' ) && WC()->customer && method_exists( WC()->customer, 'get_is_vat_exempt' )
		? (bool) WC()->customer->get_is_vat_exempt()
		: false;
	$cache_context = array(
		'market_identity' => $identity,
		'schema'        => QIL_SCHEMA_VERSION,
        'inventory_version' => QIL_VERSION,
		'products'      => (string) $product_version,
		'locale'        => $language['locale'],
		'site_locale'   => $language['siteLocale'],
		'currency'      => function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'OMR',
		'country'       => $market['country'],
		'roles'         => $roles,
		'tax_display'   => (string) get_option( 'woocommerce_tax_display_shop', 'excl' ),
		'prices_include_tax' => function_exists( 'wc_prices_include_tax' ) ? (bool) wc_prices_include_tax() : false,
		'vat_exempt'    => $vat_exempt,
		'pricing_context' => qil_pricing_cache_context(),
	);
	$cache_key = 'qil_initial_v2_' . md5( wp_json_encode( $cache_context ) );
	$cached    = qil_perf_cache_get( $cache_key, false, $public_cache );
	if ( is_array( $cached ) && isset( $cached['products'], $cached['collections'] ) && is_array( $cached['products'] ) && is_array( $cached['collections'] ) ) {
		return $cached;
	}

	$sources    = qil_curated_source_ids();
	$latest_ids = function_exists( 'wc_get_products' )
		? wc_get_products(
			array(
				'limit'        => 48,
				'status'       => 'publish',
				'stock_status' => 'instock',
				'visibility'   => 'visible',
				'orderby'      => 'date',
				'order'        => 'DESC',
				'return'       => 'ids',
			)
		)
		: array();
	$index_ids = array_slice(
		array_values( array_unique( array_map( 'absint', array_merge( $sources['weekly-best-sellers'], $sources['best-sellers'], $sources['protein'], $sources['creatine'], $sources['fat-burner'], $sources['mass-gainer'], $latest_ids ) ) ) ),
		0,
		64
	);
	$catalogue = $index_ids
		? qil_get_catalogue( array( 'include' => $index_ids, 'limit' => count( $index_ids ), 'orderby' => 'include' ) )
		: qil_get_catalogue();
	$by_id = array();
	foreach ( $catalogue as $item ) {
		$by_id[ (int) $item['id'] ] = $item;
	}

	$collections = array();
	foreach ( array( 'protein', 'creatine', 'fat-burner', 'best-sellers', 'weekly-best-sellers' ) as $collection_key ) {
		$collections[ $collection_key ] = array_values(
			array_filter(
				array_map( 'absint', $sources[ $collection_key ] ),
				static function ( $product_id ) use ( $by_id ) {
					return isset( $by_id[ $product_id ] );
				}
			)
		);
	}

	// Taxonomy is the fallback for a sparse new catalogue; "Best sellers" never
	// falls back unless WooCommerce reports at least one completed sale.
	foreach ( array( 'protein', 'creatine', 'fat-burner' ) as $collection_key ) {
		if ( count( $collections[ $collection_key ] ) >= 4 ) {
			continue;
		}
		foreach ( $catalogue as $item ) {
			$purpose_key = 'fat-burner' === $collection_key ? 'fat_burner' : $collection_key;
			if ( $purpose_key === $item['match']['purposeKey'] && ! in_array( (int) $item['id'], $collections[ $collection_key ], true ) ) {
				$collections[ $collection_key ][] = (int) $item['id'];
			}
			if ( 8 <= count( $collections[ $collection_key ] ) ) {
				break;
			}
		}
	}

	$payload = array( 'products' => $catalogue, 'collections' => $collections );
	qil_perf_cache_set( $cache_key, $payload, 12 * MINUTE_IN_SECONDS, $public_cache );

	return $payload;
}

/**
 * Normalize storefront search text so punctuation and common Arabic letter
 * variants do not weaken title/SKU matching (for example, "Lipo-6" vs
 * "lipo6"). This helper is intentionally scoped to search only.
 */
function qil_search_normalize_text( $value ) {
	$text = qil_clean_text( $value );
	$text = remove_accents( $text );
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	$text = strtr(
		$text,
		array(
			'أ' => 'ا',
			'إ' => 'ا',
			'آ' => 'ا',
			'ٱ' => 'ا',
			'ى' => 'ي',
		)
	);
	$text = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text );
	$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
	return trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
}

/** Return a punctuation-free form used for compact model/SKU spellings. */
function qil_search_compact_text( $value ) {
	return preg_replace( '/[^\p{L}\p{N}]+/u', '', qil_search_normalize_text( $value ) );
}

/** Whether a normalized phrase begins at a word boundary in another phrase. */
function qil_search_has_word_prefix( $haystack, $needle ) {
	$haystack = qil_search_normalize_text( $haystack );
	$needle   = qil_search_normalize_text( $needle );
	if ( '' === $haystack || '' === $needle ) {
		return false;
	}
	return (bool) preg_match( '/(?:^|\s)' . preg_quote( $needle, '/' ) . '/u', $haystack );
}

/**
 * Rank one product by search intent. Product title, brand and SKU always beat
 * description/category matches so the preview shows what the visitor typed.
 */
function qil_search_product_score( $product, $search, array $matched_skus = array() ) {
	if ( ! is_a( $product, 'WC_Product' ) ) {
		return 0;
	}

	$query         = qil_search_normalize_text( $search );
	$query_compact = qil_search_compact_text( $search );
	if ( '' === $query ) {
		return 0;
	}

	$title = qil_search_normalize_text( $product->get_name() );
	$brand = '';
	$brand_terms = taxonomy_exists( 'product_brand' ) ? wp_get_post_terms( $product->get_id(), 'product_brand' ) : array();
	if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms[0]->name ) ) {
		$brand = qil_search_normalize_text( $brand_terms[0]->name );
	}
	if ( '' === $brand ) {
		$brand = qil_search_normalize_text( qil_product_fact( $product, array( 'pa_brand', 'brand' ) ) );
	}

	$sku_values = array_values( array_filter( array_merge( array( $product->get_sku() ), $matched_skus ) ) );
	$score      = 0;

	if ( $title === $query ) {
		$score = max( $score, 1600 );
	} elseif ( 0 === strpos( $title, $query ) ) {
		$score = max( $score, 1450 );
	} elseif ( qil_search_has_word_prefix( $title, $query ) ) {
		$score = max( $score, 1280 );
	} elseif ( false !== strpos( $title, $query ) ) {
		$score = max( $score, 1160 );
	}

	$title_compact = qil_search_compact_text( $title );
	if ( strlen( $query_compact ) >= 3 && false !== strpos( $title_compact, $query_compact ) ) {
		$score = max( $score, 1040 );
	}

	if ( '' !== $brand ) {
		if ( $brand === $query ) {
			$score = max( $score, 1380 );
		} elseif ( 0 === strpos( $brand, $query ) ) {
			$score = max( $score, 1220 );
		} elseif ( qil_search_has_word_prefix( $brand, $query ) || false !== strpos( $brand, $query ) ) {
			$score = max( $score, 1040 );
		}
	}

	foreach ( $sku_values as $sku_value ) {
		$sku         = qil_search_normalize_text( $sku_value );
		$sku_compact = qil_search_compact_text( $sku_value );
		if ( $sku === $query || ( '' !== $query_compact && $sku_compact === $query_compact ) ) {
			$score = max( $score, 1750 );
		} elseif ( 0 === strpos( $sku, $query ) || ( '' !== $query_compact && 0 === strpos( $sku_compact, $query_compact ) ) ) {
			$score = max( $score, 1520 );
		} elseif ( strlen( $query_compact ) >= 3 && false !== strpos( $sku_compact, $query_compact ) ) {
			$score = max( $score, 1340 );
		}
	}

	$tokens = array_values( array_filter( preg_split( '/\s+/u', $query ) ) );
	if ( $tokens ) {
		$title_words = preg_split( '/\s+/u', $title );
		$all_title_tokens = true;
		foreach ( $tokens as $token ) {
			$matched = false;
			foreach ( $title_words as $word ) {
				if ( 0 === strpos( $word, $token ) ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				$all_title_tokens = false;
				break;
			}
		}
		if ( $all_title_tokens ) {
			$score = max( $score, 980 );
		}
	}

	$category_names = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
	$tag_names      = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
	$category_names = is_wp_error( $category_names ) ? array() : $category_names;
	$tag_names      = is_wp_error( $tag_names ) ? array() : $tag_names;
	$taxonomy_text  = qil_search_normalize_text( implode( ' ', array_merge( $category_names, $tag_names ) ) );
	$combined       = trim( $title . ' ' . $brand . ' ' . $taxonomy_text );

	if ( $tokens ) {
		$all_combined = true;
		foreach ( $tokens as $token ) {
			if ( false === strpos( $combined, $token ) ) {
				$all_combined = false;
				break;
			}
		}
		if ( $all_combined ) {
			$score = max( $score, 720 );
		}
	}
	if ( '' !== $taxonomy_text && false !== strpos( $taxonomy_text, $query ) ) {
		$score = max( $score, 520 );
	}

	// Content is only a last-resort match. It can never outrank a title/brand/SKU.
	if ( $score < 520 ) {
		$content = qil_search_normalize_text( $product->get_short_description() . ' ' . $product->get_description() );
		if ( $tokens ) {
			$all_content = true;
			foreach ( $tokens as $token ) {
				if ( false === strpos( $content, $token ) ) {
					$all_content = false;
					break;
				}
			}
			if ( $all_content ) {
				$score = max( $score, 260 );
			}
		}
	}

	if ( $score > 0 ) {
		$score += min( 36, (int) round( log( max( 1, (int) $product->get_total_sales() + 1 ), 2 ) * 3 ) );
		$score += min( 10, (int) round( (float) $product->get_average_rating() * 2 ) );
	}

	return $score;
}

/**
 * Return visible product IDs for the lightweight public search endpoint.
 * Candidate discovery is broad; final ordering is deliberately strict.
 */
function qil_search_product_ids( $search, $limit ) {
	global $wpdb;

	$search     = qil_clean_text( $search );
	$normalized = qil_search_normalize_text( $search );
	$aliases    = array(
		'protein' => 'protein', 'proteins' => 'protein', 'بروتين' => 'protein', 'البروتين' => 'protein',
		'creatine' => 'creatine', 'creatines' => 'creatine', 'كرياتين' => 'creatine', 'الكرياتين' => 'creatine',
		'best sellers' => 'best-sellers', 'bestsellers' => 'best-sellers', 'الأكثر مبيعا' => 'best-sellers', 'الاكثر مبيعا' => 'best-sellers',
	);
	if ( isset( $aliases[ $normalized ] ) ) {
		$sources = qil_curated_source_ids();
		return array_slice( $sources[ $aliases[ $normalized ] ], 0, $limit );
	}

	// Matching product IDs carry no price; they depend only on catalogue and
	// term versions, so they are shared between all shoppers.
	$search_cache_key = 'qil_search_ids_v1_' . md5( (string) wp_json_encode( array( QIL_VERSION, qil_perf_product_version(), function_exists( 'wp_cache_get_last_changed' ) ? (string) wp_cache_get_last_changed( 'terms' ) : '', $search, (int) $limit ) ) );
	$search_cached    = qil_perf_cache_get( $search_cache_key );
	if ( is_array( $search_cached ) ) {
		return array_slice( array_map( 'absint', $search_cached ), 0, $limit );
	}
	$search_lock = qil_perf_lock( 'search-ids|' . $search_cache_key, 15 );
	if ( '' === $search_lock ) {
		$search_cached = qil_perf_wait_for_cache( $search_cache_key, 1.5, true, 'is_array' );
		if ( is_array( $search_cached ) ) {
			return array_slice( array_map( 'absint', $search_cached ), 0, $limit );
		}
		$search_lock = qil_perf_lock( 'search-ids|' . $search_cache_key, 15 );
		if ( '' === $search_lock ) {
			$search_cached = qil_perf_wait_for_cache( $search_cache_key, 1.5, true, 'is_array' );
			if ( is_array( $search_cached ) ) {
				return array_slice( array_map( 'absint', $search_cached ), 0, $limit );
			}
		}
	}

	$candidate_limit = max( 24, min( 48, $limit * 6 ) );
	$candidate_ids   = array();
	$matched_skus    = array();

	// Title candidates first, including prefix/word-start names that WordPress
	// full-text relevance can otherwise place behind description matches. A
	// second letter/number-spaced variant catches common spellings such as
	// "Lipo6" when the catalogue title is "Lipo-6".
	$search_variants = array( $search );
	$compact_search  = preg_replace( '/[^\p{L}\p{N}]+/u', '', $search );
	if ( is_string( $compact_search ) && strlen( $compact_search ) >= 3 ) {
		$search_variants[] = $compact_search;
		$spaced_search = preg_replace( '/(?<=\p{L})(?=\p{N})|(?<=\p{N})(?=\p{L})/u', ' ', $compact_search );
		$hyphen_search = preg_replace( '/(?<=\p{L})(?=\p{N})|(?<=\p{N})(?=\p{L})/u', '-', $compact_search );
		if ( is_string( $spaced_search ) && '' !== trim( $spaced_search ) ) {
			$search_variants[] = $spaced_search;
		}
		if ( is_string( $hyphen_search ) && '' !== trim( $hyphen_search ) ) {
			$search_variants[] = $hyphen_search;
		}
	}
	$search_variants = array_values( array_unique( array_filter( array_map( 'trim', $search_variants ) ) ) );
	foreach ( $search_variants as $title_search ) {
		$title_like   = '%' . $wpdb->esc_like( $title_search ) . '%';
		$title_prefix = $wpdb->esc_like( $title_search ) . '%';
		$title_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'product' AND post_status = 'publish' AND post_title LIKE %s
				 ORDER BY CASE WHEN post_title LIKE %s THEN 0 ELSE 1 END, post_date DESC
				 LIMIT %d",
				$title_like,
				$title_prefix,
				$candidate_limit
			)
		);
		$candidate_ids = array_merge( $candidate_ids, array_map( 'absint', $title_ids ) );
	}

	// SKU prefix/substring candidates, mapping variation SKUs back to the parent.
	foreach ( $search_variants as $sku_search ) {
		$sku_like = '%' . $wpdb->esc_like( $sku_search ) . '%';
		$sku_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_parent, pm.meta_value AS sku
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_sku' AND pm.meta_value LIKE %s
				   AND p.post_type IN ('product','product_variation')
				   AND p.post_status = 'publish'
				 LIMIT %d",
				$sku_like,
				min( 16, $candidate_limit )
			)
		);
		foreach ( $sku_rows as $sku_row ) {
			$product_id = 'product_variation' === get_post_type( (int) $sku_row->ID ) && (int) $sku_row->post_parent > 0 ? (int) $sku_row->post_parent : (int) $sku_row->ID;
			$candidate_ids[] = $product_id;
			if ( ! isset( $matched_skus[ $product_id ] ) ) {
				$matched_skus[ $product_id ] = array();
			}
			$matched_skus[ $product_id ][] = (string) $sku_row->sku;
		}
	}

	// Standard WordPress search remains useful for descriptions and attributes,
	// but its order is no longer trusted as the final preview order.
	$query = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $candidate_limit,
			'fields'                 => 'ids',
			's'                      => $search,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array(
				array( 'key' => '_stock_status', 'value' => 'instock', 'compare' => '=' ),
			),
			'tax_query'              => qil_product_visibility_tax_query(),
			'orderby'                => array( 'relevance' => 'DESC', 'date' => 'DESC' ),
			'suppress_filters'       => false,
		)
	);
	$candidate_ids = array_merge( $candidate_ids, array_map( 'absint', $query->posts ) );

	// Brand taxonomy matches should behave like title matches.
	if ( taxonomy_exists( 'product_brand' ) ) {
		$brand_terms = get_terms(
			array(
				'taxonomy'   => 'product_brand',
				'hide_empty' => true,
				'search'     => $search,
				'number'     => 8,
			)
		);
		if ( ! is_wp_error( $brand_terms ) && $brand_terms ) {
			$brand_ids = get_objects_in_term( wp_list_pluck( $brand_terms, 'term_id' ), 'product_brand' );
			if ( ! is_wp_error( $brand_ids ) ) {
				$candidate_ids = array_merge( $candidate_ids, array_slice( array_map( 'absint', (array) $brand_ids ), 0, $candidate_limit ) );
			}
		}
	}

	$candidate_ids = array_values( array_unique( array_filter( array_map( 'absint', $candidate_ids ) ) ) );
	$ranked = array();
	foreach ( $candidate_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! is_a( $product, 'WC_Product' ) || ! $product->is_visible() || ! $product->is_in_stock() || post_password_required( $product_id ) ) {
			continue;
		}
		$score = qil_search_product_score( $product, $search, isset( $matched_skus[ $product_id ] ) ? $matched_skus[ $product_id ] : array() );
		if ( $score <= 0 ) {
			continue;
		}
		$ranked[] = array( 'id' => $product_id, 'score' => $score );
	}

	usort(
		$ranked,
		static function ( $left, $right ) {
			if ( $left['score'] === $right['score'] ) {
				return $left['id'] <=> $right['id'];
			}
			return $right['score'] <=> $left['score'];
		}
	);

	// If a high-confidence name/brand/SKU match exists, keep the preview clean
	// by suppressing low-confidence description/category-only candidates.
	if ( ! empty( $ranked[0]['score'] ) && $ranked[0]['score'] >= 1000 ) {
		$ranked = array_values(
			array_filter(
				$ranked,
				static function ( $entry ) { return isset( $entry['score'] ) && $entry['score'] >= 900; }
			)
		);
	}

	$result_ids = array_slice( array_column( $ranked, 'id' ), 0, $limit );
	qil_perf_cache_set( $search_cache_key, $result_ids, 2 * MINUTE_IN_SECONDS );
	qil_perf_unlock( $search_lock );
	return $result_ids;
}

/**
 * Price-sensitive cache context shared by public catalogue reads. Currency,
 * market tax rules and role pricing must never leak across cached responses.
 */
function qil_pricing_cache_context() {
	$user  = wp_get_current_user();
	$roles = $user instanceof WP_User ? array_values( (array) $user->roles ) : array();
	sort( $roles, SORT_STRING );
	$vat_exempt = function_exists( 'WC' ) && WC()->customer && method_exists( WC()->customer, 'get_is_vat_exempt' )
		? (bool) WC()->customer->get_is_vat_exempt()
		: false;
	$tax_location = array();
	if ( function_exists( 'WC' ) && WC()->customer && method_exists( WC()->customer, 'get_taxable_address' ) ) {
		$tax_location = array_values( array_map( 'sanitize_text_field', (array) WC()->customer->get_taxable_address() ) );
	}

	return array(
		'roles'              => $roles,
		'tax_display'        => (string) get_option( 'woocommerce_tax_display_shop', 'excl' ),
		'prices_include_tax' => function_exists( 'wc_prices_include_tax' ) ? (bool) wc_prices_include_tax() : false,
		'vat_exempt'         => $vat_exempt,
		'tax_location'       => $tax_location,
	);
}

/**
 * Return the explicit storefront locale carried by public REST requests.
 * REST routes do not retain the /ar/ path, so deriving language from the REST
 * URL can cross-contaminate translated option labels and search payloads.
 *
 * @return string|WP_Error
 */
function qil_rest_storefront_locale( WP_REST_Request $request ) {
	$locale = strtolower( sanitize_key( (string) $request->get_param( 'qil_locale' ) ) );
	if ( ! in_array( $locale, array( 'en', 'ar' ), true ) ) {
		return new WP_Error(
			'qil_invalid_locale',
			'An explicit storefront locale of en or ar is required.',
			array( 'status' => 400 )
		);
	}

	return $locale;
}

/**
 * Identify Qimia's price- and inventory-sensitive public REST reads before
 * WordPress builds the REST response. LiteSpeed can otherwise decide that a
 * GET request is public before the later REST headers are attached.
 */
function qil_is_live_catalogue_rest_request() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( '' === $uri ) {
		return false;
	}

	return (bool) preg_match( '#(?:/wp-json|rest_route=)/?qimia-lab/v1/(?:search|goals(?:/|%2F|$)|quick-view(?:/|%2F|$))#i', $uri );
}

/**
 * Tell LiteSpeed through its supported cache-control API that live product
 * reads must never enter its public edge cache. The REST response still sends
 * explicit browser/CDN no-store headers as a second layer below.
 */
function qil_disable_edge_cache_for_live_rest() {
	if ( ! qil_experience_enabled() || ! qil_is_live_catalogue_rest_request() ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	do_action( 'litespeed_control_set_nocache', 'Qimia live catalogue REST' );
}
add_action( 'init', 'qil_disable_edge_cache_for_live_rest', -9999 );

/** Mark live catalogue REST responses private and uncacheable at the edge. */
function qil_private_live_rest_response( $payload ) {
	$response = rest_ensure_response( $payload );
	$response->header( 'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0' );
	$response->header( 'CDN-Cache-Control', 'no-store' );
	$response->header( 'Cloudflare-CDN-Cache-Control', 'no-store' );
	$response->header( 'Pragma', 'no-cache' );
	$response->header( 'Expires', '0' );
	$response->header( 'Surrogate-Control', 'no-store' );
	$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache, no-store' );
	$response->header( 'Vary', 'Cookie, Accept-Language' );
	return $response;
}

/**
 * Apply the live-inventory cache policy to successful and error responses.
 * Validation failures otherwise bypass the callback wrapper and can inherit an
 * edge-cache policy from LiteSpeed or a CDN.
 */
function qil_no_store_live_rest_dispatch( $response, $server, $request ) {
	if ( ! qil_experience_enabled() || ! is_a( $request, 'WP_REST_Request' ) ) {
		return $response;
	}
	$route = (string) $request->get_route();
	if ( ! preg_match( '#^/qimia-lab/v1/(?:search|goals(?:/|$)|quick-view(?:/|$))#', $route ) ) {
		return $response;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	do_action( 'litespeed_control_set_nocache', 'Qimia live catalogue REST' );
	if ( is_wp_error( $response ) && is_object( $server ) && method_exists( $server, 'error_to_response' ) ) {
		$response = $server->error_to_response( $response );
	}
	return qil_private_live_rest_response( $response );
}
add_filter( 'rest_post_dispatch', 'qil_no_store_live_rest_dispatch', PHP_INT_MAX, 3 );

/**
 * Cache-friendly, read-only search used when WoodMart AJAX search is absent.
 */
function qil_rest_search_products( WP_REST_Request $request ) {
	$requested_locale = qil_rest_storefront_locale( $request );
	if ( is_wp_error( $requested_locale ) ) {
		return $requested_locale;
	}
	$search = qil_clean_text( $request->get_param( 'q' ) );
	$search = function_exists( 'mb_substr' ) ? mb_substr( $search, 0, 64, 'UTF-8' ) : substr( $search, 0, 64 );
	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $search, 'UTF-8' ) : strlen( $search );
	$limit  = max( 4, min( 8, (int) $request->get_param( 'limit' ) ) );
	if ( $length < 2 ) {
		return qil_private_live_rest_response( array( 'products' => array(), 'query' => $search ) );
	}

	$language = qil_language_context( $requested_locale );
	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'OMR';
	$country  = function_exists( 'WC' ) && WC()->customer ? WC()->customer->get_shipping_country() : '';
	if ( '' === $country && function_exists( 'WC' ) && WC()->customer ) {
		$country = WC()->customer->get_billing_country();
	}
	$country = strtoupper( sanitize_text_field( (string) $country ) );
	$market_identity = qil_perf_market_identity();
	$public_cache    = qil_perf_identity_public( $market_identity );
	$cache_context = array_merge(
		qil_pricing_cache_context(),
		array(
			'market'   => $market_identity,
			'schema'   => QIL_SCHEMA_VERSION,
			'products' => class_exists( 'WC_Cache_Helper' ) ? (string) WC_Cache_Helper::get_transient_version( 'product' ) : '0',
			'locale'   => $language['locale'],
			'currency' => $currency,
			'country'  => $country,
			'limit'    => $limit,
			'search'   => $search,
		)
	);
	$cache_key = 'qil_search_v6_' . md5( wp_json_encode( $cache_context ) );
	// Shared between PHP workers for the same market, so the same letters
	// typed by many shoppers are searched once per product version. Session
	// and account results stay in memory cache only.
	$payload   = qil_perf_cache_get( $cache_key, false, $public_cache );
	if ( ! is_array( $payload ) || ! isset( $payload['products'] ) ) {
		$product_ids = qil_search_product_ids( $search, $limit );
		$products    = $product_ids
			? qil_get_catalogue( array( 'include' => $product_ids, 'limit' => count( $product_ids ), 'orderby' => 'include', '_qil_locale' => $requested_locale ) )
			: array();
		$payload = array( 'products' => $products, 'query' => $search );
		qil_perf_cache_set( $cache_key, $payload, (int) apply_filters( 'qil_search_cache_ttl', 2 * MINUTE_IN_SECONDS ), $public_cache );
	}

	return qil_private_live_rest_response( $payload );
}

/**
 * Lightweight variable-product metadata for the on-page option picker. Only a
 * bounded attribute matrix leaves this endpoint; prices and promotion details
 * continue to come from WooCommerce's authoritative get_variation response.
 */
function qil_rest_quick_view_product( WP_REST_Request $request ) {
	$requested_locale = qil_rest_storefront_locale( $request );
	if ( is_wp_error( $requested_locale ) ) {
		return $requested_locale;
	}
	$product_id = absint( $request->get_param( 'id' ) );
	$product    = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
	if (
		! $product
		|| ! $product->is_type( 'variable' )
		|| 'publish' !== $product->get_status()
		|| ! $product->is_visible()
	) {
		return new WP_Error( 'qil_product_unavailable', 'Product options are unavailable.', array( 'status' => 404 ) );
	}

	$product_version = class_exists( 'WC_Cache_Helper' ) ? WC_Cache_Helper::get_transient_version( 'product' ) : '0';
	$cache_key       = 'qil_quick_view_v4_' . md5( QIL_SCHEMA_VERSION . '|' . (string) $product_version . '|' . $requested_locale . '|' . $product_id );
	$payload         = get_transient( $cache_key );
	if ( ! is_array( $payload ) ) {
		$options = qil_product_options( $product, $requested_locale );
		$name    = qil_clean_text( $product->get_name() );
		// A quick view opened from the Arabic route has to name the product and
		// its flavours the same way the Arabic product page does.
		if ( 'ar' === $requested_locale ) {
			$name_map  = qil_translate_batch( array( $name ), 'product_title', true );
			$name      = qil_translated( $name, $name_map );
			$labels    = array();
			foreach ( $options['attributes'] as $attribute ) {
				foreach ( (array) ( isset( $attribute['values'] ) ? $attribute['values'] : array() ) as $value_row ) {
					if ( isset( $value_row['label'] ) ) {
						$labels[] = $value_row['label'];
					}
				}
			}
			$label_map = qil_translate_batch( $labels, 'general', false );
			foreach ( $options['attributes'] as $attribute_index => $attribute ) {
				foreach ( (array) ( isset( $attribute['values'] ) ? $attribute['values'] : array() ) as $value_index => $value_row ) {
					if ( isset( $value_row['label'] ) ) {
						$options['attributes'][ $attribute_index ]['values'][ $value_index ]['label'] = qil_translated( $value_row['label'], $label_map );
					}
				}
			}
		}
		$payload = array(
			'id'         => $product_id,
			'name'       => $name,
			'url'        => esc_url_raw( qil_localized_url( $product->get_permalink(), 'ar' === $requested_locale ) ),
			'fallback'   => (bool) $options['fallback'],
			'unavailable'=> (bool) $options['unavailable'],
			'attributes' => $options['attributes'],
			'defaults'   => $options['defaults'],
			'combinations' => $options['combinations'],
		);
		set_transient( $cache_key, $payload, 5 * MINUTE_IN_SECONDS );
	}

	return qil_private_live_rest_response( $payload );
}

/** Register read-only storefront routes; permissions require an enabled experience. */
function qil_register_rest_routes() {
	register_rest_route(
		'qimia-lab/v1',
		'/search',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'qil_rest_search_products',
			'permission_callback' => static function () {
				return qil_experience_enabled() && function_exists( 'wc_get_products' );
			},
			'args'                => array(
				'q'          => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'limit'      => array( 'type' => 'integer', 'default' => 8, 'minimum' => 4, 'maximum' => 8 ),
				'qil_locale' => array( 'type' => 'string', 'required' => true, 'enum' => array( 'en', 'ar' ), 'sanitize_callback' => 'sanitize_key' ),
			),
		)
	);
	register_rest_route(
		'qimia-lab/v1',
		'/quick-view/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'qil_rest_quick_view_product',
			'permission_callback' => static function () {
				return qil_experience_enabled() && function_exists( 'wc_get_product' );
			},
			'args'                => array(
				'id'         => array( 'type' => 'integer', 'required' => true, 'minimum' => 1, 'sanitize_callback' => 'absint' ),
				'qil_locale' => array( 'type' => 'string', 'required' => true, 'enum' => array( 'en', 'ar' ), 'sanitize_callback' => 'sanitize_key' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'qil_register_rest_routes' );

/** Keep the custom bag count synchronized with WooCommerce fragments. */
function qil_cart_count_fragment( $fragments ) {
	if ( ! qil_experience_enabled() || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $fragments;
	}
	$count    = (int) WC()->cart->get_cart_contents_count();
	$language = qil_language_context();
	$label    = $language['isArabic'] ? $count . ' منتجات في السلة' : sprintf( _n( '%d item in cart', '%d items in cart', $count, 'qimia-intelligence-lab' ), $count );
	$fragments['span[data-qil-cart-count]'] = sprintf(
		'<span class="wd-cart-number wd-tools-count%s" data-qil-cart-count role="status" aria-live="polite" aria-atomic="true" aria-label="%s"%s>%d</span>',
		0 === $count ? ' is-empty' : '',
		esc_attr( $label ),
		0 === $count ? ' hidden aria-hidden="true"' : ' aria-hidden="false"',
		$count
	);
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'qil_cart_count_fragment', 20 );

/**
 * Resolve language from the existing / and /ar/ storefront routes.
 */
function qil_language_context( $locale_override = '' ) {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	$locale      = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	$translated  = defined( 'QAATM_LANGUAGE' ) ? (string) QAATM_LANGUAGE : '';
	$override    = strtolower( sanitize_key( (string) $locale_override ) );
	$override    = in_array( $override, array( 'en', 'ar' ), true ) ? $override : '';
	$is_arabic   = $override
		? 'ar' === $override
		: ( 0 === strpos( strtolower( $locale ), 'ar' )
			|| 0 === strpos( strtolower( $translated ), 'ar' )
			|| (bool) preg_match( '#(?:^|/)ar(?:/|$)#i', $path )
			|| is_rtl() );

	return array(
		'locale'       => $is_arabic ? 'ar' : 'en',
		'siteLocale'   => $locale,
		'isArabic'     => $is_arabic,
		'direction'    => $is_arabic ? 'rtl' : 'ltr',
		'languageUrls' => qil_language_route_urls(),
	);
}

/**
 * Build the English and Arabic URLs for the CURRENT request, the way the
 * installed translator does, so the pair stays correct on a product page or any
 * other route instead of only on the front page. The translator's own router is
 * asked first whenever it is loaded, so its rules always win.
 */
function qil_language_route_urls() {

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$parts       = wp_parse_url( (string) $request_uri );
	$path        = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
	// Read the canonical WordPress setting, not a language-filtered home_url().
	$base = untrailingslashit( (string) get_option( 'home', home_url( '/' ) ) );
	$base_parts = wp_parse_url( $base );
	if ( ! is_array( $base_parts ) || empty( $base_parts['host'] ) || ! in_array( $base_parts['scheme'] ?? '', array('http','https'), true ) ) {
		$base = untrailingslashit( home_url( '/' ) );
	}
	$home_path = untrailingslashit( (string) wp_parse_url( $base, PHP_URL_PATH ) );
	if ( $home_path && ( $path === $home_path || 0 === strpos( $path, $home_path . '/' ) ) ) {
		$path = substr( $path, strlen($home_path) );
	}
	$path = '/' . ltrim( $path, '/' );
	$english     = preg_replace( '~^/ar(?=/|$)~', '', $path, 1 );
	$english     = '' === $english ? '/' : $english;
	$arabic      = preg_match( '~^/ar(?:/|$)~', $path ) ? $path : '/ar' . ( '/' === $path ? '/' : '/' . ltrim( $path, '/' ) );

	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( (string) $parts['query'], $query );
		foreach ( array( 'qaatm_lang', 'qaatm_preview', 'qaatm_prepare', '_qaatm_compiler', '_qaatm_variant', '_qaatm_bust', 'add-to-cart', 'remove_item', 'undo_item', '_wpnonce', 'wc-ajax' ) as $drop ) {
			unset( $query[ $drop ] );
		}
	}

	$build = static function ( $route ) use ( $query, $base ) {
		$url = $base . '/' . ltrim( $route, '/' );
		return $query ? add_query_arg( $query, $url ) : $url;
	};

	return array( 'en' => $build( $english ), 'ar' => $build( $arabic ) );
}

/**
 * Keep every emitted storefront URL on the language route the visitor is on.
 *
 * The installed translation plugin owns the real product and category slugs.
 * When it has already produced an Arabic URL this returns it untouched, so the
 * plugin's slug always wins; it only adds the /ar/ prefix when a URL was built
 * outside the Arabic request context (REST search, Quick View, cached rows).
 */
function qil_localized_url( $url, $is_arabic ) {
	$url = (string) $url;
	if ( ! $is_arabic || '' === $url ) {
		return $url;
	}

	$home_path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	$parts     = wp_parse_url( $url );
	$host      = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
	$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

	// Never rewrite an external or add-to-cart style URL.
	if ( '' !== $host && $host !== $home_host ) {
		return $url;
	}

	$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
	$rest = '' !== $home_path && 0 === strpos( $path, $home_path . '/' )
		? substr( $path, strlen( $home_path ) )
		: $path;
	$rest = '/' . ltrim( (string) $rest, '/' );

	// The translation plugin already routed this URL.
	if ( preg_match( '#^/ar(/|$)#i', $rest ) ) {
		return $url;
	}

	$query    = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
	$fragment = isset( $parts['fragment'] ) && '' !== $parts['fragment'] ? '#' . $parts['fragment'] : '';

	return home_url( '/ar' . $rest ) . $query . $fragment;
}

/**
 * Resolve the active GCC market and its truthful free-delivery message.
 */
function qil_market_context( $is_arabic = false ) {
	$currency = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'OMR';
	$country  = '';

	if ( function_exists( 'WC' ) && WC()->customer ) {
		$country = strtoupper( (string) WC()->customer->get_shipping_country() );
		if ( '' === $country ) {
			$country = strtoupper( (string) WC()->customer->get_billing_country() );
		}
	}

	if ( '' === $country && isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
		$cf_country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
		if ( preg_match( '/^[A-Z]{2}$/', $cf_country ) ) {
			$country = $cf_country;
		}
	}

	// An explicitly selected storefront currency is the clearest market signal.
	$currency_markets = array( 'OMR' => 'OM', 'SAR' => 'SA', 'QAR' => 'QA', 'AED' => 'AE', 'KWD' => 'KW' );
	$market           = isset( $currency_markets[ $currency ] ) ? $currency_markets[ $currency ] : $country;
	$market           = $market ? $market : 'GCC';
	$messages         = array(
		'OM'  => array(
			'en'        => 'Free delivery across Oman on orders over 15 OMR',
			'ar'        => 'توصيل مجاني داخل عُمان للطلبات التي تزيد على 15 ريال عماني',
			'threshold' => 15,
			'currency'  => 'OMR',
			'label'     => 'Oman',
			'labelAr'   => 'عُمان',
			'shortEn'   => 'Free delivery in Oman over 15 OMR',
			'shortAr'   => 'توصيل مجاني داخل عُمان فوق 15 ريال عماني',
		),
		'SA'  => array(
			'en'        => 'Free delivery to Saudi Arabia on orders over 144 SAR',
			'ar'        => 'توصيل مجاني إلى المملكة العربية السعودية للطلبات التي تزيد على 144 ريال سعودي',
			'threshold' => 144,
			'currency'  => 'SAR',
			'label'     => 'Saudi Arabia',
			'labelAr'   => 'السعودية',
			'shortEn'   => 'Free delivery to Saudi Arabia over 144 SAR',
			'shortAr'   => 'توصيل مجاني إلى السعودية فوق 144 ريال سعودي',
		),
		'QA'  => array(
			'en'        => 'Free delivery to Qatar on orders over 144 QAR',
			'ar'        => 'توصيل مجاني إلى قطر للطلبات التي تزيد على 144 ريال قطري',
			'threshold' => 144,
			'currency'  => 'QAR',
			'label'     => 'Qatar',
			'labelAr'   => 'قطر',
			'shortEn'   => 'Free delivery to Qatar over 144 QAR',
			'shortAr'   => 'توصيل مجاني إلى قطر فوق 144 ريال قطري',
		),
		'AE'  => array(
			'en'        => 'Free delivery to the UAE on orders over 144 AED',
			'ar'        => 'توصيل مجاني إلى الإمارات للطلبات التي تزيد على 144 درهم إماراتي',
			'threshold' => 144,
			'currency'  => 'AED',
			'label'     => 'United Arab Emirates',
			'labelAr'   => 'الإمارات',
			'shortEn'   => 'Free delivery to the UAE over 144 AED',
			'shortAr'   => 'توصيل مجاني إلى الإمارات فوق 144 درهم إماراتي',
		),
		'KW'  => array(
			'en'        => 'Free delivery to Kuwait on orders over 12 KWD',
			'ar'        => 'توصيل مجاني إلى الكويت للطلبات التي تزيد على 12 دينار كويتي',
			'threshold' => 12,
			'currency'  => 'KWD',
			'label'     => 'Kuwait',
			'labelAr'   => 'الكويت',
			'shortEn'   => 'Free delivery to Kuwait over 12 KWD',
			'shortAr'   => 'توصيل مجاني إلى الكويت فوق 12 دينار كويتي',
		),
		'GCC' => array(
			'en'        => 'GCC delivery options are shown at checkout',
			'ar'        => 'تظهر خيارات التوصيل إلى دول الخليج عند إتمام الطلب',
			'threshold' => null,
			'currency'  => '',
			'label'     => 'GCC',
			'labelAr'   => 'الخليج',
			'shortEn'   => 'GCC delivery options shown at checkout',
			'shortAr'   => 'خيارات التوصيل الخليجي عند الدفع',
		),
	);

	$message_key = isset( $messages[ $market ] ) ? $market : 'GCC';
	$message     = $messages[ $message_key ];

	return array(
		'country'           => $country,
		'market'            => $message_key,
		'marketLabel'       => $is_arabic && isset( $message['labelAr'] ) ? $message['labelAr'] : $message['label'],
		'marketLabelEn'     => $message['label'],
		'marketLabelAr'     => isset( $message['labelAr'] ) ? $message['labelAr'] : $message['label'],
		'deliveryShort'     => $is_arabic ? $message['shortAr'] : $message['shortEn'],
		'deliveryShortEn'   => $message['shortEn'],
		'deliveryShortAr'   => $message['shortAr'],
		'currency'          => $currency,
		'currencySymbol'    => function_exists( 'get_woocommerce_currency_symbol' ) ? qil_clean_text( get_woocommerce_currency_symbol( $currency ) ) : $currency,
		'deliveryMessage'   => $is_arabic ? $message['ar'] : $message['en'],
		'deliveryMessageEn' => $message['en'],
		'deliveryMessageAr' => $message['ar'],
		'freeDeliveryThreshold' => $message['threshold'],
		'freeDeliveryCurrency'  => $message['currency'],
	);
}

/**
 * Detect an Elementor/WoodMart page that contains the reusable lab shortcode.
 */
function qil_has_shortcode() {
	global $post;

	return qil_experience_enabled()
		&& is_a( $post, 'WP_Post' )
		&& has_shortcode( (string) $post->post_content, 'qimia_intelligence_lab' );
}

/**
 * Load the translator's self-hosted Inter and Tajawal stylesheet everywhere
 * the Qimia interface renders.
 *
 * The translator may register its canonical handle without enqueueing it on a
 * no-translate shell. In that case enqueue the registered handle; when no
 * handle exists yet, use the same local stylesheet URL under a Qimia fallback
 * handle. Returning the active handle lets the lab stylesheet depend on it and
 * keeps the ordering deterministic through CSS optimisers.
 *
 * @return string Enqueued stylesheet handle, or an empty string if unavailable.
 */
function qil_enqueue_local_fonts() {
	if ( wp_style_is( 'qaatm-fonts', 'enqueued' ) ) {
		return 'qaatm-fonts';
	}
	if ( wp_style_is( 'qil-local-fonts', 'enqueued' ) ) {
		return 'qil-local-fonts';
	}
	if ( wp_style_is( 'qaatm-fonts', 'registered' ) ) {
		wp_enqueue_style( 'qaatm-fonts' );
		return 'qaatm-fonts';
	}
	if ( ! class_exists( 'QAATM_Router' ) || ! method_exists( 'QAATM_Router', 'font_stylesheet_url' ) ) {
		return '';
	}

	$font_url = (string) QAATM_Router::font_stylesheet_url( true );
	if ( '' === $font_url ) {
		return '';
	}
	if ( ! wp_style_is( 'qil-local-fonts', 'registered' ) ) {
		wp_register_style( 'qil-local-fonts', $font_url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The translator versions this local URL.
	}
	wp_enqueue_style( 'qil-local-fonts' );
	return 'qil-local-fonts';
}

/**
 * Resolve the production asset without hiding readable source files in debug.
 *
 * The minified files are generated from the same release sources and avoid
 * shipping comments and development whitespace to visitors. A missing release
 * file fails safely back to its readable counterpart.
 *
 * @param string $extension Supported asset extension.
 * @return string Relative asset path.
 */
function qil_frontend_asset_file( $extension ) {
	$extension = strtolower( (string) $extension );
	if ( ! in_array( $extension, array( 'css', 'js' ), true ) ) {
		return '';
	}

	$source = 'assets/qil.' . $extension;
	$minified = 'assets/qil.min.' . $extension;
	$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

	return ! $is_debug && is_readable( QIL_DIR . $minified ) ? $minified : $source;
}

/**
 * Return the two homepage proof totals without repeating their database work.
 *
 * WooCommerce's product cache version invalidates the key when products change;
 * the short TTL also keeps a standalone brand-term edit from staying stale.
 *
 * @param int $catalogue_fallback Indexed-product fallback.
 * @return array{products:int,brands:int}
 */
function qil_catalogue_totals( $catalogue_fallback = 0 ) {
	$product_version = class_exists( 'WC_Cache_Helper' ) ? WC_Cache_Helper::get_transient_version( 'product' ) : '0';
	$cache_key       = 'qil_totals_v1_' . md5( QIL_SCHEMA_VERSION . '|' . (string) $product_version );
	$cached          = get_transient( $cache_key );
	if ( is_array( $cached ) && isset( $cached['products'], $cached['brands'] ) ) {
		return array(
			'products' => max( 0, (int) $cached['products'] ),
			'brands'   => max( 0, (int) $cached['brands'] ),
		);
	}

	$product_count = max( 0, (int) $catalogue_fallback );
	if ( function_exists( 'wc_get_products' ) ) {
		$count_query = wc_get_products(
			array(
				'limit'      => 1,
				'page'       => 1,
				'paginate'   => true,
				'status'     => 'publish',
				'visibility' => 'visible',
				'return'     => 'ids',
			)
		);
		if ( is_object( $count_query ) && isset( $count_query->total ) ) {
			$product_count = max( 0, (int) $count_query->total );
		}
	}

	$brand_count = taxonomy_exists( 'product_brand' ) ? wp_count_terms( array( 'taxonomy' => 'product_brand', 'hide_empty' => true ) ) : 0;
	$totals      = array(
		'products' => $product_count,
		'brands'   => is_wp_error( $brand_count ) ? 0 : max( 0, (int) $brand_count ),
	);
	set_transient( $cache_key, $totals, 15 * MINUTE_IN_SECONDS );

	return $totals;
}

/**
 * Load scoped assets and expose only the product data needed by the interface.
 */
function qil_enqueue_assets( $force = false ) {
	static $did_configure = false;

	$render_mode = qil_render_mode();
	if ( ! $force && 'none' === $render_mode && ! qil_has_shortcode() ) {
		return;
	}
	// Off the homepage only the navigation is on the page, and the header's
	// search runs through the REST endpoint. Shipping the whole catalogue with
	// every product and category view would cost far more than it could serve.
	$product_page = qil_is_product_view() && ! $force && ! qil_has_shortcode();
	$chrome_only  = ( 'chrome' === $render_mode ) && ! $product_page && ! $force && ! qil_has_shortcode();

	// Search/index and link-preview crawlers receive the same server-rendered
	// HTML, but never shopper-only JavaScript. This preserves SEO/indexable
	// content while preventing personalization, cart and navigation AJAX.
	$noninteractive_bot = ( function_exists( 'qil_perf_noninteractive_bot' ) && qil_perf_noninteractive_bot() ) || ( function_exists( 'qil_perf_crawler_family' ) && '' !== qil_perf_crawler_family() );

	$css_asset = qil_frontend_asset_file( 'css' );
	$js_asset  = qil_frontend_asset_file( 'js' );
	$css_path = QIL_DIR . $css_asset;
	$js_path  = QIL_DIR . $js_asset;
	$css_version = defined( 'WP_DEBUG' ) && WP_DEBUG && is_readable( $css_path ) ? (string) filemtime( $css_path ) : QIL_VERSION;
	$js_version  = defined( 'WP_DEBUG' ) && WP_DEBUG && is_readable( $js_path ) ? (string) filemtime( $js_path ) : QIL_VERSION;
	$script_dependencies  = array();
	if ( $noninteractive_bot ) {
		$font_handle        = qil_enqueue_local_fonts();
		$style_dependencies = '' !== $font_handle ? array( $font_handle ) : array();
		wp_enqueue_style( 'qimia-intelligence-lab', QIL_URL . $css_asset, $style_dependencies, $css_version );
		return;
	}
	// Core WooCommerce owns add-to-cart and fragments. The visible drawer is the
	// theme's own WoodMart mini-cart. The dedicated homepage bypasses WoodMart's
	// header builder, so ask the installed theme to enqueue the same modules it
	// uses on a normal storefront page before resolving QIL's dependencies.
	$is_cart_page = function_exists( 'is_cart' ) && is_cart();
	if ( function_exists( 'woodmart_enqueue_js_script' ) ) {
		foreach ( array( 'cart-widget', 'action-after-add-to-cart', 'on-remove-from-cart' ) as $woodmart_cart_module ) {
			// The full cart updates inline. Do not force the theme's drawer/popup
			// success action onto a page where its sidebar may not be rendered.
			if ( $is_cart_page && 'action-after-add-to-cart' === $woodmart_cart_module ) {
				continue;
			}
			woodmart_enqueue_js_script( $woodmart_cart_module );
		}
	}
	// Never register another helpers.min.js alias; the theme's public module
	// loader above owns the correct paths and dependency graph for this version.
	foreach ( array( 'wc-add-to-cart', 'wc-cart-fragments' ) as $script_handle ) {
		if ( wp_script_is( $script_handle, 'registered' ) ) {
			wp_enqueue_script( $script_handle );
			$script_dependencies[] = $script_handle;
		}
	}
	// A dedicated homepage still renders a real mini-cart. Performance filters
	// for non-shop pages can suppress its Woo configuration while leaving the
	// script in place. Preserve Woo's own data only on pages using this drawer;
	// never replace valid configuration or invent session/cache keys.
	$cart_script_data = null;
	add_filter( 'woocommerce_get_script_data', static function ( $data, $handle ) use ( &$cart_script_data ) {
		if ( 'wc-cart-fragments' === $handle && is_array( $data ) && ! empty( $data['wc_ajax_url'] ) ) {
			$cart_script_data = $data;
		}
		return $data;
	}, -9999, 2 );
	add_filter( 'woocommerce_get_script_data', static function ( $data, $handle ) use ( &$cart_script_data ) {
		if ( 'wc-cart-fragments' === $handle && empty( $data ) && is_array( $cart_script_data ) ) {
			return $cart_script_data;
		}
		return $data;
	}, PHP_INT_MAX, 2 );
	$search_scripts_ready = false;
	$cart_scripts_ready   = false;
	foreach ( array( 'wd-cart-widget', 'wd-action-after-add-to-cart', 'wd-on-remove-from-cart' ) as $script_handle ) {
		if ( $is_cart_page && 'wd-action-after-add-to-cart' === $script_handle ) {
			continue;
		}
		if ( ! wp_script_is( $script_handle, 'registered' ) ) {
			continue;
		}
		wp_enqueue_script( $script_handle );
		$script_dependencies[] = $script_handle;
		if ( 'wd-cart-widget' === $script_handle ) {
			$cart_scripts_ready = true;
		}
	}
	$cart_scripts_ready = $cart_scripts_ready && function_exists( 'woodmart_cart_side_widget' );
	$font_handle        = qil_enqueue_local_fonts();
	$style_dependencies = '' !== $font_handle ? array( $font_handle ) : array();
	foreach ( array( 'wd-wd-search-form', 'wd-wd-search-results', 'wd-wd-search-dropdown', 'wd-opt-search-history', 'wd-header-cart-side', 'wd-header-cart', 'wd-widget-shopping-cart', 'wd-widget-product-list' ) as $style_handle ) {
		if ( wp_style_is( $style_handle, 'registered' ) ) {
			$style_dependencies[] = $style_handle;
		}
	}

	wp_enqueue_style( 'qimia-intelligence-lab', QIL_URL . $css_asset, $style_dependencies, $css_version );
	wp_enqueue_script( 'qimia-intelligence-lab', QIL_URL . $js_asset, $script_dependencies, $js_version, true );
	if ( $did_configure ) {
		return;
	}
	$did_configure = true;

	$cart_count = 0;
	if ( function_exists( 'WC' ) && WC()->cart ) {
		$cart_count = (int) WC()->cart->get_cart_contents_count();
	}

	$language  = qil_language_context();
	$market    = qil_market_context( $language['isArabic'] );
	if ( $chrome_only ) {
		$initial_payload = array( 'products' => array(), 'collections' => array() );
	} elseif ( $product_page ) {
		// The product page needs exactly two things in the payload: the product
		// itself, so the variation engine and the price block can read it, and
		// the rail beside it. Not the whole catalogue.
		$pdp          = qil_current_product_payload();
		$pdp_record   = isset( $pdp['product'] ) ? $pdp['product'] : null;
		$pdp_related  = isset( $pdp['related'] ) && is_array( $pdp['related'] ) ? $pdp['related'] : array();
		$pdp_products = $pdp_record ? array_merge( array( $pdp_record ), $pdp_related ) : $pdp_related;
		$initial_payload = array(
			'products'    => $pdp_products,
			'collections' => array( 'related' => wp_list_pluck( $pdp_related, 'id' ) ),
		);
	} else {
		$initial_payload = qil_initial_catalogue_payload();
	}
	$catalogue       = $initial_payload['products'];
	// Reuse the home page index for the additional public inventory shelf.
	if ( 'full' === $render_mode && function_exists( 'qil_home_index' ) ) qil_home_index( $catalogue );
	$collections     = $initial_payload['collections'];
	$catalogue_count = count( $catalogue );
	$brand_count = 0;
	// The catalogue and brand totals are only printed by the homepage's proof
	// row, so they are not counted on a chrome-only page.
	if ( ! $chrome_only && ! $product_page ) {
		$totals          = qil_catalogue_totals( $catalogue_count );
		$catalogue_count = $totals['products'];
		$brand_count     = $totals['brands'];
	}
	$wc_ajax_url = class_exists( 'WC_AJAX' )
		? WC_AJAX::get_endpoint( '%%endpoint%%' )
		: add_query_arg( 'wc-ajax', '%%endpoint%%', home_url( '/' ) );

	$config = array(
		'version'        => QIL_VERSION,
        'repeatPurchaseEnabled' => true,
        'personalizationEnabled' => QIL_Personalization::enabled(),
		'schemaVersion'  => QIL_SCHEMA_VERSION,
		'chromeOnly'     => (bool) $chrome_only,
		'homeUrl'        => qil_view_context()['homeUrl'],
		'isHome'         => 'full' === $render_mode,
        'accountUrl' => function_exists('wc_get_page_permalink') ? qil_localized_url(wc_get_page_permalink('myaccount'), $language['isArabic']) : '',
		'heroScrollMotion' => false,
		'currencySwitchEnabled' => class_exists('Qimia_Unlimited_Geo_Currency') && count(qil_storefront_currency_codes()) > 1,
		'currencyCodes' => qil_storefront_currency_codes(),
		'goalUrl'        => esc_url_raw( trailingslashit( rest_url( 'qimia-lab/v1/goals' ) ) ),
		'restNonce'      => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'goalPolicy'     => qil_goal_policy(),
		'catalogueVersion' => qil_goal_catalogue_version(),
		'productId'      => $product_page && ! empty( $initial_payload['products'][0]['id'] ) ? (int) $initial_payload['products'][0]['id'] : 0,
		'products'       => $catalogue,
		'collections'    => $collections,
		'catalogueReady' => function_exists( 'wc_get_products' ),
		'catalogueCount' => $catalogue_count,
		'indexedProductCount' => count( $catalogue ),
		'brandCount'     => $brand_count,
		'shopUrl'        => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
		'categoryUrls'   => function_exists( 'qil_view_context' ) ? qil_view_context()['categoryUrls'] : array(),
		'cartUrl'        => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
		'supportUrl'     => apply_filters( 'qil_support_url', home_url( '/contact-us/' ) ),
		'searchUrl'      => esc_url_raw( add_query_arg( array( 'qil_schema' => QIL_SCHEMA_VERSION, 'qil_locale' => $language['locale'] ), rest_url( 'qimia-lab/v1/search' ) ) ),
		// Live search starts at this many characters; shorter input uses the page index.
		'searchMinChars' => max( 2, min( 4, (int) apply_filters( 'qil_search_min_chars', 3 ) ) ),
		'quickViewUrl'   => esc_url_raw( add_query_arg( 'qil_locale', $language['locale'], trailingslashit( rest_url( 'qimia-lab/v1/quick-view' ) ) ) ),
		'wcAjaxUrl'      => esc_url_raw( $wc_ajax_url ),
		'priceDecimals'  => function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2,
		'woodmartSearch' => $search_scripts_ready,
		'woodmartCart'   => $cart_scripts_ready,
		'isCart'         => $is_cart_page,
		'cartCount'      => $cart_count,
		'cartEnabled'    => true,
		'checkoutEnabled' => false,
		'locale'         => $language['locale'],
		'siteLocale'     => $language['siteLocale'],
		'isArabic'       => $language['isArabic'],
		'direction'      => $language['direction'],
		'languageUrls'   => $language['languageUrls'],
		'languageUrl'    => $language['languageUrls'][ $language['isArabic'] ? 'en' : 'ar' ],
		'country'        => $market['country'],
		'market'         => $market['market'],
		'marketLabel'    => $market['marketLabel'],
		'currency'       => $market['currency'],
		'currencySymbol' => $market['currencySymbol'],
		'deliveryMessage' => $market['deliveryMessage'],
		'deliveryMessageEn' => $market['deliveryMessageEn'],
		'deliveryMessageAr' => $market['deliveryMessageAr'],
		'deliveryShort'  => $market['deliveryShort'],
		'deliveryShortEn' => $market['deliveryShortEn'],
		'deliveryShortAr' => $market['deliveryShortAr'],
		'freeDeliveryThreshold' => $market['freeDeliveryThreshold'],
		'freeDeliveryCurrency' => $market['freeDeliveryCurrency'],
		'isPreview'      => false,
	);

	wp_add_inline_script(
		'qimia-intelligence-lab',
		'window.QIMIA_LAB = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'qil_enqueue_assets', 20 );

/**
 * Discover the self-hosted font files the lab actually paints with.
 *
 * Both faces are already on this server: the Qimia AI Translator self-hosts
 * Tajawal and Inter in its own assets/fonts/qaatm-fonts.css with
 * font-display:swap. Nothing new is downloaded here — the stylesheet is parsed
 * once, the URLs for the weights the lab uses are cached, and they are
 * preloaded so the first paint is in the right face instead of swapping into it
 * a moment later. Arabic gets Tajawal, English gets Inter, and the English
 * words inside an Arabic page get the same Inter file the English page uses.
 *
 * @return array<string,string> Absolute woff2 URLs, keyed family-weight.
 */
function qil_local_font_files() {
	$cache_key = 'qil_font_files_v2';
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && $cached ) {
		return $cached;
	}

	$files = array();
	$css   = '';
	$url   = '';
	if ( class_exists( 'QAATM_Router' ) && method_exists( 'QAATM_Router', 'font_stylesheet_url' ) ) {
		$url = (string) QAATM_Router::font_stylesheet_url( true );
	}
	if ( '' !== $url ) {
		$relative = wp_parse_url( $url, PHP_URL_PATH );
		$path     = $relative ? untrailingslashit( ABSPATH ) . $relative : '';
		if ( $path && is_readable( $path ) ) {
			$css = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local stylesheet on the same install.
		}
	}

	if ( '' !== $css ) {
		$base = trailingslashit( dirname( $url ) );
		// Keep only the weights the lab paints with. Google-font stylesheets list
		// several unicode subsets per weight, so select the Arabic core for
		// Tajawal and Latin core for Inter rather than blindly keeping the first
		// (usually Cyrillic-ext) block.
		$wanted = array( 'Tajawal' => array( '500', '700', '800' ), 'Inter' => array( '600', '700', '800' ) );
		$core_ranges = array(
			'Tajawal' => '/unicode-range\s*:[^;}]*U\+0600-06FF/i',
			'Inter'   => '/unicode-range\s*:[^;}]*U\+0000-00FF/i',
		);
		if ( preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $blocks ) ) {
			foreach ( $blocks[1] as $block ) {
				if ( ! preg_match( '/font-family:\s*[\'"]?([A-Za-z0-9 ]+)/i', $block, $family_match ) ) {
					continue;
				}
				$family = trim( $family_match[1] );
				if ( ! isset( $wanted[ $family ] ) ) {
					continue;
				}
				if ( ! preg_match( '/font-weight:\s*(\d+)/i', $block, $weight_match ) ) {
					continue;
				}
				$weight = $weight_match[1];
				if ( ! in_array( $weight, $wanted[ $family ], true ) ) {
					continue;
				}
				if ( preg_match( '/unicode-range\s*:/i', $block ) && ! preg_match( $core_ranges[ $family ], $block ) ) {
					continue;
				}
				$key = $family . '-' . $weight;
				if ( isset( $files[ $key ] ) ) {
					continue;
				}
				if ( ! preg_match( '#url\(\s*[\'"]?([^\'")]+\.woff2)#i', $block, $src_match ) ) {
					continue;
				}
				$src = trim( $src_match[1] );
				if ( preg_match( '#^https?://#i', $src ) ) {
					$files[ $key ] = $src;
				} elseif ( 0 === strpos( $src, '//' ) ) {
					$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );
					$files[ $key ] = ( $scheme ? $scheme : 'https' ) . ':' . $src;
				} elseif ( 0 === strpos( $src, '/' ) ) {
					$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );
					$host   = (string) wp_parse_url( $url, PHP_URL_HOST );
					$port   = wp_parse_url( $url, PHP_URL_PORT );
					if ( '' !== $scheme && '' !== $host ) {
						$origin = $scheme . '://' . $host . ( $port ? ':' . (int) $port : '' );
						$files[ $key ] = $origin . $src;
					} else {
						$files[ $key ] = home_url( $src );
					}
				} else {
					$files[ $key ] = $base . ltrim( $src, './' );
				}
			}
		}
	}

	if ( $files ) {
		set_transient( $cache_key, $files, DAY_IN_SECONDS );
	}
	return $files;
}

/**
 * Give the browser the responsive hero art before it reaches the stage markup.
 * The stylesheet stays on WordPress's normal enqueue path so cache/optimization
 * plugins can rewrite one canonical URL without leaving a duplicate preload.
 */
function qil_preload_critical_assets() {
	if ( ! qil_should_render() ) {
		return;
	}

	$hero_desktop = QIL_URL . 'assets/qimia-digital-world-v14-1672.webp';
	$hero_mobile  = QIL_URL . 'assets/qimia-digital-world-v14-960.webp';

	printf(
		'<link rel="preload" as="image" type="image/webp" href="%1$s" media="(max-width: 980px)" fetchpriority="high">' . "\n"
		. '<link rel="preload" as="image" type="image/webp" href="%2$s" media="(min-width: 981px)" fetchpriority="high">' . "\n",
		esc_url( $hero_mobile ),
		esc_url( $hero_desktop )
	);

}

/**
 * Preload the small set of core faces this route paints with. Duplicate URLs
 * from variable faces are collapsed before any tags are printed.
 */
function qil_preload_fonts() {
	if ( 'none' === qil_render_mode() && ! qil_has_shortcode() ) {
		return;
	}

	$files = qil_local_font_files();
	if ( ! $files ) {
		return;
	}
	$is_arabic = qil_language_context()['isArabic'];
	// Body copy sits at 500/600 while the navigation, hero and commerce labels
	// use the real 800 faces. Keep the number of requested files small; variable
	// faces that share one URL are emitted only once below.
	$wanted    = $is_arabic ? array( 'Tajawal-500', 'Tajawal-800', 'Inter-800' ) : array( 'Inter-600', 'Inter-800' );
	$preloaded = array();
	foreach ( $wanted as $key ) {
		if ( empty( $files[ $key ] ) ) {
			continue;
		}
		$url = $files[ $key ];
		if ( isset( $preloaded[ $url ] ) ) {
			continue;
		}
		$preloaded[ $url ] = true;
		printf(
			'<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
			esc_url( $url )
		);
	}
}
add_action( 'wp_head', 'qil_preload_fonts', 2 );
add_action( 'wp_head', 'qil_preload_critical_assets', 1 );

/**
 * Remove only audited assets that the standalone homepage template does not
 * render. Core WooCommerce, Qimia AI, currency, language and menu stay intact.
 */
function qil_dequeue_dedicated_template_assets() {
	if ( ! qil_should_render() ) {
		return;
	}

	/*
	 * The lab paints in two faces, both already self-hosted on this server by
	 * the translator: Tajawal and Inter. Measured on staging, the page was still
	 * fetching four render-blocking stylesheets from fonts.googleapis.com — DM
	 * Sans, Anek Telugu, Istok Web and a second Inter — queued by the theme and
	 * Elementor for a layout this document does not render. Each is a DNS
	 * lookup, a TLS handshake and a round trip before first paint, for faces
	 * nothing here draws with.
	 * Only this template's own document is affected; every theme page keeps its
	 * fonts exactly as the theme queued them.
	 */
	foreach ( wp_styles()->queue as $handle ) {
		$src = isset( wp_styles()->registered[ $handle ] ) ? (string) wp_styles()->registered[ $handle ]->src : '';
		if ( '' === $src || false === strpos( $src, 'fonts.googleapis.com' ) ) {
			continue;
		}
		wp_dequeue_style( $handle );
	}

	// Recheck at the final enqueue priority in case the translator registered its
	// canonical handle after the lab prepared its own dependency list.
	qil_enqueue_local_fonts();

	// A second helpers.min.js execution resets window.woodmartThemeModule and
	// erases functions registered by earlier modules. Canonicalise any legacy or
	// third-party alias to WoodMart's public handle before WordPress resolves the
	// footer dependency graph, then dequeue the aliases.
	$wp_scripts       = wp_scripts();
	$canonical_helper = 'woodmart-theme';
	$helper_aliases   = array( 'qil-woodmart-helpers' );
	if ( $wp_scripts && isset( $wp_scripts->registered[ $canonical_helper ] ) ) {
		foreach ( $wp_scripts->registered as $registered_handle => $registered_script ) {
			if ( $canonical_helper === $registered_handle ) {
				continue;
			}
			$registered_src = isset( $registered_script->src ) ? (string) $registered_script->src : '';
			if ( false !== strpos( $registered_src, 'js/scripts/global/helpers.min.js' ) ) {
				$helper_aliases[] = (string) $registered_handle;
			}
		}
		$helper_aliases = array_values( array_unique( $helper_aliases ) );
		foreach ( $wp_scripts->registered as $registered_handle => $registered_script ) {
			$dependencies = array();
			foreach ( (array) $registered_script->deps as $dependency ) {
				$dependency = in_array( $dependency, $helper_aliases, true ) ? $canonical_helper : $dependency;
				if ( $dependency !== $registered_handle ) {
					$dependencies[] = $dependency;
				}
			}
			$registered_script->deps = array_values( array_unique( $dependencies ) );
		}
	}
	foreach ( array_values( array_unique( $helper_aliases ) ) as $helper_alias ) {
		wp_dequeue_script( $helper_alias );
	}

	$scripts = array(
		'qil-woodmart-helpers',
		'wd-before-search-content',
		'wd-autocomplete-library',
		'wd-ajax-search',
		'wd-clear-search',
		'wd-search-history',
		'elementor-vendors-redux',
		'elementor-web-cli',
		'elementor-pro-notes',
		'elementor-pro-notes-app-initiator',
		'elementor-common-modules',
		'elementor-dialog',
		'elementor-dev-tools',
		'elementor-common',
		'elementor-app-loader',
		'elementor-webpack-runtime',
		'elementor-frontend-modules',
		'elementor-frontend',
		'smartmenus',
		'swiper',
		'elementor-pro-webpack-runtime',
		'elementor-pro-frontend',
		'pro-elements-handlers',
		'fluentform-elementor',
		'pDate',
		'pDatepicker',
		'pDatepickerLoader',
		'yayrev-time-localizer',
		'woo-variation-swatches',
		'sourcebuster-js',
		'wc-order-attribution',
		'google_gtagjs',
		'googlesitekit-events-provider-content-events',
		'googlesitekit-events-provider-woocommerce',
		'googlesitekit-events-provider-wpforms',
	);
	foreach ( $scripts as $handle ) {
		wp_dequeue_script( $handle );
	}

	$styles = array(
		'elementor-frontend',
		'elementor-pro',
		'elementor-common',
		'e-theme-ui-light',
		'elementor-icons',
		'elementor-icons-shared-0',
		'elementor-icons-qimia-font',
		'elementor-gf-anektelugu',
		'elementor-gf-istokweb',
		'elementor-gf-inter',
		'elementor-post-7',
		'elementor-post-91',
		'elementor-post-246',
		'widget-image',
		'widget-icon-box',
		'widget-heading',
		'widget-nav-menu',
		'widget-social-icons',
		'e-apple-webkit',
		'widget-nested-carousel',
		'widget-divider',
		'widget-image-box',
		'widget-testimonial-carousel',
		'widget-carousel-module-base',
		'wd-helpers-wpb-elem',
		'wd-elementor-base',
		'wd-elementor-pro-base',
		'swiper',
		'e-swiper',
		'fluentform-elementor',
		'pDate',
		'pDatepicker',
		'woo-variation-swatches',
		'woo-variation-swatches-pro',
		'yayextra',
	);
	foreach ( $styles as $handle ) {
		wp_dequeue_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'qil_dequeue_dedicated_template_assets', PHP_INT_MAX );

/**
 * Reusable, lightweight catalogue mount for Elementor or WoodMart content.
 * The full storefront homepage continues to use its dedicated template.
 */
function qil_shortcode() {
	if ( ! qil_experience_enabled() ) {
		return '';
	}

	qil_enqueue_assets( true );
	$language = qil_language_context();
	$goals    = array(
		'muscle'   => array( 'Build muscle', 'بناء العضلات' ),
		'fat-loss' => array( 'Fat loss', 'إدارة الدهون' ),
		'performance' => array( 'Performance', 'الأداء' ),
		'energy'   => array( 'Energy & focus', 'الطاقة والتركيز' ),
		'recovery' => array( 'Recovery', 'التعافي' ),
		'sleep'    => array( 'Sleep & calm', 'النوم والهدوء' ),
		'wellness' => array( 'Daily wellness', 'العافية اليومية' ),
		'beauty'   => array( 'Hair, skin & beauty', 'الشعر والبشرة والجمال' ),
	);

	ob_start();
	?>
	<div id="qimia-lab" class="qil-shell qil-shortcode" dir="<?php echo esc_attr( $language['direction'] ); ?>" data-qil-locale="<?php echo esc_attr( $language['locale'] ); ?>">
		<svg class="qil-sprite" aria-hidden="true">
			<symbol id="qil-i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
			<symbol id="qil-i-spark" viewBox="0 0 24 24"><path d="M12 2l1.6 5.1L19 9l-5.4 1.9L12 16l-1.6-5.1L5 9l5.4-1.9L12 2Z"/></symbol>
			<symbol id="qil-i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
			<symbol id="qil-i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
			<symbol id="qil-i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
		</svg>
		<?php echo qil_section_buy_again(); // Public empty shell, hydrated privately. ?>
        <section id="qil-match" class="qil-section qil-match-section">
			<div class="qil-container">
				<div class="qil-section-heading">
					<span class="qil-kicker" data-i18n="startOutcome">START WITH YOUR OUTCOME</span>
					<h2 data-i18n="whatGoal">What would you like to support?</h2>
				</div>
				<div class="qil-goal-grid" role="radiogroup" aria-label="Choose a shopping goal">
					<?php foreach ( $goals as $goal_key => $goal_labels ) : ?>
						<button class="qil-goal<?php echo 'muscle' === $goal_key ? ' is-active' : ''; ?>" type="button" data-goal="<?php echo esc_attr( $goal_key ); ?>" role="radio" aria-checked="<?php echo 'muscle' === $goal_key ? 'true' : 'false'; ?>">
							<span><strong><?php echo esc_html( $language['isArabic'] ? $goal_labels[1] : $goal_labels[0] ); ?></strong></span><span class="qil-select-dot"></span>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="qil-product-grid" data-qil-results aria-live="polite"></div>
				<div class="qil-empty" data-qil-empty hidden><strong data-i18n="noMatches">No verified close match yet</strong></div>
				<div class="qil-results-footer"><button class="qil-text-button" type="button" data-qil-show-more><span data-i18n="showMore">Show more products</span><svg><use href="#qil-i-arrow"/></svg></button></div>
			</div>
		</section>
		<div class="qil-compare-dock" data-qil-compare-dock hidden><div><span data-i18n="compareProducts">Compare products</span><div data-qil-compare-thumbs></div></div><button class="qil-button qil-button-primary qil-button-small" type="button" data-qil-open-compare><span data-i18n="compareNow">Compare now</span><span data-qil-compare-count>0/2</span></button></div>
		<div class="qil-modal" data-qil-compare-modal hidden aria-hidden="true"><div class="qil-modal-backdrop" data-qil-modal-close></div><div class="qil-modal-panel qil-compare-panel" role="dialog" aria-modal="true"><button class="qil-modal-close" type="button" data-qil-modal-close aria-label="Close"><svg><use href="#qil-i-close"/></svg></button><h2 data-i18n="sideBySide">Your shortlist, side by side.</h2><div data-qil-compare-table></div></div></div>
		<div class="qil-toast" data-qil-toast role="status" aria-live="polite" hidden></div>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'qimia_intelligence_lab', 'qil_shortcode' );

/**
 * The WordPress admin bar stays. It was hidden on the lab homepage so the
 * concept could be judged as a visitor sees it, but an administrator working on
 * the storefront needs the bar to get anywhere, and the fixed chrome is
 * offset below it rather than under it. Filter it off if a clean screenshot is
 * wanted.
 */
function qil_hide_frontend_admin_bar( $show ) {
	return (bool) apply_filters( 'qil_show_admin_bar', $show );
}
add_filter( 'show_admin_bar', 'qil_hide_frontend_admin_bar', PHP_INT_MAX );

/**
 * Replace only the front-page template, and only when the experience is enabled.
 */
function qil_template_include( $template ) {
	if ( qil_should_render() ) {
		$lab_template = QIL_DIR . 'templates/home.php';
		if ( is_readable( $lab_template ) ) {
			return $lab_template;
		}
	}

	// The product page deliberately does NOT take the template over; see
	// qil_is_product_view() for what that cost on this install.
	return $template;
}
add_filter( 'template_include', 'qil_template_include', 99 );

require_once QIL_DIR . 'includes/class-qil-admin.php';
QIL_Admin::init();
