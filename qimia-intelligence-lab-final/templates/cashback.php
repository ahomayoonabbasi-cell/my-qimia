<?php
/** Public cashback section. $view contains an allowlisted, presentation-only model. */
defined( 'ABSPATH' ) || exit;
?>
<section class="qil-cashback qaatm-no-translate notranslate" dir="<?php echo esc_attr($view['direction']); ?>" lang="<?php echo esc_attr($view['locale']); ?>" translate="no" data-qaatm-no-rewrite data-no-translation aria-labelledby="<?php echo esc_attr($title_id); ?>" data-qil-cashback-currency="<?php echo esc_attr($view['currency']); ?>">
	<div class="qil-cashback__wrap">
		<div class="qil-cashback__panel">
			<header class="qil-cashback__header">
				<div class="qil-cashback__intro">
					<span class="qil-cashback__kicker"><?php echo esc_html($view['kicker']); ?></span>
					<h2 id="<?php echo esc_attr($title_id); ?>" class="qil-cashback__title"><?php echo esc_html($view['title']); ?></h2>
					<p class="qil-cashback__lead"><?php echo esc_html($view['lead']); ?></p>
				</div>
				<figure class="qil-cashback__art">
                    <img src="<?php echo esc_url(QIL_URL.'assets/qimia-cashback-floating-480.webp'); ?>"
                         srcset="<?php echo esc_url(QIL_URL.'assets/qimia-cashback-floating-480.webp'); ?> 480w, <?php echo esc_url(QIL_URL.'assets/qimia-cashback-floating-960.webp'); ?> 960w"
                         sizes="(max-width: 760px) 280px, (max-width: 1100px) 36vw, 440px"
                         width="960" height="800" loading="lazy" decoding="async" fetchpriority="low"
                         alt="<?php echo esc_attr($view['ar'] ? 'عميل كيميا يحمل قسيمة كاش باك وحقيبة مشترياته' : 'Qimia customer holding a cashback coupon and his shopping bag'); ?>">
                </figure>
				<div class="qil-cashback__delivery">
					<span class="qil-cashback__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg></span>
					<div><strong><?php echo esc_html($view['email']); ?></strong><span><?php echo esc_html($view['validity']); ?></span></div>
				</div>
			</header>
			<ol class="qil-cashback__tiers" role="list">
				<?php foreach($view['rows'] as $i=>$row): ?>
				<li class="qil-cashback__tier<?php echo $i===5?' qil-cashback__tier--last':''; ?>" data-band="<?php echo esc_attr($row['key']); ?>">
					<span class="qil-cashback__order-label"><?php echo esc_html($view['order']); ?></span>
					<p class="qil-cashback__range"><?php echo esc_html($row['range']); ?></p>
					<div class="qil-cashback__reward"><strong><bdi><?php echo esc_html($row['reward']); ?></bdi></strong><span><?php echo esc_html($view['back']); ?></span></div>
				</li>
				<?php endforeach; ?>
			</ol>
			<footer class="qil-cashback__footer">
				<p class="qil-cashback__note"><?php echo esc_html($view['note']); ?></p>
				<span class="qil-cashback__currency"><?php echo esc_html($view['currency_label']); ?><bdi><?php echo esc_html($view['currency']); ?></bdi></span>
			</footer>
			<details class="qil-cashback__details">
				<summary><span><?php echo esc_html($view['details']); ?></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
				<ul><?php foreach($view['terms'] as $term): ?><li><?php echo esc_html($term); ?></li><?php endforeach; ?></ul>
			</details>
		</div>
	</div>
</section>
