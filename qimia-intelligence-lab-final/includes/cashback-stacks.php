<?php
/**
 * Cashback Stacks: the store's own stack products, shown with the value a
 * shopper can verify — what is inside, the price, what the same items cost
 * separately today (only when that is really higher) and the cashback band
 * the stack price lands in. Plus a read-only pricing assistant for the
 * merchant. No price, product or bundle is created or changed here.
 *
 * @package Qimia_Intelligence_Lab
 */
defined( 'ABSPATH' ) || exit;

final class QIL_Stacks {
	const MIN_STACKS = 2;

	public static function boot() {
		add_shortcode( 'qimia_cashback_stacks', array( __CLASS__, 'section' ) );
	}

	/** Price points (OMR) that sit inside the issuer's bands; filterable. */
	public static function price_points() {
		return array_values( array_filter( array_map( 'floatval', (array) apply_filters( 'qil_stack_price_points', array( 24.9, 34.9, 44.9, 54.9, 64.9 ) ) ) ) );
	}

	public static function term_ids() {
		$ids = array();
		foreach ( preg_split( '/[\s,]+/', (string) QIL_Boost::settings()['stack_slugs'] ) as $slug ) {
			$slug = sanitize_title( $slug );
			if ( '' === $slug ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** Merchant map from the Growth tab: "stackId: componentId, componentId x2". */
	private static function mapped() {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			foreach ( preg_split( '/\R/', (string) QIL_Boost::settings()['stack_components'] ) as $line ) {
				if ( ! preg_match( '/^\s*(\d+)\s*:\s*(.+)$/', $line, $match ) ) {
					continue;
				}
				foreach ( preg_split( '/\s*,\s*/', trim( $match[2] ) ) as $part ) {
					if ( preg_match( '/^(\d+)(?:\s*[x*]\s*(\d+))?$/i', trim( $part ), $piece ) ) {
						$map[ (int) $match[1] ][] = array( (int) $piece[1], max( 1, (int) ( $piece[2] ?? 1 ) ) );
					}
				}
			}
		}
		return $map;
	}

	/**
	 * What is inside a stack, as [product_id, quantity] pairs: the merchant's
	 * map first, then grouped products and the common bundle plugins' own data.
	 */
	public static function components( $product ) {
		$id  = (int) $product->get_id();
		$map = self::mapped();
		if ( ! empty( $map[ $id ] ) ) {
			return array_slice( $map[ $id ], 0, 8 );
		}
		$pairs = array();
		if ( $product->is_type( 'grouped' ) ) {
			foreach ( (array) $product->get_children() as $child ) {
				$pairs[] = array( (int) $child, 1 );
			}
		} elseif ( method_exists( $product, 'get_bundled_items' ) ) { // WooCommerce Product Bundles.
			foreach ( (array) $product->get_bundled_items() as $item ) {
				if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) ) {
					$pairs[] = array( (int) $item->get_product_id(), method_exists( $item, 'get_quantity' ) ? max( 1, (int) $item->get_quantity( 'default' ) ) : 1 );
				}
			}
		} else {
			$woosb = get_post_meta( $id, 'woosb_ids', true ); // WPC Product Bundles.
			if ( is_string( $woosb ) && '' !== $woosb ) {
				foreach ( explode( ',', $woosb ) as $part ) {
					$bits = explode( '/', trim( $part ) );
					if ( absint( $bits[0] ?? 0 ) ) {
						$pairs[] = array( absint( $bits[0] ), max( 1, (int) ( $bits[1] ?? 1 ) ) );
					}
				}
			} elseif ( is_array( $woosb ) ) {
				foreach ( $woosb as $row ) {
					if ( is_array( $row ) && absint( $row['id'] ?? 0 ) ) {
						$pairs[] = array( absint( $row['id'] ), max( 1, (int) ( $row['qty'] ?? 1 ) ) );
					}
				}
			}
			$yith = get_post_meta( $id, '_yith_wcpb_bundle_data', true ); // YITH Product Bundles.
			if ( ! $pairs && is_array( $yith ) ) {
				foreach ( $yith as $row ) {
					if ( is_array( $row ) && absint( $row['product_id'] ?? 0 ) ) {
						$pairs[] = array( absint( $row['product_id'] ), max( 1, (int) ( $row['bp_quantity'] ?? 1 ) ) );
					}
				}
			}
		}
		return array_slice( $pairs, 0, 8 );
	}

	private static function display_price( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			return (float) $product->get_variation_price( 'min', true );
		}
		if ( $product->is_type( 'grouped' ) ) {
			return 0.0;
		}
		return (float) wc_get_price_to_display( $product );
	}

	/**
	 * Stack cards for this market and language. Public data only; keyed by the
	 * product version and the Growth settings, cached for ten minutes.
	 */
	public static function stacks() {
		static $memo = array();
		if ( ! QIL_Boost::on( 'stacks' ) ) {
			return array();
		}
		$identity = qil_perf_market_identity();
		unset( $identity['user'], $identity['session'] );
		$locale = QIL_Boost::is_ar() ? 'ar' : 'en';
		$key    = 'qil_stacks_v1_' . md5( (string) wp_json_encode( array( QIL_VERSION, qil_perf_product_version(), $identity, $locale, QIL_Boost::settings()['stack_slugs'], QIL_Boost::settings()['stack_components'], QIL_Boost::bands() ) ) );
		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}
		$cached = qil_perf_cache_get( $key );
		if ( is_array( $cached ) ) {
			return $memo[ $key ] = $cached;
		}
		$rows  = array();
		$terms = self::term_ids();
		if ( $terms && class_exists( 'WP_Query' ) ) {
			$query = new WP_Query( array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => 16,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'                => array( 'menu_order' => 'ASC', 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
				'meta_query'             => array( array( 'key' => '_stock_status', 'value' => 'instock' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'tax_query'              => qil_product_visibility_tax_query( $terms ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'suppress_filters'       => false,
			) );
			$ids = array_map( 'absint', (array) $query->posts );
			if ( $ids && function_exists( '_prime_post_caches' ) ) {
				_prime_post_caches( $ids, true, true );
			}
			$market = QIL_Boost::market();
			$names  = array();
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product || ! $product->is_visible() || ! $product->is_purchasable() || ! $product->is_in_stock() || ! $product->get_image_id() ) {
					continue;
				}
				$price = self::display_price( $product );
				if ( $price <= 0 ) {
					continue;
				}
				$regular = $product->is_type( 'simple' ) && (float) $product->get_regular_price() > 0 ? (float) wc_get_price_to_display( $product, array( 'price' => (float) $product->get_regular_price() ) ) : $price;
				$inside  = array();
				$sum     = 0.0;
				$known   = true;
				foreach ( self::components( $product ) as $pair ) {
					$part = wc_get_product( $pair[0] );
					$each = $part ? self::display_price( $part ) : 0.0;
					if ( ! $part || $each <= 0 || 'publish' !== get_post_status( $part->is_type( 'variation' ) ? $part->get_parent_id() : $part->get_id() ) ) {
						$known = false;
						continue;
					}
					$inside[] = array( 'name' => qil_clean_text( $part->get_name() ), 'qty' => (int) $pair[1] );
					$names[]  = qil_clean_text( $part->get_name() );
					$sum     += $each * (int) $pair[1];
				}
				// "Separately" is only ever the real sum of the components today;
				// a stack's own sale price is shown as "was" instead.
				$separately = $known && count( $inside ) >= 2 ? $sum : 0.0;
				$was        = $product->is_on_sale() && $regular > $price + 0.0005 ? $regular : 0.0;
				$reward     = $market['rate'] ? QIL_Boost::reward_for_omr( $price / $market['rate'] ) : null;
				$image      = function_exists( 'qil_image_data' ) ? qil_image_data( $product->get_image_id(), $product->get_name() ) : array();
				$name       = qil_clean_text( $product->get_name() );
				$names[]    = $name;
				$row        = array(
					'id'         => $id,
					'name'       => $name,
					'url'        => esc_url_raw( qil_localized_url( $product->get_permalink(), 'ar' === $locale ) ),
					'image'      => $image,
					'price'      => $price,
					'separately' => $separately > $price + 0.0005 ? $separately : 0.0,
					'was'        => $was,
					'inside'     => $inside,
					'blurb'      => $inside ? '' : wp_trim_words( qil_clean_text( $product->get_short_description() ), 16, '…' ),
					'reward'     => $reward,
					'type'       => $product->is_type( 'simple' ) ? 's' : ( $product->is_type( 'variable' ) ? 'v' : 'o' ),
					'ajax'       => $product->is_type( 'simple' ) && $product->supports( 'ajax_add_to_cart' ),
					'sku'        => qil_clean_text( $product->get_sku() ),
				);
				$rows[]     = $row;
				if ( count( $rows ) >= 8 ) {
					break;
				}
			}
			if ( 'ar' === $locale && $names && function_exists( 'qil_translate_batch' ) ) {
				$map = qil_translate_batch( $names, 'product_title', true );
				foreach ( $rows as &$row ) {
					$row['name'] = qil_translated( $row['name'], $map );
					foreach ( $row['inside'] as &$part ) {
						$part['name'] = qil_translated( $part['name'], $map );
					}
					unset( $part );
				}
				unset( $row );
			}
		}
		qil_perf_cache_set( $key, $rows, 10 * MINUTE_IN_SECONDS );
		return $memo[ $key ] = $rows;
	}

	private static function add_control( array $row ) {
		$ar = QIL_Boost::is_ar();
		if ( 's' === $row['type'] && $row['ajax'] ) {
			$cart = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
			return sprintf(
				'<a href="%1$s" data-quantity="1" data-product_id="%2$d" data-product_sku="%3$s" class="qil-button qil-button-primary qil-bundle-add add_to_cart_button ajax_add_to_cart product_type_simple" rel="nofollow" aria-label="%4$s"><span>%5$s</span><svg aria-hidden="true"><use href="#qil-i-cart-plus"/></svg></a>',
				esc_url( add_query_arg( array( 'add-to-cart' => (int) $row['id'], 'quantity' => 1 ), $cart ) ),
				(int) $row['id'],
				esc_attr( $row['sku'] ),
				esc_attr( sprintf( $ar ? 'أضف %s إلى السلة' : 'Add %s to your cart', $row['name'] ) ),
				esc_html( $ar ? 'أضف المجموعة' : 'Add the stack' )
			);
		}
		return sprintf(
			'<a href="%1$s" class="qil-button qil-button-primary qil-bundle-add"><span>%2$s</span><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></a>',
			esc_url( $row['url'] ),
			esc_html( $ar ? 'اختر المجموعة' : 'Choose this stack' )
		);
	}

	public static function section() {
		$stacks = self::stacks();
		if ( count( $stacks ) < self::MIN_STACKS ) {
			return '';
		}
		$ar = QIL_Boost::is_ar();
		ob_start();
		?>
		<section id="qil-stacks" class="qil-section qil-stacks" aria-labelledby="qil-stacks-title">
			<div class="qil-container">
				<header class="qil-stacks-head">
					<span class="qil-kicker"><?php echo esc_html( $ar ? 'مجموعات مصمّمة للكاش باك' : 'STACKS BUILT FOR CASHBACK' ); ?></span>
					<h2 id="qil-stacks-title"><?php echo esc_html( $ar ? 'قيمة واضحة. بدون خصومات غريبة.' : 'Clear value. No strange discounts.' ); ?></h2>
					<p><?php echo esc_html( $ar ? 'كل مجموعة تقع ضمن فئة كاش باك. ترى ما بداخلها، وسعرها منفصلة اليوم، والكاش باك الذي تمنحك إياه.' : 'Every stack lands on a cashback tier. See what is inside, what the same items cost separately today, and the cashback it earns.' ); ?></p>
				</header>
				<div class="qil-stacks-grid qil-rail" data-qil-rail="stacks">
					<?php foreach ( $stacks as $row ) : ?>
						<article class="qil-bundle-card qil-reveal" data-product-id="<?php echo esc_attr( $row['id'] ); ?>">
							<a class="qil-bundle-media" href="<?php echo esc_url( $row['url'] ); ?>" tabindex="-1" aria-hidden="true">
								<?php if ( ! empty( $row['image']['src'] ) ) : ?>
									<img src="<?php echo esc_url( $row['image']['src'] ); ?>"<?php echo ! empty( $row['image']['srcset'] ) ? ' srcset="' . esc_attr( $row['image']['srcset'] ) . '" sizes="(max-width: 680px) 72vw, 300px"' : ''; ?> width="<?php echo esc_attr( $row['image']['width'] ); ?>" height="<?php echo esc_attr( $row['image']['height'] ); ?>" alt="" loading="lazy" decoding="async">
								<?php endif; ?>
								<?php if ( null !== $row['reward'] ) : ?>
									<span class="qil-bundle-reward"><small><?php echo esc_html( $ar ? 'كاش باك' : 'CASHBACK' ); ?></small><b><bdi><?php echo esc_html( '+' . QIL_Boost::reward_text( $row['reward'] ) ); ?></bdi></b></span>
								<?php endif; ?>
							</a>
							<div class="qil-bundle-body">
								<h3><a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a></h3>
								<?php if ( $row['inside'] ) : ?>
									<ul class="qil-bundle-inside" aria-label="<?php echo esc_attr( $ar ? 'محتويات المجموعة' : 'What is inside' ); ?>">
										<?php foreach ( $row['inside'] as $part ) : ?>
											<li dir="auto"><?php echo esc_html( ( $part['qty'] > 1 ? $part['qty'] . ' × ' : '' ) . $part['name'] ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php elseif ( '' !== $row['blurb'] ) : ?>
									<p class="qil-bundle-blurb"><?php echo esc_html( $row['blurb'] ); ?></p>
								<?php endif; ?>
								<?php $reference = $row['separately'] > 0 ? $row['separately'] : (float) ( $row['was'] ?? 0 ); ?>
								<div class="qil-bundle-value">
									<strong><?php echo QIL_Boost::price_html( $row['price'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_price() through wp_kses_post(). ?></strong>
									<?php if ( $reference > 0 ) : ?>
										<span class="qil-bundle-separately"><?php echo esc_html( $row['separately'] > 0 ? ( $ar ? 'منفصلة' : 'Separately' ) : ( $ar ? 'كان' : 'Was' ) ); ?> <del><?php echo QIL_Boost::price_html( $reference ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></del></span>
									<?php endif; ?>
								</div>
								<?php if ( $reference > 0 ) : ?>
									<p class="qil-bundle-save"><?php echo wp_kses_post( sprintf( $ar ? 'توفّر %s اليوم' : 'You save %s today', QIL_Boost::price_html( $reference - $row['price'] ) ) ); ?></p>
								<?php endif; ?>
								<?php if ( null !== $row['reward'] ) : ?>
									<p class="qil-bundle-earn"><?php echo esc_html( sprintf( $ar ? 'ويمنحك كاش باك %s لطلبك القادم' : 'Plus %s cashback for your next order', QIL_Boost::reward_text( $row['reward'] ) ) ); ?></p>
								<?php endif; ?>
								<?php echo self::add_control( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the builder. ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
				<p class="qil-stacks-note"><?php echo esc_html( $ar ? 'الأسعار "منفصلة" هي أسعار المنتجات نفسها اليوم في كيميا. الكاش باك رصيد لطلب قادم على الطلبات المدفوعة المؤهلة.' : '"Separately" is what the same products cost at Qimia today. Cashback is credit for a future order on eligible paid orders.' ); ?></p>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------
	   Pricing assistant (Growth tab, read-only)
	   ------------------------------------------------------------------ */

	/** Cheapest in-stock best seller for a role, from the shared pool. */
	private static function best_for_role( $role ) {
		foreach ( QIL_Boost::pool() as $row ) {
			if ( in_array( $role, $row['g'], true ) ) {
				return $row;
			}
		}
		return null;
	}

	public static function admin_assistant() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$market = QIL_Boost::market();
		$points = self::price_points();
		echo '<h3>Stack pricing assistant</h3><p>Read-only. Prices are changed only in WooCommerce. Each price point sits inside one cashback band, so the stack earns that band\'s cashback. Bands and rewards come live from the cashback issuer.</p>';
		if ( ! QIL_Boost::bands() ) {
			echo '<p><strong>The cashback issuer is not active, so no band can be shown.</strong></p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Price point (OMR)</th><th>Cashback</th><th>Cashback as % of price</th></tr></thead><tbody>';
		foreach ( $points as $point ) {
			$reward = QIL_Boost::reward_for_omr( $point );
			echo '<tr><td>' . esc_html( number_format( $point, 3 ) ) . '</td><td>' . esc_html( number_format( (float) $reward, 3 ) ) . ' OMR</td><td>' . esc_html( $point > 0 ? number_format( 100 * (float) $reward / $point, 1 ) . '%' : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$stacks = self::stacks();
		echo '<h4>Current stacks (' . (int) count( $stacks ) . ')</h4>';
		if ( ! $stacks ) {
			echo '<p>No in-stock products were found in the stack categories: <code>' . esc_html( QIL_Boost::settings()['stack_slugs'] ) . '</code>.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Stack</th><th>Price</th><th>Separately</th><th>Cashback now</th><th>Nearest price point</th></tr></thead><tbody>';
			foreach ( $stacks as $row ) {
				$omr     = $market['rate'] ? $row['price'] / $market['rate'] : $row['price'];
				$nearest = null;
				foreach ( $points as $point ) {
					if ( null === $nearest || abs( $point - $omr ) < abs( $nearest - $omr ) ) {
						$nearest = $point;
					}
				}
				echo '<tr><td><a href="' . esc_url( get_edit_post_link( $row['id'] ) ?: $row['url'] ) . '">' . esc_html( $row['name'] ) . '</a>' . ( $row['inside'] ? '' : ' <em>(components unknown — map them below)</em>' ) . '</td><td>' . wp_kses_post( QIL_Boost::price_html( $row['price'] ) ) . '</td><td>' . ( $row['separately'] > 0 ? wp_kses_post( QIL_Boost::price_html( $row['separately'] ) ) : '—' ) . '</td><td>' . esc_html( null === $row['reward'] ? '—' : number_format( (float) $row['reward'], 3 ) . ' OMR' ) . '</td><td>' . esc_html( null === $nearest ? '—' : number_format( $nearest, 3 ) . ' OMR (' . sprintf( '%+.3f', $nearest - $omr ) . ')' ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		$blueprints = (array) apply_filters( 'qil_stack_blueprints', array(
			'Muscle Starter'   => array( 'protein', 'creatine' ),
			'Recovery Pack'    => array( 'magnesium', 'ashwagandha', 'multivitamin' ),
			'Daily Essentials' => array( 'omega3', 'multivitamin', 'magnesium' ),
		) );
		echo '<h4>Blueprints from today\'s best sellers</h4><table class="widefat striped"><thead><tr><th>Stack</th><th>Suggested contents</th><th>Separately</th><th>Price point at or below</th></tr></thead><tbody>';
		foreach ( $blueprints as $label => $roles ) {
			$parts = array();
			$sum   = 0.0;
			foreach ( (array) $roles as $role ) {
				$row = self::best_for_role( (string) $role );
				if ( $row ) {
					$parts[] = $row['n'];
					$sum    += $row['p'];
				} else {
					$parts[] = '(' . QIL_Boost::role_label( $role ) . ': none in stock)';
				}
			}
			$omr   = $market['rate'] ? $sum / $market['rate'] : $sum;
			$below = null;
			foreach ( $points as $point ) {
				if ( $point <= $omr + 1e-9 ) {
					$below = $point;
				}
			}
			$advice = null === $below ? 'Below the first point; add a product to reach ' . number_format( $points[0] ?? 0, 3 ) . ' OMR' : number_format( $below, 3 ) . ' OMR — saves ' . number_format( $omr - $below, 3 ) . ' OMR (' . ( $omr > 0 ? number_format( 100 * ( $omr - $below ) / $omr, 1 ) : '0' ) . '%) and earns ' . number_format( (float) QIL_Boost::reward_for_omr( $below ), 3 ) . ' OMR cashback';
			echo '<tr><td>' . esc_html( (string) $label ) . '</td><td>' . esc_html( implode( ' + ', $parts ) ) . '</td><td>' . wp_kses_post( QIL_Boost::price_html( $sum ) ) . '</td><td>' . esc_html( $advice ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
QIL_Stacks::boot();
