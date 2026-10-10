<?php
// Contract tests for the Beauty ↔ Intelligence Lab / AI Commerce / AI Product Box seams.
// Runs the real integration.php, compare-beauty.php and beauty-native-cards.php over small doubles.
//   php tests/php/beauty-integration.php
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('ABSPATH', __DIR__ . '/');
define('QBY_VERSION', '2.4.0');
define('MINUTE_IN_SECONDS', 60);

/* ---------- WordPress doubles ---------- */
$hooks = array();
function add_filter($tag, $callback, $priority = 10, $args = 1) { global $hooks; $hooks[$tag][] = array('cb' => $callback, 'priority' => $priority, 'args' => $args); return true; }
function add_action($tag, $callback, $priority = 10, $args = 1) { return add_filter($tag, $callback, $priority, $args); }
function add_shortcode($tag, $callback) {}
/** Runs every callback registered by the files under test for $tag, in priority order. */
function run_filter($tag, $value, ...$extra) {
    global $hooks; $list = $hooks[$tag] ?? array();
    usort($list, static function ($a, $b) { return $a['priority'] <=> $b['priority']; });
    foreach ($list as $hook) { $value = call_user_func_array($hook['cb'], array_slice(array_merge(array($value), $extra), 0, $hook['args'])); }
    return $value;
}
$page = array('product' => false, 'queried' => 0, 'tax' => false);
function is_product() { global $page; return $page['product']; }
function get_queried_object_id() { global $page; return $page['queried']; }
function is_tax($t = '') { global $page; return $page['tax']; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function is_wp_error($v) { return $v instanceof WP_Error; }
class WP_Error {}
class WP_REST_Response {
    public $data; public $status; public $headers = array();
    function __construct($data = null, $status = 200) { $this->data = $data; $this->status = $status; }
    function get_data() { return $this->data; } function set_data($d) { $this->data = $d; }
    function get_status() { return $this->status; } function header($k, $v) { $this->headers[$k] = $v; }
}
class Test_Request {
    function __construct(public $route, public $params = array(), public $body = '') {}
    function get_route() { return $this->route; }
    function get_param($k) { return $this->params[$k] ?? null; }
    function get_body() { return $this->body; }
    function get_json_params() { return json_decode($this->body, true); }
}
function rest_ensure_response($r) { return $r instanceof WP_REST_Response ? $r : new WP_REST_Response($r); }
function absint($v) { return abs((int) $v); }
function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); }
function wp_json_encode($d, $o = 0) { return json_encode($d, $o); }
function esc_url_raw($u) { return filter_var($u, FILTER_VALIDATE_URL) ? $u : ''; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function sanitize_key($k) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $k)); }
function taxonomy_exists($t) { return false; }
function get_the_terms($id, $tax) { return array((object) array('slug' => 'shampoo', 'term_id' => 7)); }
function get_ancestors($id, $tax) { return array(1, 2); }

/* ---------- Catalogue doubles: 10 = beauty, 11 = supplement, 12 = beauty tool ---------- */
$classes = array(10 => 'beauty', 11 => 'supplement', 12 => 'accessory', 20 => 'beauty', 21 => 'beauty');
function qby_resolved_class($id) { global $classes; return $classes[(int) $id] ?? ''; }
function qby_uses_beauty_ui($id = 0) { $id = $id ?: get_queried_object_id(); return in_array(qby_resolved_class($id), array('beauty', 'accessory'), true); }
function qby_is_cosmetic($id = 0) { return qby_resolved_class($id) === 'beauty'; }
function qby_frontend_enabled() { return true; }
function qby_stage_allowed() { return true; }
function qby_ar() { return false; }
function qby_t($en, $ar) { return qby_ar() ? $ar : $en; }
function qby_details($id) { return array('size' => '50 ml', 'inci' => 'Aqua, Glycerin', 'hair_type' => 'Dry hair', 'hair_type_ar' => 'شعر جاف', 'key_ingredients' => 'Keratin', 'directions_en' => 'Apply to damp hair.'); }
function qby_product_department($id) { return qby_resolved_class($id) === 'accessory' ? 'beauty-tools' : 'hair-care'; }
function qby_beauty_field_schema($d = '') { return array('hair_type' => array('en' => 'Hair type', 'ar' => 'نوع الشعر'), 'material' => array('en' => 'Material', 'ar' => 'الخامة')); }
function qby_terms() { return array('shampoo' => array('Shampoo', 'شامبو', 'hair-care')); }
function qby_beauty_query($args) { return array('ids' => array(20, 21), 'products' => array()); }
function qby_knowledge_render($id) { return '<section id="qby-product-details" data-id="' . (int) $id . '"></section>'; }
class Test_Product { function __construct(public $id, public $type = 'simple') {} function get_id() { return $this->id; } function is_type($t) { return $t === $this->type; } }
function wc_get_product($id) { return new Test_Product((int) $id); }

$checks = array('passed' => 0, 'failed' => 0);
function check($name, $ok, $detail = '') {
    global $checks; $checks[$ok ? 'passed' : 'failed']++;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . ($ok ? '' : '  → ' . (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE))) . "\n";
}

$dir = __DIR__ . '/../../qimia-beauty/includes/';
require $dir . 'beauty-native-cards.php';

/* ------------------------------------------------------------------ */
echo "\nCards (qil_catalogue_product_record, qby_qil_records)\n";
$record = array('id' => 10, 'name' => 'Repair Shampoo', 'facts' => array(array('label' => 'Serving', 'value' => '1 scoop')), 'dietary' => array('vegan'),
    'price' => array('amount' => 5, 'perServing' => 0.2, 'perServingFormatted' => '0.200'), 'match' => array('purposeKey' => 'muscle', 'reasons' => array('x')));
$out = run_filter('qil_catalogue_product_record', $record, new Test_Product(10), array());
check('beauty card is classed beauty with schema 1', ($out['productClass'] ?? '') === 'beauty' && ($out['beauty']['schema'] ?? 0) === 1, $out);
check('beauty card drops oral facts, dietary claims and per-serving prices', $out['facts'] === array() && $out['dietary'] === array() && !isset($out['price']['perServing']) && !isset($out['price']['perServingFormatted']) && $out['price']['amount'] === 5, $out['price']);
check('beauty card gets two facts per language', count($out['beauty']['facts']['en']) === 2 && count($out['beauty']['facts']['ar']) === 2, $out['beauty']['facts']);
check('beauty card facts come from recorded details', $out['beauty']['facts']['en'][0]['value'] === '50 ml' && $out['beauty']['facts']['en'][1]['value'] === 'Dry hair' && $out['beauty']['facts']['ar'][1]['value'] === 'شعر جاف', $out['beauty']['facts']);
check('beauty card match is beauty with no reasons', $out['match'] === array('purposeKey' => 'beauty', 'reasons' => array()), $out['match']);
$tool = run_filter('qil_catalogue_product_record', $record, new Test_Product(12), array());
check('beauty tool is classed accessory without a size row', $tool['productClass'] === 'accessory' && $tool['beauty']['facts']['en'][0]['label'] !== 'Size', $tool['beauty']['facts']);
$supp = $record; $supp['id'] = 11;
check('supplement card is untouched', run_filter('qil_catalogue_product_record', $supp, new Test_Product(11), array()) === $supp);
$identity = run_filter('qil_cache_market_identity', array('currency' => 'OMR'));
check('cache identity splits by Beauty version', ($identity['beautyRecordSchema'] ?? '') === 'qby-2.4.0-1' && $identity['currency'] === 'OMR', $identity);
check('no records without Intelligence Lab', qby_qil_records(array(10, 20)) === array());
if (true) {
    define('QIL_VERSION', '1.19.0');
    $catalogue_calls = array();
    function qil_experience_enabled() { return true; }
    function qil_get_catalogue($args) { global $catalogue_calls; $catalogue_calls[] = $args; return array_map(static function ($id) { return array('id' => $id, 'name' => "P$id</script><b>"); }, $args['include']); }
}
$records = qby_qil_records(array(new Test_Product(21), 11, 10, 10, (object) array('ID' => 20)));
check('records keep caller order, skip supplements and duplicates', array_column($records, 'id') === array(21, 10, 20), array_column($records, 'id'));
check('records come from one bounded catalogue call', count($catalogue_calls) === 1 && $catalogue_calls[0]['_qil_catalogue_selection'] === true && $catalogue_calls[0]['limit'] === 3, $catalogue_calls);
$json = qby_qil_json($records);
check('embedded card JSON can never close its script tag', substr_count($json, '</script>') === 1 && !str_contains($json, '<b>'), $json);

/* ------------------------------------------------------------------ */
require $dir . 'compare-beauty.php';
echo "\nCompare (/amir-ai/v1/products/compare and /advice)\n";
$pair = array('ok' => true, 'currency' => 'OMR', 'best_value_product_id' => 11, 'value_insight' => array('x'), 'recommendation_insight' => array('y'), 'products' => array(
    array('id' => 10, 'name' => 'Repair Shampoo', 'product_class' => 'beauty', 'price' => array('amount' => 5, 'currency' => 'OMR', 'decimals' => 3), 'compare_facts' => array('brand' => 'A', 'expiry' => '2027', 'servings' => 30)),
    array('id' => 11, 'name' => 'Hair Vitamins', 'product_class' => 'supplement', 'price' => array('amount' => 9, 'currency' => 'OMR'), 'compare_facts' => array('brand' => 'B', 'servings' => 60)),
));
$res = run_filter('rest_request_after_callbacks', new WP_REST_Response($pair), array(), new Test_Request('/amir-ai/v1/products/compare'));
$data = $res->get_data();
check('mixed pair never names a winner', $data['best_value_product_id'] === 0 && $data['value_insight'] === array() && $data['recommendation_insight'] === array() && $data['comparison_quality']['forced_winner_allowed'] === false, $data);
check('mixed pair is labelled mixed', $data['qimia_product_classes'] === 'mixed' && $data['qby_beauty_comparison']['mixed'] === true);
check('beauty side drops oral facts', !isset($data['products'][0]['compare_facts']['servings']) && $data['products'][0]['compare_facts']['total_content'] === '50 ml', $data['products'][0]['compare_facts']);
check('supplement side stays a supplement', $data['products'][1]['product_class'] === 'supplement' && $data['products'][1]['compare_facts']['purpose_profile']['family'] === 'supplement');
check('exact single-pack price per 100 ml', ($data['products'][0]['qby_price_per_100']['amount'] ?? null) == 10 && $data['qby_beauty_comparison']['rows'][0]['values'][0]['en'] === 'OMR 10.000 / 100 ml', $data['qby_beauty_comparison']['rows'][0] ?? null);
$keys = array_column($data['qby_beauty_comparison']['rows'], 'key');
check('comparison rows only for recorded details', in_array('hair', $keys, true) && in_array('ingredients', $keys, true) && !in_array('spf', $keys, true), $keys);
$plain = $pair; $plain['products'][0]['id'] = 11;
check('supplement-only pair passes through unchanged', run_filter('rest_request_after_callbacks', new WP_REST_Response($plain), array(), new Test_Request('/amir-ai/v1/products/compare'))->get_data() === $plain);
check('other routes pass through unchanged', run_filter('rest_request_after_callbacks', new WP_REST_Response($pair), array(), new Test_Request('/amir-ai/v1/chat'))->get_data() === $pair);
$error = new WP_Error();
check('errors pass through unchanged', run_filter('rest_request_after_callbacks', $error, array(), new Test_Request('/amir-ai/v1/products/compare')) === $error);
$routes = array(
    '/amir-ai/v1/products/compare/advice' => array(array('methods' => 'POST', 'callback' => array('AAICE_REST', 'product_compare_advice'), 'permission_callback' => 'perm')),
    '/amir-ai/v1/other' => array(array('callback' => array('AAICE_REST', 'product_compare_advice'))),
);
$swapped = run_filter('rest_endpoints', $routes);
$advice = $swapped['/amir-ai/v1/products/compare/advice'][0];
check('advice callback is wrapped, permission and methods unchanged', $advice['callback'] instanceof Closure && $advice['permission_callback'] === 'perm' && $advice['methods'] === 'POST');
check('other routes keep their callbacks', $swapped['/amir-ai/v1/other'] === $routes['/amir-ai/v1/other']);
$custom = array('/amir-ai/v1/products/compare/advice' => array(array('callback' => 'someone_else')));
check('an unexpected advice callback is left alone', run_filter('rest_endpoints', $custom) === $custom);
$original = static function ($request) { return 'native'; };
check('supplement-only advice goes to the native handler', qby_compare_beauty_advice(new Test_Request('/x', array(), json_encode(array('ids' => array(11, 11)))), $original) === 'native');
check('advice with one id goes to the native handler', qby_compare_beauty_advice(new Test_Request('/x', array(), json_encode(array('ids' => array(10)))), $original) === 'native');
check('oversized advice body goes to the native handler', qby_compare_beauty_advice(new Test_Request('/x', array(), str_repeat('a', 40000)), $original) === 'native');

/* ------------------------------------------------------------------ */
require $dir . 'integration.php';
echo "\nAI and product page (AI Commerce prompt, AI Product Box shortcodes, QIL product knowledge)\n";
$parts = run_filter('qimia_ai_instruction_parts_v1', array('stable' => 'BASE'));
check('AI instructions keep the base and add the topical rule', str_starts_with($parts['stable'], 'BASE') && str_contains($parts['stable'], 'cosmetics are topical'), $parts);
$parts = run_filter('qimia_ai_instruction_parts_v1', array('dynamic' => 'x'));
check('AI instructions without a stable part still get the rule', str_contains($parts['stable'] ?? '', 'cosmetics are topical') && $parts['dynamic'] === 'x', $parts);
$page = array('product' => true, 'queried' => 10, 'tax' => false);
check('supplement facts are hidden on a beauty product', run_filter('pre_do_shortcode_tag', false, 'qimia_supplement_facts', '') === '');
check('AI box becomes Beauty details on a beauty product', str_contains((string) run_filter('pre_do_shortcode_tag', false, 'qimia_ai_box', ''), 'data-id="10"'));
check('other shortcodes are untouched', run_filter('pre_do_shortcode_tag', false, 'gallery', array()) === false);
check('an explicit supplement product id keeps its facts', run_filter('pre_do_shortcode_tag', false, 'qimia_supplement_facts', array('product_id' => 11)) === false);
$page = array('product' => true, 'queried' => 11, 'tax' => false);
check('supplement product keeps its AI box', run_filter('pre_do_shortcode_tag', false, 'qimia_ai_box', '') === false);
check('QIL product intelligence stays on for supplements', run_filter('qil_product_intelligence_enabled', true) === true);
$page = array('product' => true, 'queried' => 10, 'tax' => false);
check('QIL product intelligence is off on beauty products', run_filter('qil_product_intelligence_enabled', true) === false);
check('personal picks skip beauty products', run_filter('qil_personal_product_eligible', true, new Test_Product(10)) === false && run_filter('qil_personal_product_eligible', true, new Test_Product(11)) === true);
$k = run_filter('qil_product_knowledge', array('faq' => array('x'), 'serving' => '1 scoop', 'hasFacts' => true), 10, false);
check('beauty knowledge has no oral fields', $k['faq'] === array() && $k['serving'] === '' && $k['hasFacts'] === false && $k['ingredients'] === 'Aqua, Glycerin' && $k['title'] === 'Topical care', $k);
$k11 = array('faq' => array('x'), 'serving' => '1 scoop');
check('supplement knowledge is untouched', run_filter('qil_product_knowledge', $k11, 11, false) === $k11);

/* ------------------------------------------------------------------ */
echo "\nSearch (/qimia-lab/v1/search, main query)\n";
function qil_search_product_ids($q, $n) { return array(11); }
$search = array('products' => array(array('id' => 11, 'name' => 'Hair Vitamins'), array('id' => 30, 'name' => 'Biotin')));
$res = run_filter('rest_request_after_callbacks', new WP_REST_Response($search), array(), new Test_Request('/qimia-lab/v1/search', array('q' => 'shampoo', 'qil_locale' => 'en', 'limit' => 8)));
check('an exclusive beauty term returns beauty results only', array_column($res->get_data()['products'], 'id') === array(20, 21), $res->get_data());
$res = run_filter('rest_request_after_callbacks', new WP_REST_Response($search), array(), new Test_Request('/qimia-lab/v1/search', array('q' => 'hair', 'qil_locale' => 'en', 'limit' => 4)));
check('ambiguous "hair" mixes beauty and supplements', array_column($res->get_data()['products'], 'id') === array(20, 21, 11, 30), $res->get_data());
$res = run_filter('rest_request_after_callbacks', new WP_REST_Response($search), array(), new Test_Request('/qimia-lab/v1/search', array('q' => 'protein', 'qil_locale' => 'en')));
check('other searches pass through unchanged', $res->get_data() === $search);
$res = run_filter('rest_request_after_callbacks', new WP_REST_Response($search), array(), new Test_Request('/qimia-lab/v1/search', array('q' => 'shampoo', 'qil_locale' => 'fr')));
check('unknown locales pass through unchanged', $res->get_data() === $search);
check('grouped results stay within 4–8 and drop duplicates', array_column(qby_group_search_results(array(array('id' => 1), array('id' => 2), array('id' => 3)), array(array('id' => 3), array('id' => 4), array('id' => 5)), 2), 'id') === array(1, 2, 3, 4));

class Test_Query {
    public $vars = array(); public $set_calls = array();
    function __construct(public $search = false) {}
    function is_main_query() { return true; } function is_search() { return $this->search; }
    function is_post_type_archive($t) { return false; } function is_tax($t = '') { return false; }
    function get($k) { return $this->vars[$k] ?? ''; }
    function set($k, $v) { $this->vars[$k] = $v; $this->set_calls[] = $k; }
}
function qby_query_value($k, $d = '') { return $d; }
$query = new Test_Query();
run_filter('pre_get_posts', $query);
check('an ordinary page query is left alone', $query->set_calls === array(), $query->vars);
$query = new Test_Query(); $query->vars['tax_query'] = array(array('taxonomy' => 'product_visibility', 'terms' => array(5), 'operator' => 'NOT IN'));
$before = $query->vars;
run_filter('pre_get_posts', $query);
check('an existing tax query is not rewritten without a Beauty scope', $query->vars === $before, $query->vars);

echo "\n" . $checks['passed'] . ' passed, ' . $checks['failed'] . " failed\n";
exit($checks['failed'] ? 1 : 0);
