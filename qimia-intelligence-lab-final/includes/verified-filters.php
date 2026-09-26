<?php
/** Exact merchant evidence for the three existing goal filters; no AI or writes. */
defined( 'ABSPATH' ) || exit;

function qil_filter_text( $value ) {
    if ( ! is_scalar( $value ) || is_bool( $value ) ) return '';
    $value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $value = strtr( $value, array( '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9', '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9', '٫'=>'.', '–'=>'-', '—'=>'-', '‑'=>'-', 'أ'=>'ا', 'إ'=>'ا', 'آ'=>'ا', 'ى'=>'ي' ) );
    $value = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{200B}-\x{200F}]/u', '', $value );
    $value = preg_replace( '/\s+/u', ' ', trim( $value ) );
    return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
}

/**
 * Expand explicit dietary claims, not goal classification. Unknown stays null;
 * an explicit negative or conflicting ingredient amount always wins.
 * A caffeine-free claim is deliberately NOT proof of stimulant-free status.
 */
function qil_goal_verified_dietary( $product, array $dietary, array $segments = array(), array $caffeine = array() ) {
    $title = qil_filter_text( $product->get_name() );
    if ( ! $segments ) $segments = qil_fact_segments( $product->get_short_description() . "\n" . $product->get_description() );
    $structured = get_post_meta( $product->get_id(), '_qimia_supplement_facts_structured', true );
    if ( is_array( $structured ) && isset( $structured['rows'] ) && is_array( $structured['rows'] ) ) {
        foreach ( $structured['rows'] as $row ) {
            if ( ! is_array( $row ) ) continue;
            $segments[] = qil_filter_text( $row['label_en'] ?? '' ) . ': ' . qil_filter_text( $row['value'] ?? '' );
        }
    }
    $lines = array_map( 'qil_filter_text', $segments );
    $text = $title . ' ' . implode( ' ', $lines );
    $rules = array(
        'vegan' => array(
            'title' => '/\bvegan\b|\bفيغان\b|نباتي\s*(?:100\s*%|بالكامل)/u',
            'yes' => '/\b(?:vegan[ -]friendly|(?:certified|100\s*%)\s*vegan|suitable\s+for\s+vegans|(?:this\s+(?:product|formula|supplement)\s+is|is\s+certified)\s+vegan)\b|مناسب\s+للنباتيين|نباتي\s+بالكامل|خال(?:ي[ةه]?)?\s+من\s+المكونات\s+الحيواني[ةه]/u',
            'no' => '/\b(?:(?:not|non)[ -]+vegan|not\s+(?:suitable|safe)\s+for\s+vegans|unsuitable\s+for\s+vegans)\b|غير\s+(?:نباتي|مناسب\s+للنباتيين)|لا\s+يناسب\s+النباتيين/u',
        ),
        'stimulantFree' => array(
            'title' => '/\b(?:stimulant[ -]*free|stim[ -]*free|non[ -]*stimulant)\b|(?:بدون|خال(?:ي[ةه]?)?\s+من)\s+(?:المنبهات|المنشطات)/u',
            'yes' => '/\b(?:stimulant[ -]*free|stim[ -]*free|non[ -]*stimulant|(?:no|without)\s+(?:added\s+)?stimulants?)\b|(?:بدون|خال(?:ي[ةه]?)?\s+من)\s+(?:المنبهات|المنشطات)/u',
            'no' => '/\b(?:(?:not|non)[ -]+(?:stimulant|stim)[ -]*free|(?:contains?|with|includes?)\s+(?:added\s+)?stimulants?)\b|يحتوي\s+علي\s+(?:المنبهات|المنشطات)|غير\s+خال\S*\s+من\s+(?:المنبهات|المنشطات)/u',
        ),
    );
    $evidence = isset( $dietary['evidence'] ) && is_array( $dietary['evidence'] ) ? $dietary['evidence'] : array();
    foreach ( $rules as $flag => $rule ) {
        $no = false === ( $dietary[ $flag ] ?? null ) || (bool) preg_match( $rule['no'], $text );
        $yes = true === ( $dietary[ $flag ] ?? null );
        $source = $yes ? 'taxonomy_or_attribute' : '';
        if ( preg_match( $rule['title'], $title ) ) { $yes = true; $source = $source ?: 'explicit_product_title'; }
        foreach ( $lines as $line ) {
            // A question, comparative paragraph or choice between formulations
            // is not a verified claim about every option of this product.
            if ( preg_match( '/\?|\x{061F}|\b(?:unlike|compared|other\s+products|available\s+in|choose\s+between)\b/u', $line ) ) continue;
            if ( preg_match( $rule['yes'], $line ) ) { $yes = true; $source = $source ?: 'explicit_product_statement'; }
        }
        if ( 'stimulantFree' === $flag ) {
            if ( 'known' === ( $caffeine['state'] ?? '' ) ) $no = true;
            // Scan direct labelled amounts as well: a contradictory "free"
            // sentence must not hide a positive caffeine fact later in the copy.
            if ( preg_match_all( '/\b(?:caffeine(?:\s+(?:anhydrous|citrate))?|yohimbine(?:\s+hcl)?|synephrine)\s*[:·-]?\s*(\d+(?:[.,]\d+)?)\s*(?:mcg|mg|g)\b/u', $text, $amounts ) ) {
                foreach ( $amounts[1] as $amount ) if ( (float) str_replace( ',', '.', $amount ) > 0 ) $no = true;
            }
        }
        $dietary[ $flag ] = $no ? false : ( $yes ? true : null );
        $evidence[ $flag ] = $no ? 'explicit_negative_or_conflict' : $source;
    }
    $labels = isset( $dietary['labels'] ) && is_array( $dietary['labels'] ) ? $dietary['labels'] : array();
    $labels = array_values( array_filter( $labels, static function ( $label ) {
        return ! in_array( qil_filter_text( $label ), array( 'vegan', 'vegan-friendly', 'stimulant-free', 'stim-free', 'non-stimulant' ), true );
    } ) );
    if ( true === $dietary['vegan'] ) $labels[] = 'vegan';
    if ( true === $dietary['stimulantFree'] ) $labels[] = 'stimulant-free';
    $dietary['labels'] = array_values( array_unique( $labels ) );
    $dietary['evidence'] = $evidence;
    return $dietary;
}

/** Exact count only: never read grams, capsule counts, ranges or a dose as servings. */
function qil_filter_serving_count( $raw ) {
    $text = qil_filter_text( $raw );
    $label = '(?:servings?(?:\s+per\s+(?:container|pack|bottle))?|number\s+of\s+servings|srv|عدد\s+الحصص(?:\s+(?:في|لكل)\s+العبو[ةه])?|الحصص|حصه|حصة|حصص)';
    if ( ! preg_match( '/^(?:' . $label . '\s*[:·-]?\s*)?([1-9]\d{0,4})(?:\.0+)?(?:\s*' . $label . ')?$/u', $text, $match ) ) return 0;
    return (int) $match[1] <= 10000 ? (int) $match[1] : 0;
}

/**
 * Null means absent, zero means present but ambiguous/conflicting. Only an
 * absent field may fall back to the parent's common flavour-only pack count.
 */
function qil_filter_product_servings( $product, $fallback = '' ) {
    $values = array();
    $structured = get_post_meta( $product->get_id(), '_qimia_supplement_facts_structured', true );
    if ( is_string( $structured ) ) $structured = json_decode( $structured, true );
    if ( is_array( $structured ) ) {
        foreach ( array( 'servings_en', 'servings_ar' ) as $key ) {
            if ( isset( $structured[ $key ] ) && '' !== qil_filter_text( $structured[ $key ] ) ) $values[] = $structured[ $key ];
        }
    }
    // Nutrition Facts is authoritative. Old attributes must not override or
    // invalidate the label. Conflicting translations remain unverified.
    if ( $values ) {
        $counts = array_unique( array_map( 'qil_filter_serving_count', $values ) );
        return 1 === count( $counts ) && reset( $counts ) > 0 ? (int) reset( $counts ) : 0;
    }
    foreach ( array( 'pa_servings', 'servings', 'pa_number-of-servings', 'number-of-servings', 'pa_servings-per-container', 'servings-per-container' ) as $attribute ) {
        $raw = $product->get_attribute( $attribute );
        if ( '' !== qil_filter_text( $raw ) ) $values[] = $raw;
    }
    foreach ( array( 'servings', '_servings', 'number_of_servings', '_number_of_servings', 'servings_per_container', '_servings_per_container', 'qimia_servings', '_qimia_servings' ) as $key ) {
        $raw = get_post_meta( $product->get_id(), $key, true );
        if ( '' !== qil_filter_text( $raw ) ) $values[] = $raw;
    }
    if ( $values ) {
        $counts = array_unique( array_map( 'qil_filter_serving_count', $values ) );
        return 1 === count( $counts ) && reset( $counts ) > 0 ? (int) reset( $counts ) : 0;
    }
    if ( '' !== qil_filter_text( $fallback ) ) return qil_filter_serving_count( $fallback );
    // Woo builds a variation title from its parent's name; that is not an
    // independent serving-count claim for a different size.
    if ( $product->is_type( 'variation' ) ) return null;
    $text = qil_filter_text( $product->get_name() );
    if ( preg_match( '/\d\s*(?:-|\/|to)\s*\d+\s*(?:servings?|srv)\b/u', $text ) ) return 0;
    if ( preg_match_all( '/\b([1-9]\d{0,4})\s*(?:servings?|srv)\b/u', $text, $matches ) ) {
        $counts = array_unique( array_map( 'intval', $matches[1] ) );
        return 1 === count( $counts ) ? (int) reset( $counts ) : 0;
    }
    return null;
}

/** Available variant + that variant's serving count, never a sold-out price minimum. */
function qil_goal_value_record( array $record, $product = null ) {
    $record['price']['perServing'] = 0.0;
    $record['price']['perServingFormatted'] = '';
    $record['price']['perServingHtml'] = '';
    $record['price']['perServingVerified'] = false;
    $record['price']['perServingBasis'] = null;
    $product = $product ?: wc_get_product( (int) $record['id'] );
    if ( ! $product ) return $record;

    // Variable products can otherwise walk every visible child every time the
    // same card is hydrated by search, goals, product pages and personalization.
    // Cache only the five derived per-serving fields; the surrounding live card
    // remains owned by the current WooCommerce request.
    $market_identity = qil_perf_market_identity();
    $cache_context = array(
        QIL_VERSION,
        qil_perf_product_version(),
        (int) $product->get_id(),
        (string) ( $record['price']['currency'] ?? '' ),
        (string) ( $record['facts']['servings'] ?? '' ),
        $market_identity,
    );
    $cache_key = 'qil_per_serving_v2_' . md5( wp_json_encode( $cache_context ) );
    $public_runtime_cache = qil_perf_identity_public( $market_identity );
    $cached = qil_perf_cache_get( $cache_key, false, $public_runtime_cache );
    if ( is_array( $cached ) ) {
        foreach ( $cached as $key => $value ) $record['price'][ $key ] = $value;
        return $record;
    }

    $parent_count = qil_filter_product_servings( $product, $record['facts']['servings'] ?? '' );
    $variable = $product->is_type( 'variable' );
    $inherit = true;
    if ( $variable ) {
        foreach ( array_keys( (array) $product->get_variation_attributes() ) as $key ) {
            $key = qil_goal_token( preg_replace( '/^pa_/i', '', (string) $key ) );
            if ( ! in_array( $key, array( 'flavor', 'flavour', 'flavors', 'flavours', 'taste', 'نكهة', 'النكهة' ), true ) ) { $inherit = false; break; }
        }
        $ids = $product->get_visible_children();
        if ( $ids ) update_meta_cache( 'post', $ids );
    } else {
        $ids = ( $product->is_type( 'simple' ) || $product->is_type( 'variation' ) ) ? array( $product->get_id() ) : array();
    }
    $best = null;
    foreach ( $ids as $id ) {
        $choice = (int) $id === (int) $product->get_id() ? $product : wc_get_product( $id );
        if ( ! $choice || 'publish' !== $choice->get_status() || 'instock' !== $choice->get_stock_status()
            || ! $choice->is_in_stock() || ! $choice->is_purchasable() || ! $choice->has_enough_stock( 1 ) ) continue;
        if ( $variable && ( ! $choice->is_type( 'variation' ) || (int) $choice->get_parent_id() !== (int) $product->get_id()
            || ! $choice->variation_is_active() || ! $choice->variation_is_visible() ) ) continue;
        $count = $variable ? qil_filter_product_servings( $choice ) : $parent_count;
        if ( null === $count && $inherit ) $count = $parent_count;
        if ( ! $count || $count < 1 || $count > 10000 ) continue;
        $price = (float) wc_get_price_to_display( $choice );
        if ( ! is_finite( $price ) || $price <= 0 ) continue;
        $unit = $price / $count;
        if ( null === $best || $unit < $best['unit'] || ( $unit === $best['unit'] && (int) $id < $best['productId'] ) ) {
            $best = array( 'unit' => $unit, 'productId' => (int) $id, 'variationId' => $choice->is_type( 'variation' ) ? (int) $id : 0, 'servings' => $count, 'displayPrice' => $price );
        }
    }
    if ( null !== $best ) {
        $display = qil_price_display( $best['unit'], $best['unit'], $record['price']['currency'] );
        $record['price']['perServing'] = $best['unit'];
        $record['price']['perServingFormatted'] = $display['formatted'];
        $record['price']['perServingHtml'] = $display['formattedHtml'];
        $record['price']['perServingVerified'] = true;
        $record['price']['perServingBasis'] = $best;
    }

    $derived = array();
    foreach ( array( 'perServing', 'perServingFormatted', 'perServingHtml', 'perServingVerified', 'perServingBasis' ) as $key ) {
        $derived[ $key ] = $record['price'][ $key ];
    }
    qil_perf_cache_set( $cache_key, $derived, $public_runtime_cache ? 30 : 2 * MINUTE_IN_SECONDS, $public_runtime_cache );
    return $record;
}
