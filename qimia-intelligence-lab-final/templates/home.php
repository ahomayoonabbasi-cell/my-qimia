<?php
/**
 * Qimia Intelligence Lab staging homepage.
 *
 * All markup lives in includes/sections.php so the storefront and the
 * Elementor widgets render from one source.
 *
 * @package Qimia_Intelligence_Lab
 */

defined( 'ABSPATH' ) || exit;

$qil_context = qil_view_context();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	// The sandbox must never be indexed; the live store must never be hidden.
	// This line used to be unconditional, which would have de-indexed the real
	// homepage the moment this template rendered on production.
	if ( qil_is_staging_sandbox() ) :
		?>
		<meta name="robots" content="noindex,nofollow,noarchive">
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'qil-body' ); ?>>
<?php wp_body_open(); ?>

<a class="qil-skip" href="#qil-main"><?php echo esc_html( $qil_context['isArabic'] ? 'انتقل إلى المحتوى الرئيسي' : 'Skip to main content' ); ?></a>

<div id="qimia-lab" class="qil-shell qaatm-no-translate notranslate<?php echo $qil_context['isArabic'] ? ' is-arabic' : ''; ?>" dir="<?php echo esc_attr( $qil_context['direction'] ); ?>" lang="<?php echo esc_attr( $qil_context['locale'] ); ?>" translate="no" data-qil-locale="<?php echo esc_attr( $qil_context['locale'] ); ?>" data-qaatm-no-rewrite data-no-translation>
	<?php
	echo qil_sprite(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted section markup.
	?>

	<div class="qil-ambient qil-ambient-one"></div>
	<div class="qil-ambient qil-ambient-two"></div>

	<?php
	echo qil_section_topbar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo qil_section_header(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>

	<main id="qil-main">
		<?php
		echo qil_section_hero(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo qil_section_category_rail(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo qil_section_goal_engine(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        // Keep the private shelf immediately before the public collections section.
        // It is an empty cache-safe shell until the account-bound response arrives.
        echo qil_section_buy_again(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo qil_section_collections(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		do_action('qil_home_personal_area'); // Optional My Qimia plugin; no direct class dependency.
		echo QIL_Cashback::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped public campaign view.
		echo qil_section_myqimia_explainer(); // Public explanation; no private account data.
		echo qil_section_brands(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo qil_section_ai(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo qil_section_facts(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo qil_section_compare(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo qil_section_method(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo qil_section_routine(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo qil_section_trust(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</main>

	<?php
	echo qil_section_footer(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo qil_section_docks(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</div>

<?php if ( function_exists( 'woodmart_cart_side_widget' ) || function_exists( 'woocommerce_mini_cart' ) ) : ?>
	<?php
	/*
	 * Use WoodMart's current renderer instead of copying its template. This keeps
	 * the homepage drawer aligned with the installed theme version, its settings
	 * and WooCommerce fragment lifecycle. The compact fallback is only for a
	 * setup where WooCommerce is present but the theme renderer is unavailable.
	 *
	 * This is a standalone document, so the theme footer does not print the
	 * shared close-side overlay. Print exactly one after the drawer.
	 */
	if ( function_exists( 'woodmart_cart_side_widget' ) ) {
		woodmart_cart_side_widget();
	} else {
		?>
		<div class="cart-widget-side wd-side-hidden wd-right" role="complementary" aria-label="<?php echo esc_attr__( 'Shopping cart sidebar', 'woodmart' ); ?>">
			<div class="wd-heading">
				<span class="title"><?php echo esc_html__( 'Shopping cart', 'woodmart' ); ?></span>
				<div class="close-side-widget wd-action-btn wd-style-text wd-cross-icon"><a href="#" rel="nofollow"><?php echo esc_html__( 'Close', 'woodmart' ); ?></a></div>
			</div>
			<div class="widget woocommerce widget_shopping_cart"><div class="widget_shopping_cart_content"><?php woocommerce_mini_cart(); ?></div></div>
		</div>
		<?php
	}
	?>
	<div class="wd-close-side wd-fill"></div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
