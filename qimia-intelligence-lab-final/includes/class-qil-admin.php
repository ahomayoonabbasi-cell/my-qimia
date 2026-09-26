<?php
/** Lab owns presentation only. My Qimia owns its own workspace settings. */
defined('ABSPATH') || exit;
final class QIL_Admin {
    private const SLUG = 'qimia-intelligence-lab';
    public static function init(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'), 110);
        add_action('admin_init', array(__CLASS__, 'legacy_tabs'), 7);
    }
    public static function menu(): void {
        remove_action('settings_page_'.self::SLUG, 'qil_render_settings_page');
        remove_submenu_page('options-general.php', self::SLUG);
        add_options_page('Qimia Intelligence Lab', 'Qimia Intelligence Lab', 'manage_options', self::SLUG, array(__CLASS__, 'render'));
    }
    public static function legacy_tabs(): void {
        if (!current_user_can('manage_options') || ($_SERVER['REQUEST_METHOD']??'GET')!=='GET') return;
        if (sanitize_key(wp_unslash($_GET['page']??''))!==self::SLUG) return;
        $tab=sanitize_key(wp_unslash($_GET['tab']??''));
        if (in_array($tab,array('my-qimia','food','access'),true) && class_exists('QH_Phase3_Admin')) {
            wp_safe_redirect(QH_Phase3_Admin::url($tab,sanitize_key(wp_unslash($_GET['view']??'')))); exit;
        }
    }
    private static function rows(array $rows): void {
        echo '<table class="widefat striped"><tbody>';
        foreach($rows as $name=>$value)echo '<tr><th scope="row">'.esc_html($name).'</th><td>'.esc_html((string)$value).'</td></tr>';
        echo '</tbody></table>';
    }
    public static function render(): void {
        if(!current_user_can('manage_options'))wp_die('Permission denied.');
        $tab=sanitize_key(wp_unslash($_GET['tab']??'storefront'));
        echo '<style>.qimia-admin{direction:ltr;max-width:1200px;font-size:16px;line-height:1.6}.qimia-admin-panel{padding:24px;background:white;border:1px solid #d7e6e3;border-radius:16px;margin:18px 0}.qimia-admin-tabs{display:flex;gap:16px;flex-wrap:wrap}.qimia-admin-tabs a{padding:12px;min-height:44px}.qimia-admin .button{min-height:44px;padding:6px 16px}</style>';
        echo '<div class="wrap qimia-admin" lang="en" dir="ltr"><h1>QIMIA INTELLIGENCE LAB</h1><p>Storefront presentation · '.esc_html(QIL_VERSION).'</p><nav class="qimia-admin-tabs" aria-label="Qimia Lab settings">';
        foreach(array('storefront'=>'Storefront & Hero','shopping'=>'Shopping Experience','performance'=>'Performance','connections'=>'Connections') as $key=>$label)echo '<a href="'.esc_url(add_query_arg(array('page'=>self::SLUG,'tab'=>$key),admin_url('options-general.php'))).'"'.($tab===$key?' aria-current="page"':'').'>'.esc_html($label).'</a>';
        echo '</nav>';
        if($tab==='connections')self::connections();elseif($tab==='shopping')QIL_Personalization::admin();elseif($tab==='performance'&&function_exists('qil_perf_admin'))qil_perf_admin();else self::storefront();
        echo '</div>';
    }
    private static function connections(): void {
        echo '<section class="qimia-admin-panel"><h2>Independent plugins, existing connections</h2>';
        self::rows(array('My Qimia runtime'=>class_exists('QH_Account')?'Connected':'Not active', 'Workspace on this host'=>function_exists('qh_environment_allowed')&&qh_environment_allowed()?'Existing access policy allows this host':'Unavailable under the existing access policy','AI connection'=>class_exists('AAICE_Connection')?'Existing provider connection available':'Provider plugin not detected','Cashback'=>class_exists('QCB2_Account')?'Existing account read model available':'Cashback account plugin not detected'));
        echo '<p>Lab does not own workspace tables, account permissions, AI credentials, coupon issuance or reminder jobs. Existing identifiers remain unchanged.</p>';
        if(class_exists('QH_Phase3_Admin'))echo '<p><a class="button" href="'.esc_url(QH_Phase3_Admin::url()).'">Manage My Qimia</a></p>';
        echo '</section>';
    }
    private static function storefront(): void {
        echo '<section class="qimia-admin-panel"><h2>Existing storefront</h2>';self::rows(array('Host'=>qil_current_host(),'Storefront rendering'=>qil_experience_enabled()?'Enabled':'Disabled','Environment'=>qil_is_staging_sandbox()?'Staging':'Live host','Homepage CTA'=>function_exists('qh_environment_allowed')&&qh_environment_allowed()&&qh_page_url()?'My Qimia in the existing hero':'Workspace page not available'));
        echo '<p>The existing hero image, headline, product sections, navigation essentials and public SEO settings are preserved. The extra mobile My Qimia row has been removed; the existing menu remains available.</p>';
        if(!qil_is_staging_sandbox()){echo '<form method="post" action="options.php">';settings_fields('qil_settings');echo '<p><label><input type="checkbox" name="qil_enabled" value="1" '.checked('1',(string)get_option('qil_enabled','0'),false).'> Enable the existing Qimia storefront experience</label></p>';submit_button('Save storefront setting');echo '</form>';}
        echo '</section>';self::hero();
        echo '<section class="qimia-admin-panel"><h2>Emergency stop</h2><p>The existing <code>QIL_DISABLE</code> and <code>QH_DISABLE</code> switches remain available. Keep a verified staging backup and the previous plugin ZIPs before a replacement.</p></section>';
    }
    /** Existing Hero Studio setting keys, sanitizer, media UI and scripts; English presentation only. */
    private static function hero(): void {
        $s=qil_hero_settings();$image=qil_hero_image_data($s['desktop_id']);
        echo '<section class="qimia-admin-panel qil-hero-studio" id="qil-hero-studio"><h2>Hero Studio</h2><p>Fine-tune the existing hero overlay. No image or layout setting is changed merely by opening this page.</p><form method="post" action="options.php">';settings_fields('qil_hero_settings');echo '<div class="qil-hero-admin-grid"><div>';
        foreach(array('desktop_id'=>'Desktop image','mobile_id'=>'Mobile image') as $key=>$label){echo '<div class="qil-hero-media-row"><strong>'.esc_html($label).'</strong><input type="hidden" id="qil-hero-'.esc_attr($key).'" name="qil_hero_studio['.esc_attr($key).']" value="'.esc_attr($s[$key]).'"><button type="button" class="button" data-qil-hero-media="'.esc_attr($key).'">Choose / Replace</button><button type="button" class="button" data-qil-hero-clear="'.esc_attr($key).'">Use default</button><span data-qil-hero-name="'.esc_attr($key).'">'.esc_html($s[$key]?get_the_title($s[$key]):'Built-in / default').'</span></div>';}
        echo '<p class="description">When a mobile image is not selected, the desktop choice is used. With both empty, the bundled responsive images are used.</p>';
        foreach(array('desktop'=>'Desktop','mobile'=>'Mobile / tablet') as $mode=>$label){echo '<fieldset><legend><strong>'.esc_html($label).'</strong></legend>';foreach(array('width'=>'Width','height'=>'Height','left'=>'Left offset','bottom'=>'Bottom offset') as $field=>$text){$key=($mode==='mobile'?'mobile_':'').$field;$size=in_array($field,array('width','height'),true);echo '<label class="qil-hero-field" for="qil-hero-'.esc_attr($key).'"><span>'.esc_html($text).' (%)</span><input id="qil-hero-'.esc_attr($key).'" data-qil-hero-number="'.esc_attr($key).'" name="qil_hero_studio['.esc_attr($key).']" type="number" min="'.($size?'20':'-45').'" max="'.($size?'130':'45').'" step="0.1" value="'.esc_attr($s[$key]).'"><input data-qil-hero-range="'.esc_attr($key).'" aria-label="'.esc_attr($label.' '.$text).'" type="range" min="'.($size?'20':'-45').'" max="'.($size?'130':'45').'" step="0.1" value="'.esc_attr($s[$key]).'"></label>'; }echo '</fieldset>';}
        echo '</div><div><label for="qil-hero-preview-mode">Preview</label> <select id="qil-hero-preview-mode"><option value="desktop">Desktop</option><option value="mobile">Mobile / tablet</option></select><div class="qil-hero-preview" data-qil-hero-preview style="background-image:url(\''.esc_url(QIL_URL.'assets/qimia-digital-world-v14-1672.webp').'\')"><img data-qil-hero-preview-image src="'.esc_url($image['url']).'" alt="Hero layout preview"></div><p class="description">Positive left offset moves right in both languages. The preview is illustrative.</p></div></div>';submit_button('Save hero');echo '<button class="button" type="button" data-qil-hero-reset>Restore original size & position</button></form></section>';
    }
}
