<?php
/**
 * Qimia Intelligence Lab — section renderers.
 *
 * Every homepage section lives here as a single function that accepts an
 * argument array and returns markup. templates/home.php composes them in
 * order; the Elementor widgets in includes/elementor.php call the exact same
 * functions with editor-supplied values, so the storefront and the builder can
 * never drift apart.
 *
 * @package Qimia_Intelligence_Lab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared, request-scoped context every section needs.
 */
function qil_view_context() {
	static $context = null;

	if ( null !== $context ) {
		return $context;
	}

	$language  = function_exists( 'qil_language_context' ) ? qil_language_context() : array( 'isArabic' => false );
	$is_arabic = ! empty( $language['isArabic'] );
	$market    = function_exists( 'qil_market_context' ) ? qil_market_context( $is_arabic ) : array();

	$logo_id = (int) get_theme_mod( 'custom_logo' );
	$logo    = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'qil-logo-image', 'alt' => get_bloginfo( 'name' ) ) ) : '';
	if ( '' === $logo ) {
		$logo = sprintf(
			'<img class="qil-logo-image" src="%1$s" width="480" height="257" alt="%2$s" decoding="async">',
			esc_url( QIL_URL . 'assets/qimia-logo.webp' ),
			esc_attr( 'Qimia Online' )
		);
	}

	$language_urls = isset( $language['languageUrls'] ) && is_array( $language['languageUrls'] ) ? $language['languageUrls'] : array();

	$category_url = static function ( $slugs ) use ( $is_arabic ) {
		foreach ( (array) $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				continue;
			}
			return function_exists( 'qil_localized_url' ) ? qil_localized_url( $url, $is_arabic ) : $url;
		}
		return home_url( $is_arabic ? '/ar/shop/' : '/shop/' );
	};

	$local_page = static function ( $slug ) use ( $is_arabic ) {
		return home_url( ( $is_arabic ? '/ar/' : '/' ) . trim( $slug, '/' ) . '/' );
	};

	$shop_url = $is_arabic
		? home_url( '/ar/shop/' )
		: ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ) );

	$currency = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'OMR';

	$context = array(
		'language'      => $language,
		'isArabic'      => $is_arabic,
		'direction'     => $is_arabic ? 'rtl' : 'ltr',
		'locale'        => $is_arabic ? 'ar' : 'en',
		'market'        => $market,
		'logo'          => $logo,
		'homeUrl'       => home_url( $is_arabic ? '/ar/' : '/' ),
		'shopUrl'       => $shop_url,
		'aboutUrl'      => home_url( $is_arabic ? '/ar/about/' : '/about/' ),
		'cartUrl'       => $is_arabic ? home_url( '/ar/cart/' ) : ( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ),
		'currency'      => $currency,
		'currencyMark'  => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency ) : $currency,
		'gulfCurrency'  => in_array( $currency, array( 'OMR', 'AED', 'SAR', 'QAR', 'KWD' ), true ) ? ' gulf-currency' : '',
		'languageEnUrl' => isset( $language_urls['en'] ) ? $language_urls['en'] : home_url( '/' ),
		'languageArUrl' => isset( $language_urls['ar'] ) ? $language_urls['ar'] : home_url( '/ar/' ),
		'proteinUrl'    => $category_url( array( 'proteins', 'protein' ) ),
		'creatineUrl'   => $category_url( array( 'creatines-oman', 'creatines', 'creatine' ) ),
		'fatBurnerUrl'  => $category_url( array( 'fat-burners', 'fat-burner' ) ),
		'massGainerUrl' => $category_url( array( 'mass-gainers', 'mass-gainer', 'weight-gainers', 'weight-gainer', 'gainers' ) ),
		'contactUrl'    => $local_page( 'contact' ),
		'blogUrl'       => $local_page( 'blog' ),
		'privacyUrl'    => $local_page( 'privacy' ),
		'returnsUrl'    => $local_page( 'refund_policy' ),
		'accountUrl'    => $local_page( 'my-account' ),
		'wishlistUrl'   => $local_page( 'wishlist' ),
	);

	$context['bestSellersUrl'] = add_query_arg( 'orderby', 'popularity', $context['shopUrl'] );
	// The routine builder shows category entry points, not single products, so it
	// needs the archive URL for each purpose the client index can resolve.
	$context['categoryUrls'] = array(
		'protein'     => $context['proteinUrl'],
		'creatine'    => $context['creatineUrl'],
		'mass_gainer' => $context['massGainerUrl'],
		'fat_burner'  => $context['fatBurnerUrl'],
	);

	return $context;
}

/**
 * Pick the English or Arabic variant of one editable string.
 */
function qil_text( $args, $key, $english, $arabic ) {
	$context = qil_view_context();
	$key_en  = $key . 'En';
	$key_ar  = $key . 'Ar';

	if ( $context['isArabic'] ) {
		$value = isset( $args[ $key_ar ] ) ? trim( (string) $args[ $key_ar ] ) : '';
		return '' !== $value ? $value : $arabic;
	}

	$value = isset( $args[ $key_en ] ) ? trim( (string) $args[ $key_en ] ) : '';
	return '' !== $value ? $value : $english;
}

/**
 * The single icon sprite every section draws from.
 */
function qil_sprite() {
	ob_start();
	?>
	<svg class="qil-sprite" aria-hidden="true">
		<symbol id="qil-i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
		<symbol id="qil-i-chevron" viewBox="0 0 24 24"><path d="m7 9 5 5 5-5"/></symbol>
		<symbol id="qil-i-spark" viewBox="0 0 24 24"><path d="M12 2l1.6 5.1L19 9l-5.4 1.9L12 16l-1.6-5.1L5 9l5.4-1.9L12 2Z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/></symbol>
		<symbol id="qil-i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 5 5"/></symbol>
		<symbol id="qil-i-bag" viewBox="0 0 24 24"><path d="M4.5 8.5h15l-1 12h-13l-1-12Z"/><path d="M9 9V6.5a3 3 0 0 1 6 0V9"/></symbol>
		<symbol id="qil-i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
		<symbol id="qil-i-muscle" viewBox="0 0 24 24"><path d="M7 11V8m10 3V8M4 10h3v5H4v-5Zm13 0h3v5h-3v-5Zm-10 2h10v2H7z"/></symbol>
		<symbol id="qil-i-energy" viewBox="0 0 24 24"><path d="m13.5 2-8 12h6l-1 8 8-12h-6l1-8Z"/></symbol>
		<symbol id="qil-i-recovery" viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 0-2.3 6"/><path d="M20 5v6h-6"/></symbol>
		<symbol id="qil-i-sleep" viewBox="0 0 24 24"><path d="M20 15.5A8.5 8.5 0 0 1 8.5 4 8.5 8.5 0 1 0 20 15.5Z"/></symbol>
		<symbol id="qil-i-wellness" viewBox="0 0 24 24"><path d="M12 21s-8-4.8-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.2-8 11-8 11Z"/><path d="M8 12h2l1-2 2 4 1-2h2"/></symbol>
		<symbol id="qil-i-beauty" viewBox="0 0 24 24"><path d="M12 2c.6 5.5 4.5 9.4 10 10-5.5.6-9.4 4.5-10 10-.6-5.5-4.5-9.4-10-10 5.5-.6 9.4-4.5 10-10Z"/></symbol>
		<symbol id="qil-i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
		<symbol id="qil-i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
		<symbol id="qil-i-compare" viewBox="0 0 24 24"><path d="M7 4v13M4 7l3-3 3 3M17 20V7M14 17l3 3 3-3"/></symbol>
		<symbol id="qil-i-cart-plus" viewBox="0 0 24 24"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 8H7"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M15 2v6M12 5h6"/></symbol>
		<symbol id="qil-i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
		<symbol id="qil-i-message" viewBox="0 0 24 24"><path d="M4 5h16v12H9l-5 4V5Z"/><path d="M8 9h8M8 13h5"/></symbol>
		<symbol id="qil-i-shield" viewBox="0 0 24 24"><path d="M12 2 4.5 5v6c0 5.2 3 8.8 7.5 11 4.5-2.2 7.5-5.8 7.5-11V5L12 2Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></symbol>
		<symbol id="qil-i-store" viewBox="0 0 24 24"><path d="M4 10v10h16V10M3 10l2-6h14l2 6"/><path d="M3 10a3 3 0 0 0 5 2 3 3 0 0 0 4 0 3 3 0 0 0 4 0 3 3 0 0 0 5-2M9 20v-5h6v5"/></symbol>
		<symbol id="qil-i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></symbol>
		<symbol id="qil-i-truck" viewBox="0 0 24 24"><path d="M3 6h11v10H3zM14 10h3.2l2.8 3v3h-6z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></symbol>
	</svg>
	<?php
	return (string) ob_get_clean();
}

/**
 * Announcement bar. One truthful free-delivery line for the active market.
 */
function qil_section_topbar( array $args = array() ) {
	$context = qil_view_context();
	$market  = $context['market'];
	$short   = isset( $market['deliveryShort'] ) ? (string) $market['deliveryShort'] : '';
	$label   = isset( $market['marketLabel'] ) ? (string) $market['marketLabel'] : '';

	ob_start();
	?>
	<div class="qil-topbar">
		<div class="qil-container qil-topbar-inner">
			<p class="qil-topbar-delivery">
				<span class="qil-status-dot" aria-hidden="true"></span>
				<svg aria-hidden="true"><use href="#qil-i-truck"/></svg>
				<span class="qil-topbar-message" data-qil-delivery-line><?php echo esc_html( $short ); ?></span>
				<?php if ( '' !== $label ) : ?>
					<em class="qil-topbar-market" data-qil-delivery-market><?php echo esc_html( $label ); ?></em>
				<?php endif; ?>
			</p>
			<?php
			// A live storefront must never announce that checkout is off. This
			// strip belongs to the sandbox and renders nowhere else.
			if ( ! function_exists( 'qil_is_staging_sandbox' ) || qil_is_staging_sandbox() ) :
				?>
				<p class="qil-stage-label"><span data-i18n="stage">Private staging · Cart preview on · Checkout off</span></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Whether a desktop header link represents the current page.
 *
 * Fragment links are sections of the homepage rather than separate pages, so
 * Home remains current while the shopper moves through those sections.
 */
function qil_header_link_is_current( array $link, array $context ) {
	$key = isset( $link['key'] ) ? sanitize_key( (string) $link['key'] ) : '';

	if ( 'home' === $key && function_exists( 'qil_should_render' ) && qil_should_render() ) {
		return true;
	}

	if (
		'shop' === $key
		&& (
			( function_exists( 'is_shop' ) && is_shop() )
			|| ( function_exists( 'is_product' ) && is_product() )
			|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() )
		)
	) {
		return true;
	}

	if ( 'account' === $key && function_exists( 'is_account_page' ) && is_account_page() ) {
		return true;
	}

	$url = isset( $link['url'] ) ? trim( (string) $link['url'] ) : '';
	if ( '' === $url || '#' === substr( $url, 0, 1 ) || null !== wp_parse_url( $url, PHP_URL_FRAGMENT ) ) {
		return false;
	}

	$link_host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$home_host = strtolower( (string) wp_parse_url( $context['homeUrl'], PHP_URL_HOST ) );
	if ( '' !== $link_host && '' !== $home_host && $link_host !== $home_host ) {
		return false;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$link_path   = '/' . trim( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ), '/' );
	$current     = '/' . trim( rawurldecode( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) ), '/' );

	return $link_path === $current;
}

/**
 * Sticky storefront header.
 */
function qil_section_header( array $args = array() ) {
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];

	$is_home = function_exists( 'qil_should_render' ) && qil_should_render();
	$links = isset( $args['links'] ) && is_array( $args['links'] ) ? $args['links'] : array(
		array( 'key' => 'shop', 'url' => $context['shopUrl'], 'i18n' => 'navShop', 'label' => 'Shop', 'labelAr' => 'المتجر' ),
		array( 'url' => $context['homeUrl'] . '#qil-match', 'i18n' => 'navGoals', 'label' => 'Goals', 'labelAr' => 'الأهداف' ),
		array( 'url' => $context['homeUrl'] . '#qil-ai', 'i18n' => 'navLab', 'label' => 'Qimia AI', 'labelAr' => 'ذكاء كيميا' ),
		array( 'url' => $context['homeUrl'] . '#qil-compare', 'i18n' => 'navCompare', 'label' => 'Compare', 'labelAr' => 'المقارنة' ),
		array( 'key' => 'account', 'url' => $context['accountUrl'], 'i18n' => '', 'label' => 'My Account', 'labelAr' => 'حسابي' ),
		array( 'key' => 'about', 'url' => $context['aboutUrl'], 'i18n' => '', 'label' => 'About us', 'labelAr' => 'من نحن' ),
	);
	array_unshift( $links, array( 'key' => 'home', 'url' => $context['homeUrl'], 'i18n' => '', 'label' => 'Home', 'labelAr' => 'الرئيسية' ) );
	$links = (array) apply_filters( 'qil_header_links', $links, $context );

	ob_start();
	?>
	<header class="qil-header" data-qil-header>
		<div class="qil-container qil-nav">
            <details class="qil-pages" data-qil-pages>
                <summary aria-label="<?php echo esc_attr($is_arabic?'تنقل الموقع':'Site navigation'); ?>"><svg aria-hidden="true"><use href="#qil-i-menu"/></svg></summary>
                <nav aria-label="<?php echo esc_attr($is_arabic?'صفحات كيميا':'Qimia pages'); ?>">
                <?php foreach($links as $link): ?><a href="<?php echo esc_url($link['url']??'#'); ?>"<?php echo qil_header_link_is_current($link,$context)?' aria-current="page"':''; ?><?php echo function_exists('qh_header_link_attributes')?qh_header_link_attributes($link):''; ?>><?php echo function_exists('qh_header_link_markup')?qh_header_link_markup($link,$is_arabic):esc_html($is_arabic?($link['labelAr']??$link['label']):$link['label']); ?></a><?php endforeach; ?>
                <button type="button" data-qil-menu-open><?php echo esc_html($is_arabic?'تسوق الأقسام والعلامات':'Shop categories & brands'); ?></button>
                </nav>
            </details>
			<button class="qil-menu-button qil-icon-button" type="button" data-qil-menu-open aria-label="<?php echo esc_attr( $is_arabic ? 'فتح قائمة كيميا للتسوق' : 'Open the Qimia shopping menu' ); ?>"><svg><use href="#qil-i-menu"/></svg><span><?php echo esc_html( $is_arabic ? 'القائمة' : 'Menu' ); ?></span></button>

			<a class="qil-brand" href="<?php echo esc_url( $context['homeUrl'] ); ?>" aria-label="<?php echo esc_attr( $is_arabic ? 'الصفحة الرئيسية لكيميا' : 'Qimia home' ); ?>">
				<span class="qil-wp-logo"><?php echo wp_kses_post( $context['logo'] ); ?></span>
			</a>

			<nav class="qil-links" aria-label="<?php echo esc_attr( $is_arabic ? 'التنقل الرئيسي' : 'Primary navigation' ); ?>">
				<?php foreach ( $links as $link ) : ?>
					<?php $is_current = qil_header_link_is_current( $link, $context ); ?>
					<a href="<?php echo esc_url( isset( $link['url'] ) ? $link['url'] : '#' ); ?>"<?php echo ! empty( $link['i18n'] ) ? ' data-i18n="' . esc_attr( $link['i18n'] ) . '"' : ''; ?><?php echo $is_current ? ' aria-current="page"' : ''; ?><?php echo function_exists('qh_header_link_attributes')?qh_header_link_attributes($link):''; ?>><?php echo function_exists('qh_header_link_markup')?qh_header_link_markup($link,$is_arabic):esc_html( $is_arabic && ! empty( $link['labelAr'] ) ? $link['labelAr'] : ( isset( $link['label'] ) ? $link['label'] : '' ) ); ?></a>
				<?php endforeach; ?>
			</nav>

            <div class="qil-actions">
				<button class="qil-icon-button" type="button" data-qil-search-toggle aria-label="<?php echo esc_attr( $is_arabic ? 'البحث في الكتالوج' : 'Search catalogue' ); ?>"><svg><use href="#qil-i-search"/></svg></button>
				<details class="qil-locale-control qaatm-no-translate notranslate" translate="no" data-qaatm-no-rewrite data-no-translation>
					<summary aria-label="<?php echo esc_attr( $is_arabic ? 'اللغة والعملة' : 'Language and currency' ); ?>">
						<span class="qil-locale-currency woocommerce-Price-currencySymbol<?php echo esc_attr( $context['gulfCurrency'] ); ?>" aria-hidden="true"><?php echo esc_html( $context['currencyMark'] ); ?></span>
						<span class="qil-locale-values"><strong class="qil-locale-language" lang="<?php echo esc_attr( $is_arabic ? 'ar' : 'en' ); ?>" dir="<?php echo esc_attr( $is_arabic ? 'rtl' : 'ltr' ); ?>"><?php echo esc_html( $is_arabic ? 'العربية' : 'ENGLISH' ); ?></strong></span>
						<svg aria-hidden="true"><use href="#qil-i-chevron"/></svg>
					</summary>
					<div class="qil-locale-menu">
                        <div class="qil-currency-picker" data-qil-currency-picker data-qil-switch-enabled="<?php echo class_exists('Qimia_Unlimited_Geo_Currency') && count(qil_storefront_currency_codes()) > 1 ? '1' : '0'; ?>">
                            <span class="qil-currency-heading"><?php echo esc_html($is_arabic ? 'اختر العملة' : 'Choose currency'); ?></span>
                            <form method="get" action="<?php echo esc_url(qil_navigation_current_url()); ?>" class="qil-currency-options" aria-label="<?php echo esc_attr($is_arabic ? 'اختيار العملة' : 'Choose currency'); ?>">
                                <?php echo qil_currency_form_fields(); // Escaped names and values only. ?>
                                <?php foreach (qil_storefront_currency_codes() as $currency_code) : ?>
                                    <button type="submit" name="qimia_currency" value="<?php echo esc_attr($currency_code); ?>" class="qil-currency-option no-prefetch" data-qil-currency-code="<?php echo esc_attr($currency_code); ?>" data-qaatm-no-rewrite<?php echo $context['currency'] === $currency_code ? ' aria-current="true"' : ''; ?>>
                                        <span><?php echo esc_html($currency_code); ?></span><svg aria-hidden="true"><use href="#qil-i-check"/></svg>
                                    </button>
                                <?php endforeach; ?>
                            </form>
                            <p class="qil-currency-status" data-qil-currency-status role="status" aria-live="polite" hidden></p>
                        </div>
						<div class="qil-locale-field">
							<span><?php echo esc_html( $is_arabic ? 'اللغة' : 'Language' ); ?></span>
							<nav class="qil-lang qaatm-switcher" aria-label="<?php echo esc_attr( $is_arabic ? 'اختيار اللغة' : 'Choose language' ); ?>">
									<a data-qil-language="en" class="qaatm-lang qaatm-lang-en<?php echo $is_arabic ? '' : ' is-active'; ?>" href="<?php echo esc_url( $context['languageEnUrl'] ); ?>" rel="alternate" lang="en" dir="ltr" hreflang="en-OM" aria-label="English" data-qaatm-no-rewrite<?php echo $is_arabic ? '' : ' aria-current="page"'; ?>><span>EN</span></a>
									<span class="qaatm-switcher-divider" aria-hidden="true"></span>
									<a data-qil-language="ar" class="qaatm-lang qaatm-lang-ar<?php echo $is_arabic ? ' is-active' : ''; ?>" href="<?php echo esc_url( $context['languageArUrl'] ); ?>" rel="alternate" lang="ar" dir="rtl" hreflang="ar-OM" aria-label="العربية" data-qaatm-no-rewrite<?php echo $is_arabic ? ' aria-current="page"' : ''; ?>><span>العربية</span></a>
								</nav>
						</div>
					</div>
				</details>
				<div class="qil-bag wd-tools-element wd-design-5 cart-widget-opener">
					<a href="<?php echo esc_url( $context['cartUrl'] ); ?>" title="<?php echo esc_attr( $is_arabic ? 'سلة التسوق' : 'Shopping cart' ); ?>" aria-label="<?php echo esc_attr( $is_arabic ? 'عرض سلة التسوق' : 'View shopping cart' ); ?>">
						<span class="wd-tools-icon"><svg><use href="#qil-i-bag"/></svg><span class="wd-cart-number wd-tools-count is-empty" data-qil-cart-count hidden>0</span></span>
						<span class="wd-tools-text" aria-hidden="true"><span class="wd-cart-subtotal"></span></span>
					</a>
				</div>
			</div>

		</div>
		<div class="qil-searchbar wd-search-form wd-header-search-form" data-qil-searchbar hidden>
			<form class="qil-container searchform wd-style-4 woodmart-ajax-search" role="search" method="get" action="<?php echo esc_url( $context['homeUrl'] ); ?>" data-thumbnail="1" data-price="1" data-post_type="product" data-count="20" data-sku="0" data-symbols_count="3" data-include_cat_search="no" autocomplete="off">
				<button class="searchsubmit wd-with-img" type="submit" aria-label="<?php echo esc_attr( $is_arabic ? 'بحث' : 'Search' ); ?>"><svg><use href="#qil-i-search"/></svg><span class="screen-reader-text"><?php echo esc_html( $is_arabic ? 'بحث' : 'Search' ); ?></span></button>
				<input class="s" type="text" name="s" data-qil-search data-i18n-placeholder="searchPlaceholder" placeholder="Search a product, ingredient or goal…" aria-label="<?php echo esc_attr( $is_arabic ? 'البحث عن المنتجات' : 'Search for products' ); ?>" autocomplete="off" required>
				<input type="hidden" name="post_type" value="product">
				<div class="wd-clear-search wd-action-btn wd-style-icon wd-cross-icon wd-role-btn wd-hide"><a href="#" rel="nofollow" aria-label="<?php echo esc_attr( $is_arabic ? 'مسح البحث' : 'Clear search' ); ?>"></a></div>
				<button class="qil-search-close" type="button" data-qil-search-close aria-label="<?php echo esc_attr( $is_arabic ? 'إغلاق البحث' : 'Close search' ); ?>"><svg><use href="#qil-i-close"/></svg></button>
			</form>
			<div class="qil-container wd-search-results-wrapper"><div class="wd-search-results wd-dropdown-results wd-dropdown wd-scroll"><div class="wd-scroll-content"></div></div></div>
		</div>
	</header>
	<?php
	return (string) ob_get_clean();
}

/**
 * Hero portal: slogan, live AI prompt, goal chips, floating Qimia mark.
 */
function qil_section_hero( array $args = array() ) {
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];

	// Migrate stored Elementor defaults as well as the shortcode defaults.
	// Merchant-authored copy and the Arabic headline retain their current values.
	foreach (array('lineOneEn' => 'STOP GUESSING.', 'lineTwoEn' => 'START KNOWING.', 'lineThreeEn' => 'YOUR QIMIA.') as $key => $previous) {
		if (isset($args[$key]) && trim((string) $args[$key]) === $previous) {
			unset($args[$key]);
		}
	}
	$line_one   = qil_text( $args, 'lineOne', 'STOP GUESSING', 'لا تخمين.' );
	$line_two   = qil_text( $args, 'lineTwo', '', 'معرفة حقيقية.' );
	$line_three = qil_text( $args, 'lineThree', 'START MY QIMIA.', 'كيميا الخاصة بك.' );
	$split_myqimia_title = ! $is_arabic && 'START MY QIMIA.' === $line_three;
	$line_three_markup = $split_myqimia_title
		? '<span class="qil-myqimia-title-start">START </span><span class="qil-myqimia-title-name">MY QIMIA.</span>'
		: esc_html($line_three);
	$myqimia_url = qil_myqimia_url('today', $is_arabic);
	$myqimia_label = $is_arabic ? 'افتح ماي كيميا' : 'Open My Qimia';
	// Replace only previous bundled defaults, not the merchant's custom hero copy.
	$lead_defaults = array(
		'leadEn' => array('Every label, every price, every product actually in stock — read by Qimia AI before you choose.', 'Join My Qimia with your purchase — keep your orders, routine and cashback together in one personal space.', 'Shop to join My Qimia — your routine, orders & cashback, together.'),
		'leadAr' => array('كل ملصق، كل سعر، وكل منتج متوفر فعلاً — يقرأها ذكاء كيميا قبل أن تختار.', 'انضم إلى ماي كيميا مع شرائك — اجمع طلباتك وروتينك وكاش باكك في مساحة شخصية واحدة.', 'انضم إلى ماي كيميا مع شرائك — روتينك وطلباتك وكاش باكك معاً.'),
	);
	foreach ($lead_defaults as $lead_key => $old_defaults) {
		if (isset($args[$lead_key]) && in_array(trim((string)$args[$lead_key]), $old_defaults, true)) {
			unset($args[$lead_key]);
		}
	}
	$lead       = qil_text(
		$args,
		'lead',
		$myqimia_url ? 'Shop to join My Qimia — your routine, orders & cashback, together.' : 'Explore supplements, compare products and shop with confidence.',
		$myqimia_url ? 'انضم إلى ماي كيميا مع شرائك — روتينك وطلباتك وكاش باكك معاً.' : 'استكشف المكمّلات، وقارن المنتجات، وتسوّق بثقة.'
	);
	$eyebrow    = qil_text( $args, 'eyebrow', 'QIMIA PORTAL', 'بوابة كيميا' );
	$show_orb   = ! isset( $args['showOrb'] ) || 'no' !== $args['showOrb'];
	$orb_image  = isset( $args['orbImage'] ) && '' !== $args['orbImage'] ? $args['orbImage'] : QIL_URL . 'assets/qimia-hero-orbit-v9.webp';

	$goals = array(
		array( 'muscle', 'qil-i-muscle', 'Build Muscle', 'بناء العضلات', 'Help me build muscle with available Qimia products', 'ساعدني في بناء العضلات بمنتجات كيميا المتاحة' ),
		array( 'fat-loss', 'qil-i-energy', 'Fat Loss', 'إدارة الدهون', 'I want practical support for a fat-loss goal', 'أريد دعماً عملياً لهدف خسارة الدهون' ),
		array( 'performance', 'qil-i-spark', 'Performance', 'الأداء', 'Help me support training performance and energy', 'ساعدني في دعم الأداء والطاقة' ),
		array( 'recovery', 'qil-i-recovery', 'Recovery', 'التعافي', 'Help me choose products for recovery', 'ساعدني في اختيار منتجات للتعافي' ),
		array( 'sleep', 'qil-i-sleep', 'Sleep & Calm', 'النوم والهدوء', 'Help me build a sleep and calm routine', 'ساعدني في روتين للنوم والهدوء' ),
		array( 'wellness', 'qil-i-wellness', 'Daily Wellness', 'العافية اليومية', 'Help me build a daily wellness routine', 'ساعدني في روتين العافية اليومية' ),
		array( 'beauty', 'qil-i-beauty', 'Beauty', 'الجمال', 'Help me support hair, skin and beauty', 'ساعدني في دعم الشعر والبشرة والجمال' ),
	);

	ob_start();
	?>
	<section class="qil-hero qil-portal qil-digital-portal" aria-labelledby="qil-portal-title" data-qil-portal>
					<picture class="qil-hero-picture qil-digital-backdrop" data-qil-hero-background aria-hidden="true">
						<source media="(max-width: 980px)" srcset="<?php echo esc_url(QIL_URL . 'assets/qimia-digital-world-v14-960.webp'); ?>" type="image/webp">
						<source srcset="<?php echo esc_url( QIL_URL . 'assets/qimia-digital-world-v14-1672.webp' ); ?>" type="image/webp">
						<img class="qil-hero-art" src="<?php echo esc_url( QIL_URL . 'assets/qimia-digital-world-v14-1672.webp' ); ?>" alt="" width="1672" height="941" fetchpriority="high" decoding="async">
					</picture>
		<div class="qil-container qil-hero-grid<?php echo $show_orb ? ' qil-hero-grid--with-orb' : ''; ?>">
			<div class="qil-hero-copy">
				<div class="qil-eyebrow"><svg><use href="#qil-i-spark"/></svg><span><?php echo esc_html( $eyebrow ); ?></span><small><?php echo esc_html( $is_arabic ? 'متصل بالمتجر' : 'LIVE STORE' ); ?></small></div>
				<h1 id="qil-portal-title">
					<span><?php echo esc_html( $line_one ); ?></span>
					<?php if ('' !== trim($line_two)) : ?><span><?php echo esc_html( $line_two ); ?></span><?php endif; ?>
					<span class="qil-myqimia-title-row<?php echo $split_myqimia_title ? ' qil-myqimia-title-row--single' : ''; ?>">
                        <?php if ($myqimia_url) : ?>
                        <a class="qil-button-primary qil-myqimia-title-link" data-qil-myqimia-cta href="<?php echo esc_url($myqimia_url); ?>" title="<?php echo esc_attr($myqimia_label); ?>" aria-label="<?php echo esc_attr($line_three . ' — ' . $myqimia_label); ?>">
                            <span class="qil-gradient-text qil-myqimia-title-text"><?php echo $line_three_markup; // Escaped text or fixed, trusted spans. ?></span>
                        </a>
                        <?php else : ?>
                        <span class="qil-myqimia-title-static"><span class="qil-gradient-text qil-myqimia-title-text"><?php echo $line_three_markup; // Escaped text or fixed, trusted spans. ?></span></span>
                        <?php endif; ?>
                    </span>
				</h1>
                <div class="qil-myqimia-inline qil-myqimia-inline--title-entry">
                    <p class="qil-hero-lead qil-myqimia-inline-copy"><?php echo esc_html( $lead ); ?></p>
                </div>

				<form class="qil-portal-query" data-qil-hero-ai-form>
					<label class="screen-reader-text" for="qil-portal-query"><?php echo esc_html( $is_arabic ? 'أخبر كيميا بما تريد تحقيقه' : 'Tell Qimia what you want to achieve' ); ?></label>
					<span class="qil-portal-query-icon" aria-hidden="true"><svg><use href="#qil-i-spark"/></svg></span>
					<input id="qil-portal-query" type="text" inputmode="text" autocomplete="off" data-qil-hero-ai-input placeholder="<?php echo esc_attr( $is_arabic ? 'مثال: بروتين لبناء عضل خفيف بميزانية 25 ر.ع' : 'Tell Qimia what you want to achieve…' ); ?>">
					<button type="submit" data-qil-hero-ai-submit><span><?php echo esc_html( $is_arabic ? 'اسأل كيميا' : 'ASK QIMIA' ); ?></span><svg><use href="#qil-i-arrow"/></svg></button>
				</form>
				<p class="qil-portal-query-status" data-qil-hero-ai-status aria-live="polite"></p>

				<div class="qil-portal-examples" aria-label="<?php echo esc_attr( $is_arabic ? 'أمثلة جاهزة' : 'Try an example' ); ?>">
					<span><?php echo esc_html( $is_arabic ? 'جرّب:' : 'Try:' ); ?></span>
					<small><?php echo esc_html( $is_arabic ? 'عضل خفيف بأقل من 25 ر.ع' : 'Lean muscle under 25 OMR' ); ?></small>
					<small><?php echo esc_html( $is_arabic ? 'بري ووركاوت بلا منبّهات' : 'Stimulant-free pre-workout' ); ?></small>
					<small><?php echo esc_html( $is_arabic ? 'قارن منتجات الكرياتين' : 'Compare creatines' ); ?></small>
					<small><?php echo esc_html( $is_arabic ? 'آيزوليت بأفضل قيمة' : 'Best-value isolate' ); ?></small>
				</div>

				<nav class="qil-portal-goals" aria-label="<?php echo esc_attr( $is_arabic ? 'ابدأ حسب الهدف' : 'Start by goal' ); ?>">
					<?php foreach ( $goals as $goal ) : ?>
						<button type="button" data-qil-hero-ai-chip data-goal="<?php echo esc_attr( $goal[0] ); ?>" data-qil-ai-prompt="<?php echo esc_attr( $is_arabic ? $goal[5] : $goal[4] ); ?>"><svg><use href="#<?php echo esc_attr( $goal[1] ); ?>"/></svg><span><?php echo esc_html( $is_arabic ? $goal[3] : $goal[2] ); ?></span></button>
					<?php endforeach; ?>
				</nav>

				<p class="qil-hero-note"><svg><use href="#qil-i-shield"/></svg><span><?php echo esc_html( $is_arabic ? 'إرشاد للتسوق، وليس تشخيصاً طبياً · يبقى سياق التصفح في هذه الصفحة' : 'Shopping guidance, not medical diagnosis · browsing context stays in this tab' ); ?></span></p>
				<div class="qil-proof">
					<div><strong data-qil-product-total aria-live="polite">—</strong><span data-i18n="supplements">supplements</span></div>
					<div><strong data-qil-brand-total aria-live="polite">—</strong><span data-i18n="brands">trusted brands</span></div>
					<div><strong><?php echo esc_html( $is_arabic ? 'مسقط' : 'MUSCAT' ); ?></strong><span><?php echo esc_html( $is_arabic ? 'فريق محلي' : 'local team' ); ?></span></div>
				</div>
			</div>

			<?php if ( $show_orb ) : ?>
				<div class="qil-hero-orb" data-qil-hero-orb aria-hidden="true">
					<span class="qil-hero-orb-glow"></span>
					<span class="qil-hero-orb-ring"></span>
					<img class="qil-hero-orb-mark" src="<?php echo esc_url( $orb_image ); ?>" alt="" width="900" height="935" decoding="async" loading="eager">
				</div>
			<?php endif; ?>

			<div class="qil-hero-visual" data-qil-hero-scroll aria-label="<?php echo esc_attr( $is_arabic ? 'مشهد كيميا الرقمي لاختيار المكمّلات' : 'Qimia digital supplement discovery scene' ); ?>">
				<span class="screen-reader-text"><?php echo esc_html( $is_arabic ? 'زوجان عُمانيان يراجعان اقتراحات ذكاء كيميا الاصطناعي على جهاز لوحي، مع المنتجات الأسبوعية الأكثر مبيعاً من الكتالوج المباشر.' : 'An Omani couple reviews Qimia AI recommendations on a tablet beside this week’s live best-selling products.' ); ?></span>
				<div class="qil-product-stage" data-qil-hero-stage>


					<div class="qil-hero-depth" data-qil-hero-depth>
						<?php echo qil_hero_avatar_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="qil-hero-depth-scroll" data-qil-hero-scroll-cue></span>
					</div>
					<div class="qil-hero-weekly">
						<span><?php echo esc_html( $is_arabic ? 'الأكثر مبيعاً هذا الأسبوع' : 'THIS WEEK’S BEST SELLERS' ); ?></span>
						<div class="qil-hero-products" data-qil-hero-products aria-label="<?php echo esc_attr( $is_arabic ? 'المنتجات الأكثر مبيعاً هذا الأسبوع' : 'This week’s best-selling products' ); ?>"></div>
					</div>
					<div class="qil-portal-signal" aria-hidden="true"><span></span><strong>LIVE</strong><small><?php echo esc_html( $is_arabic ? 'سعر · مخزون · حقائق' : 'PRICE · STOCK · FACTS' ); ?></small></div>
				</div>
			</div>
		</div>
		<div class="qil-scroll-note"><span></span><small><?php echo esc_html( $is_arabic ? 'مرّر لاستكشاف مختبر كيميا' : 'Scroll into the Qimia Lab' ); ?></small></div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Five primary category entry points, kept together at every viewport.
 */
function qil_section_category_rail( array $args = array() ) {
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];

	$tiles = isset( $args['tiles'] ) && is_array( $args['tiles'] ) ? $args['tiles'] : array(
		array( 'key' => 'protein', 'url' => $context['proteinUrl'], 'icon' => 'qil-i-muscle', 'kicker' => 'Most shopped', 'kickerAr' => 'الأكثر طلباً', 'title' => 'Protein', 'titleAr' => 'البروتين' ),
		array( 'key' => 'fat-burner', 'url' => $context['fatBurnerUrl'], 'icon' => 'qil-i-energy', 'kicker' => 'Goal support', 'kickerAr' => 'دعم الهدف', 'title' => 'Fat Burners', 'titleAr' => 'حوارق الدهون' ),
		array( 'key' => 'creatine', 'url' => $context['creatineUrl'], 'icon' => 'qil-i-spark', 'kicker' => 'Strength & performance', 'kickerAr' => 'القوة والأداء', 'title' => 'Creatine', 'titleAr' => 'الكرياتين' ),
		array( 'key' => 'offers', 'url' => qil_promotion_url( 'offers', $is_arabic ), 'icon' => 'qil-i-energy', 'kicker' => 'Real reductions', 'kickerAr' => 'تخفيضات حقيقية', 'title' => 'Offers', 'titleAr' => 'العروض' ),
		array( 'key' => 'flash', 'url' => qil_promotion_url('flash', $is_arabic), 'icon' => 'qil-i-energy', 'kicker' => 'Limited-time deals', 'kickerAr' => 'لفترة محدودة', 'title' => 'Flash Sale', 'titleAr' => 'عروض سريعة' ),
	);

	ob_start();
	?>
	<nav class="qil-category-rail" aria-label="<?php echo esc_attr( $is_arabic ? 'فئات كيميا الرئيسية' : 'Top Qimia categories' ); ?>">
		<div class="qil-container qil-category-tiles">
			<?php foreach ( $tiles as $tile ) : ?>
				<a class="qil-category-tile" href="<?php echo esc_url( isset( $tile['url'] ) ? $tile['url'] : $context['shopUrl'] ); ?>" data-qil-category="<?php echo esc_attr( isset( $tile['key'] ) ? $tile['key'] : '' ); ?>">
					<span class="qil-category-icon"><svg><use href="#<?php echo esc_attr( isset( $tile['icon'] ) ? $tile['icon'] : 'qil-i-store' ); ?>"/></svg></span>
					<span><small><?php echo esc_html( $is_arabic && ! empty( $tile['kickerAr'] ) ? $tile['kickerAr'] : ( isset( $tile['kicker'] ) ? $tile['kicker'] : '' ) ); ?></small><strong><?php echo esc_html( $is_arabic && ! empty( $tile['titleAr'] ) ? $tile['titleAr'] : ( isset( $tile['title'] ) ? $tile['title'] : '' ) ); ?></strong></span>
					<svg class="qil-category-arrow"><use href="#qil-i-arrow"/></svg>
				</a>
			<?php endforeach; ?>
		</div>
	</nav>
	<?php
	return (string) ob_get_clean();
}

/**
 * Goal engine plus the live product grid.
 */
function qil_section_goal_engine( array $args = array() ) {
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];

	$goals = array(
		array( 'muscle', 'qil-i-muscle', 'muscle', 'Build muscle', 'muscleSub', 'Protein · strength · mass' ),
		array( 'fat-loss', 'qil-i-energy', 'fatLoss', 'Fat loss', 'fatLossSub', 'Cut support · value · availability' ),
		array( 'performance', 'qil-i-spark', 'performance', 'Performance', 'performanceSub', 'Pre-workout · creatine · amino' ),
		array( 'energy', 'qil-i-energy', 'energy', 'Energy & focus', 'energySub', 'Drive · clarity · performance' ),
		array( 'recovery', 'qil-i-recovery', 'recovery', 'Recovery', 'recoverySub', 'Hydration · repair · mobility' ),
		array( 'sleep', 'qil-i-sleep', 'sleep', 'Sleep & calm', 'sleepSub', 'Rest · routine · balance' ),
		array( 'wellness', 'qil-i-wellness', 'wellness', 'Daily wellness', 'wellnessSub', 'Vitamins · gut · immunity' ),
		array( 'beauty', 'qil-i-beauty', 'beauty', 'Hair, skin & beauty', 'beautySub', 'Collagen · biotin · glow' ),
	);

	ob_start();
	?>
	<section id="qil-match" class="qil-section qil-match-section">
		<div class="qil-container">
			<div class="qil-section-heading qil-heading-row">
				<div><span class="qil-kicker" data-i18n="startOutcome">START WITH YOUR OUTCOME</span><h2 data-i18n="whatGoal">What would you like to work on?</h2><p data-i18n="chooseGoal">Choose one goal. You can refine the match afterward.</p></div>
				<div class="qil-privacy-note"><svg><use href="#qil-i-shield"/></svg><span data-i18n="privateSession"><?php echo esc_html( $is_arabic ? 'يبقى سياق التصفح في هذه الصفحة؛ وتُعالج الرسائل التي ترسلها عبر ذكاء كيميا' : 'Browsing context stays in this tab; messages you send are processed by Qimia AI' ); ?></span></div>
			</div>

			<div class="qil-goal-grid" role="group" aria-label="<?php echo esc_attr( $is_arabic ? 'اختر هدف التسوق' : 'Choose a shopping goal' ); ?>">
				<?php foreach ( $goals as $index => $goal ) : ?>
					<button class="qil-goal<?php echo 0 === $index ? ' is-active' : ''; ?>" type="button" data-goal="<?php echo esc_attr( $goal[0] ); ?>" aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>" aria-controls="qil-goal-results"><span class="qil-goal-icon"><svg><use href="#<?php echo esc_attr( $goal[1] ); ?>"/></svg></span><span><strong data-i18n="<?php echo esc_attr( $goal[2] ); ?>"><?php echo esc_html( $goal[3] ); ?></strong><small data-i18n="<?php echo esc_attr( $goal[4] ); ?>"><?php echo esc_html( $goal[5] ); ?></small></span><span class="qil-select-dot"></span></button>
				<?php endforeach; ?>
			</div>

			<div class="qil-refine">
				<span data-i18n="refine">Refine:</span>
				<button type="button" data-pref="stimfree" aria-pressed="false"><span></span><span data-i18n="stimFree">Stimulant-free</span></button>
				<button type="button" data-pref="vegan" aria-pressed="false"><span></span><span data-i18n="vegan">Vegan-friendly</span></button>
				<button type="button" data-pref="value" aria-pressed="false"><span></span><span data-i18n="bestValue">Best value</span></button>
			</div>

			<div class="qil-results-head">
				<div><div class="qil-live-label"><span></span><span data-i18n="liveMatches">LIVE MATCHES</span></div><h3><span data-i18n="selectedFor">Selected for</span> <em data-qil-selected-label>Build muscle</em></h3></div>
				<p><svg><use href="#qil-i-spark"/></svg><span data-i18n="rankingNote">Ranked by relevance, product data and availability—not discount size.</span></p>
			</div>

			<p class="qil-goal-status" data-qil-goal-status role="status" aria-live="polite"></p>
			<div class="qil-goal-loading" data-qil-goal-loading hidden aria-hidden="true"><span class="qil-goal-loading-spinner"></span><div><strong data-qil-goal-loading-label><?php echo esc_html($is_arabic ? 'نجهّز اختياراتك' : 'Preparing your picks'); ?></strong><span data-qil-goal-loading-detail><?php echo esc_html($is_arabic ? 'نتحقق من المنتجات والأسعار والتوفر' : 'Checking products, prices and availability'); ?></span></div></div>
			<div id="qil-goal-results" class="qil-product-grid" data-qil-results role="region" aria-live="polite" aria-label="<?php echo esc_attr( $is_arabic ? 'نتائج المنتجات للهدف المحدد' : 'Product results for the selected goal' ); ?>"></div>
			<div class="qil-empty" data-qil-empty hidden>
				<strong><?php echo esc_html( $is_arabic ? 'أقرب خيارات موثّقة لهدفك' : 'Closest verified matches for your goal' ); ?></strong>
				<p><?php echo esc_html( $is_arabic ? 'عدّل تفضيلاً واحداً، أو دع ذكاء كيميا يبدأ معك بسؤال قصير.' : 'Refine one preference, or let Qimia AI start with one short question.' ); ?></p>
				<button class="qil-button qil-button-primary qil-button-small" type="button" data-qimia-ai-open data-qil-ai-intent="routine"><svg><use href="#qil-i-spark"/></svg><span><?php echo esc_html( $is_arabic ? 'اسأل ذكاء كيميا' : 'Ask Qimia AI' ); ?></span></button>
			</div>
			<noscript><div class="qil-empty"><strong><?php echo esc_html( $is_arabic ? 'ابدأ من كتالوج كيميا المباشر' : 'Start with the live Qimia catalogue' ); ?></strong><p><?php echo esc_html( $is_arabic ? 'يمكنك تصفح المنتجات الحقيقية الآن.' : 'You can browse real products now.' ); ?></p><a class="qil-button qil-button-primary qil-button-small" href="<?php echo esc_url( $context['shopUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'تسوّق المنتجات' : 'Shop products' ); ?></a></div></noscript>
			<div class="qil-results-footer"><button class="qil-text-button" type="button" data-qil-show-more><span data-i18n="showMore">Show more matches</span><svg><use href="#qil-i-arrow"/></svg></button></div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Live WooCommerce collections.
 */
function qil_section_collections( array $args = array() ) {
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];

	$blocks = isset( $args['blocks'] ) && is_array( $args['blocks'] ) ? $args['blocks'] : array(
		array( 'key' => 'protein', 'url' => $context['proteinUrl'], 'kicker' => 'Daily nutrition & recovery', 'kickerAr' => 'التغذية اليومية والتعافي', 'title' => 'Proteins', 'titleAr' => 'البروتينات' ),
		array( 'key' => 'creatine', 'url' => $context['creatineUrl'], 'kicker' => 'Strength & repeat performance', 'kickerAr' => 'القوة والأداء المتكرر', 'title' => 'Creatine', 'titleAr' => 'الكرياتين' ),
		array( 'key' => 'best-sellers', 'url' => $context['bestSellersUrl'], 'kicker' => 'Guided by real demand', 'kickerAr' => 'مختارة من الطلب الفعلي', 'title' => 'Best Sellers', 'titleAr' => 'الأكثر مبيعاً' ),
	);

	$intro_kicker = qil_text( $args, 'kicker', 'SHOP THE ESSENTIALS', 'تسوّق حسب الأساسيات' );
	$intro_title  = qil_text( $args, 'title', 'What Qimia shoppers choose most.', 'الأكثر طلباً الآن في كيميا.' );
	$intro_lead   = qil_text( $args, 'lead', 'Real products, current prices and live stock—never a static list.', 'منتجات حقيقية وأسعار ومخزون مباشر—بدون قوائم ثابتة.' );

	ob_start();
	?>
	<section class="qil-section qil-commerce-collections" aria-label="<?php echo esc_attr( $is_arabic ? 'مجموعات كيميا المباشرة' : 'Live Qimia collections' ); ?>">
		<div class="qil-container">
			<header class="qil-collections-intro"><span class="qil-kicker"><?php echo esc_html( $intro_kicker ); ?></span><h2><?php echo esc_html( $intro_title ); ?></h2><p><?php echo esc_html( $intro_lead ); ?></p></header>
			<?php foreach ( $blocks as $block ) : ?>
				<article class="qil-collection-block" data-qil-collection="<?php echo esc_attr( isset( $block['key'] ) ? $block['key'] : '' ); ?>">
					<div class="qil-collection-head"><div><small><?php echo esc_html( $is_arabic && ! empty( $block['kickerAr'] ) ? $block['kickerAr'] : ( isset( $block['kicker'] ) ? $block['kicker'] : '' ) ); ?></small><h3><?php echo esc_html( $is_arabic && ! empty( $block['titleAr'] ) ? $block['titleAr'] : ( isset( $block['title'] ) ? $block['title'] : '' ) ); ?></h3></div><div class="qil-collection-tools"><div class="qil-rail-nav" data-qil-rail-nav="<?php echo esc_attr( isset( $block['key'] ) ? $block['key'] : '' ); ?>"><button type="button" data-qil-rail-prev aria-label="<?php echo esc_attr( $is_arabic ? 'السابق' : 'Previous' ); ?>"><svg><use href="#qil-i-arrow"/></svg></button><button type="button" data-qil-rail-next aria-label="<?php echo esc_attr( $is_arabic ? 'التالي' : 'Next' ); ?>"><svg><use href="#qil-i-arrow"/></svg></button></div><a href="<?php echo esc_url( isset( $block['url'] ) ? $block['url'] : $context['shopUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'عرض الكل' : 'View all' ); ?><svg><use href="#qil-i-arrow"/></svg></a></div></div>
					<div class="qil-collection-grid qil-rail" data-qil-collection-grid data-qil-rail="<?php echo esc_attr( isset( $block['key'] ) ? $block['key'] : '' ); ?>" aria-live="polite"><div class="qil-collection-skeleton" aria-hidden="true"><i></i><i></i><i></i><i></i></div></div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Complete brand directory. Resolve installed brand-logo metadata when present;
 * brands without a logo retain a typographic tile and their real archive link.
 */
function qil_brand_carousel_items( $limit = 0 ) {
	if ( ! taxonomy_exists( 'product_brand' ) ) {
		return array();
	}

	$context = qil_view_context();
	$cache_key = 'qil_brands_v2_' . (int) $limit . '_' . ( $context['isArabic'] ? 'ar' : 'en' );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_brand',
			'hide_empty' => false,
			'number'     => max( 0, (int) $limit ),
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return array();
	}

	$items   = array();
	// Brand plugins disagree on both the meta key and the value: some store an
	// attachment ID, some store a URL. Try every key this storefront could be
	// using, and accept either shape, so the carousel is not empty just because
	// the brand plugin was swapped at some point.
	$meta_keys = array(
		'thumbnail_id',
		'_thumbnail_id',
		'brand_image',
		'brand_image_id',
		'pwb_brand_image',
		'woodmart_term_thumbnail',
		'product_brand_thumbnail_id',
		'brand_thumbnail_id',
	);
	foreach ( $terms as $term ) {
		$image = array();
		foreach ( $meta_keys as $meta_key ) {
			$value = get_term_meta( $term->term_id, $meta_key, true );
			if ( ! $value ) {
				continue;
			}
			if ( is_numeric( $value ) ) {
				$resolved = wp_get_attachment_image_src( (int) $value, 'medium' );
				if ( $resolved && ! empty( $resolved[0] ) ) {
					$image = $resolved;
					break;
				}
				continue;
			}
			if ( is_string( $value ) && preg_match( '#^https?://#i', $value ) ) {
				$image = array( $value, 320, 160 );
				break;
			}
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$items[] = array(
			'name'   => qil_clean_text( $term->name ),
			'url'    => function_exists( 'qil_localized_url' ) ? qil_localized_url( $link, $context['isArabic'] ) : $link,
			'src'    => $image[0] ?? '',
			'width'  => (int) ( $image[1] ?? 320 ),
			'height' => (int) ( $image[2] ?? 160 ),
			'count'  => (int) $term->count,
		);
		if ( $limit > 0 && count( $items ) >= (int) $limit ) {
			break;
		}
	}

	set_transient( $cache_key, $items, 30 * MINUTE_IN_SECONDS );

	return $items;
}

/**
 * Complete brand carousel using the original rail styling.
 */
function qil_section_brands( array $args = array() ) {
	$context = qil_view_context();
	$brands  = qil_brand_carousel_items();
	if ( ! $brands ) {
		return '';
	}

	$kicker = qil_text( $args, 'kicker', 'TRUSTED BRANDS', 'علامات موثوقة' );
	$title  = qil_text( $args, 'title', 'The labels Qimia stocks.', 'العلامات التي يوفّرها كيميا.' );

	ob_start();
	?>
	<section class="qil-section qil-brands-section" aria-label="<?php echo esc_attr( $context['isArabic'] ? 'العلامات التجارية' : 'Brands' ); ?>">
		<div class="qil-container">
			<div class="qil-brands-head">
				<div><span class="qil-kicker"><?php echo esc_html( $kicker ); ?></span><h2><?php echo esc_html( $title ); ?></h2></div>
				<div class="qil-rail-nav" data-qil-rail-nav="brands">
                    <button type="button" data-qil-rail-prev aria-label="<?php echo esc_attr($context['isArabic'] ? 'السابق' : 'Previous'); ?>"><svg><use href="#qil-i-arrow"/></svg></button>
                    <button type="button" data-qil-rail-next aria-label="<?php echo esc_attr($context['isArabic'] ? 'التالي' : 'Next'); ?>"><svg><use href="#qil-i-arrow"/></svg></button>
                </div>
			</div>
			<div class="qil-brand-rail" data-qil-rail="brands" role="list">
				<?php foreach ( $brands as $brand ) : ?>
					<a class="qil-brand-tile" role="listitem" href="<?php echo esc_url( $brand['url'] ); ?>" aria-label="<?php echo esc_attr( $brand['name'] ); ?>">
						<?php if ( $brand['src'] ) : ?>
						<img src="<?php echo esc_url( $brand['src'] ); ?>" width="<?php echo (int) $brand['width']; ?>" height="<?php echo (int) $brand['height']; ?>" alt="" loading="lazy" decoding="async">
						<?php else : ?>
						<strong class="qil-brand-wordmark" aria-hidden="true"><?php echo esc_html( $brand['name'] ); ?></strong>
						<?php endif; ?>
						<span><?php echo esc_html( $brand['name'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Qimia AI cockpit.
 */
function qil_section_ai( array $args = array() ) {
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];

	ob_start();
	?>
	<section id="qil-ai" class="qil-section qil-ai-section">
		<div class="qil-container qil-ai-cockpit">
			<div class="qil-ai-copy">
				<div class="qil-ai-live"><span></span><span data-i18n="aiConnected">QIMIA AI · CONNECTED TO THE LIVE STORE</span></div>
				<h2 data-i18n="aiTitle">Your product guide,<br>built into the store.</h2>
				<p data-i18n="aiLead">Ask in English or Arabic. Qimia AI checks current products, prices, availability and delivery context before narrowing your options.</p>
				<div class="qil-ai-prompts" aria-label="<?php echo esc_attr( $is_arabic ? 'أسئلة مقترحة' : 'Suggested questions' ); ?>">
					<button type="button" data-qimia-ai-open data-qil-ai-intent="protein" data-i18n="promptProtein">Find a protein for lean muscle</button>
					<button type="button" data-qimia-ai-open data-qil-ai-intent="compare-picker" data-i18n="promptCompare">Compare two products</button>
					<button type="button" data-qimia-ai-open data-qil-ai-intent="delivery" data-i18n="promptDelivery">Check delivery for my country</button>
				</div>
				<button class="qil-button qil-button-primary" type="button" data-qimia-ai-open><svg><use href="#qil-i-spark"/></svg><span data-i18n="openQimiaAI">Open Qimia AI</span><span class="qil-button-meta" data-i18n="live">LIVE</span></button>
			</div>
			<div class="qil-ai-visual" aria-hidden="true">
				<svg class="qil-ai-art" viewBox="0 0 600 560" role="img" aria-hidden="true" preserveAspectRatio="xMidYMid meet" focusable="false">
				<defs>
				<linearGradient id="qilAiSky" x1="0" y1="0" x2="0.7" y2="1">
				<stop offset="0" stop-color="#ffffff"/><stop offset="46%" stop-color="#e8f8f6"/><stop offset="100%" stop-color="#cdeeea"/></linearGradient>
				<radialGradient id="qilAiHalo" cx="50%" cy="42%" r="46%">
				<stop offset="0" stop-color="#ffffff" stop-opacity=".95"/><stop offset="60%" stop-color="#b6ece5" stop-opacity=".45"/><stop offset="100%" stop-color="#8fdfd6" stop-opacity="0"/></radialGradient>
				<linearGradient id="qilAiRay" x1="0" y1="0" x2="0" y2="1">
				<stop offset="0" stop-color="#ffffff" stop-opacity=".85"/><stop offset="100%" stop-color="#ffffff" stop-opacity="0"/></linearGradient>
				<linearGradient id="qilAiFigure" gradientUnits="userSpaceOnUse" x1="232" y1="140" x2="392" y2="470">
				<stop offset="0" stop-color="#2fb3b6"/><stop offset="28%" stop-color="#0e6470"/><stop offset="70%" stop-color="#06323d"/><stop offset="100%" stop-color="#031c24"/></linearGradient>
				<linearGradient id="qilAiGlass" x1="0" y1="0" x2="0.4" y2="1">
				<stop offset="0" stop-color="#ffffff" stop-opacity=".97"/><stop offset="100%" stop-color="#dbf6f2" stop-opacity=".9"/></linearGradient>
				<filter id="qilAiSoft" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="12"/></filter>
				<filter id="qilAiDrop" x="-40%" y="-40%" width="180%" height="180%"><feDropShadow dx="0" dy="10" stdDeviation="10" flood-color="#04424c" flood-opacity=".22"/></filter>
				<pattern id="qilAiGrid" width="44" height="44" patternUnits="userSpaceOnUse">
				<path d="M44 0H0V44" fill="none" stroke="#37b9b6" stroke-opacity=".13" stroke-width="1"/></pattern>
				<clipPath id="qilAiClip"><rect x="0" y="0" width="600" height="560" rx="30"/></clipPath>
				</defs>
				<g clip-path="url(#qilAiClip)">
				  <rect width="600" height="560" fill="url(#qilAiSky)"/>
				  <rect width="600" height="560" fill="url(#qilAiGrid)"/>
				  <g class="qil-ai-rays" opacity=".55">
				    <path d="M232 -40 L296 -40 L214 560 L118 560 Z" fill="url(#qilAiRay)" opacity=".5"/>
				    <path d="M330 -40 L372 -40 L336 560 L268 560 Z" fill="url(#qilAiRay)" opacity=".35"/>
				  </g>
				  <ellipse cx="300" cy="250" rx="230" ry="230" fill="url(#qilAiHalo)"/>
				  <g class="qil-ai-orbit" fill="none" stroke="#2fb3b6" stroke-opacity=".22">
				    <ellipse cx="300" cy="286" rx="228" ry="176"/><ellipse cx="300" cy="286" rx="176" ry="134" stroke-opacity=".14"/></g>
				  <ellipse cx="300" cy="480" rx="140" ry="20" fill="#0a4b57" opacity=".22" filter="url(#qilAiSoft)"/>
				  <g class="qil-ai-figure" fill="url(#qilAiFigure)"><path d="M 246 330 C 274 318 326 318 354 330 C 392 348 416 392 424 452 C 426 468 418 478 402 478 L 198 478 C 182 478 174 468 176 452 C 184 392 208 348 246 330 Z"/><path d="M 300 126 C 264 126 238 156 238 196 C 238 228 244 254 240 280 C 237 300 230 316 222 330 C 240 348 264 358 284 362 L 316 362 C 336 358 360 348 378 330 C 370 316 363 300 360 280 C 356 254 362 228 362 196 C 362 156 336 126 300 126 Z"/></g><path d="M 238 196 C 238 156 264 126 300 126 C 278 134 260 160 260 196 C 260 228 264 256 260 282 C 256 302 246 318 238 332 C 230 332 226 331 222 330 C 230 316 237 300 240 280 C 244 254 238 228 238 196 Z" fill="#8ff0e6" opacity=".45"/><path d="M 300 360 C 302 396 302 440 300 476" fill="none" stroke="#0a3d49" stroke-opacity=".28" stroke-width="2"/>
				  <g class="qil-ai-links" fill="none" stroke="#2fb3b6" stroke-opacity=".4" stroke-width="1.5" stroke-dasharray="4 7" stroke-linecap="round">
				    <path d="M262 244 C 210 226 168 196 132 168"/>
				    <path d="M348 262 C 398 258 440 250 472 238"/>
				    <path d="M266 372 C 222 386 186 408 152 436"/></g>
				  <g class="qil-ai-medallion" style="--qil-d:0s"><circle cx="112" cy="148" r="58" fill="#5fe3d6" opacity=".16" filter="url(#qilAiSoft)"/><circle cx="112" cy="148" r="42" fill="url(#qilAiGlass)" stroke="#ffffff" stroke-opacity=".9"/><circle cx="112" cy="148" r="42" fill="none" stroke="#41c9c2" stroke-opacity=".45"/><g transform="translate(86.4,122.4) scale(2.135)" fill="none" stroke="#0a6f79" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4v13M4 7l3-3 3 3M17 20V7M14 17l3 3 3-3"/></g></g>
				  <g class="qil-ai-medallion" style="--qil-d:-1.7s"><circle cx="486" cy="216" r="58" fill="#5fe3d6" opacity=".16" filter="url(#qilAiSoft)"/><circle cx="486" cy="216" r="42" fill="url(#qilAiGlass)" stroke="#ffffff" stroke-opacity=".9"/><circle cx="486" cy="216" r="42" fill="none" stroke="#41c9c2" stroke-opacity=".45"/><g transform="translate(480.4,190.4) scale(2.135)" fill="none" stroke="#0a6f79" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l1.6 5.1L19 9l-5.4 1.9L12 16l-1.6-5.1L5 9l5.4-1.9L12 2Z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/></g></g>
				  <g class="qil-ai-medallion" style="--qil-d:-3.1s"><circle cx="130" cy="458" r="58" fill="#5fe3d6" opacity=".16" filter="url(#qilAiSoft)"/><circle cx="130" cy="458" r="42" fill="url(#qilAiGlass)" stroke="#ffffff" stroke-opacity=".9"/><circle cx="130" cy="458" r="42" fill="none" stroke="#41c9c2" stroke-opacity=".45"/><g transform="translate(104.4,432.4) scale(2.135)" fill="none" stroke="#0a6f79" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h11v10H3zM14 10h3.2l2.8 3v3h-6z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></g></g>
				  <g class="qil-ai-motes" fill="#3fc9c0">
				    <circle cx="196" cy="118" r="3" opacity=".8"/><circle cx="456" cy="138" r="2.4" opacity=".6"/>
				    <circle cx="486" cy="392" r="3" opacity=".7"/><circle cx="228" cy="486" r="2.4" opacity=".55"/>
				    <circle cx="392" cy="462" r="2.6" opacity=".6"/><circle cx="150" cy="286" r="2.2" opacity=".5"/></g>
				<g class="qil-ai-labels" font-family="Inter,Manrope,Tajawal,sans-serif" font-size="13" font-weight="850" text-anchor="middle" fill="#0c5760">
									<?php
									$qil_concepts = array(
										array( 112, 214, 'COMPARE', 'مقارنة' ),
										array( 486, 282, 'PROTEIN MATCH', 'مطابقة البروتين' ),
										array( 130, 524, 'DELIVERY', 'التوصيل' ),
									);
									foreach ( $qil_concepts as $qil_concept ) :
										list( $qil_lx, $qil_ly, $qil_en, $qil_ar ) = $qil_concept;
										?>
										<text x="<?php echo (int) $qil_lx; ?>" y="<?php echo (int) $qil_ly; ?>" direction="<?php echo $context['isArabic'] ? 'rtl' : 'ltr'; ?>" letter-spacing="<?php echo $context['isArabic'] ? '0' : '.8'; ?>"><?php echo esc_html( $context['isArabic'] ? $qil_ar : $qil_en ); ?></text>
									<?php endforeach; ?>
								</g>
				</g>
				</svg>
				</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Product-intelligence rail.
 */
function qil_section_facts( array $args = array() ) {
	$context = qil_view_context();

	ob_start();
	?>
	<section class="qil-facts-rail" aria-label="<?php echo esc_attr( $context['isArabic'] ? 'ذكاء معلومات المنتجات' : 'Product intelligence' ); ?>">
		<div class="qil-container qil-facts-grid">
			<article><span>01</span><div><strong data-i18n="factsServing">Serving details</strong><small data-i18n="factsServingSub">Read from real product labels where available</small></div></article>
			<article><span>02</span><div><strong data-i18n="factsActives">Key actives</strong><small data-i18n="factsActivesSub">Shown with the listed amount, never guessed</small></div></article>
			<article><span>03</span><div><strong data-i18n="factsPrice">Live price + stock</strong><small data-i18n="factsPriceSub">Synced with WooCommerce and your market</small></div></article>
			<article><span>04</span><div><strong data-i18n="factsSafety">Clear uncertainty</strong><small data-i18n="factsSafetySub">Missing facts stay marked as not listed</small></div></article>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Compare Lab teaser.
 */
function qil_section_compare( array $args = array() ) {
	ob_start();
	?>
	<section id="qil-compare" class="qil-section qil-compare-section">
		<div class="qil-container qil-compare-grid">
			<div class="qil-section-heading qil-on-dark"><span class="qil-kicker" data-i18n="compareLab">COMPARE LAB</span><h2 data-i18n="compareTitle">Know the difference.<br>Choose with confidence.</h2><p data-i18n="compareLead">Choose two products and compare verified live details side by side.</p><button class="qil-button qil-button-light" type="button" data-qil-open-compare><span data-i18n="openCompare">Open comparison</span><svg><use href="#qil-i-arrow"/></svg></button></div>
			<div class="qil-compare-demo" data-qil-compare-showcase aria-hidden="true"></div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Three-step method.
 */
function qil_section_method( array $args = array() ) {
	ob_start();
	?>
	<section id="qil-method" class="qil-section qil-method-section">
		<div class="qil-container">
			<div class="qil-section-heading qil-centered"><span class="qil-kicker" data-i18n="methodKicker">CLEAR BY DESIGN</span><h2 data-i18n="methodTitle">From goal to shortlist in three steps.</h2></div>
			<div class="qil-steps">
				<article><span class="qil-step-number">01</span><div class="qil-step-icon"><svg><use href="#qil-i-wellness"/></svg></div><h3 data-i18n="stepOne">Set your outcome</h3><p data-i18n="stepOneSub">Choose what you want to support. We keep the questions non-medical and focused.</p></article>
				<article><span class="qil-step-number">02</span><div class="qil-step-icon"><svg><use href="#qil-i-spark"/></svg></div><h3 data-i18n="stepTwo">See the logic</h3><p data-i18n="stepTwoSub">Every match shows why it appears, its key facts, servings and value.</p></article>
				<article><span class="qil-step-number">03</span><div class="qil-step-icon"><svg><use href="#qil-i-message"/></svg></div><h3 data-i18n="stepThree">Ask Qimia AI live</h3><p data-i18n="stepThreeSub">Continue in the live assistant without leaving the store or losing your context.</p></article>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * AI routine builder.
 */
function qil_section_routine( array $args = array() ) {
	$context = qil_view_context();

	ob_start();
	?>
	<section class="qil-section qil-stack-section">
		<div class="qil-container qil-stack-card">
			<div class="qil-stack-visual">
				<div class="qil-stack-scene" data-qil-stack-scene aria-hidden="true">
				<svg class="qil-stack-art" viewBox="0 0 640 560" role="img" aria-hidden="true" preserveAspectRatio="xMidYMid slice" focusable="false">
				<defs>
				<linearGradient id="qilSkyGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#04222b"/><stop offset="55%" stop-color="#073c48"/><stop offset="100%" stop-color="#02171e"/></linearGradient>
				<radialGradient id="qilAuroraA" cx="26%" cy="34%" r="46%"><stop offset="0" stop-color="#38d3cd" stop-opacity=".55"/><stop offset="100%" stop-color="#38d3cd" stop-opacity="0"/></radialGradient>
				<radialGradient id="qilAuroraB" cx="76%" cy="74%" r="52%"><stop offset="0" stop-color="#6ee7d6" stop-opacity=".26"/><stop offset="100%" stop-color="#6ee7d6" stop-opacity="0"/></radialGradient>
				<linearGradient id="qilFigure" gradientUnits="userSpaceOnUse" x1="380" y1="230" x2="566" y2="400">
				<stop offset="0" stop-color="#8ff2e6"/><stop offset="9%" stop-color="#2ba7ad"/><stop offset="30%" stop-color="#0a4b56"/><stop offset="62%" stop-color="#05242d"/><stop offset="100%" stop-color="#020f14"/></linearGradient>
				<linearGradient id="qilKit" gradientUnits="userSpaceOnUse" x1="440" y1="190" x2="540" y2="350">
				<stop offset="0" stop-color="#c9fbf2" stop-opacity=".4"/><stop offset="100%" stop-color="#2ab6b2" stop-opacity=".06"/></linearGradient>
				<linearGradient id="qilBeam" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#6ff0e2" stop-opacity="0"/><stop offset="45%" stop-color="#6ff0e2" stop-opacity=".45"/><stop offset="100%" stop-color="#bffaf1" stop-opacity=".9"/></linearGradient>
				<linearGradient id="qilPack" x1="0" y1="0" x2="0.6" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="55%" stop-color="#9defe2"/><stop offset="100%" stop-color="#2ab6b2"/></linearGradient>
				<filter id="qilSoft" x="-45%" y="-45%" width="190%" height="190%"><feGaussianBlur stdDeviation="15"/></filter>
				<filter id="qilTight" x="-45%" y="-45%" width="190%" height="190%"><feGaussianBlur stdDeviation="6"/></filter>
				<filter id="qilGhost" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="9"/></filter>
				<pattern id="qilGrid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" fill="none" stroke="#7fe6dd" stroke-opacity=".1" stroke-width="1"/></pattern>
				<clipPath id="qilStageClip"><rect x="0" y="0" width="640" height="560" rx="30"/></clipPath>
				</defs>
				<g clip-path="url(#qilStageClip)">
				  <rect width="640" height="560" fill="url(#qilSkyGrad)"/>
				  <rect width="640" height="560" fill="url(#qilGrid)"/>
				  <rect width="640" height="560" fill="url(#qilAuroraA)"/>
				  <rect width="640" height="560" fill="url(#qilAuroraB)"/>
				  <g class="qil-art-rings" fill="none" stroke="#7fe6dd" stroke-opacity=".24">
				    <circle cx="206" cy="248" r="126"/><circle cx="206" cy="248" r="166" stroke-opacity=".13"/><circle cx="206" cy="248" r="204" stroke-opacity=".07"/></g>
				  <ellipse cx="206" cy="248" rx="118" ry="118" fill="#4fe0d4" opacity=".17" filter="url(#qilSoft)"/>
				  <!-- depth: a soft second silhouette behind the subject -->
				  <g class="qil-art-ghost" fill="#0a4652" opacity=".38" filter="url(#qilGhost)" transform="translate(-58,16) scale(1.06) translate(-26,-22)"><g class="qil-art-hair"><path d="M 466 124 C 480 116 500 118 508 130 C 512 138 511 146 508 150 C 500 136 484 130 468 134 C 464 136 462 132 466 124 Z"/><path d="M 461.0 132.9 L 445.4 177.7 L 458.3 183.0 L 478.6 140.1 Z"/><path d="M 445.2 181.8 L 454.1 215.0 L 462.0 213.6 L 459.0 179.4 Z"/><circle cx="470" cy="136" r="9.5"/><circle cx="452" cy="180" r="7"/><circle cx="458" cy="214" r="4"/></g><path d="M 476 162 h 20 v 30 h -20 Z"/><path d="M 435.7 195.7 L 393.2 246.6 L 409.7 262.5 L 458.8 217.8 Z"/><path d="M 394.1 245.6 L 352.2 284.8 L 363.1 297.8 L 408.9 263.2 Z"/><path d="M 509.8 213.4 L 538.7 268.8 L 558.0 260.9 L 539.3 201.2 Z"/><path d="M 537.5 263.2 L 532.5 325.4 L 547.4 327.3 L 558.3 265.8 Z"/><path d="M 449.1 328.3 L 441.6 410.9 L 466.1 415.1 L 486.4 334.7 Z"/><path d="M 441.5 411.7 L 440.0 485.8 L 455.9 487.1 L 466.4 413.8 Z"/><path d="M 489.2 332.8 L 501.6 415.9 L 526.5 414.1 L 527.0 330.1 Z"/><path d="M 501.7 416.1 L 514.1 489.3 L 530.0 487.6 L 526.5 413.4 Z"/><path d="M 442.2 479.6 L 425.4 492.2 L 431.6 502.4 L 450.5 493.2 Z"/><path d="M 518.9 495.4 L 540.7 504.5 L 546.2 493.9 L 526.3 481.3 Z"/><circle cx="448" cy="206" r="16"/><circle cx="402" cy="254" r="11.5"/><circle cx="358" cy="291" r="8.5"/><circle cx="524" cy="206" r="16"/><circle cx="548" cy="264" r="10.5"/><circle cx="540" cy="326" r="7.5"/><circle cx="468" cy="330" r="19"/><circle cx="454" cy="412" r="12.5"/><circle cx="448" cy="486" r="8"/><circle cx="508" cy="330" r="19"/><circle cx="514" cy="414" r="12.5"/><circle cx="522" cy="488" r="8"/><path d="M 446 208 C 452 194 468 186 486 186 C 504 186 520 194 526 208 C 530 232 522 260 508 286 C 519 302 521 316 517 332 C 499 342 471 342 455 332 C 451 316 453 302 464 286 C 450 260 442 232 446 208 Z"/><circle cx="488" cy="142" r="25"/></g>
				  <g class="qil-art-data" fill="none" stroke="#8df0e4" stroke-opacity=".38" stroke-width="1.4" stroke-linecap="round">
				    <path d="M52 432 C 124 430 148 328 200 296"/><path d="M70 122 C 136 146 156 208 198 232"/><path d="M318 476 C 268 452 244 356 218 302"/></g>
				  <g class="qil-art-motes" fill="#c9fbf2">
				    <circle cx="92" cy="406" r="3.2"/><circle cx="130" cy="376" r="2.2" opacity=".7"/><circle cx="98" cy="164" r="2.8" opacity=".8"/>
				    <circle cx="148" cy="200" r="2" opacity=".6"/><circle cx="292" cy="450" r="2.6" opacity=".75"/><circle cx="258" cy="394" r="2" opacity=".55"/></g>
				  <path class="qil-art-beam" d="M232 266 C 286 274 312 286 344 290 L 344 306 C 310 306 284 300 232 292 Z" fill="url(#qilBeam)" opacity=".8"/>
				  <g class="qil-art-figure">
				    <ellipse cx="486" cy="502" rx="94" ry="15" fill="#02141a" opacity=".55" filter="url(#qilTight)"/>
				    <g fill="url(#qilFigure)"><g class="qil-art-hair" fill="#06323c"><path d="M 466 124 C 480 116 500 118 508 130 C 512 138 511 146 508 150 C 500 136 484 130 468 134 C 464 136 462 132 466 124 Z"/><path d="M 461.0 132.9 L 445.4 177.7 L 458.3 183.0 L 478.6 140.1 Z"/><path d="M 445.2 181.8 L 454.1 215.0 L 462.0 213.6 L 459.0 179.4 Z"/><circle cx="470" cy="136" r="9.5"/><circle cx="452" cy="180" r="7"/><circle cx="458" cy="214" r="4"/></g><path d="M 476 162 h 20 v 30 h -20 Z"/><path d="M 435.7 195.7 L 393.2 246.6 L 409.7 262.5 L 458.8 217.8 Z"/><path d="M 394.1 245.6 L 352.2 284.8 L 363.1 297.8 L 408.9 263.2 Z"/><path d="M 509.8 213.4 L 538.7 268.8 L 558.0 260.9 L 539.3 201.2 Z"/><path d="M 537.5 263.2 L 532.5 325.4 L 547.4 327.3 L 558.3 265.8 Z"/><path d="M 449.1 328.3 L 441.6 410.9 L 466.1 415.1 L 486.4 334.7 Z"/><path d="M 441.5 411.7 L 440.0 485.8 L 455.9 487.1 L 466.4 413.8 Z"/><path d="M 489.2 332.8 L 501.6 415.9 L 526.5 414.1 L 527.0 330.1 Z"/><path d="M 501.7 416.1 L 514.1 489.3 L 530.0 487.6 L 526.5 413.4 Z"/><path d="M 442.2 479.6 L 425.4 492.2 L 431.6 502.4 L 450.5 493.2 Z"/><path d="M 518.9 495.4 L 540.7 504.5 L 546.2 493.9 L 526.3 481.3 Z"/><circle cx="448" cy="206" r="16"/><circle cx="402" cy="254" r="11.5"/><circle cx="358" cy="291" r="8.5"/><circle cx="524" cy="206" r="16"/><circle cx="548" cy="264" r="10.5"/><circle cx="540" cy="326" r="7.5"/><circle cx="468" cy="330" r="19"/><circle cx="454" cy="412" r="12.5"/><circle cx="448" cy="486" r="8"/><circle cx="508" cy="330" r="19"/><circle cx="514" cy="414" r="12.5"/><circle cx="522" cy="488" r="8"/><path d="M 446 208 C 452 194 468 186 486 186 C 504 186 520 194 526 208 C 530 232 522 260 508 286 C 519 302 521 316 517 332 C 499 342 471 342 455 332 C 451 316 453 302 464 286 C 450 260 442 232 446 208 Z"/><circle cx="488" cy="142" r="25"/></g>
				    <path d="M454 206 C 468 216 506 216 520 206 L 514 254 C 496 262 476 262 460 254 Z" fill="url(#qilKit)"/>
				    <path d="M457 332 C 475 340 499 340 517 332 L 513 376 C 495 384 479 384 461 376 Z" fill="url(#qilKit)"/>
				  </g>
				  <g class="qil-art-pack">
				    <ellipse cx="342" cy="296" rx="64" ry="64" fill="#8df0e4" opacity=".22" filter="url(#qilSoft)"/>
				    <rect x="306" y="260" width="72" height="72" rx="20" fill="url(#qilPack)"/>
				    <rect x="306" y="260" width="72" height="72" rx="20" fill="none" stroke="#ffffff" stroke-opacity=".85"/>
				    <path d="M342 260 V332 M306 296 H378" stroke="#0a6a70" stroke-opacity=".32" stroke-width="2"/>
				    <rect x="328" y="282" width="28" height="28" rx="9" fill="#ffffff" opacity=".92"/>
				    <path d="M335 296 l5 5 10 -11" fill="none" stroke="#0b7f89" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></g>
				  <g class="qil-art-chips" font-family="Inter,Manrope,Tajawal,sans-serif" font-size="12" font-weight="800">
					<?php
					$qil_readouts = array(
						array( 36, 56, 150, 'LABEL READ', 'قراءة الملصق' ),
						array( 36, 316, 168, 'DOSE MATCHED', 'مطابقة الجرعة' ),
						array( 404, 72, 164, 'BUDGET KEPT', 'ضمن الميزانية' ),
					);
					foreach ( $qil_readouts as $qil_chip ) :
						list( $qil_cx, $qil_cy, $qil_cw, $qil_label_en, $qil_label_ar ) = $qil_chip;
						// SVG <text> inherits the document direction, which pushed the
						// Arabic and English labels straight out of their chips on the
						// RTL route. Anchor each label to the side its script starts on.
						$qil_dot_x   = $context['isArabic'] ? $qil_cw - 20 : 19;
						$qil_text_x  = $context['isArabic'] ? $qil_cw - 42 : 34;
						$qil_anchor  = $context['isArabic'] ? 'end' : 'start';
						$qil_dir     = $context['isArabic'] ? 'rtl' : 'ltr';
						?>
						<g transform="translate(<?php echo (int) $qil_cx; ?>,<?php echo (int) $qil_cy; ?>)">
							<rect width="<?php echo (int) $qil_cw; ?>" height="34" rx="17" fill="#ffffff" fill-opacity=".1" stroke="#8df0e4" stroke-opacity=".3"/>
							<circle cx="<?php echo (int) $qil_dot_x; ?>" cy="17" r="4.5" fill="#5ee9b4"/>
							<text x="<?php echo (int) $qil_text_x; ?>" y="22" fill="#d6fbf5" text-anchor="<?php echo esc_attr( $qil_anchor ); ?>" direction="<?php echo esc_attr( $qil_dir ); ?>" letter-spacing="<?php echo $context['isArabic'] ? '0' : '1'; ?>"><?php echo esc_html( $context['isArabic'] ? $qil_label_ar : $qil_label_en ); ?></text>
						</g>
					<?php endforeach; ?>
				</g>
				</g>
				</svg>
					<img class="qil-stack-q" src="<?php echo esc_url( QIL_URL . 'assets/qimia-hero-orbit-v9.webp' ); ?>" alt="" width="900" height="935" loading="lazy" decoding="async">
				</div>
				<div class="qil-routine-products" data-qil-routine-products aria-label="<?php echo esc_attr( $context['isArabic'] ? 'منتجات كيميا المقترحة لبناء روتين' : 'Real Qimia products for a routine' ); ?>"></div>
			</div>
			<div class="qil-stack-copy"><span class="qil-kicker" data-i18n="stackKicker">AI ROUTINE BUILDER</span><h2 data-i18n="stackTitle">Turn products into a simple routine.</h2><p data-i18n="stackLead">Use Qimia AI to organise a clear morning, training and evening plan around one goal and one budget. No diagnosis. No unnecessary extras.</p><button class="qil-button qil-button-primary" type="button" data-qimia-ai-open data-qil-ai-intent="routine"><span data-i18n="buildRoutine">Build with Qimia AI</span><span class="qil-button-meta" data-i18n="live">LIVE</span><svg><use href="#qil-i-arrow"/></svg></button></div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Trust grid.
 */
function qil_section_trust( array $args = array() ) {
	ob_start();
	?>
	<section class="qil-section qil-trust-section">
		<div class="qil-container">
			<div class="qil-trust-head"><div><span class="qil-kicker" data-i18n="trustKicker">QIMIA STANDARD</span><h2 data-i18n="trustTitle">Digital intelligence.<br>Local accountability.</h2></div><p data-i18n="trustLead">Technology should make wellness shopping more transparent—not more confusing. Every recommendation remains connected to a real product and a real local team.</p></div>
			<div class="qil-trust-grid">
				<article><span>01</span><svg><use href="#qil-i-shield"/></svg><h3 data-i18n="authentic">Authentic products</h3><p data-i18n="authenticSub">Sourced through trusted distribution channels.</p></article>
				<article><span>02</span><svg><use href="#qil-i-check"/></svg><h3 data-i18n="factsFirst">Facts before hype</h3><p data-i18n="factsFirstSub">Labels, servings and product details in plain language.</p></article>
				<article><span>03</span><svg><use href="#qil-i-message"/></svg><h3 data-i18n="localSupport">Live store intelligence</h3><p data-i18n="localSupportSub">AI stays connected to current Qimia products, stock and prices.</p></article>
				<article><span>04</span><svg><use href="#qil-i-recovery"/></svg><h3 data-i18n="gccDelivery">Oman & GCC delivery</h3><p data-i18n="gccDeliverySub">Availability and fulfilment from a regional store.</p></article>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Intelligent footer.
 */
function qil_section_footer( array $args = array() ) {
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];

	ob_start();
	?>
	<footer class="qil-footer">
		<div class="qil-container qil-footer-intelligence">
			<div>
				<small><?php echo esc_html( $is_arabic ? 'ذكاء المتجر المباشر' : 'LIVE STORE INTELLIGENCE' ); ?></small>
				<h2><?php echo esc_html( $is_arabic ? 'قرار أوضح، من دون مغادرة كيميا.' : 'A clearer decision, without leaving Qimia.' ); ?></h2>
				<p><?php echo esc_html( $is_arabic ? 'اختر هدفك، قارن الحقائق المباشرة، ثم تابع مع ذكاء كيميا ضمن الجلسة نفسها.' : 'Choose a goal, compare live facts, then continue with Qimia AI in the same session.' ); ?></p>
			</div>
			<div class="qil-footer-intelligence-actions">
				<button class="qil-button qil-button-primary" type="button" data-qimia-ai-open><svg><use href="#qil-i-spark"/></svg><span><?php echo esc_html( $is_arabic ? 'اسأل ذكاء كيميا' : 'Ask Qimia AI' ); ?></span></button>
				<a class="qil-button qil-button-ghost" href="<?php echo esc_url( $context['shopUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'تسوّق الآن' : 'Shop now' ); ?><svg><use href="#qil-i-arrow"/></svg></a>
			</div>
		</div>
		<div class="qil-container qil-footer-grid qil-footer-directory">
			<div class="qil-footer-brand"><a class="qil-brand qil-brand-light" href="<?php echo esc_url( $context['homeUrl'] ); ?>"><span class="qil-wp-logo"><?php echo wp_kses_post( $context['logo'] ); ?></span></a><p data-i18n="footerLine">Smarter supplement choices, grounded in real products and human care.</p><strong><?php echo esc_html( $is_arabic ? 'مسقط · سلطنة عُمان' : 'Muscat · Sultanate of Oman' ); ?></strong></div>
			<nav class="qil-footer-links" aria-label="<?php echo esc_attr( $is_arabic ? 'روابط التسوق' : 'Shopping links' ); ?>">
				<h3><?php echo esc_html( $is_arabic ? 'تسوّق' : 'Shop' ); ?></h3>
				<a href="<?php echo esc_url( $context['shopUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'جميع المنتجات' : 'All products' ); ?></a>
				<a href="<?php echo esc_url( $context['proteinUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'البروتين' : 'Protein' ); ?></a>
				<a href="<?php echo esc_url( $context['creatineUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'الكرياتين' : 'Creatine' ); ?></a>
				<a href="<?php echo esc_url( $context['fatBurnerUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'دعم إدارة الوزن' : 'Weight support' ); ?></a>
				<a href="<?php echo esc_url( $context['bestSellersUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'الأكثر مبيعاً' : 'Best sellers' ); ?></a>
			</nav>
			<nav class="qil-footer-links" aria-label="<?php echo esc_attr( $is_arabic ? 'روابط كيميا الذكية' : 'Qimia intelligence links' ); ?>">
				<h3><?php echo esc_html( $is_arabic ? 'كيميا الذكية' : 'Qimia intelligence' ); ?></h3>
				<a href="#qil-match"><?php echo esc_html( $is_arabic ? 'اختر حسب الهدف' : 'Explore by goal' ); ?></a>
				<a href="#qil-compare"><?php echo esc_html( $is_arabic ? 'مقارنة المنتجات' : 'Compare products' ); ?></a>
				<a href="#qil-ai" data-qimia-ai-open><?php echo esc_html( $is_arabic ? 'اسأل ذكاء كيميا' : 'Ask Qimia AI' ); ?></a>
				<a href="<?php echo esc_url( $context['wishlistUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'المفضلة' : 'Wishlist' ); ?></a>
				<a href="<?php echo esc_url( $context['accountUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'حسابي' : 'My account' ); ?></a>
			</nav>
			<nav class="qil-footer-links" aria-label="<?php echo esc_attr( $is_arabic ? 'روابط المساعدة' : 'Help links' ); ?>">
				<h3><?php echo esc_html( $is_arabic ? 'المساعدة' : 'Help' ); ?></h3>
				<a href="<?php echo esc_url( $context['aboutUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'من نحن' : 'About us' ); ?></a>
				<a href="<?php echo esc_url( $context['contactUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'تواصل معنا' : 'Contact us' ); ?></a>
				<a href="<?php echo esc_url( $context['blogUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'المدونة' : 'Blog' ); ?></a>
				<a href="<?php echo esc_url( $context['returnsUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'الإرجاع والاسترداد' : 'Returns & refunds' ); ?></a>
				<a href="<?php echo esc_url( $context['privacyUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'الخصوصية' : 'Privacy' ); ?></a>
			</nav>
		</div>
		<div class="qil-container qil-footer-meta"><span><?php echo esc_html( $is_arabic ? 'توصيل داخل عُمان ودول الخليج' : 'Oman & GCC delivery' ); ?></span><span>© <?php echo esc_html( gmdate( 'Y' ) ); ?> Qimia</span></div>
		<div class="qil-container qil-disclaimer" data-i18n="disclaimer">Qimia Intelligence Lab provides shopping guidance only and does not diagnose, treat or replace medical advice.</div>
	</footer>
	<?php
	return (string) ob_get_clean();
}

/**
 * Compare dock, comparison modal and toast.
 */
function qil_section_docks( array $args = array() ) {
	$context = qil_view_context();

	ob_start();
	?>
	<div class="qil-compare-dock" data-qil-compare-dock hidden>
		<div><span data-i18n="compareProducts">Compare products</span><div data-qil-compare-thumbs></div></div>
		<button class="qil-button qil-button-primary qil-button-small" type="button" data-qil-open-compare><span data-i18n="compareNow">Compare now</span><span data-qil-compare-count>0/2</span></button>
	</div>

	<div class="qil-modal" data-qil-compare-modal hidden aria-hidden="true">
		<div class="qil-modal-backdrop" data-qil-modal-close></div>
		<div class="qil-modal-panel qil-compare-panel" role="dialog" aria-modal="true" aria-labelledby="qil-compare-title">
			<button class="qil-modal-close" type="button" data-qil-modal-close aria-label="<?php echo esc_attr( $context['isArabic'] ? 'إغلاق' : 'Close' ); ?>"><svg><use href="#qil-i-close"/></svg></button>
			<span class="qil-kicker" data-i18n="compareLab">COMPARE LAB</span><h2 id="qil-compare-title" data-i18n="sideBySide">Your shortlist, side by side.</h2>
			<div data-qil-compare-table></div>
		</div>
	</div>

	<div class="qil-toast" data-qil-toast role="status" aria-live="polite" hidden></div>
	<?php
	return (string) ob_get_clean();
}
