<?php
/** Read-only integration with the existing Product Box and Supplement Facts. */
defined( 'ABSPATH' ) || exit;

/** Read saved HTML through the owning plugin. Never regenerate facts or write meta. */
function qil_saved_product_markup( $id ) {
	return array(
		'box' => function_exists( 'qimia_render_ai_box' ) ? (string) qimia_render_ai_box( (int) $id ) : '',
		'facts' => function_exists( 'qimia_render_supplement_facts' ) ? (string) qimia_render_supplement_facts( (int) $id ) : '',
	);
}

/**
 * Locate element byte ranges without serialising the merchant's form/JSON/HTML.
 * Quoted > characters and script/style/textarea raw text are skipped as units.
 * Malformed/unclosed elements are ignored rather than destructively repaired.
 */
function qil_product_html_regions( $html ) {
	$pattern = '~<!--[\s\S]*?-->|<(script|style|textarea)\b(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*>[\s\S]*?</\1\s*>|</?([a-z][a-z0-9:-]*)\b(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*>~i';
	if ( ! preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) { return array(); }
	$nodes = array(); $stack = array();
	$void = array( 'area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr' );
	foreach ( $matches as $match ) {
		$tag_html = $match[0][0]; $start = $match[0][1];
		if ( 0 === strpos( $tag_html, '<!--' ) || ! empty( $match[1][0] ) || empty( $match[2][0] ) ) { continue; }
		$tag = strtolower( $match[2][0] );
		if ( '</' === substr( $tag_html, 0, 2 ) ) {
			for ( $i = count( $stack ) - 1; $i >= 0; --$i ) {
				$key = $stack[ $i ];
				if ( $nodes[ $key ]['tag'] !== $tag ) { continue; }
				$nodes[ $key ]['end'] = $start + strlen( $tag_html );
				$stack = array_slice( $stack, 0, $i ); break;
			}
			continue;
		}
		$class = '';
		if ( preg_match( '~\bclass\s*=\s*(["\x27])(.*?)\1~is', $tag_html, $cm ) ) { $class = ' ' . preg_replace( '/\s+/', ' ', $cm[2] ) . ' '; }
		$key = count( $nodes );
		$nodes[] = array( 'tag'=>$tag, 'start'=>$start, 'openEnd'=>$start+strlen($tag_html), 'end'=>0, 'class'=>$class, 'parent'=>$stack ? end($stack) : -1 );
		if ( in_array( $tag, $void, true ) || '/>' === substr( $tag_html, -2 ) ) { $nodes[$key]['end'] = $nodes[$key]['openEnd']; }
		else { $stack[] = $key; }
	}
	return $nodes;
}

/** Integrate an Elementor product document in one pass, retaining native bytes. */
function qil_integrate_product_document( $content, $record, $saved ) {
	$nodes = qil_product_html_regions( $content ); $cuts = array(); $top_end = 0; $boundaries = array();
	foreach ( $nodes as $key => $node ) {
		if ( ! $node['end'] ) { continue; }
		$cls = $node['class'];
		if ( preg_match( '~ (?:woocommerce-product-gallery|wd-single-gallery|wd-single-add-cart) ~', $cls ) || ( 'form' === $node['tag'] && false !== strpos( $cls, ' cart ' ) ) ) {
			$top_end = max( $top_end, $node['end'] );
		}
		$type = false !== strpos($cls,' qimia-ai-box-wrap ') ? 'box' : ( false !== strpos($cls,' qimia-sf-classic ') ? 'facts' : '' );
		if ( $type ) {
			// The template's rendered instance wins over the fallback renderer.
			if ( ! isset( $saved[ $type . '_captured' ] ) ) { $saved[$type] = substr($content,$node['start'],$node['end']-$node['start']); $saved[$type.'_captured'] = true; }
			// Preserve the merchant box in place; only remember that it already exists.
		}
		if ( preg_match( '~ (?:product_meta|wd-single-meta|woocommerce-tabs|wd-single-tabs|wd-single-content|woocommerce-Tabs-panel--description) ~', $cls ) ) { $boundaries[] = $key; }
	}
	$insert = strlen( $content );
	foreach ( $boundaries as $key ) {
		if ( $nodes[$key]['start'] < $top_end ) { continue; }
		// Place before the entire full-width row, never inside an inline SKU span.
		$parent = $nodes[$key]['parent'];
		while ( $parent >= 0 && $nodes[$parent]['start'] >= $top_end ) { $key = $parent; $parent = $nodes[$key]['parent']; }
		$insert = min( $insert, $nodes[$key]['start'] );
	}
	// With no lower row, append to the document. A bounded client fallback handles
	// custom templates that omit native Woo hooks. Never splice inside a form.
	$shell = qil_product_world_markup( $record, $saved, $insert < strlen($content) ? 'server' : 'fallback' );
	$edits = array();
	// No source-node cuts: the original boxes, facts, forms and JSON remain byte-identical.
	$edits[] = array($insert,0,$shell);
	usort( $edits, static function($a,$b) { return $b[0] <=> $a[0]; } );
	foreach ( $edits as $edit ) { $content = substr_replace($content,$edit[2],$edit[0],$edit[1]); }
	return $content;
}

function qil_product_world_markup( $record, $saved, $placement = 'native' ) {
	$context = qil_view_context();
	return sprintf(
		'<div id="qil-product-world" class="qil-shell qil-elementor-shell qil-pdp-band qaatm-no-translate notranslate%1$s" dir="%2$s" lang="%3$s" translate="no" data-qil-locale="%3$s" data-qil-shell data-qil-placement="%4$s" data-qaatm-no-rewrite data-no-translation>%5$s%6$s</div>',
		$context['isArabic'] ? ' is-arabic' : '', esc_attr($context['direction']), esc_attr($context['locale']), esc_attr($placement),
		qil_section_product_workspace( $record, $saved ),
		qil_section_product_related() . qil_section_buy_again( 'product' ) . qil_section_product_buybar(array('record'=>$record)) . qil_section_docks()
	);
}

/** Configured currencies only; the owner's engine performs all conversions. */
function qil_storefront_currency_codes() {
	$current = function_exists('get_woocommerce_currency') ? strtoupper(get_woocommerce_currency()) : '';
	$codes = $current ? array($current) : array();
	if ( class_exists('Qimia_Unlimited_Geo_Currency') ) {
		$key = Qimia_Unlimited_Geo_Currency::OPTION_KEY;
		$settings = get_option($key,array());
		if ( is_array($settings) && !empty($settings['enabled']) ) {
			$currencies = json_decode( $settings['currencies_json'] ?? '{}', true );
			foreach ( is_array($currencies) ? $currencies : array() as $code=>$row ) {
				if ( preg_match('/^[A-Z]{3}$/',(string)$code) && is_array($row) && isset($row['rate']) && is_numeric($row['rate']) && (float)$row['rate']>0 ) { $codes[]=$code; }
			}
		}
	}
	$codes = (array) apply_filters('qil_storefront_currency_codes',array_values(array_unique($codes)));
	return array_values(array_filter($codes,static function($v){return is_string($v) && preg_match('/^[A-Z]{3}$/',$v);}));
}
