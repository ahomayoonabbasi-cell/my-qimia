<?php
/**
 * Taxonomy-led, lazy goal catalogue. No classification from product titles,
 * descriptions, translated copy, sales or medical inference.
 *
 * @package Qimia_Intelligence_Lab
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/verified-filters.php';

function qil_goal_policy() {
	return (array) apply_filters( 'qil_goal_policy', array(
		'muscle'      => array( 'protein', 'mass_gainer', 'creatine', 'amino_recovery' ),
		'fat-loss'    => array( 'fat_burner' ),
		'performance' => array( 'pre_workout', 'creatine', 'amino_recovery' ),
		'energy'      => array( 'pre_workout', 'energy_support', 'nootropic' ),
		'recovery'    => array( 'amino_recovery', 'hydration', 'joint_support', 'recovery_support' ),
		'sleep'       => array( 'sleep_support' ),
		'wellness'    => array( 'daily_wellness', 'omega_support' ),
		'beauty'      => array( 'beauty_support' ),
	) );
}

/** Stable taxonomy vocabulary, not category IDs, display names or title rules. */
function qil_goal_term_policy() {
	return (array) apply_filters( 'qil_goal_term_policy', array(
		'protein'          => array( 'protein', 'proteins', 'protein-whey', 'whey-protein', 'whey-isolate', 'whey', 'casein', 'plant-protein', 'beef-protein', 'egg-protein', 'protein-blends' ),
		'mass_gainer'      => array( 'mass-gainer', 'mass-gainers', 'weight-gainer', 'weight-gainers', 'weight-gain' ),
		'creatine'         => array( 'creatine', 'creatines', 'creatine-monohydrate', 'creatines-oman', 'creatine-oman' ),
		'fat_burner'       => array( 'fat-burner', 'fat-burners', 'thermogenic', 'thermogenics', 'weight-loss' ),
		'pre_workout'      => array( 'pre-workout', 'pre-workouts', 'preworkout', 'preworkouts', 'caffeine-pro-preworkouts', 'pump-nitric-oxide' ),
		'amino_recovery'   => array( 'amino-acids', 'amino-acids-oman', 'bcaa', 'bcaas', 'eaa', 'eaas', 'glutamine', 'glutamine-oman' ),
		'hydration'        => array( 'hydration', 'electrolytes', 'electrolyte', 'isotonic' ),
		'joint_support'    => array( 'joint-support', 'joint-health', 'joints', 'glucosamine' ),
		'recovery_support' => array( 'recovery-support', 'recovery' ),
		'sleep_support'    => array( 'sleep', 'sleep-support', 'sleep-aid', 'sleep-aids', 'melatonin', 'melatonin-oman' ),
		'daily_wellness'   => array( 'daily-wellness', 'wellness', 'vitamin', 'vitamins', 'multivitamin', 'multivitamins', 'vitamins-minerals', 'minerals', 'magnesium', 'magnesium-oman', 'zinc', 'zinc-oman', 'probiotic', 'probiotics', 'gut-health', 'greens' ),
		'omega_support'    => array( 'omega', 'omega-3', 'omega-3-oman', 'fish-oil', 'omega-support' ),
		'beauty_support'   => array( 'beauty', 'beauty-support', 'collagen', 'collagens', 'collagen-oman', 'biotin', 'hair-nail-skin-health', 'hair-skin-nails' ),
		'energy_support'   => array( 'energy', 'energy-support', 'energy-drinks' ),
		'nootropic'        => array( 'nootropic', 'nootropics', 'focus' ),
	) );
}

function qil_goal_token( $value ) {
	return sanitize_title( str_replace( '_', '-', strtolower( trim( (string) $value ) ) ) );
}

function qil_goal_purposes_for_term( $value, $attribute = false ) {
	// Build canonical lookup once per request, not once per term per product.
	static $lookup = null;
	if ( null === $lookup ) {
		$lookup = array();
		foreach ( qil_goal_term_policy() as $purpose => $aliases ) {
			foreach ( array_merge( array( $purpose ), (array) $aliases ) as $alias ) {
				$token = qil_goal_token( $alias );
				$lookup[ $token ][] = $purpose;
			}
		}
	}
	$token = qil_goal_token( $value );
	$out = $lookup[ $token ] ?? array();
	if ( $attribute && ! $out ) {
		$policy = qil_goal_policy();
		if ( isset( $policy[ $token ][0] ) ) { $out[] = $policy[ $token ][0]; }
	}
	return array_values( array_unique( $out ) );
}

/** One compact evidence tuple: [canonical purpose, tier, source, term ID]. */
function qil_goal_taxonomy_profile( $product_id, $categories = null, $tags = null ) {
	static $memo = array();
	$product_id = absint( $product_id );
	if ( isset( $memo[ $product_id ] ) ) {
		return $memo[ $product_id ];
	}
	$entries = array();
	$tokens = array();
	$add = static function ( $token, $tier, $source, $term_id = 0, $attribute = false ) use ( &$entries, &$tokens ) {
		$token = qil_goal_token( $token );
		$tokens[] = $token;
		foreach ( qil_goal_purposes_for_term( $token, $attribute ) as $purpose ) {
			if ( ! isset( $entries[ $purpose ] ) || $tier > $entries[ $purpose ][1] ) {
				$entries[ $purpose ] = array( $purpose, (int) $tier, $source, (int) $term_id );
			}
		}
	};
	$categories = null === $categories ? get_the_terms( $product_id, 'product_cat' ) : $categories;
	$tags       = null === $tags ? get_the_terms( $product_id, 'product_tag' ) : $tags;
	foreach ( is_array( $categories ) ? $categories : array() as $term ) {
		$add( $term->slug, 3, 'category', $term->term_id );
		foreach ( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$add( $ancestor->slug, 2, 'ancestor', $ancestor->term_id );
			}
		}
	}
	foreach ( is_array( $tags ) ? $tags : array() as $term ) {
		$add( $term->slug, 1, 'tag', $term->term_id );
	}
	$purpose_attributes = (array) apply_filters( 'qil_goal_attribute_names', array( 'pa_purpose', 'purpose', 'pa_goal', 'goal', 'pa_goals', 'goals', 'pa_product-purpose', 'product-purpose' ) );
	$diet_attributes = (array) apply_filters( 'qil_goal_dietary_attribute_names', array( 'pa_dietary', 'dietary', 'pa_diet', 'diet', 'pa_dietary-preference', 'dietary-preference', 'pa_vegan', 'vegan', 'pa_stimulant-free', 'stimulant-free' ) );
	$diet_attributes = array_merge( $diet_attributes, array( 'pa_vegan-friendly', 'vegan-friendly', 'pa_stim-free', 'stim-free', 'pa_caffeine-free', 'caffeine-free', 'dietary preferences', 'dietary-preferences' ) );
	$purpose_attributes = array_map( 'qil_goal_token', $purpose_attributes );
	$diet_attributes = array_map( 'qil_goal_token', $diet_attributes );
	$attributes = get_post_meta( $product_id, '_product_attributes', true );
	foreach ( is_array( $attributes ) ? $attributes : array() as $attribute ) {
		$name = isset( $attribute['name'] ) ? (string) $attribute['name'] : '';
		$is_purpose = in_array( qil_goal_token( $name ), $purpose_attributes, true );
		$is_diet = in_array( qil_goal_token( $name ), $diet_attributes, true );
		if ( ! $is_purpose && ! $is_diet ) {
			continue;
		}
		$values = array();
		if ( ! empty( $attribute['is_taxonomy'] ) ) {
			$terms = get_the_terms( $product_id, $name );
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$values[] = $term->slug;
			}
		} else {
			$values = preg_split( '/[|,;،]+/u', isset( $attribute['value'] ) && is_scalar( $attribute['value'] ) ? (string) $attribute['value'] : '' );
		}
		foreach ( $values as $value ) {
			if ( $is_purpose ) {
				$add( $value, 4, 'attribute', 0, true );
			} else {
				$token = qil_goal_token( $value );
				$key = qil_goal_token( preg_replace( '/^pa_/i', '', $name ) );
				$key = array( 'vegan-friendly' => 'vegan', 'stim-free' => 'stimulant-free' )[ $key ] ?? $key;
				if ( in_array( $key, array( 'vegan', 'stimulant-free', 'caffeine-free' ), true ) ) {
					if ( in_array( $token, array_map( 'qil_goal_token', array( 'yes', 'true', '1', 'نعم', $key ) ), true ) ) {
						$tokens[] = $key;
					} elseif ( in_array( $token, array_map( 'qil_goal_token', array( 'no', 'false', '0', 'لا', 'not-' . $key ) ), true ) ) {
						$tokens[] = 'not-' . $key;
					}
				} else {
					$tokens[] = $token;
				}
			}
		}
	}
	$goals = array();
	foreach ( qil_goal_policy() as $goal => $purposes ) {
		foreach ( (array) $purposes as $purpose ) {
			if ( isset( $entries[ $purpose ] ) ) {
				$goals[] = $goal;
				break;
			}
		}
	}
	$ordered = array_values( $entries );
	usort( $ordered, static function ( $a, $b ) { return $b[1] <=> $a[1] ?: strcmp( $a[0], $b[0] ); } );
	$flag_policy = (array) apply_filters( 'qil_goal_dietary_terms', array(
		'vegan' => array( 'vegan', 'vegan-friendly', 'مناسب للنباتيين', 'نباتي بالكامل' ), 'vegetarian' => array( 'vegetarian' ),
		'glutenFree' => array( 'gluten-free' ), 'sugarFree' => array( 'sugar-free' ),
		'halal' => array( 'halal' ), 'stimulantFree' => array( 'stimulant-free', 'non-stimulant', 'stim-free', 'بدون منبهات', 'خالي من المنبهات', 'بدون منشطات' ),
		'caffeineFree' => array( 'caffeine-free' ),
	) );
	$dietary = array( 'labels' => array() );
	foreach ( $flag_policy as $flag => $aliases ) {
		$yes = false;
		$no = false;
		foreach ( (array) $aliases as $alias ) {
			$alias = qil_goal_token( $alias );
			$yes = $yes || in_array( $alias, $tokens, true );
			$no = $no || in_array( 'not-' . $alias, $tokens, true ) || in_array( 'non-' . $alias, $tokens, true );
		}
		$dietary[ $flag ] = $no ? false : ( $yes ? true : null );
		if ( true === $dietary[ $flag ] ) {
			$dietary['labels'][] = reset( $aliases );
		}
	}
	return $memo[ $product_id ] = array( 'goals' => $goals, 'purposeKey' => $ordered ? $ordered[0][0] : '', 'evidence' => $ordered, 'dietary' => $dietary );
}

function qil_goal_catalogue_version() {
	$woo = class_exists( 'WC_Cache_Helper' ) ? WC_Cache_Helper::get_transient_version( 'product' ) : '';
	$terms = function_exists( 'wp_cache_get_last_changed' ) ? wp_cache_get_last_changed( 'terms' ) : '';
	return substr( hash( 'sha256', QIL_VERSION . '|' . $woo . '|' . $terms . '|' . wp_json_encode( array( qil_goal_policy(), qil_goal_term_policy() ) ) ), 0, 20 );
}

/**
 * Goal membership changes only when taxonomy/purpose evidence or publication
 * changes. Stock and price changes must not invalidate the expensive membership
 * index; those are checked by the live Woo catalogue layer afterwards.
 */
function qil_goal_membership_version() {
	$generation = (int) get_option( 'qil_goal_membership_generation', 1 );
	$attribute_names = (array) apply_filters( 'qil_goal_attribute_names', array( 'pa_purpose', 'purpose', 'pa_goal', 'goal', 'pa_goals', 'goals', 'pa_product-purpose', 'product-purpose' ) );
	return substr( hash( 'sha256', QIL_VERSION . '|' . $generation . '|' . wp_json_encode( array( qil_goal_policy(), qil_goal_term_policy(), $attribute_names ) ) ), 0, 20 );
}

function qil_goal_bump_membership_generation() {
	$generation = max( 1, (int) get_option( 'qil_goal_membership_generation', 1 ) );
	update_option( 'qil_goal_membership_generation', $generation + 1, false );
}

/** Taxonomies that can carry explicit goal/purpose evidence. */
function qil_goal_membership_taxonomies() {
	$taxonomies = array( 'product_cat', 'product_tag' );
	foreach ( (array) apply_filters( 'qil_goal_attribute_names', array( 'pa_purpose', 'purpose', 'pa_goal', 'goal', 'pa_goals', 'goals', 'pa_product-purpose', 'product-purpose' ) ) as $name ) {
		$name = sanitize_key( (string) $name );
		if ( $name && taxonomy_exists( $name ) ) $taxonomies[] = $name;
	}
	return array_values( array_unique( $taxonomies ) );
}

/** Keep the membership cache exact without coupling it to inventory churn. */
function qil_goal_membership_terms_changed( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( 'product' !== get_post_type( (int) $object_id ) ) return;
	if ( in_array( (string) $taxonomy, qil_goal_membership_taxonomies(), true ) ) qil_goal_bump_membership_generation();
}
add_action( 'set_object_terms', 'qil_goal_membership_terms_changed', 10, 4 );

function qil_goal_membership_term_definition_changed( $term_id, $tt_id, $taxonomy ) {
	if ( in_array( (string) $taxonomy, qil_goal_membership_taxonomies(), true ) ) qil_goal_bump_membership_generation();
}
add_action( 'created_term', 'qil_goal_membership_term_definition_changed', 10, 3 );
add_action( 'edited_term', 'qil_goal_membership_term_definition_changed', 10, 3 );
add_action( 'delete_term', 'qil_goal_membership_term_definition_changed', 10, 3 );

function qil_goal_membership_meta_changed( $meta_id, $object_id, $meta_key ) {
	if ( '_product_attributes' === (string) $meta_key && 'product' === get_post_type( (int) $object_id ) ) qil_goal_bump_membership_generation();
}
add_action( 'added_post_meta', 'qil_goal_membership_meta_changed', 10, 3 );
add_action( 'updated_post_meta', 'qil_goal_membership_meta_changed', 10, 3 );
add_action( 'deleted_post_meta', 'qil_goal_membership_meta_changed', 10, 3 );

function qil_goal_membership_status_changed( $new_status, $old_status, $post ) {
	if ( $new_status === $old_status || ! is_a( $post, 'WP_Post' ) || 'product' !== $post->post_type ) return;
	qil_goal_bump_membership_generation();
}
add_action( 'transition_post_status', 'qil_goal_membership_status_changed', 10, 3 );

/**
 * Targeted goal membership lookup. The previous implementation scanned every
 * published product on each cold five-minute cache. This query starts from the
 * exact category/tag/purpose vocabulary for the requested goal, then applies
 * the existing taxonomy-profile gate to preserve identical classification.
 */
function qil_goal_candidate_ids( $goal ) {
	$policy = qil_goal_policy();
	if ( ! isset( $policy[ $goal ] ) ) return array();

	$cache_key = 'qil_goal_ids_v2_' . md5( $goal . '|' . qil_goal_membership_version() );
	$cached = get_transient( $cache_key );
	if ( false !== $cached && is_array( $cached ) ) return array_values( array_map( 'absint', $cached ) );

	$purpose_policy = qil_goal_term_policy();
	$tokens = array( qil_goal_token( $goal ) );
	foreach ( (array) $policy[ $goal ] as $purpose ) {
		$tokens[] = qil_goal_token( $purpose );
		foreach ( (array) ( $purpose_policy[ $purpose ] ?? array() ) as $alias ) $tokens[] = qil_goal_token( $alias );
	}
	$tokens = array_values( array_unique( array_filter( $tokens ) ) );

	$match_clauses = array();
	foreach ( qil_goal_membership_taxonomies() as $taxonomy ) {
		$term_ids = get_terms( array(
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'slug' => $tokens,
			'fields' => 'ids',
		) );
		if ( is_wp_error( $term_ids ) || ! $term_ids ) continue;
		$clause = array(
			'taxonomy' => $taxonomy,
			'field' => 'term_id',
			'terms' => array_values( array_map( 'absint', $term_ids ) ),
			'operator' => 'IN',
		);
		if ( 'product_cat' === $taxonomy ) $clause['include_children'] = true;
		$match_clauses[] = $clause;
	}

	$candidate_ids = array();
	if ( $match_clauses ) {
		$tax_query = array( 'relation' => 'AND' );
		$tax_query[] = array_merge( array( 'relation' => 'OR' ), $match_clauses );
		if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			$visibility = wc_get_product_visibility_term_ids();
			$hidden = array_filter( array(
				isset( $visibility['exclude-from-catalog'] ) ? (int) $visibility['exclude-from-catalog'] : 0,
				isset( $visibility['exclude-from-search'] ) ? (int) $visibility['exclude-from-search'] : 0,
			) );
			if ( $hidden ) $tax_query[] = array( 'taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => array_values( $hidden ), 'operator' => 'NOT IN' );
		}
		$query = new WP_Query( array(
			'post_type' => 'product', 'post_status' => 'publish', 'has_password' => false,
			'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true,
			'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true,
			'tax_query' => $tax_query,
			'update_post_meta_cache' => false, 'update_post_term_cache' => false,
		) );
		$candidate_ids = array_map( 'absint', (array) $query->posts );
	}

	// Preserve products whose explicit purpose is stored in a non-taxonomy
	// WooCommerce attribute. SQL only discovers a narrow shortlist; the exact
	// existing qil_goal_taxonomy_profile() still decides membership below.
	global $wpdb;
	$attribute_names = array();
	foreach ( (array) apply_filters( 'qil_goal_attribute_names', array( 'pa_purpose', 'purpose', 'pa_goal', 'goal', 'pa_goals', 'goals', 'pa_product-purpose', 'product-purpose' ) ) as $name ) {
		$name = sanitize_text_field( (string) $name );
		if ( $name && ! taxonomy_exists( $name ) ) $attribute_names[] = $name;
	}
	if ( $attribute_names && $tokens ) {
		$name_parts = array(); $value_parts = array(); $params = array();
		foreach ( array_values( array_unique( $attribute_names ) ) as $name ) {
			$name_parts[] = 'pm.meta_value LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $name ) . '%';
		}
		$needles = array();
		foreach ( $tokens as $token ) {
			$needles[] = $token;
			$needles[] = str_replace( '-', ' ', $token );
			$needles[] = str_replace( '-', '_', $token );
		}
		foreach ( array_values( array_unique( array_filter( $needles ) ) ) as $needle ) {
			$value_parts[] = 'pm.meta_value LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$sql = "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_product_attributes' AND p.post_type = 'product' AND p.post_status = 'publish' AND (" . implode( ' OR ', $name_parts ) . ') AND (' . implode( ' OR ', $value_parts ) . ')';
		$attribute_ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );
		$candidate_ids = array_merge( $candidate_ids, array_map( 'absint', (array) $attribute_ids ) );
	}

	$candidate_ids = array_values( array_unique( array_filter( $candidate_ids ) ) );
	if ( $candidate_ids ) {
		update_meta_cache( 'post', $candidate_ids );
		update_object_term_cache( $candidate_ids, 'product' );
	}
	$visibility = function_exists( 'wc_get_product_visibility_term_ids' ) ? wc_get_product_visibility_term_ids() : array();
	$hidden_terms = array_filter( array(
		isset( $visibility['exclude-from-catalog'] ) ? (int) $visibility['exclude-from-catalog'] : 0,
		isset( $visibility['exclude-from-search'] ) ? (int) $visibility['exclude-from-search'] : 0,
	) );
	$accepted = array();
	foreach ( $candidate_ids as $id ) {
		if ( 'publish' !== get_post_status( $id ) || post_password_required( $id ) ) continue;
		if ( $hidden_terms && has_term( $hidden_terms, 'product_visibility', $id ) ) continue;
		$profile = qil_goal_taxonomy_profile( $id );
		if ( in_array( $goal, (array) $profile['goals'], true ) ) $accepted[] = $id;
	}
	$accepted = array_values( array_unique( array_map( 'absint', $accepted ) ) );
	set_transient( $cache_key, $accepted, 6 * HOUR_IN_SECONDS );
	return $accepted;
}

/** Relevance tuple shared by server responses and the browser context. */
function qil_goal_product_evidence( array $record, $goal ) {
	$policy = qil_goal_policy();
	$purposes = isset( $policy[ $goal ] ) ? (array) $policy[ $goal ] : array();
	$best = null;
	foreach ( isset( $record['match']['evidence'] ) ? (array) $record['match']['evidence'] : array() as $entry ) {
		$position = array_search( $entry[0], $purposes, true );
		if ( false === $position || empty( $entry[1] ) ) { continue; }
		$evidence = array( 'purposeKey' => $entry[0], 'tier' => (int) $entry[1], 'priority' => (int) $position, 'sources' => array( $entry[2] ), 'reasonCodes' => array( 'taxonomy_' . $entry[2] ) );
		if ( ! $best || $evidence['tier'] > $best['tier'] || ( $evidence['tier'] === $best['tier'] && $position < $best['priority'] ) ) { $best = $evidence; }
	}
	return $best;
}

function qil_goal_rank_records( array $records, $goal, array $filters = array(), $query = '' ) {
	$eligible = array();
	$query = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $query ), 'UTF-8' ) : strtolower( trim( $query ) );
	foreach ( $records as $record ) {
		$evidence = qil_goal_product_evidence( $record, $goal );
		if ( ! $evidence ) { continue; }
		if ( in_array( 'stimfree', $filters, true ) && true !== ( $record['dietary']['stimulantFree'] ?? null ) ) { continue; }
		if ( in_array( 'vegan', $filters, true ) && true !== ( $record['dietary']['vegan'] ?? null ) ) { continue; }
		$per_serving = isset( $record['price']['perServing'] ) && is_numeric( $record['price']['perServing'] ) ? (float) $record['price']['perServing'] : 0;
		if ( in_array( 'value', $filters, true ) && ( $per_serving <= 0 || ! is_finite( $per_serving ) || true !== ( $record['price']['perServingVerified'] ?? false ) ) ) { continue; }
		if ( '' !== $query ) {
			$text = implode( ' ', array( $record['name'] ?? '', $record['brand'] ?? '', implode( ' ', $record['categorySlugs'] ?? array() ), implode( ' ', $record['match']['searchTokens'] ?? array() ) ) );
			$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
			$ok = true;
			foreach ( preg_split( '/\s+/u', $query ) as $word ) { if ( false === strpos( $text, $word ) ) { $ok = false; break; } }
			if ( ! $ok ) { continue; }
		}
		$complete = ( ! empty( $record['images'] ) ? 1 : 0 ) + ( ! empty( $record['facts']['servings'] ) ? 1 : 0 ) + ( ! empty( $record['facts']['primaryActives'] ) ? 1 : 0 );
		$stock_rank = ! empty( $record['stock']['inStock'] ) ? 0 : 1;
		$purchase_rank = ! empty( $record['purchase']['purchasable'] ) ? 0 : 1;
		$rank = in_array( 'value', $filters, true )
			? array( $stock_rank, $purchase_rank, $per_serving, -$evidence['tier'], $evidence['priority'] )
			: array( -$evidence['tier'], $evidence['priority'], 0, $stock_rank, $purchase_rank );
		$eligible[] = array( 'record' => $record, 'rank' => array_merge( $rank, array( -$complete, -(int) ( $record['salesCount'] ?? 0 ), -(float) ( $record['rating'] ?? 0 ), (int) $record['id'] ) ) );
	}
	usort( $eligible, static function ( $a, $b ) {
		foreach ( $a['rank'] as $i => $value ) { $diff = $value <=> $b['rank'][ $i ]; if ( $diff ) { return $diff; } }
		return 0;
	} );
	return array_values( array_map( static function ( $row ) { return $row['record']; }, $eligible ) );
}

function qil_rest_goal_products( WP_REST_Request $request ) {
	$goal = sanitize_key( (string) $request->get_param( 'goal' ) );
	$policy = qil_goal_policy();
	if ( ! isset( $policy[ $goal ] ) ) { return new WP_Error( 'qil_invalid_goal', 'Unknown goal.', array( 'status' => 400 ) ); }
	$locale = qil_rest_storefront_locale( $request );
	if ( is_wp_error( $locale ) ) { return $locale; }
	$locale = is_array( $locale ) ? ( $locale['locale'] ?? 'en' ) : $locale;
	$filters = array_values( array_intersect( array( 'stimfree', 'vegan', 'value' ), explode( ',', (string) $request->get_param( 'filters' ) ) ) );
	$page = max( 1, absint( $request->get_param( 'page' ) ) );
	$limit = 48;
	$query = sanitize_text_field( (string) $request->get_param( 'q' ) );
	$version = qil_goal_catalogue_version();
	$market = qil_market_context( 'ar' === $locale );
	// Do not share personalised prices. Even anonymous Woo sessions get an
	// isolated key; responses themselves are always private/no-store.
	$session = function_exists( 'WC' ) && WC()->session ? (string) WC()->session->get_customer_id() : '';
	$cache_key = 'qil_goal_cards_' . md5( wp_json_encode( array( $version, $goal, $locale, $market['country'], $market['currency'], qil_pricing_cache_context(), get_current_user_id(), $session, in_array( 'value', $filters, true ) ) ) );
	$records = get_transient( $cache_key );
	if ( ! is_array( $records ) ) {
		$records = array();
		foreach ( array_chunk( qil_goal_candidate_ids( $goal ), 96 ) as $chunk ) {
			$records = array_merge( $records, qil_get_catalogue( array( 'include' => $chunk, 'limit' => count( $chunk ), '_qil_locale' => $locale ) ) );
		}
		set_transient( $cache_key, $records, 2 * MINUTE_IN_SECONDS );
	}
	$records = qil_goal_rank_records( $records, $goal, $filters, $query );
	$total = count( $records );
	$records = array_slice( $records, ( $page - 1 ) * $limit, $limit );
	$evidence = array();
	foreach ( $records as $record ) { $evidence[ $record['id'] ] = qil_goal_product_evidence( $record, $goal ); }
	$context = array(
		'schemaVersion' => '1.0', 'goal' => $goal, 'filters' => $filters, 'query' => $query,
		'locale' => $locale, 'direction' => 'ar' === $locale ? 'rtl' : 'ltr',
		'market' => array( 'country' => $market['country'], 'currency' => $market['currency'] ),
		'productIds' => array_values( wp_list_pluck( $records, 'id' ) ), 'evidence' => $evidence,
		'rejected' => (object) array(), 'catalogueVersion' => $version, 'createdAt' => gmdate( 'c' ),
	);
	return qil_private_live_rest_response( array( 'products' => $records, 'context' => $context, 'total' => $total, 'page' => $page, 'hasMore' => $page * $limit < $total, 'catalogueVersion' => $version ) );
}

function qil_register_goal_route() {
	register_rest_route( 'qimia-lab/v1', '/goals/(?P<goal>[a-z-]+)', array(
		'methods' => WP_REST_Server::READABLE, 'callback' => 'qil_rest_goal_products',
		'permission_callback' => static function () { return qil_experience_enabled() && function_exists( 'wc_get_products' ); },
		'args' => array(
			'goal' => array( 'type' => 'string', 'required' => true, 'enum' => array_keys( qil_goal_policy() ), 'sanitize_callback' => 'sanitize_key' ),
			'qil_locale' => array( 'type' => 'string', 'required' => true, 'enum' => array( 'en', 'ar' ) ),
			'filters' => array( 'type' => 'string', 'default' => '', 'maxLength' => 64, 'pattern' => '^(?:(?:stimfree|vegan|value)(?:,(?:stimfree|vegan|value))*)?$' ),
			'q' => array( 'type' => 'string', 'default' => '', 'maxLength' => 64, 'sanitize_callback' => 'sanitize_text_field' ),
			'page' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 10000 ),
		),
	) );
}
add_action( 'rest_api_init', 'qil_register_goal_route' );
