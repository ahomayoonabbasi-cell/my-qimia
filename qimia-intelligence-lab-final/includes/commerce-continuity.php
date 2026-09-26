<?php
/**
 * Truthful inventory labels and authenticated, exact-item repeat purchasing.
 * Presentation adapter only: WooCommerce remains the order/cart authority.
 * No customer table, email lookup, price override, cron, AI call or order creation.
 */
defined( 'ABSPATH' ) || exit;

/** Tracking also works for POS / REST / cron writes where HTTP_HOST is absent. */
function qil_continuity_enabled() {
    if ( defined( 'QIL_DISABLE' ) && QIL_DISABLE ) return false;
    $host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
    return in_array( $host, array( 'qimia.om', 'www.qimia.om', 'qimialab.qimia.om' ), true )
        && ( 'qimialab.qimia.om' === $host || '1' === (string) get_option( 'qil_enabled', '0' ) );
}

function qil_inventory_window( $kind ) {
    $defaults = array( 'new' => 30, 'restock' => 14 );
    $kind = isset( $defaults[ $kind ] ) ? $kind : 'new';
    $days = (int) get_option( 'qil_inventory_' . $kind . '_days', $defaults[ $kind ] );
    return max( 0, min( 90, (int) apply_filters( 'qil_inventory_' . $kind . '_days', $days ) ) ) * DAY_IN_SECONDS;
}

/** Record FIRST publication; merely editing a published product never makes it new. */
function qil_inventory_first_publish( $new_status, $old_status, $post ) {
    if ( ! qil_continuity_enabled() || 'product' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status ) return;
    $GLOBALS['qil_inventory_published_now'][ (int) $post->ID ] = true;
    if ( ! get_post_meta( $post->ID, '_qil_first_published_at', true ) ) {
        $stamp = (int) get_post_time( 'U', true, $post );
        if ( $stamp > 0 && $stamp <= time() ) add_post_meta( $post->ID, '_qil_first_published_at', $stamp, true );
    }
}
add_action( 'transition_post_status', 'qil_inventory_first_publish', 10, 3 );

/** Preserve the historical publish date before an existing product date is edited. */
function qil_inventory_preserve_publish( $post_id ) {
    if ( ! qil_continuity_enabled() || 'product' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) return;
    if ( ! get_post_meta( $post_id, '_qil_first_published_at', true ) ) {
        $stamp = (int) get_post_time( 'U', true, $post_id );
        if ( $stamp > 0 && $stamp <= time() ) add_post_meta( $post_id, '_qil_first_published_at', $stamp, true );
    }
}
add_action( 'pre_post_update', 'qil_inventory_preserve_publish', 10, 1 );

/** Capture the actual OLD persisted status before WordPress/Woo/POS replaces it. */
function qil_inventory_before_meta( $check, $object_id, $key, $value, $previous = '' ) {
    if ( '_stock_status' !== $key || null !== $check || ! qil_continuity_enabled() ) return $check;
    if ( ! in_array( get_post_type( $object_id ), array( 'product', 'product_variation' ), true ) ) return $check;
    $old = (string) get_post_meta( $object_id, '_stock_status', true );
    // Missing metadata is UNKNOWN, never an assumed out-of-stock state.
    $GLOBALS['qil_inventory_pending'][ (int) $object_id ] = array( 'old' => $old, 'new' => (string) $value );
    return $check;
}
add_filter( 'update_post_metadata', 'qil_inventory_before_meta', 999, 5 );

function qil_inventory_after_meta( $meta_id, $object_id, $key, $value ) {
    if ( '_stock_status' !== $key || ! qil_continuity_enabled() ) return;
    $pending = $GLOBALS['qil_inventory_pending'][ (int) $object_id ] ?? null;
    unset( $GLOBALS['qil_inventory_pending'][ (int) $object_id ] );
    if ( ! is_array( $pending ) || $pending['new'] !== (string) $value ) return;
    if ( 'instock' !== (string) $value ) {
        // Never resurrect a previous stock cycle's timestamp after a backorder.
        delete_post_meta( $object_id, '_qil_restock_observed_at' );
        return;
    }
    if ( 'outofstock' !== $pending['old'] ) return;
    $parent_id = 'product_variation' === get_post_type( $object_id ) ? (int) wp_get_post_parent_id( $object_id ) : (int) $object_id;
    if ( ! empty( $GLOBALS['qil_inventory_published_now'][ $parent_id ] ) ) return;
    // Draft imports and initial publication are not returns to live stock.
    if ( 'publish' !== get_post_status( $parent_id ) || 'publish' !== get_post_status( $object_id ) ) return;
    update_post_meta( $object_id, '_qil_restock_observed_at', time() );
    // Do NOT mark the parent when just one sibling returns. Parent transitions
    // are observed separately when WooCommerce syncs its actual stock status.
}
add_action( 'updated_post_meta', 'qil_inventory_after_meta', 10, 4 );

/** Public, live truth; a variation reads its own restock, and its parent's age. */
function qil_inventory_state( $product, $now = null ) {
    $now = null === $now ? time() : (int) $now;
    $empty = array( 'newArrival' => false, 'backInStock' => false, 'newUntil' => 0, 'restockUntil' => 0, 'checkedAt' => $now );
    if ( ! is_a( $product, 'WC_Product' ) ) return $empty;
    $parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
    if ( ! $parent || 'publish' !== $parent->get_status() || ! $parent->is_visible() || post_password_required( $parent->get_id() ) ) return $empty;
    $status = (string) $product->get_stock_status();
    $empty['stockStatus'] = $status;
    $empty['inStock'] = 'instock' === $status && $product->is_in_stock() && $product->has_enough_stock( 1 );
    if ( ! $empty['inStock'] || ! $product->is_purchasable() || '' === (string) $product->get_price() || (float) $product->get_price() <= 0 ) return $empty;
    if ( $product->is_type( 'variation' ) && ( ! $product->variation_is_active() || ! $product->variation_is_visible() ) ) return $empty;
    if ( ! $product->get_image_id() && ! $parent->get_image_id() ) return $empty;
    $published = (int) get_post_meta( $parent->get_id(), '_qil_first_published_at', true );
    if ( ! $published ) $published = (int) get_post_time( 'U', true, $parent->get_id() );
    $restocked = (int) get_post_meta( $product->get_id(), '_qil_restock_observed_at', true );
    $new_window = qil_inventory_window( 'new' );
    $restock_window = qil_inventory_window( 'restock' );
    $empty['newArrival'] = $new_window > 0 && $published > 0 && $published <= $now && $now < $published + $new_window;
    $empty['backInStock'] = $restock_window > 0 && $restocked > 0 && $restocked <= $now && $now < $restocked + $restock_window;
    $empty['newUntil'] = $empty['newArrival'] ? $published + $new_window : 0;
    $empty['restockUntil'] = $empty['backInStock'] ? $restocked + $restock_window : 0;
    return $empty;
}

/** Standard Woo loops (including compatible WoodMart loop templates). */
function qil_inventory_loop_badges() {
    if ( ! qil_experience_enabled() || is_admin() ) return;
    global $product;
    if ( ! is_a( $product, 'WC_Product' ) ) return;
    $inventory = qil_inventory_state( $product );
    $ar = ! empty( qil_language_context()['isArabic'] );
    echo '<span class="qil-native-inventory" dir="' . ( $ar ? 'rtl' : 'ltr' ) . '" data-qil-native-inventory="' . esc_attr( $product->get_id() ) . '">';
    if ( $inventory['newArrival'] ) echo '<span class="qil-inventory-badge is-new" data-qil-inventory-until="' . esc_attr( $inventory['newUntil'] ) . '">' . esc_html( $ar ? 'وصل حديثاً' : 'New Arrival' ) . '</span>';
    if ( $inventory['backInStock'] ) echo '<span class="qil-inventory-badge is-restocked" data-qil-inventory-until="' . esc_attr( $inventory['restockUntil'] ) . '">' . esc_html( $ar ? 'متوفر من جديد' : 'Back in Stock' ) . '</span>';
    echo '</span>';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'qil_inventory_loop_badges', 8 );

function qil_continuity_private_headers() {
    if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
    do_action( 'litespeed_control_set_nocache', 'Qimia private repeat purchase' );
    nocache_headers();
    if ( ! headers_sent() ) {
        header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
        header( 'CDN-Cache-Control: no-store' );
        header( 'Cloudflare-CDN-Cache-Control: no-store' );
        header( 'Surrogate-Control: no-store' );
        header( 'X-LiteSpeed-Cache-Control: no-cache, no-store' );
        header( 'Vary: Cookie, Accept-Language' );
    }
}
add_action( 'init', static function () {
    $endpoint = isset( $_GET['wc-ajax'] ) && is_string( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : '';
    if ( in_array( $endpoint, array( 'qil_repeat_context', 'qil_buy_again' ), true ) ) qil_continuity_private_headers();
}, -9998 );

function qil_continuity_request_allowed() {
    if ( ! qil_experience_enabled() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return false;
    if ( 'cross-site' === ( $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '' ) ) return false;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ( $origin ) {
        $origin_host = strtolower( (string) wp_parse_url( $origin, PHP_URL_HOST ) );
        if ( ! in_array( $origin_host, array( qil_current_host(), strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ), true ) ) return false;
    }
    return true;
}

/** No order/email/customer identifiers from the client influence the read query. */
function qil_repeat_orders( $customer_id ) {
    if ( $customer_id < 1 || $customer_id !== (int) get_current_user_id() || ! function_exists( 'wc_get_orders' ) ) return array();
    return wc_get_orders( array( 'type' => 'shop_order', 'customer_id' => $customer_id,
        'status' => array( 'wc-processing', 'wc-completed' ), 'limit' => 30, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ) );
}

function qil_repeat_order_owned( $order, $user_id ) {
    return is_a( $order, 'WC_Order' ) && $user_id > 0 && (int) $order->get_customer_id() === (int) $user_id
        && $order->has_status( array( 'processing', 'completed' ) );
}

/** Resolve exactly what was ordered. Never infer a replacement variation. */
function qil_repeat_selection( $order, $item ) {
    if ( ! is_a( $item, 'WC_Order_Item_Product' ) || (float) $item->get_quantity() + (float) $order->get_qty_refunded_for_item( $item->get_id() ) <= 0 ) return null;
    $parent = wc_get_product( $item->get_product_id() );
    if ( ! $parent || 'publish' !== $parent->get_status() || ! $parent->is_visible() || post_password_required( $parent->get_id() ) ) return null;
    $variation_id = (int) $item->get_variation_id();
    $product = $variation_id ? wc_get_product( $variation_id ) : $parent;
    if ( ! $product || ( $variation_id && ( ! $product->is_type( 'variation' ) || (int) $product->get_parent_id() !== (int) $parent->get_id() ) ) ) return null;
    if ( ! $variation_id && ! $parent->is_type( 'simple' ) ) return null;
    if ( $variation_id && ( ! $parent->is_type( 'variable' ) || 'publish' !== $product->get_status() || ! $product->variation_is_active() || ! $product->variation_is_visible() ) ) return null;
    $attributes = array(); $labels = array();
    if ( $variation_id ) {
        $fixed = $product->get_variation_attributes();
        foreach ( (array) $parent->get_variation_attributes() as $taxonomy => $allowed ) {
            $key = wc_variation_attribute_name( $taxonomy );
            $stored = $item->get_meta( substr( $key, 10 ), true );
            if ( ! is_scalar( $stored ) || '' === (string) $stored ) $stored = $item->get_meta( $taxonomy, true );
            if ( ! is_scalar( $stored ) || '' === (string) $stored ) $stored = $item->get_meta( $key, true );
            if ( ! is_scalar( $stored ) || '' === (string) $stored ) return null;
            $value = (string) $stored;
            $display = $value;
            if ( taxonomy_exists( $taxonomy ) ) {
                $term = get_term_by( 'slug', $value, $taxonomy );
                if ( ! $term ) $term = get_term_by( 'name', $value, $taxonomy );
                if ( ! $term || is_wp_error( $term ) ) return null;
                $value = (string) $term->slug; $display = (string) $term->name;
            }
            if ( ! in_array( $value, array_map( 'strval', (array) $allowed ), true ) ) return null;
            if ( ! array_key_exists( $key, $fixed ) || ( '' !== (string) $fixed[ $key ] && (string) $fixed[ $key ] !== $value ) ) return null;
            $attributes[ $key ] = $value;
            $labels[] = qil_clean_text( wc_attribute_label( $taxonomy, $parent ) ) . ': ' . qil_clean_text( $display );
        }
        if ( ! $attributes ) return null;
    }
    $can_add = 'instock' === $product->get_stock_status() && $product->is_in_stock() && $product->is_purchasable()
        && '' !== (string) $product->get_price() && (float) $product->get_price() > 0 && $product->has_enough_stock( 1 );
    $can_add = $can_add && (bool) apply_filters( 'qil_repeat_purchase_eligible', $can_add, $product, $item, $order );
    return array( 'parent' => $parent, 'product' => $product, 'attributes' => $attributes,
        'label' => implode( ' · ', $labels ), 'canAdd' => $can_add, 'variationId' => $variation_id );
}

/** Compact current-account history. No persistent copy of the customer's history. */
function qil_repeat_context_data( $locale = 'en' ) {
    $user_id = (int) get_current_user_id();
    $result = array( 'purchases' => array(), 'products' => array(), 'nonce' => '', 'authenticated' => $user_id > 0 );
    if ( ! $user_id ) return $result;
    $rows = array(); $seen = array();
    foreach ( qil_repeat_orders( $user_id ) as $order ) {
        if ( ! qil_repeat_order_owned( $order, $user_id ) ) continue;
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) continue;
            $parent_id = (int) $item->get_product_id();
            $selection = qil_repeat_selection( $order, $item );
            // Separate cards list only currently repeatable purchases. A sold-out
            // flavour must neither appear nor hide another flavour actually bought.
            if ( ! $selection || ! $selection['canAdd'] ) continue;
            $identity_attributes = $selection['attributes'];
            ksort( $identity_attributes );
            $identity = $parent_id . ':' . $selection['variationId'] . ':' . wp_json_encode( $identity_attributes );
            if ( isset( $seen[ $identity ] ) ) continue;
            $seen[ $identity ] = true;
            $product = $selection['product'];
            $row = array( 'productId' => $parent_id, 'variationId' => $selection['variationId'],
                'orderId' => (int) $order->get_id(), 'itemId' => (int) $item->get_id(), 'canAdd' => $selection['canAdd'],
                'selection' => $selection['label'], 'url' => esc_url_raw( qil_localized_url( $selection['parent']->get_permalink(), 'ar' === $locale ) ), 'sku' => qil_clean_text( $product->get_sku() ),
                'inventory' => qil_inventory_state( $product ),
                'price' => qil_product_price_schema( $product, strtoupper( (string) get_woocommerce_currency() ), 0 ) );
            $row['image'] = $selection['variationId'] ? qil_image_data( $product->get_image_id(), qil_clean_text( $product->get_name() ) ) : null;
            $rows[] = $row;
            if ( count( $rows ) >= 24 ) break 2;
        }
    }
    $rail_ids = array_slice( array_values( array_unique( array_column( $rows, 'productId' ) ) ), 0, 8 );
    $records = $rail_ids ? qil_get_catalogue( array( 'include' => $rail_ids, 'limit' => count( $rail_ids ), '_qil_locale' => $locale ) ) : array();
    $by_id = array();
    foreach ( $records as $record ) $by_id[ (int) $record['id'] ] = $record;
    $added_records = array();
    foreach ( $rows as &$row ) {
        if ( 'ar' === $locale && $row['selection'] ) {
            $map = qil_translate_batch( array( $row['selection'] ), 'general', false );
            $row['selection'] = qil_translated( $row['selection'], $map );
        }
        if ( empty( $added_records[ $row['productId'] ] ) && in_array( $row['productId'], $rail_ids, true ) && isset( $by_id[ $row['productId'] ] ) ) {
            $record = $by_id[ $row['productId'] ];
            // Keep the shared product description/public range intact. The
            // private UI overlays the exact live selection only on its card;
            // Change options and Compare keep the normal parent catalogue data.
            $result['products'][] = $record;
            $added_records[ $row['productId'] ] = true;
        }
    }
    unset( $row );
    $result['purchases'] = $rows;
    $result['nonce'] = wp_create_nonce( 'qil_buy_again' );
    return $result;
}

/**
 * Public inventory labels for a set of products, shared by every visitor of
 * the same page. The labels carry their own expiry timestamps and the browser
 * removes an expired badge itself, so a short shared copy is always truthful.
 * WooCommerce's product version changes on edits and stock-status changes.
 */
function qil_inventory_states( array $ids ) {
    if ( ! $ids ) return array();
    $sorted = $ids; sort( $sorted, SORT_NUMERIC );
    // Destination rules can make a product unsellable in one market only.
    $market = qil_perf_market_identity(); unset( $market['user'], $market['session'] );
    $key = 'qil_inv_v2_' . md5( wp_json_encode( array( qil_perf_product_version(), $market, $sorted ) ) );
    $cached = qil_perf_cache_get( $key );
    $states = array();
    if ( is_array( $cached ) ) {
        foreach ( $ids as $id ) if ( isset( $cached[ $id ] ) && is_array( $cached[ $id ] ) ) $states[ $id ] = $cached[ $id ];
        if ( count( $states ) === count( $ids ) ) return $states;
    }
    $states = array();
    foreach ( $ids as $id ) $states[ $id ] = qil_inventory_state( wc_get_product( $id ) );
    qil_perf_cache_set( $key, $states, (int) apply_filters( 'qil_inventory_cache_ttl', 2 * MINUTE_IN_SECONDS ) );
    return $states;
}

function qil_ajax_repeat_context() {
    qil_continuity_private_headers();
    if ( function_exists( 'qil_perf_noninteractive_bot' ) && qil_perf_noninteractive_bot() ) wp_send_json( array( 'serverTime' => time(), 'inventory' => array(), 'currency' => strtoupper( (string) get_woocommerce_currency() ), 'bot' => true ) );
    if ( ! qil_continuity_request_allowed() || ! function_exists( 'wc_get_product' ) ) wp_send_json( array( 'error' => true ), 403 );
    $locale = isset( $_POST['locale'] ) && 'ar' === $_POST['locale'] ? 'ar' : 'en';
    $inventory_only = ! empty( $_POST['inventory_only'] );
    $data = $inventory_only ? array() : qil_repeat_context_data( $locale );
    $raw_ids = isset( $_POST['products'] ) && is_string( $_POST['products'] ) ? substr( wp_unslash( $_POST['products'] ), 0, 1024 ) : '';
    $ids = array_slice( array_unique( array_filter( array_map( 'absint', explode( ',', $raw_ids ) ) ) ), 0, 64 );
    $data['serverTime'] = time();
    $data['inventory'] = qil_inventory_states( $ids );
    $data['currency'] = strtoupper( (string) get_woocommerce_currency() );
    wp_send_json( $data );
}
add_action( 'wc_ajax_qil_repeat_context', 'qil_ajax_repeat_context' );

function qil_repeat_error( $message, $status = 409, $code = 'unavailable' ) {
    wp_send_json( array( 'error' => true, 'code' => $code, 'message' => $message ), $status );
}

/** Render the CURRENT cart; never replay stale cached HTML after another add. */
function qil_repeat_cart_payload( $data ) {
    $level = ob_get_level();
    try {
        ob_start(); woocommerce_mini_cart(); $mini_cart = ob_get_clean();
        $data['fragments'] = apply_filters( 'woocommerce_add_to_cart_fragments', array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>' ) );
        $data['cart_hash'] = WC()->cart->get_cart_hash();
        return $data;
    } catch ( Throwable $error ) {
        while ( ob_get_level() > $level ) ob_end_clean();
        throw $error;
    }
}

function qil_repeat_replay( $done, $ar ) {
    if ( ! empty( $done['error'] ) ) wp_send_json( $done, 409 );
    try { wp_send_json( qil_repeat_cart_payload( $done ) ); }
    catch ( Throwable $error ) {
        qil_repeat_error( $ar ? 'راجع السلة لتأكيد الإضافة.' : 'Check your cart to confirm the addition.', 409, 'cart_refresh' );
    }
}

/** Add ONE unit of the verified old selection at TODAY'S WooCommerce price. */
function qil_ajax_buy_again() {
    qil_continuity_private_headers();
    $ar = isset( $_POST['locale'] ) && 'ar' === $_POST['locale'];
    if ( ! qil_continuity_request_allowed() || ! is_user_logged_in() ) qil_repeat_error( $ar ? 'سجّل الدخول لشراء منتجك مرة أخرى.' : 'Sign in to buy your product again.', 401, 'login' );
    if ( ! check_ajax_referer( 'qil_buy_again', 'nonce', false ) ) qil_repeat_error( $ar ? 'حدّث الصفحة ثم حاول مرة أخرى.' : 'Refresh the page and try again.', 403, 'nonce' );
    $order_id = isset( $_POST['order_id'] ) && is_scalar( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $item_id = isset( $_POST['item_id'] ) && is_scalar( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
    $order = wc_get_order( $order_id );
    if ( ! qil_repeat_order_owned( $order, (int) get_current_user_id() ) ) qil_repeat_error( $ar ? 'تعذّر التحقق من هذا الشراء.' : 'This purchase could not be verified.', 403 );
    $item = $order->get_item( $item_id );
    if ( ! $item || (int) $item->get_order_id() !== $order_id ) qil_repeat_error( $ar ? 'تعذّر التحقق من المنتج.' : 'This item could not be verified.', 403 );
    $selection = qil_repeat_selection( $order, $item );
    if ( ! $selection || ! $selection['canAdd'] ) qil_repeat_error( $ar ? 'خيارك السابق غير متوفر حالياً. اختر الخيارات بنفسك.' : 'Your previous option is unavailable. Please choose your options.' );
    if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) wc_load_cart();
    if ( ! WC()->cart || ! WC()->session ) qil_repeat_error( $ar ? 'تعذّر فتح السلة.' : 'Your cart is unavailable.', 503 );
    $request_id = isset( $_POST['request_id'] ) && is_string( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
    if ( ! preg_match( '/^[a-zA-Z0-9_-]{16,80}$/D', $request_id ) ) qil_repeat_error( $ar ? 'أعد المحاولة من الزر.' : 'Please try again using the button.', 400 );
    $scope = get_current_user_id() . '|' . WC()->session->get_customer_id();
    $memo_key = 'qil_repeat_done_' . md5( $scope . '|' . $request_id );
    $lock_key = 'qil_repeat_lock_' . md5( $scope );
    $done = get_transient( $memo_key );
    if ( is_array( $done ) ) qil_repeat_replay( $done, $ar );
    $lock = get_option( $lock_key, array() );
    if ( is_array( $lock ) && ! empty( $lock['at'] ) && (int) $lock['at'] < time() - 120 ) delete_option( $lock_key );
    $lock_owner = array( 'at' => time(), 'owner' => $request_id );
    if ( ! add_option( $lock_key, $lock_owner, '', false ) ) qil_repeat_error( $ar ? 'جارٍ تحديث السلة. حاول بعد لحظة.' : 'Your cart is updating. Try again in a moment.', 409, 'busy' );
    // Release even if a third-party validation handler terminates the request.
    register_shutdown_function( static function () use ( $lock_key, $lock_owner ) {
        if ( get_option( $lock_key ) === $lock_owner ) delete_option( $lock_key );
    } );
    $added = false;
    $buffer_level = ob_get_level();
    try {
        $done = get_transient( $memo_key );
        if ( is_array( $done ) ) { delete_option( $lock_key ); qil_repeat_replay( $done, $ar ); }
        $parent_id = (int) $selection['parent']->get_id();
        $variation_id = $selection['variationId'];
        $attributes = $selection['attributes'];
        $cart_data = (array) apply_filters( 'woocommerce_order_again_cart_item_data', array(), $item, $order );
        if ( ! apply_filters( 'woocommerce_add_to_cart_validation', true, $parent_id, 1, $variation_id, $attributes, $cart_data ) ) throw new Exception( $ar ? 'لا يمكن إضافة هذا الخيار حالياً. راجع صفحة المنتج.' : 'This option cannot be added right now. Please check the product page.' );
        $cart_key = WC()->cart->add_to_cart( $parent_id, 1, $variation_id, $attributes, $cart_data );
        if ( ! $cart_key ) throw new Exception( $ar ? 'تعذّرت الإضافة. راجع الكمية والتوفّر في السلة.' : 'Could not add the item. Check availability and the quantity already in your cart.' );
        $added = true;
        do_action( 'woocommerce_ajax_added_to_cart', $parent_id );
        WC()->cart->calculate_totals();
        if ( method_exists( WC()->cart, 'set_session' ) ) WC()->cart->set_session();
        WC()->session->set_customer_session_cookie( true );
        if ( method_exists( WC()->session, 'save_data' ) ) WC()->session->save_data();
        $receipt = array( 'error' => false, 'productId' => $parent_id, 'variationId' => $variation_id, 'quantity' => 1 );
        // A compact receipt prevents replay; fragments are always rendered live.
        set_transient( $memo_key, $receipt, 10 * MINUTE_IN_SECONDS );
        $response = qil_repeat_cart_payload( $receipt );
        delete_option( $lock_key );
        wp_send_json( $response );
    } catch ( Throwable $error ) {
        while ( ob_get_level() > $buffer_level ) ob_end_clean();
        delete_option( $lock_key );
        // Woo errors may contain links. Return text only; never expose stack traces.
        $message = $ar ? 'تعذّرت الإضافة. راجع السلة قبل المحاولة مرة أخرى.' : 'Could not add the item. Check your cart before trying again.';
        if ( $added ) {
            // A cart mutation may have succeeded before a third-party fragment
            // renderer failed. Do not allow the same request to add twice.
            $uncertain = array( 'error' => true, 'code' => 'cart_refresh', 'message' => $message );
            set_transient( $memo_key, $uncertain, 10 * MINUTE_IN_SECONDS );
            wp_send_json( $uncertain, 409 );
        }
        qil_repeat_error( $message );
    }
}
add_action( 'wc_ajax_qil_buy_again', 'qil_ajax_buy_again' );

/** Public empty shell is safe for full-page caches; account data arrives privately. */
function qil_section_buy_again( $placement = 'home' ) {
    if ( class_exists( 'QIL_Personalization' ) && QIL_Personalization::enabled() ) return QIL_Personalization::shell( $placement );
    $ar = ! empty( qil_view_context()['isArabic'] );
    $placement = 'product' === $placement ? 'product' : 'home';
    // Unique IDs also work when both empty shells are present in a builder preview.
    $key = 'buy-again-' . $placement;
    $title_id = 'qil-repeat-title-' . $placement;
    ob_start(); ?>
    <section class="qil-section qil-commerce-collections qil-repeat-section" data-qil-repeat-section="<?php echo esc_attr( $placement ); ?>" hidden aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
        <div class="qil-container">
            <article class="qil-collection-block">
                <div class="qil-collection-head">
                    <div>
                        <small><?php echo esc_html( $ar ? 'من مشترياتك السابقة' : 'FROM YOUR PREVIOUS ORDERS' ); ?></small>
                        <h3 id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $ar ? 'اشترِ مجدداً' : 'Buy again' ); ?></h3>
                        <p><?php echo esc_html( $ar ? 'اختياراتك السابقة المتوفرة الآن. أضف قطعة واحدة بالسعر الحالي.' : 'Your previous choices, available now. Add one at the current price.' ); ?></p>
                    </div>
                    <div class="qil-collection-tools">
                        <div class="qil-rail-nav" data-qil-rail-nav="<?php echo esc_attr( $key ); ?>">
                            <button type="button" data-qil-rail-prev aria-label="<?php echo esc_attr( $ar ? 'السابق' : 'Previous' ); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button>
                            <button type="button" data-qil-rail-next aria-label="<?php echo esc_attr( $ar ? 'التالي' : 'Next' ); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button>
                        </div>
                    </div>
                </div>
                <div class="qil-collection-grid qil-rail" data-qil-repeat-grid data-qil-rail="<?php echo esc_attr( $key ); ?>"></div>
                <p class="qil-repeat-status" data-qil-repeat-status role="status" aria-live="polite"></p>
            </article>
        </div>
    </section>
    <?php return (string) ob_get_clean();
}

add_action( 'admin_init', static function () {
    foreach ( array( 'new' => 30, 'restock' => 14 ) as $kind => $default ) {
        register_setting( 'qil_inventory_settings', 'qil_inventory_' . $kind . '_days', array(
            'type' => 'integer', 'default' => $default, 'sanitize_callback' => static function ( $value ) { return max( 0, min( 90, (int) $value ) ); }
        ) );
    }
} );
function qil_continuity_settings_form() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <details><summary><strong>Product labels &amp; Buy Again</strong></summary>
        <form method="post" action="options.php"><?php settings_fields( 'qil_inventory_settings' ); ?>
        <table class="form-table" role="presentation">
        <?php foreach ( array( 'new' => array( 'New Arrival duration (days)', 30 ), 'restock' => array( 'Back in Stock duration (days)', 14 ) ) as $kind => $label ) : ?>
        <tr><th><label for="qil-inventory-<?php echo esc_attr( $kind ); ?>"><?php echo esc_html( $label[0] ); ?></label></th><td><input id="qil-inventory-<?php echo esc_attr( $kind ); ?>" type="number" min="0" max="90" name="qil_inventory_<?php echo esc_attr( $kind ); ?>_days" value="<?php echo esc_attr( get_option( 'qil_inventory_' . $kind . '_days', $label[1] ) ); ?>" class="small-text"> <span class="description">0 disables this label.</span></td></tr>
        <?php endforeach; ?></table>
        <p>New Arrival defaults to 30 days from the first recorded publication date (a 20-day-old product qualifies; existing saved durations are preserved). Editing a product never resets its age. Existing items use their published date. Back in Stock requires an observed outofstock → instock transition after this update; no historical restocks are guessed. Direct SQL writes that bypass WordPress/WooCommerce hooks cannot be observed.</p>
        <p>Buy Again reads the signed-in customer's latest 30 Processing / Completed orders, with up to 24 distinct in-stock selections and 8 cards in each separate homepage / product-page section. Existing purchase buttons are never replaced. It adds one unit of the exact previous selection. Guests and accounts without eligible history see no empty personal section.</p>
        <?php submit_button( 'Save product label settings' ); ?></form>
    </details>
    <?php
}
