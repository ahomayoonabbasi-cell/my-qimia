<?php
/** Native Beauty archive filters. Replace the former Hair-only form and its three hooks. */
defined('ABSPATH') || exit;

function qby_archive_context($term = null) {
    $term = $term ?: get_queried_object();
    if (!$term || is_wp_error($term) || ($term->taxonomy ?? '') !== 'product_cat') { return false; }
    $root = get_term_by('slug', 'beauty', 'product_cat');
    if (!$root || is_wp_error($root)) { return false; }
    $ancestors = array_map('intval', get_ancestors((int) $term->term_id, 'product_cat'));
    if ((int) $term->term_id !== (int) $root->term_id && !in_array((int) $root->term_id, $ancestors, true)) { return false; }
    $department = qby_department_slug($term->slug);
    if (!$department) {
        foreach ($ancestors as $id) {
            $parent = get_term($id, 'product_cat');
            if ($parent && !is_wp_error($parent)) { $department = qby_department_slug($parent->slug); if ($department) { break; } }
        }
    }
    $children = get_term_children((int) $term->term_id, 'product_cat');
    $ids = array((int) $term->term_id);
    if (!is_wp_error($children)) { $ids = array_merge($ids, array_map('intval', $children)); }
    return array('term'=>$term, 'department'=>$department, 'ids'=>array_values(array_unique($ids)));
}
function qby_archive_input($key, $default = '') {
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_title(wp_unslash((string) $_GET[$key])) : $default;
}
function qby_archive_facet_fields($department) {
    $fields = array(
        'skin-care'=>array('care', 'skin_type'),
        'makeup'=>array('skin_type', 'finish', 'coverage', 'shade'),
        'hair-care'=>array('care', 'hair_type'),
        'fragrance'=>array('concentration'),
        'body-care'=>array('care', 'skin_type'),
        'beauty-tools'=>array(),
    );
    return $department !== '' ? ($fields[$department] ?? array()) : array_keys(qby_beauty_facets());
}
function qby_archive_types($context) {
    $out = array(); $presence = qby_beauty_presence();
    foreach (qby_beauty_terms() as $slug => $row) {
        if ($slug === 'beauty' || $slug === $context['term']->slug) { continue; }
        $term = get_term_by('slug', $slug, 'product_cat');
        if (!$term || is_wp_error($term) || !in_array((int) $term->term_id, $context['ids'], true) || empty($presence[(int) $term->term_id])) { continue; }
        $out[$slug] = qby_t($row[0], $row[1]);
    }
    return $out;
}

/** One indexed, bounded aggregate; no product IDs or objects are loaded for facet options. */
function qby_archive_facet_options($context) {
    $facets = qby_beauty_facets(); $taxonomies = array();
    foreach (qby_archive_facet_fields($context['department']) as $field) {
        if (isset($facets[$field]) && taxonomy_exists($facets[$field])) { $taxonomies[$field] = $facets[$field]; }
    }
    if (!$taxonomies) { return array(); }
    $cache_key = 'qby_archive_facets_'.QBY_VERSION.'_'.qby_cache_generation().'_'.(int) $context['term']->term_id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) { return $cached; }
    global $wpdb;
    $visibility = wc_get_product_visibility_term_ids();
    $excluded = array_filter(array((int) ($visibility['exclude-from-catalog'] ?? 0)));
    if (get_option('woocommerce_hide_out_of_stock_items') === 'yes' && !empty($visibility['outofstock'])) { $excluded[] = (int) $visibility['outofstock']; }
    $visible = $excluded ? " AND NOT EXISTS (SELECT 1 FROM {$wpdb->term_relationships} qby_av WHERE qby_av.object_id=p.ID AND qby_av.term_taxonomy_id IN (".implode(',', $excluded)."))" : '';
    $taxonomy_placeholders = implode(',', array_fill(0, count($taxonomies), '%s'));
    $class_sql = qby_beauty_class_sql('p.ID');
    $sql = "SELECT DISTINCT ft.term_id, ft.taxonomy, t.slug, t.name FROM {$wpdb->term_relationships} fr INNER JOIN {$wpdb->term_taxonomy} ft ON ft.term_taxonomy_id=fr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id=ft.term_id INNER JOIN {$wpdb->posts} p ON p.ID=fr.object_id AND p.post_type='product' AND p.post_status='publish' AND p.post_parent=0 AND p.post_password='' WHERE ft.taxonomy IN ({$taxonomy_placeholders}) AND EXISTS (SELECT 1 FROM {$wpdb->term_relationships} qby_ac INNER JOIN {$wpdb->term_taxonomy} qby_at ON qby_at.term_taxonomy_id=qby_ac.term_taxonomy_id AND qby_at.taxonomy='product_cat' WHERE qby_ac.object_id=p.ID AND qby_at.term_id IN (".implode(',', array_map('intval', $context['ids'])).")) AND ({$class_sql}) {$visible} ORDER BY t.name ASC LIMIT 500";
    $rows = $wpdb->get_results($wpdb->prepare($sql, array_values($taxonomies)));
    $out = array();
    foreach ((array) $rows as $row) {
        $field = array_search($row->taxonomy, $taxonomies, true);
        if ($field !== false) { $out[$field][(string) $row->slug] = html_entity_decode((string) $row->name, ENT_QUOTES, 'UTF-8'); }
    }
    set_transient($cache_key, $out, 5 * MINUTE_IN_SECONDS);
    return $out;
}
function qby_archive_filters() {
    static $done = false;
    if ($done || !qby_beauty_context()) { return ''; }
    $context = qby_archive_context(); if (!$context) { return ''; }
    $url = get_term_link($context['term']); if (is_wp_error($url)) { return ''; }
    $done = true;
    if (function_exists('qil_localized_url')) { $url = qil_localized_url($url, qby_ar()); }
    $types = qby_archive_types($context); $options = qby_archive_facet_options($context);
    // Reviewed legacy concerns are only relevant inside Hair Care; do not label other departments as hair.
    if ($context['department'] === 'hair-care') {
        foreach (qby_concerns() as $key => $label) {
            if ($key !== 'all') { $options['care'][$key] = qby_t($label[0], $label[1]); }
        }
    }
    $labels = array('care'=>array('Concern', 'الاحتياج'), 'skin_type'=>array('Skin type', 'نوع البشرة'), 'hair_type'=>array('Hair type', 'نوع الشعر'), 'finish'=>array('Finish', 'اللمسة النهائية'), 'coverage'=>array('Coverage', 'التغطية'), 'shade'=>array('Shade', 'درجة اللون'), 'concentration'=>array('Fragrance concentration', 'تركيز العطر'));
    if (!$types && !$options) { return ''; }
    $own_fields = array('qby_type');
    ob_start(); ?>
    <form class="qby qby-archive-filters" method="get" action="<?php echo esc_url($url); ?>">
        <?php if ($types): ?><label><?php echo esc_html(qby_t('Product type', 'نوع المنتج')); ?><select name="qby_type"><option value=""><?php echo esc_html(qby_t('All types in this category', 'كل الأنواع في هذه الفئة')); ?></option><?php foreach ($types as $slug => $label): ?><option value="<?php echo esc_attr($slug); ?>" <?php selected(qby_archive_input('qby_type'), $slug); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <?php foreach ($options as $field => $terms): if (!$terms || !isset($labels[$field])) { continue; } $name = $field === 'care' ? 'care' : 'qby_'.$field; $own_fields[] = $name; ?>
            <label><?php echo esc_html(qby_t($labels[$field][0], $labels[$field][1])); ?><select name="<?php echo esc_attr($name); ?>"><option value=""><?php echo esc_html(qby_t('All', 'الكل')); ?></option><?php foreach ($terms as $slug => $label): ?><option value="<?php echo esc_attr($slug); ?>" <?php selected(qby_archive_input($name), $slug); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
        <?php endforeach; ?>
        <?php foreach ($_GET as $key => $value) {
            if (!is_string($key) || !is_scalar($value) || in_array($key, $own_fields, true)) { continue; }
            $native = in_array($key, array('min_price', 'max_price', 'filter_product_brand', 'filter_brand', 'stock_status', 'filter_stock_status', 'on_sale', 'orderby', 'qimia_currency'), true) || preg_match('/^(?:filter_|query_type_)[a-z0-9_-]{1,64}$/i', $key);
            if ($native) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr(sanitize_text_field(wp_unslash((string) $value))).'">'; }
        } ?>
        <button type="submit" class="qby-button"><?php echo esc_html(qby_t('Apply filters', 'تطبيق الفلاتر')); ?></button><?php echo qby_link($url, qby_t('Reset', 'إعادة تعيين'), 'qby-text-link'); ?>
    </form>
    <?php return ob_get_clean();
}
add_filter('elementor/widget/render_content', static function ($html, $widget) {
    if (qby_beauty_context() && $widget->get_name() === 'wd_archive_products') { $html = qby_archive_filters().$html; }
    return $html;
}, 110, 2);
add_action('woocommerce_before_shop_loop', static function () { echo qby_archive_filters(); }, 7);
add_action('pre_get_posts', static function ($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_tax('product_cat')) { return; }
    $context = qby_archive_context($query->get_queried_object());
    if (!$context) { return; }
    $extra = array(); $empty = false;
    $type = qby_archive_input('qby_type');
    if ($type) {
        $term = get_term_by('slug', $type, 'product_cat');
        if (!$term || is_wp_error($term) || !in_array((int) $term->term_id, $context['ids'], true)) { $empty = true; }
        else { $extra[] = array('taxonomy'=>'product_cat', 'field'=>'term_id', 'terms'=>array((int) $term->term_id), 'include_children'=>true); }
    }
    $fields = qby_archive_facet_fields($context['department']); $facets = qby_beauty_facets();
    foreach ($facets as $field => $taxonomy) {
        if ($field === 'care') { continue; }
        $value = qby_archive_input('qby_'.$field); if (!$value || $value === 'all') { continue; }
        if (!in_array($field, $fields, true) || !taxonomy_exists($taxonomy)) { $empty = true; continue; }
        $extra[] = array('taxonomy'=>$taxonomy, 'field'=>'slug', 'terms'=>array($value));
    }
    if ($extra) {
        $tax = (array) $query->get('tax_query');
        $combined = array('relation'=>'AND'); if ($tax) { $combined[] = $tax; }
        foreach ($extra as $clause) { $combined[] = $clause; }
        $query->set('tax_query', $combined);
    }
    $care = qby_archive_input('care'); if ($care === 'all') { $care = ''; }
    $legacy_ids = array();
    if ($care && $context['department'] === 'hair-care') {
        foreach (qby_seed() as $id => $row) { if (qby_seed_record($id) && in_array($care, $row['concerns'] ?? array(), true)) { $legacy_ids[] = (int) $id; } }
    }
    $native_care = $care && in_array('care', $fields, true) && taxonomy_exists($facets['care']);
    // One callback for this exact query; remove itself after applying so nested/other queries stay untouched.
    $scope = null;
    $scope = static function ($clauses, $candidate) use ($query, &$scope, $empty, $care, $legacy_ids, $native_care, $facets) {
        if ($candidate !== $query) { return $clauses; }
        remove_filter('posts_clauses', $scope, 30);
        global $wpdb;
        $posts = $wpdb->posts;
        $clauses['where'] .= ' AND ('.qby_beauty_class_sql($posts.'.ID').')';
        if ($empty) { $clauses['where'] .= ' AND 1=0'; }
        if ($care) {
            $parts = $legacy_ids ? array($posts.'.ID IN ('.implode(',', $legacy_ids).')') : array();
            if ($native_care) {
                $parts[] = $wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->term_relationships} qby_cr INNER JOIN {$wpdb->term_taxonomy} qby_ct ON qby_ct.term_taxonomy_id=qby_cr.term_taxonomy_id INNER JOIN {$wpdb->terms} qby_c ON qby_c.term_id=qby_ct.term_id WHERE qby_cr.object_id={$posts}.ID AND qby_ct.taxonomy=%s AND qby_c.slug=%s)", $facets['care'], $care);
            }
            $clauses['where'] .= ' AND ('.($parts ? implode(' OR ', $parts) : '1=0').')';
        }
        return $clauses;
    };
    add_filter('posts_clauses', $scope, 30, 2);
}, 1100);
