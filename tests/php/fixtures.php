<?php
/**
 * A small Qimia-like catalogue: categories, products (simple + variable),
 * a flash-sale set, stacks, one customer with orders and coupons.
 */

function qt_product( $id, array $props, array $cats = array() ) {
	$p = new WC_Product( $id );
	foreach ( $props as $k => $v ) { if ( 'no_image' !== $k ) { $p->$k = $v; } }
	if ( ! $p->image_id && empty( $props['no_image'] ) ) { $p->image_id = 9000 + $id; }
	if ( ! $p->sku ) { $p->sku = 'SKU' . $id; }
	$GLOBALS['qt']['products'][ $id ] = $p;
	foreach ( $cats as $tid ) { $GLOBALS['qt']['rel'][ $id ]['product_cat'][] = $tid; }
	return $p;
}
function qt_variation( $id, $parent, $price, array $attrs, array $extra = array() ) {
	$v = qt_product( $id, array_merge( array( 'type' => 'variation', 'parent_id' => $parent, 'regular_price' => $price, 'name' => $GLOBALS['qt']['products'][ $parent ]->name . ' - ' . implode( ', ', $attrs ), 'attrs' => $attrs ), $extra ) );
	$GLOBALS['qt']['products'][ $parent ]->children[] = $id;
	return $v;
}

function qt_fixtures() {
	qt_reset();
	$cats = array(
		10 => array( 'proteins', 'Proteins' ), 11 => array( 'creatines-oman', 'Creatine' ), 12 => array( 'pre-workouts', 'Pre-workout' ),
		13 => array( 'fat-burners', 'Fat Burners' ), 15 => array( 'multivitamins', 'Multivitamins' ), 16 => array( 'magnesium-oman', 'Magnesium' ),
		17 => array( 'daily-wellness', 'Daily Wellness' ), 18 => array( 'omega-3-oman', 'Omega 3' ), 19 => array( 'accessories', 'Accessories' ),
		20 => array( 'flash-sale', 'Flash Sale' ), 21 => array( 'staks-offer', 'Stacks Offer' ),
	);
	foreach ( $cats as $id => $c ) { qt_term( 'product_cat', $id, $c[0], $c[1] ); }
	foreach ( array( 'vanilla' => 'Vanilla', 'chocolate' => 'Chocolate', 'fruit-punch' => 'Fruit Punch', 'blue-raz' => 'Blue Raz' ) as $slug => $name ) { qt_term( 'pa_flavour', 700 + count( $GLOBALS['qt']['terms']['pa_flavour'] ?? array() ), $slug, $name ); }

	qt_product( 101, array( 'name' => 'Gold Standard 100% Whey', 'type' => 'variable', 'total_sales' => 500 ), array( 10 ) );
	qt_variation( 1011, 101, '19.900', array( 'pa_flavour' => 'vanilla' ) );
	qt_variation( 1012, 101, '21.500', array( 'pa_flavour' => 'chocolate' ) );
	qt_product( 102, array( 'name' => 'Creatine Monohydrate 300g', 'regular_price' => '5.980', 'total_sales' => 400 ), array( 11 ) );
	update_post_meta( 102, 'servings', '60 servings' );
	qt_product( 103, array( 'name' => 'C4 Pre-Workout 30 Servings', 'type' => 'variable', 'total_sales' => 300 ), array( 12 ) );
	qt_variation( 1031, 103, '9.500', array( 'pa_flavour' => 'fruit-punch' ) );
	qt_variation( 1032, 103, '9.500', array( 'pa_flavour' => 'blue-raz' ) );
	qt_product( 104, array( 'name' => 'Daily Multivitamin', 'regular_price' => '4.560', 'total_sales' => 250 ), array( 15 ) );
	update_post_meta( 104, 'servings', '60' );
	qt_product( 105, array( 'name' => 'Magnesium Glycinate', 'regular_price' => '3.900', 'total_sales' => 200 ), array( 16 ) );
	update_post_meta( 105, 'servings', '30' );
	qt_product( 106, array( 'name' => 'Ashwagandha KSM-66', 'regular_price' => '4.200', 'total_sales' => 150 ), array( 17 ) );
	qt_product( 107, array( 'name' => 'Omega 3 Fish Oil', 'regular_price' => '5.500', 'total_sales' => 180 ), array( 18 ) );
	qt_product( 108, array( 'name' => 'Lipo 6 Fat Burner', 'regular_price' => '8.900', 'total_sales' => 220 ), array( 13 ) );
	qt_product( 109, array( 'name' => 'Qimia Shaker Bottle', 'regular_price' => '1.500', 'total_sales' => 600 ), array( 19 ) );
	qt_product( 110, array( 'name' => 'ISO 100 Hydrolyzed Isolate', 'regular_price' => '24.000', 'total_sales' => 350 ), array( 10 ) );
	qt_product( 111, array( 'name' => 'Hidden Creatine', 'regular_price' => '3.000', 'total_sales' => 999, 'visible' => false ), array( 11 ) );
	qt_product( 112, array( 'name' => 'Sold-out Creatine', 'regular_price' => '3.000', 'total_sales' => 998, 'stock_status' => 'outofstock' ), array( 11 ) );

	// Flash-sale candidates: real reductions; plus deliberate non-candidates.
	$flash = array(
		201 => array( 'Whey Isolate Flash', '30.000', '22.500', 90, 10 ),    // 25%
		202 => array( 'BCAA Energy', '12.000', '9.000', 80, 11 ),            // 25%
		203 => array( 'Creatine HCL', '10.000', '8.000', 70, 11 ),           // 20%
		204 => array( 'Mass Gainer 6lb', '28.000', '21.000', 60, 10 ),       // 25%
		205 => array( 'ZMA Night', '8.000', '6.400', 50, 17 ),               // 20%
		206 => array( 'Vitamin D3 5000', '5.000', '4.000', 40, 15 ),         // 20%
		207 => array( 'Collagen Peptides', '15.000', '10.500', 35, 17 ),     // 30%
		208 => array( 'Electrolyte Mix', '7.000', '5.950', 30, 17 ),         // 15%
		209 => array( 'Glutamine 500g', '11.000', '8.800', 25, 17 ),         // 20%
		210 => array( 'Casein Night', '26.000', '19.500', 20, 10 ),          // 25%
		211 => array( 'Oat Protein Bar Box', '9.000', '7.650', 15, 10 ),     // 15%
		212 => array( 'Joint Support', '12.000', '9.600', 12, 17 ),          // 20%
		213 => array( 'Tiny Discount', '10.000', '9.700', 10, 17 ),          // 3% (below minimum)
	);
	foreach ( $flash as $id => $f ) {
		qt_product( $id, array( 'name' => $f[0], 'regular_price' => $f[1], 'sale_price' => $f[2], 'total_sales' => $f[3], 'manage_stock' => true, 'stock_qty' => $f[4] ), array( 20, 17 ) );
	}
	$GLOBALS['qt']['products'][201]->sale_to = time() + 30 * HOUR_IN_SECONDS;
	qt_product( 214, array( 'name' => 'No Image Flash', 'regular_price' => '10.000', 'sale_price' => '7.000', 'total_sales' => 9, 'no_image' => true ), array( 20 ) );
	qt_product( 215, array( 'name' => 'Sold Out Flash', 'regular_price' => '10.000', 'sale_price' => '7.000', 'total_sales' => 8, 'stock_status' => 'outofstock' ), array( 20 ) );
	qt_product( 216, array( 'name' => 'Mixed Flavours Flash', 'type' => 'variable', 'total_sales' => 7 ), array( 20 ) );
	qt_variation( 2161, 216, '20.000', array( 'pa_flavour' => 'vanilla' ), array( 'sale_price' => '15.000', 'manage_stock' => true, 'stock_qty' => 4 ) );
	qt_variation( 2162, 216, '20.000', array( 'pa_flavour' => 'chocolate' ), array( 'sale_price' => '18.000', 'manage_stock' => true, 'stock_qty' => 3 ) );

	// Stacks.
	qt_product( 301, array( 'name' => 'Muscle Starter Stack', 'regular_price' => '24.900', 'total_sales' => 40 ), array( 21 ) );
	update_post_meta( 301, 'woosb_ids', '101/1,102/1' );
	qt_product( 302, array( 'name' => 'Recovery Pack', 'regular_price' => '11.900', 'total_sales' => 30 ), array( 21 ) );
	qt_product( 303, array( 'name' => 'Daily Essentials', 'regular_price' => '36.000', 'sale_price' => '34.900', 'total_sales' => 20, 'short' => 'Omega 3, a multivitamin and magnesium for everyday wellness.' ), array( 21 ) );
	qt_product( 304, array( 'name' => 'Sold Out Stack', 'regular_price' => '44.900', 'stock_status' => 'outofstock' ), array( 21 ) );

	// Customer 7 with orders and cashback coupons.
	$GLOBALS['qt']['users'][7] = new WP_User( 7, 'jane@example.com' );
	$GLOBALS['qt']['usermeta'][7]['billing_email'] = 'Jane.Billing@example.com';
	update_post_meta( 1031, 'servings', '30' ); // Pre-workout variation: 30 servings.
	$orders = array(
		5001 => array( 40, array( array( 1, 103, 1031, 1, array( 'pa_flavour' => 'fruit-punch' ) ), array( 2, 102, 0, 1, array() ), array( 3, 105, 0, 2, array() ) ) ),
		5002 => array( 62, array( array( 4, 104, 0, 1, array() ) ) ),
		5003 => array( 10, array( array( 5, 101, 1011, 1, array( 'pa_flavour' => 'vanilla' ) ) ) ),
	);
	foreach ( $orders as $oid => $spec ) {
		$o = new WC_Order(); $o->id = $oid; $o->customer_id = 7; $o->paid = time() - $spec[0] * DAY_IN_SECONDS;
		foreach ( $spec[1] as $row ) {
			$item = new WC_Order_Item_Product(); $item->id = $oid * 10 + $row[0]; $item->order_id = $oid; $item->product_id = $row[1]; $item->variation_id = $row[2]; $item->quantity = $row[3]; $item->total = 10; $item->meta = $row[4];
			$o->items[ $item->id ] = $item;
		}
		$GLOBALS['qt']['orders'][ $oid ] = $o;
	}
	$day = DAY_IN_SECONDS;
	$GLOBALS['qt']['coupons'] = array(
		801 => array( 'code' => 'CB-7-A', 'amount' => 4, 'emails' => array( 'jane@example.com' ), 'expires' => time() + 5 * $day, 'limit' => 1 ),
		802 => array( 'code' => 'CB-7-B', 'amount' => 3, 'emails' => array( 'jane.billing@example.com' ), 'expires' => time() + 20 * $day, 'limit' => 1 ),
		803 => array( 'code' => 'CB-7-OLD', 'amount' => 5, 'emails' => array( 'jane@example.com' ), 'expires' => time() - $day, 'limit' => 1 ),
		804 => array( 'code' => 'CB-7-USED', 'amount' => 6, 'emails' => array( 'jane@example.com' ), 'expires' => time() + 9 * $day, 'limit' => 1, 'count' => 1, 'used_by' => array( '7' ) ),
		805 => array( 'code' => 'PCT-10', 'amount' => 10, 'type' => 'percent', 'emails' => array( 'jane@example.com' ), 'expires' => time() + 9 * $day, 'limit' => 1 ),
		806 => array( 'code' => 'CB-OTHER', 'amount' => 8, 'emails' => array( 'someone@example.com' ), 'expires' => time() + 9 * $day, 'limit' => 1 ),
		807 => array( 'code' => 'WELCOME-OPEN', 'amount' => 2, 'emails' => array( 'jane@example.com' ) ),
		808 => array( 'code' => 'CB-7-MARKED', 'amount' => 2, 'emails' => array( 'jane@example.com' ), 'minimum' => 10 ),
	);
	update_post_meta( 808, '_qcb2_source_order', 5001 );
}
