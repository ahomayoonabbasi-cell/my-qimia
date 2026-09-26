<?php
/**
 * Qimia Intelligence Lab — merchant product knowledge.
 *
 * The store already carries two stored product records for every
 * product: the "Qimia AI Box (Structured)" and the classic "Supplement Facts"
 * table, both owned by the Qimia AI Product Box plugin. This file reads those
 * records and nothing else.
 *
 * The rule that makes this safe: every line the band prints here was stored by
 * the product information workflow for this exact product. Nothing is inferred, translated,
 * averaged or generated. A field the merchant left empty stays absent — it
 * never becomes a guess and never becomes a placeholder. The AI is reached
 * only when the shopper asks for it by pressing Ask or Compare.
 *
 * @package Qimia_Intelligence_Lab
 */

defined( 'ABSPATH' ) || exit;

const QIL_BOX_META   = '_qimia_ai_box_structured';
const QIL_FACTS_META = '_qimia_supplement_facts_structured';

/** Stored text can be malformed; never turn nested arrays into visible 'Array'. */
function qil_knowledge_text( $value ) {
 return is_scalar( $value ) && !is_bool( $value ) ? trim( wp_strip_all_tags( (string) $value ) ) : '';
}

/**
 * Normalise a merchant list field into trimmed, non-empty strings.
 *
 * @param mixed $value Raw meta value.
 * @param int   $limit Maximum entries to keep.
 * @return string[]
 */
function qil_knowledge_list( $value, $limit = 0 ) {
	if ( is_string( $value ) ) {
		$value = array( $value );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	$out = array();
	foreach ( $value as $entry ) {
		if ( !is_scalar( $entry ) || is_bool( $entry ) ) {
			continue;
		}
		$entry = qil_knowledge_text( $entry );
		if ( '' !== $entry ) {
			$out[] = $entry;
		}
	}
	if ( $limit > 0 ) {
		$out = array_slice( $out, 0, $limit );
	}
	return $out;
}

/**
 * The merchant's own product record, in the language this view is rendering.
 *
 * @param int  $product_id Product post ID.
 * @param bool $is_arabic  Whether the Arabic route is rendering.
 * @return array
 */
function qil_product_knowledge( $product_id, $is_arabic = false ) {
	$product_id = (int) $product_id;
	static $cache = array();
	$key = $product_id . ( $is_arabic ? '|ar' : '|en' );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$knowledge = array(
		'hasBox'       => false,
		'hasFacts'     => false,
		'title'        => '',
		'bodyEffects'  => array(),
		'timeline'     => array(),
		'serving'      => '',
		'whoFor'       => array(),
		'notes'        => array(),
		'faq'          => array(),
		'servingSize'  => '',
		'servings'     => '',
		'netContent'   => '',
		'rows'         => array(),
		'ingredients'  => '',
		'directions'   => '',
		'allergens'    => '',
	);

	if ( ! $product_id ) {
		$cache[ $key ] = $knowledge;
		return $knowledge;
	}

	$box = get_post_meta( $product_id, QIL_BOX_META, true );
	if ( is_array( $box ) ) {
		$lang = $is_arabic ? 'ar' : 'en';
		$side = isset( $box[ $lang ] ) && is_array( $box[ $lang ] ) ? $box[ $lang ] : array();
		// The merchant may have filled only one language. Never machine-fill the
		// other one: fall back to the language that actually has content, so the
		// shopper reads the merchant's words rather than an empty card.
		if ( ! array_filter( $side, static function ( $value ) {
			return is_array( $value ) ? (bool) array_filter( $value ) : '' !== qil_knowledge_text( $value );
		} ) ) {
			$other = $is_arabic ? 'en' : 'ar';
			$side  = isset( $box[ $other ] ) && is_array( $box[ $other ] ) ? $box[ $other ] : array();
		}

		$title = $is_arabic
			? qil_knowledge_text( $box['title_ar'] ?? '' )
			: qil_knowledge_text( $box['title_en'] ?? '' );

		$knowledge['title']       = trim( wp_strip_all_tags( $title ) );
		$knowledge['bodyEffects'] = qil_knowledge_list( isset( $side['body_effects'] ) ? $side['body_effects'] : array() );
		$knowledge['serving']     = qil_knowledge_text( $side['serving_summary'] ?? '' );
		$knowledge['whoFor']      = qil_knowledge_list( isset( $side['who_for'] ) ? $side['who_for'] : array() );
		$knowledge['notes']       = qil_knowledge_list( isset( $side['important_notes'] ) ? $side['important_notes'] : array() );

		if ( ! empty( $side['one_pack_expectation'] ) && is_array( $side['one_pack_expectation'] ) ) {
			foreach ( $side['one_pack_expectation'] as $phase ) {
				if ( ! is_array( $phase ) ) {
					continue;
				}
				$label = qil_knowledge_text( $phase['phase'] ?? '' );
				$text  = qil_knowledge_text( $phase['text'] ?? '' );
				if ( '' === $text ) {
					continue;
				}
				$knowledge['timeline'][] = array( 'phase' => $label, 'text' => $text );
			}
		}

		if ( ! empty( $side['faq'] ) && is_array( $side['faq'] ) ) {
			foreach ( $side['faq'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$question = qil_knowledge_text( $item['q'] ?? '' );
				$answer   = qil_knowledge_text( $item['a'] ?? '' );
				if ( '' === $question || '' === $answer ) {
					continue;
				}
				$knowledge['faq'][] = array( 'q' => $question, 'a' => $answer );
			}
		}

		$knowledge['hasBox'] = (bool) (
			$knowledge['bodyEffects'] || $knowledge['timeline'] || $knowledge['serving']
			|| $knowledge['whoFor'] || $knowledge['notes'] || $knowledge['faq']
		);
	}

	$facts = get_post_meta( $product_id, QIL_FACTS_META, true );
	if ( is_array( $facts ) ) {
		$knowledge['servingSize'] = qil_knowledge_text( $facts['serving_size_en'] ?? '' );
		$knowledge['servings']    = qil_knowledge_text( $facts['servings_en'] ?? '' );
		$knowledge['netContent']  = qil_knowledge_text( $facts['total_content_en'] ?? '' );
		$knowledge['ingredients'] = qil_knowledge_text( $facts['ingredients_en'] ?? '' );
		$knowledge['directions']  = qil_knowledge_text( $facts['directions_en'] ?? '' );
		$knowledge['allergens']   = qil_knowledge_text( $facts['allergens_en'] ?? '' );

		if ( ! empty( $facts['rows'] ) && is_array( $facts['rows'] ) ) {
			foreach ( $facts['rows'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				// Match the owning Product Box: only blank/per_serving basis is shown.
				if (isset($row['basis']) && !is_scalar($row['basis'])) { continue; }
				$basis = sanitize_key( qil_knowledge_text( $row['basis'] ?? '' ) );
				if ( ! in_array( $basis, array( '', 'per_serving' ), true ) ) { continue; }
				$label = qil_knowledge_text( $row['label_en'] ?? '' );
				$value = qil_knowledge_text( $row['value'] ?? '' );
				if ( '' === $label || '' === $value ) {
					continue;
				}
				$knowledge['rows'][] = array(
					'label'   => $label,
					'value'   => $value,
					'section' => qil_knowledge_text( $row['section'] ?? '' ),
					'basis'   => qil_knowledge_text( $row['basis'] ?? '' ),
				);
			}
		}

		$knowledge['hasFacts'] = (bool) (
			$knowledge['servingSize'] || $knowledge['servings'] || $knowledge['netContent']
			|| $knowledge['rows'] || $knowledge['ingredients'] || $knowledge['directions']
		);
	}

	/**
	 * Filter the merchant knowledge record used by the product band.
	 *
	 * @param array $knowledge  Normalised record.
	 * @param int   $product_id Product ID.
	 * @param bool  $is_arabic  Arabic route.
	 */
	$knowledge = (array) apply_filters( 'qil_product_knowledge', $knowledge, $product_id, $is_arabic );

	$cache[ $key ] = $knowledge;
	return $knowledge;
}

/** Compact, optional excerpts. Never displace or clone the full native box. */
function qil_product_knowledge_cards(array $k,$ar=false){
 $t=static function($en,$arabic)use($ar){return $ar?$arabic:$en;};
 ob_start(); ?>
 <div class="qil-knowledge-brief-grid">
 <?php if(!empty($k['bodyEffects'])||!empty($k['whoFor'])): ?>
 <details class="qil-knowledge-brief" data-qil-knowledge-disclosure="purpose"><summary><?php echo esc_html($t('What it supports','ما الذي يدعمه')); ?><span aria-hidden="true">+</span></summary><div>
 <?php if(!empty($k['bodyEffects'])): ?><ul><?php foreach(array_slice($k['bodyEffects'],0,3) as $line): ?><li><?php echo esc_html($line); ?></li><?php endforeach; ?></ul><?php endif; ?>
 <?php if(!empty($k['whoFor'])): ?><p><strong><?php echo esc_html($t('Who it is for','لمن يناسب')); ?></strong></p><ul><?php foreach(array_slice($k['whoFor'],0,3) as $line): ?><li><?php echo esc_html($line); ?></li><?php endforeach; ?></ul><?php endif; ?>
 </div></details><?php endif; ?>
 <?php if(!empty($k['hasFacts'])): ?>
 <details class="qil-knowledge-brief" data-qil-knowledge-disclosure="label"><summary><?php echo esc_html($t('Serving & label','الحصة والملصق')); ?><span aria-hidden="true">+</span></summary><div class="notranslate" lang="en" dir="ltr" translate="no"><dl>
 <?php foreach(array('netContent'=>'Net content','servingSize'=>'Serving size','servings'=>'Servings per container') as $key=>$label):if(empty($k[$key]))continue; ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($k[$key]); ?></dd></div><?php endforeach; ?>
 <?php foreach(array_slice($k['rows'],0,4) as $row): ?><div><dt><?php echo esc_html($row['label']); ?></dt><dd><?php echo esc_html($row['value']); ?></dd></div><?php endforeach; ?>
 </dl><p class="qil-knowledge-basis">Listed values per serving. Open the original table for the full record.</p></div></details><?php endif; ?>
 <?php if(!empty($k['serving'])||!empty($k['directions'])): ?>
 <details class="qil-knowledge-brief" data-qil-knowledge-disclosure="usage"><summary><?php echo esc_html($t('How to use','طريقة الاستخدام')); ?><span aria-hidden="true">+</span></summary><div>
 <?php if(!empty($k['serving'])): ?><p><?php echo esc_html($k['serving']); ?></p><?php endif; ?>
 <?php if(!empty($k['directions']) && empty($k['serving'])): ?><p lang="en" dir="ltr"><?php echo esc_html($k['directions']); ?></p><?php endif; ?>
 </div></details><?php endif; ?>
 <?php if(!empty($k['notes'])||!empty($k['allergens'])): ?>
 <details class="qil-knowledge-brief" data-qil-knowledge-disclosure="notes"><summary><?php echo esc_html($t('Important notes','ملاحظات مهمة')); ?><span aria-hidden="true">+</span></summary><div>
 <?php if(!empty($k['notes'])): ?><ul><?php foreach(array_slice($k['notes'],0,4) as $line): ?><li><?php echo esc_html($line); ?></li><?php endforeach; ?></ul><?php endif; ?>
 <?php if(!empty($k['allergens'])): ?><p lang="en" dir="ltr"><strong>Allergens:</strong> <?php echo esc_html($k['allergens']); ?></p><?php endif; ?>
 </div></details><?php endif; ?>
 </div>
 <?php return (string)ob_get_clean();
}
