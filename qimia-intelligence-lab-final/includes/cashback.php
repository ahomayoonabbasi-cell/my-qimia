<?php
/**
 * Read-only homepage cashback presentation.
 *
 * The cashback bridge owns eligibility, rules, issuance and email delivery.
 * The site's currency engine owns selection and rates. This module only reads
 * their public configuration. It never scans orders, writes settings, sends
 * email, changes a coupon or applies product-price rounding to a reward.
 *
 * @package Qimia_Intelligence_Lab
 */
defined( 'ABSPATH' ) || exit;

final class QIL_Cashback {
	const STYLE = 'qimia-lab-cashback';
	const BANDS = array(
		'upto20' => array( 0, 20 ), 'upto30' => array( 20, 30 ),
		'upto40' => array( 30, 40 ), 'upto50' => array( 40, 50 ),
		'upto60' => array( 50, 60 ), 'above60' => array( 60, null ),
	);

	public static function boot() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 35 );
		add_shortcode( 'qimia_cashback_rules', array( __CLASS__, 'shortcode' ) );
		foreach ( array('qcb2_settings','qimia_ugc_settings') as $option ) {
			add_action( 'update_option_'.$option, array(__CLASS__,'purge_home') );
			add_action( 'add_option_'.$option, array(__CLASS__,'purge_home') );
		}
	}

	/** Settings changed: invalidate only the two homepage URLs, not the whole shop. */
	public static function purge_home() {
		$home = get_option('home', '');
		if ( ! is_string($home) ) { return; }
		$parts = wp_parse_url($home);
		if ( ! is_array($parts) || empty($parts['host']) || ! in_array(isset($parts['scheme'])?$parts['scheme']:'',array('https','http'),true) ) { return; }
		$home = rtrim($home, '/');
		do_action('litespeed_purge_url', $home.'/');
		do_action('litespeed_purge_url', $home.'/ar/');
	}

	/**
	 * Only the issuer's public rule; never the full settings array.
	 *
	 * 1.7.0: the amounts come from the cashback issuer itself (QCB2_Core::public_policy,
	 * bridge 2.5.0+, or a direct QCB2_Core::tier() probe for 2.4.x). A saved legacy
	 * `cashback_tiers` option is NEVER used for display: in 2.4.x the issuer does not read
	 * it, so showing it could promise 9 OMR while the issuer sends 2 OMR. When the issuer
	 * is not active or cannot be summarised, the section is hidden instead of guessed.
	 */
	public static function policy() {
		try {
			if ( is_callable( array( 'QCB2_Core', 'public_policy' ) ) ) {
				$p = QCB2_Core::public_policy();
				$amounts = isset( $p['amounts'] ) ? $p['amounts'] : null;
				$minimum = isset( $p['minimum_omr'] ) ? $p['minimum_omr'] : null;
				$source = 'cashback_issuer_policy';
			} elseif ( is_callable( array( 'QCB2_Core', 'tier' ) ) && is_callable( array( 'QCB2_Core', 'cfg' ) ) ) {
				$cfg = QCB2_Core::cfg();
				$gap = (float) ( isset( $cfg['gap_50_60'] ) ? $cfg['gap_50_60'] : 5 );
				$amounts = array();
				foreach ( self::BANDS as $key => $bounds ) {
					$first = QCB2_Core::tier( round( $bounds[0] + 0.001, 3 ), $gap );
					$last = QCB2_Core::tier( null === $bounds[1] ? 1000000.0 : (float) $bounds[1], $gap );
					if ( abs( $first - $last ) > 0.0001 ) { return null; }
					$amounts[ $key ] = $first;
				}
				$minimum = isset( $cfg['minimum_omr'] ) ? $cfg['minimum_omr'] : 0;
				$source = 'cashback_issuer_tier_probe';
			} else {
				return null; // Issuer inactive: no campaign promise is shown.
			}
			if ( ! is_array( $amounts ) || count( $amounts ) !== count( self::BANDS ) ) {
				return null;
			}
			$clean = array();
			foreach ( self::BANDS as $key => $bounds ) {
				$value = self::amount( isset( $amounts[ $key ] ) ? (string) round( (float) $amounts[ $key ], 3 ) : null );
				if ( null === $value || $value < 0.001 || $value > 10000 ) {
					return null;
				}
				$clean[ $key ] = $value;
			}
			$minimum = self::amount( (string) round( (float) $minimum, 3 ) );
			if ( null === $minimum ) { return null; }
			return array(
				'amounts' => $clean, 'minimum_omr' => $minimum,
				// Issuer v2.4.x+ uses day +14 at 23:59:59 Oman time, not 14*24h.
				'days' => isset($p['expiry']['days_after_issue'])?(int)$p['expiry']['days_after_issue']:14,
                'instant' => ($p['program']??'')==='instant_paid_order'&&!empty($p['enabled']),
				'source' => $source,
			);
		} catch ( Throwable $error ) {
			return null; // Do not break the storefront or print invalid rewards.
		}
	}

	private static function amount( $value ) {
		if ( ! is_scalar($value) || is_bool($value) ) { return null; }
		$raw = strtr( trim((string)$value), array(
			'۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
			'٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٫'=>'.',
		) );
		if ( ! preg_match('/^[0-9]+(?:\.[0-9]{1,3})?$/D', $raw) ) { return null; }
		$value = (float) $raw;
		return is_finite($value) && $value >= 0 ? $value : null;
	}

	/** Returns units of the selected currency per OMR, never a market guess. */
	public static function rate( $currency ) {
		if ( ! is_string($currency) || ! preg_match('/^[A-Z]{3}$/D', $currency) ) { return null; }
		if ( 'OMR' === $currency ) { return 1.0; }
		if ( is_callable(array('QCB2_Core','rates')) ) {
			try {
				$rates = QCB2_Core::rates();
				if ( isset($rates[$currency]['rate']) ) {
					return self::positive_rate($rates[$currency]['rate']);
				}
				// A non-GCC display currency can exist in the store, even when the
				// cashback issuer only sends GCC coupons. We do not promise redemption.
			} catch ( Throwable $error ) { return null; }
		}
		$settings = get_option('qimia_ugc_settings', array());
		if ( ! is_array($settings) || (isset($settings['enabled']) && self::disabled($settings['enabled'])) ) { return null; }
		$rows = isset($settings['currencies_json']) ? $settings['currencies_json'] : array();
		if ( is_string($rows) ) { $rows = json_decode($rows, true); }
		if ( ! is_array($rows) || ! isset($rows[$currency]) || ! is_array($rows[$currency]) ) { return null; }
		$row = $rows[$currency];
		if ( isset($row['enabled']) && self::disabled($row['enabled']) ) { return null; }
		$base = isset($settings['base_currency']) && is_string($settings['base_currency']) ? strtoupper($settings['base_currency']) : 'OMR';
		if ( ! preg_match('/^[A-Z]{3}$/D', $base) ) { return null; }
		$omr = 'OMR' === $base ? 1.0 : self::positive_rate(isset($rows['OMR']['rate']) ? $rows['OMR']['rate'] : null);
		$target = $currency === $base ? 1.0 : self::positive_rate(isset($row['rate']) ? $row['rate'] : null);
		return $omr && $target ? self::positive_rate($target / $omr) : null;
	}

	private static function disabled( $value ) {
		return in_array($value, array(false,0,'0','no','false','off',''), true);
	}
	private static function positive_rate( $value ) {
		if ( ! is_scalar($value) || is_bool($value) || ! is_numeric($value) ) { return null; }
		$value = (float) $value;
		return is_finite($value) && $value > 0 && $value < 1000 ? $value : null;
	}

	/** Exact OMR values; whole-unit, explicitly approximate foreign values. */
	public static function money( $omr, $currency, $rate, $ar ) {
		$value = $omr * $rate;
		if ( 'OMR' === $currency ) {
			$number = rtrim(rtrim(number_format($value,3,'.',','),'0'),'.');
		} else {
			$whole = round($value,0,PHP_ROUND_HALF_UP);
			$number = $value > 0 && $whole < 1 ? '< 1' : number_format($whole,0,'.',',');
		}
		if ( $ar ) { $number = strtr($number, array('0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩',','=>'٬')); }
		$units = array('OMR'=>'ر.ع','AED'=>'د.إ','SAR'=>'ر.س','QAR'=>'ر.ق','KWD'=>'د.ك','BHD'=>'د.ب','USD'=>'USD');
		$unit = $ar && isset($units[$currency]) ? $units[$currency] : $currency;
		return ( 'OMR' === $currency ? '' : '≈ ' ) . $number . ' ' . $unit;
	}

	public static function view() {
		$policy = self::policy();
		if ( ! $policy ) { return null; }
		$lang = function_exists('qil_language_context') ? qil_language_context() : array('isArabic'=>false);
		$ar = ! empty($lang['isArabic']);
		$selected = function_exists('get_woocommerce_currency') ? strtoupper((string)get_woocommerce_currency()) : 'OMR';
		$rate = self::rate($selected);
		$currency = $rate ? $selected : 'OMR';
		$rate = $rate ? $rate : 1.0;
		$money = static function($value) use ($currency,$rate,$ar) { return self::money($value,$currency,$rate,$ar); };
		$rows = array();
		foreach ( self::BANDS as $key => $bounds ) {
			list($low,$high) = $bounds;
			if ( 0 === $low ) {
				$range = $ar ? 'حتى '.$money($high) : 'Up to '.$money($high);
			} elseif ( null === $high ) {
				$range = $ar ? 'أكثر من '.$money($low) : 'Over '.$money($low);
			} else {
				$range = $ar ? 'أكثر من '.$money($low).' حتى '.$money($high) : 'Over '.$money($low).' to '.$money($high);
			}
			$rows[] = array('key'=>$key, 'range'=>$range, 'reward'=>$money($policy['amounts'][$key]));
		}
		$terms = $ar ? array(
			'تُحدَّد الفئة حسب قيمة الطلب المدفوع المؤهل بالريال العُماني، شاملة الشحن والضريبة وبعد خصم المبالغ المستردة. الحد الأعلى لكل فئة مشمول.',
			'كوبون واحد لكل عميل أو بريد فوترة عن شهر الطلب الأصلي، بناءً على طلب مؤهل تختاره الحملة؛ وليس مجموع كل الطلبات.',
			'الكاش باك رصيد شراء لطلب قادم، وليس مبلغًا نقديًا. يُستخدم الكوبون لطلب واحد ولا يُجمع مع كوبون آخر؛ لا يخصم رسوم الشحن ولا يُرحّل الرصيد غير المستخدم.',
			'تنتهي صلاحية الكوبونات الجديدة الساعة ١١:٥٩ مساءً بتوقيت عُمان في اليوم الرابع عشر بعد الإصدار. تبقى صلاحية الكوبونات السابقة كما وردت في رسائلها.',
		) : array(
			'Bands use the eligible paid order value in OMR, including shipping and tax, less refunds. Each upper limit is included.',
			'One coupon per customer or billing email per source month, based on a qualifying order selected by the campaign—not the sum of all orders.',
			'Cashback is shopping credit for a future order, not a cash payout. A coupon is for one order and cannot be combined with another coupon; shipping is not discounted and unused credit does not carry forward.',
			'New coupons expire at 11:59 PM Oman time on the 14th day after issue. Previously issued coupons keep the expiry stated in their email.',
		);
        if (!empty($policy['instant'])) {
            $terms[1]=$ar?'كوبون واحد لكل طلب إلكتروني مؤهل بعد تأكيد الدفع، لاستخدامه في طلب قادم.':'One coupon per eligible online order after confirmed payment, for a later purchase.';
            $terms[3]=$ar?'الصلاحية ٤٠ × ٢٤ ساعة من الإصدار. تذكيران مشروطان قبل الانتهاء بـ١٤ و٣ أيام لنفس الكود. الكوبونات السابقة تحتفظ بمبلغها وتاريخها.':'Valid for exactly 40 × 24 hours from issue. Conditional reminders 14 and 3 days before expiry use the same code. Previous coupons keep their amount and expiry.';
        }
		if ( $policy['minimum_omr'] > 0 ) {
			$terms[] = $ar ? 'الحد الأدنى للطلب القادم: '.$money($policy['minimum_omr']).'.' : 'Minimum next-order value: '.$money($policy['minimum_omr']).'.';
		}
		if ( $currency !== 'OMR' ) {
			$terms[] = $ar ? 'تستخدم هذه الصفحة سعر التحويل الحالي في الموقع للعرض فقط. قد يعتمد احتساب الطلب الأصلي على سعره المحفوظ. البريد الإلكتروني والدفع يوضحان قيمة الكوبون وعملته بدقة.' : 'This page uses the current store exchange rate for display. The original order may use its stored rate. The email and checkout confirm the exact coupon value and currency.';
		}
		return array(
			'ar'=>$ar, 'locale'=>$ar?'ar':'en', 'direction'=>$ar?'rtl':'ltr',
			'currency'=>$currency, 'selected'=>$selected, 'fallback'=>$selected!==$currency,
			'rows'=>$rows, 'terms'=>$terms,
			'kicker'=>$ar?'كيميا · كاش باك':'QIMIA · CASHBACK',
			'title'=>$ar?'كاش باك لطلبك القادم.':'Cashback for your next order.',
			'lead'=>!empty($policy['instant'])?($ar?'بعد تأكيد دفع طلبك الإلكتروني المؤهل، يصدر كوبون لطلبك القادم. إرسال البريد منفصل عن إصدار الكوبون.':'After confirmed payment of your eligible online order, a coupon is issued for your next purchase. Email sending is separate from issuance.'):($ar?'للطلبات المؤهلة في الحملة، نرسل كوبونًا إلى بريدك الإلكتروني لتستخدمه في عملية شراء قادمة.':'For eligible campaign orders, we email a coupon to use on your next purchase.'),
			'email'=>$ar?'إشعار عبر البريد الإلكتروني':'Email notification',
			'validity'=>!empty($policy['instant'])?($ar?'٤٠ يومًا من وقت الإصدار':'40 days from issue'):($ar?'صلاحية الكوبون حسب تاريخ إصداره':'See your coupon’s exact expiry'),
			'order'=>$ar?'قيمة الطلب':'ORDER VALUE',
			'back'=>$ar?'كاش باك':'cashback',
			'currency_label'=>$ar?'العملة المعروضة: ':'Showing in ',
			'note'=>$selected!==$currency
				? ($ar?'سعر عملتك غير متاح حاليًا؛ تظهر القيم بالريال العُماني. يُرجى اعتماد قيمة الكوبون وصلاحيته في البريد الإلكتروني.':'Your currency rate is unavailable, so values are shown in OMR. Refer to your coupon email for its exact value and expiry.')
				: ($currency!=='OMR'
					? ($ar?'القيم بالعملة المختارة تقريبية ومقربة إلى أعداد صحيحة. تُحسب الفئات بالريال العُماني؛ البريد الإلكتروني يؤكد قيمة الكوبون وصلاحيته.':'Converted values are approximate and rounded to whole units. Bands are calculated in OMR; your email confirms the coupon value and expiry.')
					: ($ar?'رصيد شراء، وليس مبلغًا نقديًا. تؤكد رسالة الكوبون قيمته وصلاحيته؛ تطبق شروط الأهلية.':'Shopping credit, not a cash payout. Your coupon email confirms its value and expiry; eligibility terms apply.')),
			'details'=>$ar?'كيف يعمل الكاش باك؟':'How does cashback work?',
		);
	}

	public static function enqueue( $force = false ) {
		if ( is_admin() && ! $force ) { return; }
		$home = function_exists('qil_render_mode') && 'full' === qil_render_mode();
		global $post;
		$shortcode = isset($post->post_content) && has_shortcode($post->post_content,'qimia_cashback_rules');
		if ( ! $force && ! $home && ! $shortcode ) { return; }
		wp_enqueue_style(self::STYLE,QIL_URL.'assets/qil-cashback.css',array(),QIL_VERSION);
	}

	public static function render() {
		if ( function_exists('qil_experience_enabled') && ! qil_experience_enabled() ) { return ''; }
		$view = self::view();
		if ( ! $view ) { return ''; }
		self::enqueue(true);
		$title_id = wp_unique_id('qil-cashback-title-');
		ob_start();
		include QIL_DIR.'templates/cashback.php';
		return (string) ob_get_clean();
	}

	public static function shortcode( $atts = array() ) { return self::render(); }
}
QIL_Cashback::boot();
