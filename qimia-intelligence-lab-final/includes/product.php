<?php
/**
 * Qimia Intelligence Lab — product page.
 *
 * The page is built from the same catalogue record the homepage cards use, so a
 * product reads identically wherever it appears: one price block, one badge
 * system, one button. Variations run on the quick view's engine, rendered
 * inline instead of in a modal.
 *
 * @package Qimia_Intelligence_Lab
 */

defined( 'ABSPATH' ) || exit;

/**
 * The catalogue record for one product, plus the products shown beside it.
 *
 * Related products share a category and an explicit canonical purpose.
 * Missing matches remain empty; unrelated best sellers are not substituted.
 */
function qil_product_page_data( $product_id ) {
	$product_id = (int) $product_id;
	if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
		return array( 'product' => null, 'related' => array() );
	}

	$records = qil_get_catalogue(
		array(
			'include' => array( $product_id ),
			'limit'   => 1,
			'orderby' => 'include',
			'_qil_include_unavailable' => true,
		)
	);
	$record = isset( $records[0] ) ? $records[0] : null;
	if ( ! $record ) {
		return array( 'product' => null, 'related' => array() );
	}

	$related     = array();
	$category_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
	$category_ids = is_wp_error( $category_ids ) ? array() : array_map( 'absint', $category_ids );
	if ( $category_ids ) {
		$related = qil_get_catalogue(
			array(
				'limit'    => 9,
				'exclude'  => array( $product_id ),
				'category' => array_values(
					array_filter(
						array_map(
							static function ( $term_id ) {
								$term = get_term( $term_id, 'product_cat' );
								return ( $term && ! is_wp_error( $term ) ) ? $term->slug : '';
							},
							$category_ids
						)
					)
				),
				'orderby'  => 'popularity',
			)
		);
	}
	// An unrelated bestseller is not an alternative. Keep only shared, explicit purposes.
	$purposes = array_column( $record['match']['evidence'] ?? array(), 0 );
	$related = array_values( array_filter( $related, static function ( $candidate ) use ( $purposes ) {
		return (bool) array_intersect( $purposes, array_column( $candidate['match']['evidence'] ?? array(), 0 ) );
	} ) );

	return array( 'product' => $record, 'related' => array_slice( $related, 0, 8 ) );
}

/**
 * Label facts worth printing as their own list, taken from the same segment
 * extractor the cards use so the wording never disagrees with a card.
 */
function qil_product_label_rows( array $record, $is_arabic ) {
	$rows  = array();
	$facts = isset( $record['facts'] ) && is_array( $record['facts'] ) ? $record['facts'] : array();

	if ( ! empty( $facts['servings'] ) ) {
		$rows[] = array( 'label' => $is_arabic ? 'عدد الحصص' : 'Servings', 'value' => $facts['servings'] );
	}
	if ( ! empty( $facts['servingSize'] ) ) {
		$rows[] = array( 'label' => $is_arabic ? 'حجم الحصة' : 'Serving size', 'value' => $facts['servingSize'] );
	}
	if ( ! empty( $facts['protein'] ) ) {
		$rows[] = array( 'label' => $is_arabic ? 'البروتين لكل حصة' : 'Protein per serving', 'value' => is_array( $facts['protein'] ) ? ( $facts['protein']['amount'] ?? $facts['protein']['formatted'] ?? '' ) : $facts['protein'] );
	}
	if ( ! empty( $facts['creatine'] ) ) {
		$rows[] = array( 'label' => $is_arabic ? 'الكرياتين لكل حصة' : 'Creatine per serving', 'value' => is_array( $facts['creatine'] ) ? ( $facts['creatine']['amount'] ?? $facts['creatine']['formatted'] ?? '' ) : $facts['creatine'] );
	}
	if ( ! empty( $facts['collagen'] ) ) {
		$rows[] = array( 'label' => $is_arabic ? 'الكولاجين لكل حصة' : 'Collagen per serving', 'value' => is_array( $facts['collagen'] ) ? ( $facts['collagen']['amount'] ?? $facts['collagen']['formatted'] ?? '' ) : $facts['collagen'] );
	}
	if ( ! empty( $facts['caffeine']['label'] ) && 'known' === ( isset( $facts['caffeine']['state'] ) ? $facts['caffeine']['state'] : '' ) ) {
		$rows[] = array( 'label' => $is_arabic ? 'الكافيين' : 'Caffeine', 'value' => $facts['caffeine']['label'] );
	}

	return $rows;
}

/**
 * The product page body: gallery, purchase panel, label facts, description.
 */
function qil_section_product( array $args = array() ) {
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];
	$record    = isset( $args['record'] ) && is_array( $args['record'] ) ? $args['record'] : null;
	$product   = isset( $args['product'] ) ? $args['product'] : null;
	if ( ! $record || ! is_a( $product, 'WC_Product' ) ) {
		return '';
	}

	$images = isset( $record['images'] ) && is_array( $record['images'] ) ? $record['images'] : array();
	$primary = isset( $images[0] ) ? $images[0] : array();
	$brand   = isset( $record['brand'] ) ? (string) $record['brand'] : '';
	$price   = isset( $record['price'] ) && is_array( $record['price'] ) ? $record['price'] : array();
	$promotion = isset( $record['promotion'] ) && is_array( $record['promotion'] ) ? $record['promotion'] : array();
	$stock   = isset( $record['stock'] ) && is_array( $record['stock'] ) ? $record['stock'] : array();
	$rows    = qil_product_label_rows( $record, $is_arabic );
	$market  = function_exists( 'qil_market_context' ) ? qil_market_context( $is_arabic ) : array();
	$delivery_note = isset( $market['deliveryShort'] ) ? (string) $market['deliveryShort'] : '';
	$actives = isset( $record['facts']['primaryActives'] ) && is_array( $record['facts']['primaryActives'] ) ? $record['facts']['primaryActives'] : array();
	$is_variable = 'variable' === ( isset( $record['type'] ) ? $record['type'] : '' );

	$segments = qil_fact_segments( $product->get_description() . "\n" . $product->get_short_description() );
	$segments = array_slice( array_values( array_filter( $segments ) ), 0, 6 );
	$attributes = qil_product_attributes( $product );

	$discount = isset( $price['savingsPct'] ) ? (int) round( (float) $price['savingsPct'] ) : 0;
	$is_flash = ! empty( $promotion['isFlash'] );

	ob_start();
	?>
	<section class="qil-section qil-pdp" aria-label="<?php echo esc_attr( $is_arabic ? 'تفاصيل المنتج' : 'Product detail' ); ?>">
		<div class="qil-container">
			<nav class="qil-pdp-crumbs" aria-label="<?php echo esc_attr( $is_arabic ? 'مسار التنقل' : 'Breadcrumb' ); ?>">
				<a href="<?php echo esc_url( $context['homeUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'الرئيسية' : 'Home' ); ?></a>
				<svg aria-hidden="true"><use href="#qil-i-arrow"/></svg>
				<a href="<?php echo esc_url( $context['shopUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'المتجر' : 'Shop' ); ?></a>
				<?php if ( ! empty( $record['categories'][0] ) ) : ?>
					<svg aria-hidden="true"><use href="#qil-i-arrow"/></svg>
					<span><?php echo esc_html( $record['categories'][0] ); ?></span>
				<?php endif; ?>
			</nav>

			<div class="qil-pdp-layout">
				<div class="qil-pdp-media">
					<figure class="qil-pdp-stage">
						<?php if ( $is_flash || $discount > 0 ) : ?>
							<span class="qil-sale-badge<?php echo $is_flash ? ' qil-flash-sale-badge' : ''; ?>">
								<?php
								if ( $is_flash ) {
									echo esc_html( $is_arabic ? 'عرض خاطف' : 'Flash sale' );
									if ( $discount > 0 ) {
										echo esc_html( ' · −' . $discount . ( $is_arabic ? '٪' : '%' ) );
									}
								} else {
									echo esc_html( '−' . $discount . ( $is_arabic ? '٪' : '%' ) );
								}
								?>
							</span>
						<?php endif; ?>
						<img class="qil-pdp-photo" data-qil-pdp-photo
							src="<?php echo esc_url( isset( $primary['src'] ) ? $primary['src'] : '' ); ?>"
							<?php if ( ! empty( $primary['srcset'] ) ) : ?>srcset="<?php echo esc_attr( $primary['srcset'] ); ?>" sizes="(max-width: 980px) 92vw, 46vw"<?php endif; ?>
							alt="<?php echo esc_attr( $record['name'] ); ?>" width="<?php echo (int) ( isset( $primary['width'] ) ? $primary['width'] : 640 ); ?>" height="<?php echo (int) ( isset( $primary['height'] ) ? $primary['height'] : 640 ); ?>" decoding="async" fetchpriority="high">
					</figure>
					<?php if ( count( $images ) > 1 ) : ?>
						<div class="qil-pdp-thumbs" role="list">
							<?php foreach ( array_slice( $images, 0, 6 ) as $index => $image ) : ?>
								<button class="qil-pdp-thumb<?php echo 0 === $index ? ' is-active' : ''; ?>" type="button" role="listitem"
									data-qil-pdp-thumb="<?php echo esc_url( isset( $image['src'] ) ? $image['src'] : '' ); ?>"
									data-qil-pdp-srcset="<?php echo esc_attr( isset( $image['srcset'] ) ? $image['srcset'] : '' ); ?>"
									aria-label="<?php echo esc_attr( sprintf( $is_arabic ? 'الصورة %d' : 'Image %d', $index + 1 ) ); ?>">
									<img src="<?php echo esc_url( isset( $image['src'] ) ? $image['src'] : '' ); ?>" alt="" width="120" height="120" loading="lazy" decoding="async">
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="qil-pdp-panel">
					<?php if ( '' !== $brand ) : ?>
						<span class="qil-pdp-brand"><i></i><?php echo esc_html( $brand ); ?></span>
					<?php endif; ?>
					<h1 class="qil-pdp-title"><?php echo esc_html( $record['name'] ); ?></h1>

					<div class="qil-pdp-price qil-card-price" dir="ltr">
						<span class="qil-price-values">
							<?php if ( $is_variable ) : ?>
								<span class="qil-price-from"><?php echo esc_html( $is_arabic ? 'يبدأ من' : 'From' ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $price['onSale'] ) && ! empty( $price['regular']['formatted'] ) ) : ?>
								<span class="qil-regular-price"><?php echo esc_html( $price['regular']['formatted'] ); ?></span>
							<?php endif; ?>
							<span class="qil-current-price"><?php echo esc_html( isset( $price['formatted'] ) ? $price['formatted'] : '' ); ?></span>
						</span>
						<?php if ( ! empty( $price['perServingFormatted'] ) ) : ?>
							<small class="qil-per-serving"><?php echo esc_html( sprintf( $is_arabic ? '%s لكل حصة' : '%s per serving', $price['perServingFormatted'] ) ); ?></small>
						<?php endif; ?>
					</div>

					<p class="qil-pdp-stock<?php echo empty( $stock['inStock'] ) ? ' is-out' : ''; ?>">
						<span></span><?php echo esc_html( isset( $stock['label'] ) ? $stock['label'] : '' ); ?>
						<?php if ( ! empty( $stock['quantity'] ) && $stock['quantity'] <= 8 ) : ?>
							<em><?php echo esc_html( sprintf( $is_arabic ? 'بقي %d فقط' : 'only %d left', (int) $stock['quantity'] ) ); ?></em>
						<?php endif; ?>
					</p>

					<?php if ( $is_variable ) : ?>
						<div class="qil-pdp-options qil-qv-form" data-qil-variation-panel data-qil-product-id="<?php echo esc_attr( $record['id'] ); ?>" hidden>
							<div data-qil-variation-fields></div>
							<p class="qil-qv-status" data-qil-qv-message role="status" aria-live="polite"><?php echo esc_html( $is_arabic ? 'اختر كل الخيارات' : 'Select every option' ); ?></p>
							<div class="qil-pdp-actions">
								<a class="qil-buy qil-qv-add is-disabled" data-qil-qv-add data-qil-parent-id="<?php echo esc_attr( $record['id'] ); ?>" href="<?php echo esc_url( $record['url'] ); ?>" aria-disabled="true" tabindex="-1" rel="nofollow">
									<svg aria-hidden="true"><use href="#qil-i-cart-plus"/></svg><span><?php echo esc_html( $is_arabic ? 'أضف المحدد إلى السلة' : 'Add selected to bag' ); ?></span>
								</a>
							</div>
						</div>
					<?php elseif ( 'simple' === $record['type'] && ! empty( $stock['inStock'] ) && ! empty( $record['purchase']['purchasable'] ) ) : ?>
						<div class="qil-pdp-actions">
							<a class="qil-buy button product_type_simple add_to_cart_button ajax_add_to_cart" href="<?php echo esc_url( isset( $record['purchase']['url'] ) ? $record['purchase']['url'] : $record['url'] ); ?>"
								data-product_id="<?php echo esc_attr( $record['id'] ); ?>" data-quantity="1" rel="nofollow"
								aria-label="<?php echo esc_attr( sprintf( $is_arabic ? 'أضف %s إلى السلة' : 'Add %s to bag', $record['name'] ) ); ?>">
								<svg aria-hidden="true"><use href="#qil-i-cart-plus"/></svg><span><?php echo esc_html( $is_arabic ? 'أضف إلى السلة' : 'Add to bag' ); ?></span>
							</a>
						</div>
					<?php else : ?>
						<p class="qil-pdp-stock"><?php echo esc_html( $is_arabic ? 'راجع خيارات الشراء الأصلية للمنتج؛ قد لا يكون متاحاً الآن.' : 'Use this product’s original purchase options; it may be unavailable right now.' ); ?></p>
					<?php endif; ?>

					<button class="qil-ask-product" type="button" data-qimia-ai-open data-qil-ai-intent="product" data-qil-ai-product="<?php echo esc_attr( $record['name'] ); ?>" data-qimia-product-id="<?php echo esc_attr( $record['id'] ); ?>">
						<svg aria-hidden="true"><use href="#qil-i-spark"/></svg>
						<span><?php echo esc_html( $is_arabic ? 'اسأل ذكاء كيميا عن هذا المنتج' : 'Ask Qimia AI about this product' ); ?></span>
						<svg aria-hidden="true"><use href="#qil-i-arrow"/></svg>
					</button>

					<ul class="qil-pdp-assurances">
						<?php if ( '' !== $delivery_note ) : ?>
							<li><svg aria-hidden="true"><use href="#qil-i-truck"/></svg><?php echo esc_html( $delivery_note ); ?></li>
						<?php endif; ?>
						<li><svg aria-hidden="true"><use href="#qil-i-check"/></svg><?php echo esc_html( $is_arabic ? 'حقائق مقروءة من الملصق' : 'Facts read from the label' ); ?></li>
						<li><svg aria-hidden="true"><use href="#qil-i-shield"/></svg><?php echo esc_html( $is_arabic ? 'مخزون مباشر من المتجر' : 'Live stock from the store' ); ?></li>
					</ul>
				</div>
			</div>

			<?php if ( $rows || $actives ) : ?>
				<div class="qil-pdp-facts">
					<?php foreach ( $rows as $row ) : ?>
						<div class="qil-fact"><small class="qil-fact-label"><?php echo esc_html( $row['label'] ); ?></small><strong class="qil-fact-value"><?php echo esc_html( $row['value'] ); ?></strong></div>
					<?php endforeach; ?>
					<?php foreach ( array_slice( $actives, 0, 3 ) as $active ) : ?>
						<div class="qil-fact"><small class="qil-fact-label"><?php echo esc_html( $is_arabic ? 'مكوّن فعّال' : 'Key active' ); ?></small><strong class="qil-fact-value"><?php echo esc_html( isset( $active['name'] ) ? $active['name'] : '' ); ?></strong></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $segments || $attributes ) : ?>
				<div class="qil-pdp-detail">
					<?php if ( $segments ) : ?>
						<article class="qil-pdp-block">
							<h2><?php echo esc_html( $is_arabic ? 'ما يقوله الملصق' : 'What the label says' ); ?></h2>
							<ul>
								<?php foreach ( $segments as $segment ) : ?>
									<li><?php echo esc_html( $segment ); ?></li>
								<?php endforeach; ?>
							</ul>
						</article>
					<?php endif; ?>
					<?php if ( $attributes ) : ?>
						<article class="qil-pdp-block">
							<h2><?php echo esc_html( $is_arabic ? 'المواصفات' : 'Specifications' ); ?></h2>
							<dl class="qil-pdp-specs">
								<?php foreach ( array_slice( $attributes, 0, 10 ) as $attribute ) : ?>
									<div><dt><?php echo esc_html( $attribute['label'] ); ?></dt><dd><?php echo esc_html( $attribute['value'] ); ?></dd></div>
								<?php endforeach; ?>
							</dl>
						</article>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * The rail beside the product, rendered by the same card the homepage uses.
 */
function qil_section_product_related( array $args = array() ) {
	$related = $args['records'] ?? ( function_exists( 'qil_current_product_payload' ) ? ( qil_current_product_payload()['related'] ?? array() ) : array() );
	if ( empty( $related ) ) { return ''; }
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];

	ob_start();
	?>
	<section id="qil-product-alternatives" class="qil-section qil-commerce-collections qil-pdp-related" aria-label="<?php echo esc_attr( $is_arabic ? 'منتجات ذات صلة' : 'Related products' ); ?>">
		<div class="qil-container">
			<article class="qil-collection-block" data-qil-collection="related">
				<div class="qil-collection-head">
					<div>
						<small><?php echo esc_html( $is_arabic ? 'مختارة من نفس الفئة' : 'From the same shelf' ); ?></small>
						<h3><?php echo esc_html( $is_arabic ? 'قد يناسبك أيضاً' : 'You might also want' ); ?></h3>
					</div>
					<div class="qil-collection-tools">
						<div class="qil-rail-nav" data-qil-rail-nav="related">
							<button type="button" data-qil-rail-prev aria-label="<?php echo esc_attr( $is_arabic ? 'السابق' : 'Previous' ); ?>"><svg><use href="#qil-i-arrow"/></svg></button>
							<button type="button" data-qil-rail-next aria-label="<?php echo esc_attr( $is_arabic ? 'التالي' : 'Next' ); ?>"><svg><use href="#qil-i-arrow"/></svg></button>
						</div>
						<a href="<?php echo esc_url( $context['shopUrl'] ); ?>"><?php echo esc_html( $is_arabic ? 'عرض الكل' : 'View all' ); ?><svg><use href="#qil-i-arrow"/></svg></a>
					</div>
				</div>
				<div class="qil-collection-grid qil-rail" data-qil-collection-grid data-qil-rail="related" aria-live="polite"><div class="qil-collection-skeleton" aria-hidden="true"><i></i><i></i><i></i><i></i></div></div>
			</article>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * The Qimia intelligence band, appended below whatever the merchant has built.
 *
 * The product page here is a WoodMart layout built in Elementor, and it already
 * does the retail job well: gallery, title, price, swatches, reviews. Competing
 * with that was the mistake in 0.27 and 0.28. This adds only what the builder
 * cannot: the label read as data, what the product is actually for, a way to
 * ask Qimia AI about this specific item, and a rail of real alternatives.
 */
function qil_section_product_intelligence( array $args = array() ) {
	$context   = qil_view_context();
	$is_arabic = $context['isArabic'];
	$record    = isset( $args['record'] ) && is_array( $args['record'] ) ? $args['record'] : null;
	$product   = isset( $args['product'] ) ? $args['product'] : null;
	if ( ! $record ) {
		return '';
	}

	$rows    = qil_product_label_rows( $record, $is_arabic );
	$actives = isset( $record['facts']['primaryActives'] ) && is_array( $record['facts']['primaryActives'] ) ? $record['facts']['primaryActives'] : array();
	$goals   = isset( $record['match']['goals'] ) && is_array( $record['match']['goals'] ) ? $record['match']['goals'] : array();
	// dietary is a keyed map of flags plus a labels list; only the flags that
	// are actually true are worth a chip.
	$dietary_map = isset( $record['dietary'] ) && is_array( $record['dietary'] ) ? $record['dietary'] : array();
	$dietary_names = array(
		'vegan'         => array( 'Vegan', 'نباتي صرف' ),
		'vegetarian'    => array( 'Vegetarian', 'نباتي' ),
		'glutenFree'    => array( 'Gluten free', 'خالٍ من الغلوتين' ),
		'sugarFree'     => array( 'Sugar free', 'خالٍ من السكر' ),
		'halal'         => array( 'Halal', 'حلال' ),
		'stimulantFree' => array( 'Stimulant free', 'خالٍ من المنبّهات' ),
		'caffeineFree'  => array( 'Caffeine free', 'خالٍ من الكافيين' ),
	);
	$dietary = array();
	foreach ( $dietary_names as $flag => $names ) {
		if ( ! empty( $dietary_map[ $flag ] ) ) {
			$dietary[] = $is_arabic ? $names[1] : $names[0];
		}
	}
	$price   = isset( $record['price'] ) && is_array( $record['price'] ) ? $record['price'] : array();
	$market  = function_exists( 'qil_market_context' ) ? qil_market_context( $is_arabic ) : array();
	$delivery = isset( $market['deliveryShort'] ) ? (string) $market['deliveryShort'] : '';

	$segments = array();
	if ( is_a( $product, 'WC_Product' ) ) {
		$segments = qil_fact_segments( $product->get_description() . "\n" . $product->get_short_description() );
		$segments = array_slice( array_values( array_filter( $segments ) ), 0, 5 );
		// These lines come from the merchant's own copy, so on the Arabic route
		// they go through the translator's memory like every other catalogue
		// string. Nothing new is queued: a line only appears in Arabic once the
		// translator has already paid for it somewhere else.
		if ( $is_arabic && $segments && function_exists( 'qil_translate_batch' ) ) {
			$segment_map = qil_translate_batch( $segments, 'general', false );
			foreach ( $segments as $segment_index => $segment ) {
				$segments[ $segment_index ] = qil_translated( $segment, $segment_map );
			}
		}
	}

	$goal_labels = array(
		'muscle'    => array( 'Build muscle', 'بناء العضلات' ),
		'fat-loss'  => array( 'Fat management', 'إدارة الدهون' ),
		'performance' => array( 'Performance', 'الأداء' ),
		'energy'    => array( 'Energy & focus', 'الطاقة والتركيز' ),
		'recovery'  => array( 'Recovery', 'التعافي' ),
		'sleep'     => array( 'Sleep & calm', 'النوم والهدوء' ),
		'wellness'  => array( 'Daily wellness', 'العافية اليومية' ),
		'beauty'    => array( 'Beauty', 'الجمال' ),
	);

	$has_label = (bool) ( $rows || $actives );

	ob_start();
	?>
	<section id="qil-product-intelligence" class="qil-section qil-pdp-intel" aria-label="<?php echo esc_attr( $is_arabic ? 'قراءة كيميا للمنتج' : 'Qimia product intelligence' ); ?>">
		<div class="qil-container">
			<header class="qil-pdp-intel-head">
				<span class="qil-kicker"><?php echo esc_html( $is_arabic ? 'قراءة كيميا' : 'READ BY QIMIA' ); ?></span>
				<h2><?php echo esc_html( $is_arabic ? 'ما يقوله هذا المنتج فعلاً' : 'What this product actually says' ); ?></h2>
				<p><?php echo esc_html( $is_arabic ? 'معلومات مدرجة في سجل المنتج بالمتجر. تحقّق من ملصق العبوة؛ المعلومات الناقصة لا تُخمّن.' : 'Information listed in the store product record. Check the package label; missing facts are not guessed.' ); ?></p>
			</header>

			<div class="qil-pdp-intel-grid">
				<?php if ( $has_label ) : ?>
					<article class="qil-pdp-intel-card qil-pdp-intel-label">
						<h3><svg aria-hidden="true"><use href="#qil-i-check"/></svg><?php echo esc_html( $is_arabic ? 'حقائق المنتج المدرجة' : 'Listed product facts' ); ?></h3>
						<dl>
							<?php foreach ( $rows as $row ) : ?>
								<div><dt><?php echo esc_html( $row['label'] ); ?></dt><dd><?php echo esc_html( $row['value'] ); ?></dd></div>
							<?php endforeach; ?>
							<?php foreach ( array_slice( $actives, 0, 3 ) as $active ) : ?>
								<div><dt><?php echo esc_html( $is_arabic ? 'مكوّن فعّال' : 'Key active' ); ?></dt><dd><?php echo esc_html( isset( $active['name'] ) ? $active['name'] : '' ); ?><?php echo ! empty( $active['amount'] ) ? esc_html( ' · ' . $active['amount'] ) : ''; ?></dd></div>
							<?php endforeach; ?>
							<?php if ( ! empty( $price['perServingFormatted'] ) ) : ?>
								<div class="is-highlight"><dt><?php echo esc_html( $is_arabic ? 'التكلفة لكل حصة' : 'Cost per serving' ); ?></dt><dd dir="ltr"><?php echo esc_html( $price['perServingFormatted'] ); ?></dd></div>
							<?php endif; ?>
						</dl>
					</article>
				<?php else : ?>
				<article class="qil-pdp-intel-card"><h3><?php echo esc_html( $is_arabic ? 'حقائق المنتج' : 'Product facts' ); ?></h3><p><?php echo esc_html( $is_arabic ? 'تفاصيل الحصة والمكوّنات غير مدرجة بشكل موثّق. راجع صورة الملصق أو اسأل كيميا.' : 'Verified serving and active-ingredient details are not listed. Check the label image or ask Qimia.' ); ?></p></article>
				<?php endif; ?>

				<article class="qil-pdp-intel-card qil-pdp-intel-purpose">
					<h3><svg aria-hidden="true"><use href="#qil-i-spark"/></svg><?php echo esc_html( $is_arabic ? 'لماذا قد يناسبك' : 'What it is for' ); ?></h3>
					<?php if ( $goals ) : ?>
						<ul class="qil-pdp-goals">
							<?php foreach ( array_slice( $goals, 0, 4 ) as $goal ) : ?>
								<?php if ( ! isset( $goal_labels[ $goal ] ) ) { continue; } ?>
								<li><svg aria-hidden="true"><use href="#qil-i-<?php echo esc_attr( 'muscle' === $goal ? 'muscle' : ( 'energy' === $goal ? 'energy' : ( 'recovery' === $goal ? 'recovery' : ( 'sleep' === $goal ? 'sleep' : ( 'beauty' === $goal ? 'beauty' : 'wellness' ) ) ) ) ); ?>"/></svg><?php echo esc_html( $is_arabic ? $goal_labels[ $goal ][1] : $goal_labels[ $goal ][0] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $segments ) : ?>
						<ul class="qil-pdp-claims">
							<?php foreach ( $segments as $segment ) : ?>
								<li><?php echo esc_html( $segment ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $dietary ) : ?>
						<p class="qil-pdp-diet">
							<?php foreach ( array_slice( $dietary, 0, 4 ) as $flag ) : ?>
								<span><?php echo esc_html( $flag ); ?></span>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>
				</article>

				<article class="qil-pdp-intel-card qil-pdp-intel-ask">
					<h3><svg aria-hidden="true"><use href="#qil-i-message"/></svg><?php echo esc_html( $is_arabic ? 'اسأل عن هذا المنتج' : 'Ask about this one' ); ?></h3>
					<p><?php echo esc_html( $is_arabic ? 'قارنه بغيره، اسأل عن الجرعة أو التوقيت، أو اطلب بديلاً بميزانية أقل.' : 'Compare it, ask about dose or timing, or ask for a cheaper equivalent.' ); ?></p>
					<div class="qil-pdp-ask-actions">
						<button class="qil-button qil-button-primary" type="button" data-qimia-ai-open data-qil-ai-intent="product" data-qimia-product-id="<?php echo esc_attr( $record['id'] ); ?>" data-qimia-product-name="<?php echo esc_attr( $record['name'] ); ?>">
							<svg aria-hidden="true"><use href="#qil-i-spark"/></svg><span><?php echo esc_html( $is_arabic ? 'اسأل ذكاء كيميا' : 'Ask Qimia AI' ); ?></span>
						</button>
						<button class="qil-compare-add qil-pdp-compare" type="button" data-compare-id="<?php echo esc_attr( $record['id'] ); ?>" aria-pressed="false" aria-label="<?php echo esc_attr( $is_arabic ? 'أضف إلى المقارنة' : 'Add to compare' ); ?>">
							<svg class="qil-compare-icon" aria-hidden="true"><use href="#qil-i-compare"/></svg><span><?php echo esc_html( $is_arabic ? 'أضف إلى المقارنة' : 'Add to compare' ); ?></span>
						</button>
					</div>
					<ul class="qil-pdp-assurances">
						<?php if ( '' !== $delivery ) : ?>
							<li><svg aria-hidden="true"><use href="#qil-i-truck"/></svg><?php echo esc_html( $delivery ); ?></li>
						<?php endif; ?>
						<li><svg aria-hidden="true"><use href="#qil-i-store"/></svg><?php echo esc_html( $is_arabic ? 'تُراجع الخيارات عند الشراء' : 'Options are rechecked when purchasing' ); ?></li>
					</ul>
				</article>
			</div>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}

/**
 * Append the band to the Elementor document that renders this product.
 *
 * elementor/frontend/the_content wraps a document's own output, which is the
 * only insertion point on this store: WoodMart renders the product page as an
 * Elementor document, so no WooCommerce summary hook exists inside it. The
 * guard makes sure it lands once, on a product view, and never in the editor.
 */
function qil_append_product_intelligence( $content ) {
	static $done = false;
	if ( $done || ! qil_is_product_view() || ! apply_filters('qil_product_intelligence_enabled',true) ) { return $content; }
	if ( strpos($content,'id="qil-product-world"') !== false ) { $done = true; return $content; }
	if ( '' !== $content && ! preg_match('/(?:single_add_to_cart_button|woocommerce-product-gallery|wd-single-(?:title|add-cart|gallery)|product_title|data-qil-product-page)/i',$content) ) { return $content; }
	$payload = qil_current_product_payload(); $record = $payload['product'] ?? null;
	if ( !$record ) { return $content; }
	$done = true; $saved = qil_saved_product_markup( $record['id'] );
	return '' !== $content ? qil_integrate_product_document($content,$record,$saved) : qil_product_world_markup($record,$saved);
}
add_filter( 'elementor/frontend/the_content', 'qil_append_product_intelligence', 20 );
add_action( 'woocommerce_after_single_product_summary', 'qil_print_product_intelligence', 9 );
add_action( 'woocommerce_after_single_product', 'qil_print_product_intelligence', 20 );
add_action( 'wp_footer', 'qil_print_product_intelligence', 8 );
function qil_print_product_intelligence() {
	echo qil_append_product_intelligence(''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Owning plugin renders saved HTML.
}

/**
 * The phone buy bar.
 *
 * The merchant's layout puts add-to-cart near the top of the page, so on a
 * phone it is gone within one swipe and the shopper has to scroll back to buy.
 * This bar carries the product's name, its live price and the same button used
 * everywhere else in the storefront, and appears only once the real one has
 * scrolled out of view. A variable product sends the shopper back up to the
 * options rather than guessing a variation for them.
 */
function qil_section_product_buybar( array $args = array() ) {
	$record = isset( $args['record'] ) && is_array( $args['record'] ) ? $args['record'] : null;
	if ( ! $record ) {
		return '';
	}
	$context     = qil_view_context();
	$is_arabic   = $context['isArabic'];
	$price       = isset( $record['price'] ) && is_array( $record['price'] ) ? $record['price'] : array();
	$in_stock    = ! empty( $record['stock']['inStock'] );
	$is_variable = 'variable' === ( isset( $record['type'] ) ? $record['type'] : '' );

	ob_start();
	?>
	<div class="qil-pdp-buybar" data-qil-buybar hidden>
		<div class="qil-pdp-buybar-info">
			<strong><?php echo esc_html( $record['name'] ); ?></strong>
			<span dir="ltr" data-qil-buybar-price><?php echo esc_html( isset( $price['formatted'] ) ? $price['formatted'] : '' ); ?></span>
		</div>
		<button class="qil-buy" type="button" data-qil-buybar-purchase<?php echo ( ! $in_stock || empty( $record['purchase']['purchasable'] ) ) ? ' disabled aria-disabled="true"' : ''; ?>>
			<svg aria-hidden="true"><use href="#qil-i-cart-plus"/></svg><span><?php echo esc_html( ! $in_stock ? ( $is_arabic ? 'غير متوفر' : 'Out of stock' ) : ( $is_variable ? ( $is_arabic ? 'اختر الخيارات' : 'Choose options' ) : ( $is_arabic ? 'خيارات الشراء' : 'Buy this product' ) ) ); ?></span>
		</button>
	</div>
	<?php
	return (string) ob_get_clean();
}
