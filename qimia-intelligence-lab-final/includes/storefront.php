<?php
/** Same design system, native WooCommerce purchase flow. */
defined( 'ABSPATH' ) || exit;

function qil_enqueue_storefront_assets() {
	if ( 'chrome' !== qil_render_mode() || qil_is_elementor_context() ) { return; }
	$bot = ( function_exists( 'qil_perf_noninteractive_bot' ) && qil_perf_noninteractive_bot() ) || ( function_exists( 'qil_perf_crawler_family' ) && '' !== qil_perf_crawler_family() );
	$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
	$suffix = $debug ? '' : '.min';
	wp_enqueue_style( 'qimia-lab-storefront', QIL_URL . 'assets/qil-storefront' . $suffix . '.css', array( 'qimia-intelligence-lab' ), QIL_VERSION );
	if ( $bot ) { return; }
	if ( qil_is_product_view() ) {
		wp_enqueue_script( 'qimia-lab-product-tools', QIL_URL . 'assets/qil-storefront' . $suffix . '.js', array( 'qimia-intelligence-lab' ), QIL_VERSION, true );
	}
}
add_action( 'wp_enqueue_scripts', 'qil_enqueue_storefront_assets', 30 );

/** A single reading workspace. Only two explicit actions hand off to the AI. */
function qil_section_product_workspace( array $record, array $saved = array() ) {
	$context = qil_view_context(); $ar = $context['isArabic'];
	$price = $record['price']['formattedHtml'] ?? '';
	if ( !$price ) { $price = esc_html($record['price']['formatted'] ?? ''); }
	$box = $saved['box'] ?? ''; $facts = $saved['facts'] ?? '';
	$knowledge=qil_product_knowledge((int)$record['id'],$ar);
	ob_start(); ?>
	<section class="qil-product-workspace" data-qil-product-workspace data-product-id="<?php echo esc_attr($record['id']); ?>" aria-labelledby="qil-product-workspace-title">
		<header class="qil-product-workspace-head">
			<div><span class="qil-kicker"><?php echo esc_html($ar ? 'عالم منتجك · كيميا' : 'YOUR PRODUCT WORLD · QIMIA'); ?></span>
			<h2 id="qil-product-workspace-title"><?php echo esc_html($ar ? 'نظرة أوضح على منتجك.' : 'A closer look at your product.'); ?></h2>
			<p><?php echo esc_html($ar ? 'استكشف معلومات المنتج المحفوظة. افتح الدليل الكامل أو اسأل كيميا للمزيد.' : 'Explore saved product details. Open the full guide or ask Qimia when you need more.'); ?></p></div>
			<span class="qil-product-data-badge"><svg aria-hidden="true"><use href="#qil-i-check"/></svg><?php echo esc_html($ar ? 'من معلومات هذا المنتج' : 'From this product’s information'); ?></span>
		</header>
		<?php echo qil_product_knowledge_cards($knowledge,$ar); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php foreach(array('box'=>$box,'facts'=>$facts) as $key=>$html): if(!$html || !empty($saved[$key.'_captured']))continue; ?>
        <div data-qil-source-mount="<?php echo esc_attr($key); ?>"></div>
        <template data-qil-source-fallback="<?php echo esc_attr($key); ?>"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- owning renderer, activated only if no original exists. ?></template>
        <?php endforeach; ?>
		<div class="qil-product-assist-strip">
			<div class="qil-product-selected" aria-live="polite" aria-atomic="true">
				<small><?php echo esc_html($ar ? 'اختيارك الحالي' : 'YOUR CURRENT SELECTION'); ?></small>
				<strong data-qil-selection-name><?php echo esc_html($record['name']); ?></strong>
				<div class="qil-product-selected-price" data-qil-product-live-price><?php echo wp_kses_post($price); ?></div>
				<span class="qil-product-stock" data-qil-product-live-stock><?php echo esc_html($record['stock']['label'] ?? ''); ?></span>
				<p data-qil-product-option-status></p><p data-qil-product-variation-expiry hidden></p>
			</div>
			<div class="qil-product-ai-actions">
				<h3><?php echo esc_html($ar ? 'تحتاج مساعدة في الاختيار؟' : 'Need a second look?'); ?></h3>
				<p><?php echo esc_html($ar ? 'اقرأ المعلومات بدون فتح المحادثة. اختر المساعد فقط عندما تريد السؤال أو المقارنة.' : 'Read without opening a chat. Choose the assistant only when you want to ask or compare.'); ?></p>
				<div class="qil-product-ai-buttons">
					<button type="button" class="qil-button qil-button-primary" data-qimia-ai-open data-qil-ai-intent="product" data-qimia-product-id="<?php echo esc_attr($record['id']); ?>"><svg aria-hidden="true"><use href="#qil-i-message"/></svg><?php echo esc_html($ar ? 'اسأل كيميا عن المنتج' : 'Ask Qimia about this product'); ?></button>
					<button type="button" class="qil-button qil-button-ghost" data-qimia-ai-open data-qil-ai-intent="compare-product" data-qimia-product-id="<?php echo esc_attr($record['id']); ?>"><svg aria-hidden="true"><use href="#qil-i-compare"/></svg><?php echo esc_html($ar ? 'قارن مع كيميا' : 'Compare with Qimia'); ?></button>
				</div>
			</div>
		</div>
	</section>
	<?php return (string)ob_get_clean();
}
