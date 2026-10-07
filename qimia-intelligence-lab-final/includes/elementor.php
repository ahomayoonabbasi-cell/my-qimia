<?php
/**
 * Elementor integration.
 *
 * Every homepage section is registered as an Elementor widget that renders
 * through the same qil_section_* function the storefront template uses, so a
 * section can be dropped onto any Elementor page and edited there without the
 * two ever drifting apart. Nothing here runs unless Elementor is active and
 * the storefront experience is enabled on the current host.
 *
 * @package Qimia_Intelligence_Lab
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Qimia Lab widget category.
 */
function qil_elementor_category( $manager ) {
	if ( ! qil_experience_enabled() ) {
		return;
	}

	$manager->add_category(
		'qimia-lab',
		array(
			'title' => 'Qimia Lab',
			'icon'  => 'eicon-nerd',
		)
	);
}
add_action( 'elementor/elements/categories_registered', 'qil_elementor_category' );

/**
 * Define and register every widget once Elementor's base class exists.
 */
function qil_elementor_register_widgets( $widgets_manager = null ) {
	if ( ! qil_experience_enabled() || ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Widget_Base' ) ) {
		return;
	}

	if ( ! class_exists( 'QIL_Elementor_Section_Widget' ) ) {

		/**
		 * Shared base: renders one qil_section_* callback with the widget's settings.
		 */
		abstract class QIL_Elementor_Section_Widget extends \Elementor\Widget_Base {

			/**
			 * The qil_section_* function this widget renders.
			 */
			protected $qil_callback = '';

			public function get_categories() {
				return array( 'qimia-lab' );
			}

			public function get_icon() {
				return 'eicon-select';
			}

			public function get_script_depends() {
				return array( 'qimia-intelligence-lab' );
			}

			public function get_style_depends() {
				return array( 'qimia-intelligence-lab' );
			}

			/**
			 * Bilingual text pair helper for control registration.
			 */
			protected function qil_add_text_pair( $key, $label, $default_en = '', $default_ar = '', $type = 'text' ) {
				$control = 'textarea' === $type ? \Elementor\Controls_Manager::TEXTAREA : \Elementor\Controls_Manager::TEXT;

				$this->add_control(
					$key . 'En',
					array(
						'label'       => $label . ' (EN)',
						'type'        => $control,
						'default'     => $default_en,
						'label_block' => true,
					)
				);
				$this->add_control(
					$key . 'Ar',
					array(
						'label'       => $label . ' (AR)',
						'type'        => $control,
						'default'     => $default_ar,
						'label_block' => true,
					)
				);
			}

			/**
			 * Open the lab scope around a widget that prints its own markup.
			 *
			 * Every rule in the stylesheet is written under #qimia-lab, so any
			 * widget dropped into a theme template has to carry that scope. A
			 * page may end up with several of these; CSS matches all of them and
			 * the script delegates from the document, so they are all live.
			 */
			protected function qil_open_shell() {
				$context = qil_view_context();
				printf(
					'<div class="qil-shell qil-elementor-shell qaatm-no-translate notranslate%1$s" dir="%2$s" lang="%3$s" translate="no" data-qil-locale="%3$s" data-qil-shell data-qaatm-no-rewrite data-no-translation>',
					$context['isArabic'] ? ' is-arabic' : '',
					esc_attr( $context['direction'] ),
					esc_attr( $context['locale'] )
				);
				echo qil_sprite(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted section markup.
			}

			protected function qil_close_shell() {
				echo '</div>';
			}

			/**
			 * Sections are self-contained; wrap them so the lab scope applies
			 * even when the widget is used outside the dedicated template.
			 */
			protected function render() {
				if ( ! function_exists( $this->qil_callback ) ) {
					return;
				}

				qil_enqueue_assets( true );
				$context  = qil_view_context();
				$settings = (array) $this->get_settings_for_display();
				$markup   = call_user_func( $this->qil_callback, $settings );

				printf(
					'<div class="qil-shell qil-elementor-shell qaatm-no-translate notranslate%1$s" dir="%2$s" lang="%3$s" translate="no" data-qil-locale="%3$s" data-qaatm-no-rewrite data-no-translation>%4$s%5$s</div>',
					$context['isArabic'] ? ' is-arabic' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_attr( $context['direction'] ),
					esc_attr( $context['locale'] ),
					qil_sprite(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		}

		/**
		 * Announcement bar.
		 */
		class QIL_Widget_Topbar extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_topbar';
			public function get_name() { return 'qil-topbar'; }
			public function get_title() { return 'Qimia Topbar'; }
			public function get_icon() { return 'eicon-header'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_topbar', array( 'label' => 'Delivery bar' ) );
				$this->add_control(
					'qil_topbar_note',
					array(
						'type' => \Elementor\Controls_Manager::RAW_HTML,
						'raw'  => 'The free-delivery line follows the shopper\'s active currency (OMR 15 · AED 144 · SAR 144 · QAR 144 · KWD 12) and cannot be overwritten here, so it can never contradict WooCommerce.',
					)
				);
				$this->end_controls_section();
			}
		}

		/**
		 * Sticky header.
		 */
		class QIL_Widget_Header extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_header';
			public function get_name() { return 'qil-header'; }
			public function get_title() { return 'Qimia Header'; }
			public function get_icon() { return 'eicon-nav-menu'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_header', array( 'label' => 'Navigation' ) );
				$repeater = new \Elementor\Repeater();
				$repeater->add_control( 'label', array( 'label' => 'Label (EN)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Shop' ) );
				$repeater->add_control( 'labelAr', array( 'label' => 'Label (AR)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'المتجر' ) );
				$repeater->add_control( 'url', array( 'label' => 'URL', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '#qil-match' ) );
				$this->add_control(
					'links',
					array(
						'label'       => 'Menu links',
						'type'        => \Elementor\Controls_Manager::REPEATER,
						'fields'      => $repeater->get_controls(),
						'title_field' => '{{{ label }}}',
						'default'     => array(),
					)
				);
				$this->end_controls_section();
			}
		}

		/**
		 * Hero portal.
		 */
		class QIL_Widget_Hero extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_hero';
			public function get_name() { return 'qil-hero'; }
			public function get_title() { return 'Qimia Hero / Portal'; }
			public function get_icon() { return 'eicon-banner'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_hero_copy', array( 'label' => 'Slogan' ) );
				$this->qil_add_text_pair( 'eyebrow', 'Eyebrow', 'QIMIA PORTAL', 'بوابة كيميا' );
				$this->qil_add_text_pair( 'lineOne', 'Headline line 1', 'STOP GUESSING', 'لا تخمين.' );
				$this->qil_add_text_pair( 'lineTwo', 'Optional middle headline', '', 'معرفة حقيقية.' );
				$this->qil_add_text_pair( 'lineThree', 'Headline final line (gradient)', 'START MY QIMIA.', 'كيميا الخاصة بك.' );
				$this->qil_add_text_pair( 'lead', 'Lead paragraph', 'Shop to join My Qimia — your routine, orders & cashback, together.', 'انضم إلى ماي كيميا مع شرائك — روتينك وطلباتك وكاش باكك معاً.', 'textarea' );
				$this->end_controls_section();

				$this->start_controls_section( 'qil_hero_orb', array( 'label' => 'Floating Q mark' ) );
				$this->add_control(
					'showOrb',
					array(
						'label'        => 'Show floating Q',
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'return_value' => 'yes',
						'default'      => 'yes',
					)
				);
				$this->add_control(
					'orbImage',
					array(
						'label'       => 'Q image URL',
						'type'        => \Elementor\Controls_Manager::TEXT,
						'default'     => '',
						'placeholder' => QIL_URL . 'assets/qimia-hero-orbit-v9.webp',
						'label_block' => true,
					)
				);
				$this->end_controls_section();
			}
		}

		/**
		 * Category rail.
		 */
		class QIL_Widget_Categories extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_category_rail';
			public function get_name() { return 'qil-categories'; }
			public function get_title() { return 'Qimia Category Rail'; }
			public function get_icon() { return 'eicon-posts-grid'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_categories', array( 'label' => 'Category tiles' ) );
				$repeater = new \Elementor\Repeater();
				$repeater->add_control( 'title', array( 'label' => 'Title (EN)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Protein' ) );
				$repeater->add_control( 'titleAr', array( 'label' => 'Title (AR)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'البروتين' ) );
				$repeater->add_control( 'kicker', array( 'label' => 'Kicker (EN)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Most shopped' ) );
				$repeater->add_control( 'kickerAr', array( 'label' => 'Kicker (AR)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'الأكثر طلباً' ) );
				$repeater->add_control( 'url', array( 'label' => 'URL', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ) );
				$repeater->add_control(
					'icon',
					array(
						'label'   => 'Icon',
						'type'    => \Elementor\Controls_Manager::SELECT,
						'default' => 'qil-i-muscle',
						'options' => array(
							'qil-i-muscle'   => 'Muscle',
							'qil-i-energy'   => 'Energy',
							'qil-i-spark'    => 'Spark',
							'qil-i-recovery' => 'Recovery',
							'qil-i-sleep'    => 'Sleep',
							'qil-i-wellness' => 'Wellness',
							'qil-i-beauty'   => 'Beauty',
							'qil-i-store'    => 'Store',
						),
					)
				);
				$this->add_control(
					'tiles',
					array(
						'label'       => 'Tiles',
						'type'        => \Elementor\Controls_Manager::REPEATER,
						'fields'      => $repeater->get_controls(),
						'title_field' => '{{{ title }}}',
						'default'     => array(),
					)
				);
				$this->end_controls_section();
			}
		}

		/**
		 * Goal engine and live grid.
		 */
		class QIL_Widget_Goals extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_goal_engine';
			public function get_name() { return 'qil-goals'; }
			public function get_title() { return 'Qimia Goal Engine'; }
			public function get_icon() { return 'eicon-filter'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_goals', array( 'label' => 'Goal engine' ) );
				$this->add_control(
					'qil_goals_note',
					array(
						'type' => \Elementor\Controls_Manager::RAW_HTML,
						'raw'  => 'Goal labels are translated by the Qimia Lab dictionary so English and Arabic always match the assistant. Products come live from WooCommerce.',
					)
				);
				$this->end_controls_section();
			}
		}

		/**
		 * The product layout, for WoodMart's Elementor single-product template.
		 *
		 * This is how the product page is built on this store: the theme renders
		 * a wd-builder-on layout containing an Elementor document, so the layout
		 * belongs in that document as a widget the merchant can move, not in a
		 * PHP hook fighting the builder for the same slot.
		 */
		class QIL_Widget_Product extends QIL_Elementor_Section_Widget {
			public function get_name() { return 'qil-product'; }
			public function get_title() { return 'Qimia Product'; }
			public function get_icon() { return 'eicon-product-info'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_product_note', array( 'label' => 'Product' ) );
				$this->add_control(
					'qil_product_help',
					array(
						'type' => \Elementor\Controls_Manager::RAW_HTML,
						'raw'  => 'Renders the gallery, purchase panel, live stock, variations, label facts and specifications for whichever product the page is showing. Drop it into the single-product template; it reads the product from the query, so the editor preview shows the template\'s preview product.',
					)
				);
				$this->end_controls_section();
			}
			protected function render() {
				if ( ! function_exists( 'qil_current_product_payload' ) ) {
					return;
				}
				// Not forced: on a product view the normal request already ships
				// the product and its rail, and forcing would swap that for the
				// whole catalogue.
				qil_enqueue_assets();
				$payload = qil_current_product_payload();
				$record  = isset( $payload['product'] ) ? $payload['product'] : null;
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) get_queried_object_id() ) : null;
				if ( ! $record || ! is_a( $product, 'WC_Product' ) ) {
					echo '<p style="padding:18px;border:1px dashed #c7d8db;border-radius:12px;color:#5c7279;font:14px system-ui">Qimia Product: no product in this query yet. Set a preview product for this template.</p>';
					return;
				}
				$this->qil_open_shell();
				echo qil_section_product( array( 'record' => $record, 'product' => $product ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$this->qil_close_shell();
			}
		}

		/**
		 * The rail of products beside the one being viewed.
		 */
		class QIL_Widget_Product_Related extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_product_related';
			public function get_name() { return 'qil-product-related'; }
			public function get_title() { return 'Qimia Related Products'; }
			public function get_icon() { return 'eicon-posts-carousel'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_related_note', array( 'label' => 'Related' ) );
				$this->add_control(
					'qil_related_help',
					array(
						'type' => \Elementor\Controls_Manager::RAW_HTML,
						'raw'  => 'Six across on desktop, two on a phone, the rest on the arrows. Falls back to best sellers when the product\'s category is thin.',
					)
				);
				$this->end_controls_section();
			}
		}

		/**
		 * Brand carousel.
		 */
		class QIL_Widget_Brands extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_brands';
			public function get_name() { return 'qil-brands'; }
			public function get_title() { return 'Qimia Brands'; }
			public function get_icon() { return 'eicon-carousel'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_brands_intro', array( 'label' => 'Intro' ) );
				$this->qil_add_text_pair( 'kicker', 'Kicker', 'TRUSTED BRANDS', 'علامات موثوقة' );
				$this->qil_add_text_pair( 'title', 'Title', 'The labels Qimia stocks.', 'العلامات التي يوفّرها كيميا.' );
				$this->add_control( 'qil_brands_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Logos come from the product_brand terms that have an image. Brands without one are skipped.' ) );
				$this->end_controls_section();
			}
		}

		/**
		 * Live collections.
		 */
		class QIL_Widget_Collections extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_collections';
			public function get_name() { return 'qil-collections'; }
			public function get_title() { return 'Qimia Collections'; }
			public function get_icon() { return 'eicon-products'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_collections_intro', array( 'label' => 'Intro' ) );
				$this->qil_add_text_pair( 'kicker', 'Kicker', 'SHOP THE ESSENTIALS', 'تسوّق حسب الأساسيات' );
				$this->qil_add_text_pair( 'title', 'Title', 'What Qimia shoppers choose most.', 'الأكثر طلباً الآن في كيميا.' );
				$this->qil_add_text_pair( 'lead', 'Lead', 'Real products, current prices and live stock—never a static list.', 'منتجات حقيقية وأسعار ومخزون مباشر—بدون قوائم ثابتة.', 'textarea' );
				$this->end_controls_section();

				$this->start_controls_section( 'qil_collections_blocks', array( 'label' => 'Collections' ) );
				$repeater = new \Elementor\Repeater();
				$repeater->add_control(
					'key',
					array(
						'label'   => 'Source',
						'type'    => \Elementor\Controls_Manager::SELECT,
						'default' => 'protein',
						'options' => array(
							'protein'      => 'Proteins',
							'creatine'     => 'Creatine',
							'best-sellers' => 'Best sellers',
						),
					)
				);
				$repeater->add_control( 'title', array( 'label' => 'Title (EN)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Proteins' ) );
				$repeater->add_control( 'titleAr', array( 'label' => 'Title (AR)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'البروتينات' ) );
				$repeater->add_control( 'kicker', array( 'label' => 'Kicker (EN)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ) );
				$repeater->add_control( 'kickerAr', array( 'label' => 'Kicker (AR)', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ) );
				$repeater->add_control( 'url', array( 'label' => 'View all URL', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '' ) );
				$this->add_control(
					'blocks',
					array(
						'label'       => 'Blocks',
						'type'        => \Elementor\Controls_Manager::REPEATER,
						'fields'      => $repeater->get_controls(),
						'title_field' => '{{{ title }}}',
						'default'     => array(),
					)
				);
				$this->end_controls_section();
			}
		}

		/**
		 * Simple, control-free sections.
		 */
		class QIL_Widget_AI extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_ai';
			public function get_name() { return 'qil-ai'; }
			public function get_title() { return 'Qimia AI Cockpit'; }
			public function get_icon() { return 'eicon-code-highlight'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_ai', array( 'label' => 'Qimia AI' ) );
				$this->add_control( 'qil_ai_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Copy in this section is translated by the Qimia Lab dictionary for English and Arabic.' ) );
				$this->end_controls_section();
			}
		}

		class QIL_Widget_Facts extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_facts';
			public function get_name() { return 'qil-facts'; }
			public function get_title() { return 'Qimia Product Intelligence'; }
			public function get_icon() { return 'eicon-info-box'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_facts', array( 'label' => 'Product intelligence' ) );
				$this->add_control( 'qil_facts_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Four fixed trust statements, translated automatically.' ) );
				$this->end_controls_section();
			}
		}

		class QIL_Widget_Compare extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_compare';
			public function get_name() { return 'qil-compare'; }
			public function get_title() { return 'Qimia Compare Lab'; }
			public function get_icon() { return 'eicon-table'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_compare', array( 'label' => 'Compare Lab' ) );
				$this->add_control( 'qil_compare_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Uses the live two-product comparison and Qimia AI\'s native cache.' ) );
				$this->end_controls_section();
			}
		}

		class QIL_Widget_Method extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_method';
			public function get_name() { return 'qil-method'; }
			public function get_title() { return 'Qimia Three Steps'; }
			public function get_icon() { return 'eicon-number-field'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_method', array( 'label' => 'Three steps' ) );
				$this->add_control( 'qil_method_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Goal, logic, assistant — translated automatically.' ) );
				$this->end_controls_section();
			}
		}

		class QIL_Widget_Routine extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_routine';
			public function get_name() { return 'qil-routine'; }
			public function get_title() { return 'Qimia Routine Builder'; }
			public function get_icon() { return 'eicon-checkbox'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_routine', array( 'label' => 'Routine builder' ) );
				$this->add_control( 'qil_routine_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Shows real Qimia products and opens the live assistant.' ) );
				$this->end_controls_section();
			}
		}

		class QIL_Widget_Trust extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_trust';
			public function get_name() { return 'qil-trust'; }
			public function get_title() { return 'Qimia Trust Grid'; }
			public function get_icon() { return 'eicon-shield'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_trust', array( 'label' => 'Trust grid' ) );
				$this->add_control( 'qil_trust_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Four Qimia standards, translated automatically.' ) );
				$this->end_controls_section();
			}
		}

		class QIL_Widget_Footer extends QIL_Elementor_Section_Widget {
			protected $qil_callback = 'qil_section_footer';
			public function get_name() { return 'qil-footer'; }
			public function get_title() { return 'Qimia Footer'; }
			public function get_icon() { return 'eicon-footer'; }
			protected function register_controls() {
				$this->start_controls_section( 'qil_footer', array( 'label' => 'Footer' ) );
				$this->add_control( 'qil_footer_note', array( 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Directory links follow the active language route automatically.' ) );
				$this->end_controls_section();
			}
		}
	}

	$widgets = array(
		'QIL_Widget_Topbar',
		'QIL_Widget_Header',
		'QIL_Widget_Hero',
		'QIL_Widget_Categories',
		'QIL_Widget_Goals',
		'QIL_Widget_Collections',
		'QIL_Widget_Brands',
		'QIL_Widget_Product',
		'QIL_Widget_Product_Related',
		'QIL_Widget_AI',
		'QIL_Widget_Facts',
		'QIL_Widget_Compare',
		'QIL_Widget_Method',
		'QIL_Widget_Routine',
		'QIL_Widget_Trust',
		'QIL_Widget_Footer',
	);

	$manager = $widgets_manager;
	if ( ! is_object( $manager ) && class_exists( '\Elementor\Plugin' ) ) {
		$manager = \Elementor\Plugin::instance()->widgets_manager;
	}
	if ( ! is_object( $manager ) ) {
		return;
	}

	foreach ( $widgets as $widget_class ) {
		if ( ! class_exists( $widget_class ) ) {
			continue;
		}
		if ( method_exists( $manager, 'register' ) ) {
			$manager->register( new $widget_class() );
		} elseif ( method_exists( $manager, 'register_widget_type' ) ) {
			$manager->register_widget_type( new $widget_class() );
		}
	}
}
add_action( 'elementor/widgets/register', 'qil_elementor_register_widgets' );
add_action( 'elementor/widgets/widgets_registered', 'qil_elementor_register_widgets' );

/**
 * Load the lab stylesheet inside the Elementor editor and preview.
 */
function qil_elementor_editor_assets() {
	if ( ! qil_experience_enabled() ) {
		return;
	}

	qil_enqueue_assets( true );
}
add_action( 'elementor/editor/after_enqueue_styles', 'qil_elementor_editor_assets' );
add_action( 'elementor/preview/enqueue_styles', 'qil_elementor_editor_assets' );

/**
 * Keep lab assets present on any Elementor page that uses a Qimia Lab widget.
 */
function qil_elementor_frontend_assets() {
	if ( ! qil_experience_enabled() || ! did_action( 'elementor/loaded' ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}

	$document = \Elementor\Plugin::instance()->documents->get( $post_id );
	if ( ! $document || ! $document->is_built_with_elementor() ) {
		return;
	}

	$data = $document->get_elements_data();
	if ( ! is_array( $data ) ) {
		return;
	}

	$json = wp_json_encode( $data );
	if ( is_string( $json ) && false !== strpos( $json, '"qil-' ) ) {
		qil_enqueue_assets( true );
	}
}
add_action( 'wp_enqueue_scripts', 'qil_elementor_frontend_assets', 5 );
