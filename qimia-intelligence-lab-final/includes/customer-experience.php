<?php
/** Storefront / My Qimia presentation and task handoff. No financial writes. */
defined('ABSPATH') || exit;

/**
 * Public availability, not customer entitlement. Guests may follow the workspace
 * sign-in route. An absent/disabled companion, blocked host or unpublished page
 * returns no destination; a shop account URL must not masquerade as My Qimia.
 * No tables, user data, external calls or private API bootstrap are consulted.
 */
function qil_myqimia_url($tab = 'today', $is_arabic = null) {
    if (!function_exists('qh_environment_allowed') || !function_exists('qh_page_url') || !qh_environment_allowed()) {
        return '';
    }
    $workspace = qh_page_url();
    if (!is_string($workspace) || '' === trim($workspace)) return '';
    $tabs = array('today', 'routine', 'food', 'account');
    $tab = in_array($tab, $tabs, true) ? $tab : 'today';
    if (null === $is_arabic) {
        $ctx = function_exists('qil_language_context') ? qil_language_context() : array();
        $is_arabic = !empty($ctx['isArabic']);
    }
    if (function_exists('qil_localized_url')) $workspace = qil_localized_url($workspace, (bool)$is_arabic);
    $workspace = preg_replace('/#.*$/', '', $workspace) . '#qh-' . $tab;
    $workspace = apply_filters('qimia_myqimia_url', $workspace, $tab, (bool)$is_arabic);
    return is_string($workspace) ? esc_url_raw($workspace, array('http', 'https')) : '';
}

/** Shared entries: hide an unavailable header link; retain the story off state. */
function qil_myqimia_button($surface = 'story', $is_arabic = false) {
    $header = 'header' === $surface;
    $label = $header ? ($is_arabic ? 'ماي كيميا' : 'My Qimia') : ($is_arabic ? 'افتح ماي كيميا' : 'Open My Qimia');
    $icon = $header ? 'qil-i-user' : 'qil-i-arrow';
    $class = 'qil-button qil-button-primary ' . ($header ? 'qil-myqimia-header-button' : 'qil-myqimia-story-cta');
    $url = qil_myqimia_url('today', $is_arabic);
    $content = '<span>' . esc_html($label) . '</span><svg aria-hidden="true"><use href="#' . esc_attr($icon) . '"/></svg>';
    if ($url) {
        // Keep the icon-only mobile entry named for touch, keyboard and screen readers.
        $header_attributes = $header ? ' aria-label="' . esc_attr($label) . '" title="' . esc_attr($label) . '"' : '';
        return '<a class="' . esc_attr($class) . '" data-qil-myqimia-cta href="' . esc_url($url) . '"' . $header_attributes . '>' . $content . '</a>';
    }
    // No placeholder, disabled control or reserved gap in either header layout.
    if ($header) return '';
    $notice = $is_arabic ? 'ماي كيميا غير متاح حالياً' : 'My Qimia is currently unavailable';
    return '<button type="button" class="' . esc_attr($class . ' qil-myqimia-unavailable') . '" disabled aria-disabled="true" title="' . esc_attr($notice) . '">' . $content . '</button>';
}

/**
 * Enqueue one canonical shopping-context provider. My Qimia 3.1+ owns the
 * browser/customer continuity contract. This plugin uses a minimal
 * current-task fallback only when that companion is unavailable, so two
 * different providers never compete for the same WordPress handle at runtime.
 */
function qil_enqueue_shopping_context() {
    $handle = 'qimia-shopping-context';
    if (wp_script_is($handle, 'enqueued')) return;

    $dependencies = array();
    if (wp_script_is('qmq-agent-context', 'registered')) $dependencies[] = 'qmq-agent-context';

    $my_qimia_ready = defined('QMQ_VERSION') && version_compare((string) QMQ_VERSION, '3.1.0', '>=')
        && defined('QMQ_URL') && defined('QMQ_DIR')
        && is_readable(QMQ_DIR . 'assets/qimia-shopping-context.js');

    if ($my_qimia_ready) {
        if (!wp_script_is($handle, 'registered')) {
            wp_register_script($handle, QMQ_URL . 'assets/qimia-shopping-context.js', $dependencies, QMQ_VERSION, true);
        }
        wp_enqueue_script($handle);
        return;
    }

    if (!wp_script_is($handle, 'registered')) {
        wp_register_script($handle, QIL_URL . 'assets/qil-shopping-context-fallback.js', $dependencies, QIL_VERSION, true);
    }
    wp_enqueue_script($handle);
}

add_action('wp_enqueue_scripts', static function () {
    if (is_admin() || (function_exists('qil_is_elementor_context') && qil_is_elementor_context()) || (function_exists('qil_perf_noninteractive_bot') && qil_perf_noninteractive_bot()) || (function_exists('qil_perf_crawler_family') && '' !== qil_perf_crawler_family())) return;
    if (!function_exists('qil_experience_enabled') || !qil_experience_enabled()) return;
    // Register the canonical provider before My Qimia's own priority-90 adapter.
    qil_enqueue_shopping_context();
}, 89);

add_action('wp_enqueue_scripts', static function () {
    if (is_admin() || (function_exists('qil_is_elementor_context') && qil_is_elementor_context())) return;
    if (!function_exists('qil_experience_enabled') || !qil_experience_enabled()) return;
    $home = function_exists('qil_should_render') && qil_should_render();
    if ($home || (function_exists('qil_has_shortcode') && qil_has_shortcode())) {
        wp_enqueue_style('qimia-experience', QIL_URL . 'assets/qimia-experience.css', array(), QIL_VERSION);
        wp_enqueue_script('qimia-experience', QIL_URL . 'assets/qimia-experience.js', array(), QIL_VERSION, true);
    }
}, 90);

/** Public, bilingual walkthrough. Real destinations; no synthetic balances or health claims. */
function qil_section_myqimia_explainer() {
    $ctx = qil_view_context(); $ar = $ctx['isArabic'];
    $myqimia_ready = '' !== qil_myqimia_url('today', $ar);
    $steps = $ar ? array(
        array('01','qil-i-user','ابدأ بحسابك','سجّل الدخول بحساب كيميا نفسه، وافتح مساحتك الشخصية.'),
        array('02','qil-i-check','رتّب يومك','اجمع مكمّلاتك وروتينك وسجلّ طعامك في مكان واحد، حسب الخيارات المتاحة لحسابك.'),
        array('03','qil-i-bag','تابع مشترياتك','راجع طلباتك وقسائم الكاش باك الصادرة لحسابك، وارجع لمنتجاتك بسهولة.')
    ) : array(
        array('01','qil-i-user','Start with your account','Sign in with your existing Qimia account and open your personal space.'),
        array('02','qil-i-check','Organise your day','Keep your supplements, routine and food diary together, with the features available to your account.'),
        array('03','qil-i-bag','Stay close to your purchases','Review your orders and issued cashback coupons, and return to your products easily.')
    );
    ob_start(); ?>
    <section class="qil-myqimia-story qil-container" aria-labelledby="qil-myqimia-story-title" data-qil-myqimia-story data-qil-myqimia-state="<?php echo $myqimia_ready ? 'ready' : 'unavailable'; ?>">
        <div class="qil-myqimia-story-body">
            <div class="qil-myqimia-story-copy">
                <span class="qil-myqimia-story-eyebrow"><i aria-hidden="true"></i> MY QIMIA</span>
                <h2 id="qil-myqimia-story-title"><?php echo esc_html($ar ? 'كل ما يخصّك.' : 'Everything that’s yours.'); ?><span><?php echo esc_html($ar ? 'في مساحة واحدة.' : 'One personal space.'); ?></span></h2>
                <p><?php echo esc_html($ar ? 'من روتين مكمّلاتك إلى طلباتك وكاش باكك — ماي كيميا يجمع تجربتك بعد التسوّق، بنفس حسابك.' : 'From your supplement routine to your orders and cashback — My Qimia brings your experience together, with the account you already use.'); ?></p>
                <?php echo qil_myqimia_button('story', $ar); // Escaped, availability-aware entry. ?>
                <span class="qil-myqimia-story-privacy"><svg aria-hidden="true"><use href="#qil-i-shield"/></svg><?php echo esc_html($myqimia_ready
                    ? ($ar ? 'بياناتك مرتبطة بحسابك. المشاركة الاختيارية تبقى باختيارك.' : 'Your data stays linked to your account. Optional sharing stays your choice.')
                    : ($ar ? 'ماي كيميا غير متاح حالياً. يمكنك متابعة التسوّق كالمعتاد.' : 'My Qimia is currently unavailable. You can keep shopping as usual.')); ?></span>
            </div>
            <div class="qil-myqimia-story-visual" aria-hidden="true">
                <div class="qil-myqimia-story-orbit"></div>
                <img class="qil-myqimia-story-mark" src="<?php echo esc_url(QIL_URL.'assets/qimia-hero-orbit-v9.webp'); ?>" width="900" height="935" alt="" loading="lazy" decoding="async">
                <div class="qil-myqimia-story-card"><span class="qil-myqimia-story-card-icon"><svg><use href="#qil-i-user"/></svg></span><strong><?php echo esc_html($ar ? 'ماي كيميا' : 'My Qimia'); ?></strong><small><?php echo esc_html($ar ? 'مساحة مصمّمة ليومك' : 'A space for your everyday'); ?></small><div><span><?php echo esc_html($ar ? 'روتيني' : 'My routine'); ?></span><span><?php echo esc_html($ar ? 'طلباتي' : 'My orders'); ?></span><span><?php echo esc_html($ar ? 'كاش باك' : 'Cashback'); ?></span></div></div>
            </div>
        </div>
        <div class="qil-myqimia-story-steps" aria-label="<?php echo esc_attr($ar ? 'كيف يعمل ماي كيميا' : 'How My Qimia works'); ?>">
            <?php foreach($steps as $step): ?><div class="qil-myqimia-story-step"><div class="qil-myqimia-story-step-top"><span><?php echo esc_html($step[0]); ?></span><svg aria-hidden="true"><use href="#<?php echo esc_attr($step[1]); ?>"/></svg></div><h3><?php echo esc_html($step[2]); ?></h3><p><?php echo esc_html($step[3]); ?></p></div><?php endforeach; ?>
        </div>
    </section>
    <?php return (string)ob_get_clean();
}
