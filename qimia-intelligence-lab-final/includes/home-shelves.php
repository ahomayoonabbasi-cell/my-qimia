<?php
/** Home-only inventory shelf. No goal, hero, global layout or commerce changes. */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'qil_home_shelves_assets', 100 );
function qil_home_shelves_assets() {
    wp_enqueue_style( 'qil-home-shelves', QIL_URL . 'assets/qil-home-shelves.css', array( 'qimia-intelligence-lab', 'qimia-experience' ), QIL_VERSION );
    $bot = ( function_exists( 'qil_perf_noninteractive_bot' ) && qil_perf_noninteractive_bot() )
        || ( function_exists( 'qil_perf_crawler_family' ) && '' !== qil_perf_crawler_family() );
    if ( ! $bot ) wp_enqueue_script( 'qil-home-shelves', QIL_URL . 'assets/qil-home-shelves.min.js', array( 'qimia-intelligence-lab' ), QIL_VERSION, true );
}

/** Request-only handoff of the already-built homepage index; no duplicate prices. */
function qil_home_index( $records = null ) {
    static $index = array();
    if ( is_array($records) ) foreach ($records as $record) $index[(int)$record['id']] = $record;
    return $index;
}

/** Cache only public candidate IDs, never a customer's prices or account data. */
function qil_home_discovery_ids() {
    $windows = array( 'new' => qil_inventory_window( 'new' ), 'restock' => qil_inventory_window( 'restock' ) );
    $version = class_exists( 'WC_Cache_Helper' ) ? WC_Cache_Helper::get_transient_version( 'product' ) : '0';
    $key = 'qil_home_ids_' . md5( wp_json_encode( array( QIL_VERSION, $version, $windows ) ) );
    $cached = get_transient( $key );
    if ( is_array( $cached ) && isset( $cached['new'], $cached['restock'] ) ) return $cached;
    $ids = array( 'new' => array(), 'restock' => array() );
    if ( $windows['new'] > 0 && function_exists( 'wc_get_products' ) ) {
        $ids['new'] = wc_get_products( array( 'limit' => 24, 'status' => 'publish', 'stock_status' => 'instock', 'visibility' => 'visible', 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids' ) );
    }
    if ( $windows['restock'] > 0 && class_exists( 'WP_Query' ) ) {
        $query = new WP_Query( array(
            'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 24,
            'fields' => 'ids', 'no_found_rows' => true, 'ignore_sticky_posts' => true,
            'update_post_meta_cache' => false, 'update_post_term_cache' => false,
            'meta_key' => '_qil_restock_observed_at', 'orderby' => 'meta_value_num', 'order' => 'DESC',
            'meta_query' => array(
                array( 'key' => '_qil_restock_observed_at', 'value' => array( time() - $windows['restock'], time() ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
                array( 'key' => '_stock_status', 'value' => 'instock' ),
            ),
        ) );
        $ids['restock'] = $query->posts;
    }
    foreach ( $ids as $kind => $rows ) $ids[$kind] = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $rows ) ) ) ), 0, 24 );
    set_transient( $key, $ids, 2 * MINUTE_IN_SECONDS );
    return $ids;
}

function qil_home_discovery_payload() {
    $ids = qil_home_discovery_ids();
    $include = array_values( array_unique( array_merge( $ids['restock'], $ids['new'] ) ) );
    $initial = qil_home_index();
    $missing = array_values(array_diff($include, array_keys($initial)));
    $records = $missing ? qil_get_catalogue( array( 'include' => $missing, 'limit' => count( $missing ), 'orderby' => 'include' ) ) : array();
    foreach ($include as $id) if (isset($initial[$id])) $records[] = $initial[$id];
    $out = array( 'new' => array(), 'restock' => array(), 'products' => array(), 'inventory' => array(), '_fallback' => array(), 'now' => time() );
    $pool = array();
    foreach ( $records as $record ) {
        $id = (int) ( $record['id'] ?? 0 );
        // Re-read the established inventory state so a cached record can never
        // qualify a now-sold-out, hidden, expired or non-purchasable item.
        $product = $id ? wc_get_product( $id ) : false;
        $inventory = qil_inventory_state( $product, $out['now'] );
        if ( empty( $inventory['inStock'] ) || empty( $record['purchase']['purchasable'] ) || (float) ( $record['price']['value'] ?? 0 ) <= 0 ) continue;
        $record['inventory'] = $inventory;
        $pool[$id] = $record;
    }
    foreach ( array( 'new' => array( 'newArrival', 'newUntil' ), 'restock' => array( 'backInStock', 'restockUntil' ) ) as $kind => $keys ) {
        $eligible = array_filter( $pool, static function ( $p ) use ( $keys, $out ) { return ! empty( $p['inventory'][$keys[0]] ) && (int) $p['inventory'][$keys[1]] > $out['now']; } );
        uasort( $eligible, static function ( $a, $b ) use ( $keys ) { return (int) $b['inventory'][$keys[1]] <=> (int) $a['inventory'][$keys[1]]; } );
        $out[$kind] = array_slice( array_map( 'intval', array_keys( $eligible ) ), 0, 12 );
    }
    foreach ( array_unique( array_merge( $out['new'], $out['restock'] ) ) as $id ) {
        if (!isset($initial[$id])) $out['products'][] = $pool[$id];
        $out['inventory'][$id] = $pool[$id]['inventory'];
        if (count($out['_fallback']) < 4) $out['_fallback'][] = array('url'=>$pool[$id]['url'], 'name'=>$pool[$id]['name']);
    }
    return $out;
}

function qil_home_discovery() {
    $ctx = qil_view_context(); $ar = $ctx['isArabic'];
    $payload = qil_home_discovery_payload();
    if ( ! $payload['new'] && ! $payload['restock'] ) return '';
    $client_payload = $payload; unset( $client_payload['_fallback'] );
    ob_start(); ?>
    <section id="qil-discover" class="qil-section qil-home-discover" data-qil-home-discover aria-labelledby="qil-discover-title">
      <div class="qil-container">
        <div class="qil-section-heading"><div><span class="qil-kicker"><?php echo esc_html( $ar ? 'الجديد في كيميا' : 'FRESH AT QIMIA' ); ?></span><h2 id="qil-discover-title"><?php echo esc_html( $ar ? 'وصل حديثاً. وعاد من جديد.' : 'New finds. Welcome returns.' ); ?></h2></div></div>
        <div class="qil-home-discover-controls">
          <div class="qil-home-tabs" role="tablist" aria-label="<?php echo esc_attr( $ar ? 'جديد المتجر' : 'Store updates' ); ?>" hidden>
            <?php foreach ( array( 'new' => array( 'New Arrivals', 'وصل حديثاً' ), 'restock' => array( 'Back in Stock', 'متوفر من جديد' ) ) as $key => $label ) : ?>
            <button type="button" id="qil-discover-<?php echo esc_attr($key); ?>-tab" role="tab" aria-selected="false" tabindex="-1" aria-controls="qil-discover-panel" data-qil-home-tab="<?php echo esc_attr($key); ?>"><?php echo esc_html( $ar ? $label[1] : $label[0] ); ?></button>
            <?php endforeach; ?>
          </div>
          <div class="qil-rail-nav" data-qil-rail-nav="home-discovery" hidden>
            <button type="button" data-qil-rail-prev aria-label="<?php echo esc_attr( $ar ? 'السابق' : 'Previous' ); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button>
            <button type="button" data-qil-rail-next aria-label="<?php echo esc_attr( $ar ? 'التالي' : 'Next' ); ?>"><svg aria-hidden="true"><use href="#qil-i-arrow"/></svg></button>
          </div>
        </div>
        <div id="qil-discover-panel" role="tabpanel" aria-labelledby="qil-discover-title">
          <div class="qil-collection-grid qil-rail" data-qil-home-grid data-qil-rail="home-discovery" tabindex="0" aria-label="<?php echo esc_attr( $ar ? 'منتجات المتجر الجديدة' : 'Store updates products' ); ?>">
            <?php foreach ( $payload['_fallback'] as $p ) : ?>
            <a class="qil-home-fallback" href="<?php echo esc_url($p['url']); ?>"><?php echo esc_html($p['name']); ?></a>
            <?php endforeach; ?>
          </div>
          <p data-qil-home-empty hidden></p>
        </div>
      </div>
      <script type="application/json" data-qil-home-discovery-data><?php echo wp_json_encode( $client_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
    </section>
    <?php return (string) ob_get_clean();
}
