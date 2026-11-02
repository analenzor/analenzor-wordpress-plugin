<?php
/**
 * Plugin Name: Analenzor User Behavior Analytics
 * Description: Connect your WordPress website to Analenzor for user behavior analytics and session replay.
 * Version: 1.0.12
 * Author: Analenzor Team
 * Author URI: https://analenzor.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: analenzor-user-behavior-analytics
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class Analenzor_User_behavior_Analytics_Plugin {
    const VERSION = '1.0.10';

    public function __construct() {
		add_action('admin_head', array($this, 'admin_menu_style'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tracking'));
		add_action('wp_ajax_analenzor_commerce_state', array($this, 'commerce_state'));
		add_action('wp_ajax_nopriv_analenzor_commerce_state', array($this, 'commerce_state'));
    }
	public function admin_menu_style() {
		?>
		<style>
			#adminmenu #toplevel_page_analenzor-settings > a.menu-top {
				background: #1d4ed8 !important;
				color: #fff !important;
			}

			#adminmenu #toplevel_page_analenzor-settings > a.menu-top:hover,
			#adminmenu #toplevel_page_analenzor-settings > a.menu-top:focus,
			#adminmenu #toplevel_page_analenzor-settings.current > a.menu-top,
			#adminmenu #toplevel_page_analenzor-settings.wp-has-current-submenu > a.menu-top {
				background: #1e40af !important;
				color: #fff !important;
			}

			#adminmenu #toplevel_page_analenzor-settings .wp-menu-image::before {
				color: #fff !important;
			}

			#adminmenu #toplevel_page_analenzor-settings > a.menu-top:focus {
				box-shadow: inset 0 0 0 2px #93c5fd !important;
			}
		</style>
		<?php
	}


    public function add_settings_page() {
        add_menu_page(
            __('Analenzor Settings', 'analenzor-user-behavior-analytics'),
            __('آنالنزور | تحلیل رفتار', 'analenzor-user-behavior-analytics'),
            'manage_options',
            'analenzor-settings',
            array($this, 'render_settings_page'),
            'dashicons-chart-area',
            90
        );
    }

    public function register_settings() {
        register_setting('analenzor_options', 'analenzor_enabled', array(
            'type' => 'boolean', 'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ));
        register_setting('analenzor_options', 'analenzor_tracking_id', array(
            'type' => 'string', 'default' => '',
            'sanitize_callback' => array($this, 'sanitize_tracking_id'),
        ));
    }

    public function sanitize_tracking_id($value) {
        if (!is_scalar($value)) {
            add_settings_error('analenzor_tracking_id', 'invalid_tracking_id', __('Please enter a valid tracking ID.', 'analenzor-user-behavior-analytics'));
            return (string) get_option('analenzor_tracking_id', '');
        }
        return sanitize_text_field((string) $value);
    }

    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_analenzor-settings' !== $hook) {
            return;
        }
        wp_enqueue_style('analenzor-admin', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), self::VERSION);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $tracking_id = (string) get_option('analenzor_tracking_id', '');
        $enabled = (bool) get_option('analenzor_enabled', false);
        $configured = $enabled && '' !== trim($tracking_id);
        $fa = 0 === strpos(get_user_locale(), 'fa');
 
        $copy = function ($english, $persian) use ($fa) {
            return $fa ? $persian : $english;
        };
        ?>
        <div class="wrap analenzor-app" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
            <header class="az-header">
                <div class="az-brand"><span class="az-brand-icon dashicons dashicons-chart-area" aria-hidden="true"></span><div><strong>Analenzor</strong><span>USER BEHAVIOR ANALYTICS</span></div></div>
                <a class="az-link" href="https://dashboard.analenzor.com" target="_blank" rel="noopener noreferrer"><?php echo esc_html($copy(__('Open dashboard', 'analenzor-user-behavior-analytics'), 'ورود به داشبورد')); ?> ↗</a>
            </header>
            <?php settings_errors(); ?>
            <section class="az-hero">
                <div class="az-eyebrow">ANALENZOR / WORDPRESS</div>
                <h1><?php echo esc_html($copy(__('Understand the journey. Improve the experience.', 'analenzor-user-behavior-analytics'), 'رفتار کاربران را بشناسید؛ تجربه بهتری بسازید.')); ?></h1>
                <p><?php echo esc_html($copy(__('Connect your website to explore visitor interactions and session replays in your Analenzor dashboard.', 'analenzor-user-behavior-analytics'), 'وب‌سایت خود را متصل کنید و تعامل کاربران و بازپخش نشست‌ها را در داشبورد آنالنزور بررسی کنید.')); ?></p>
                <span class="az-status <?php echo $configured ? 'is-on' : ''; ?>"><i aria-hidden="true"></i><?php echo esc_html($configured ? $copy(__('Tracking configured', 'analenzor-user-behavior-analytics'), 'رهگیری پیکربندی شده') : $copy(__('Tracking inactive', 'analenzor-user-behavior-analytics'), 'رهگیری غیرفعال')); ?></span>
            </section>
            <div class="az-layout">
                <section class="az-card az-settings">
                    <div class="az-card-heading"><span class="az-step">01</span><div><h2><?php echo esc_html($copy(__('Website connection', 'analenzor-user-behavior-analytics'), 'اتصال وب‌سایت')); ?></h2><p><?php echo esc_html($copy(__('Manage tracking for this WordPress website.', 'analenzor-user-behavior-analytics'), 'تنظیمات رهگیری این وب‌سایت وردپرسی را مدیریت کنید.')); ?></p></div></div>
                    <form action="options.php" method="post">
                        <?php settings_fields('analenzor_options'); ?>
                        <div class="az-toggle-row"><div><label for="az-enabled"><?php echo esc_html($copy(__('Enable tracking', 'analenzor-user-behavior-analytics'), 'فعال‌سازی رهگیری')); ?></label><p><?php echo esc_html($copy(__('Load the Analenzor script on public website pages.', 'analenzor-user-behavior-analytics'), 'اسکریپت آنالنزور در صفحات عمومی سایت بارگذاری شود.')); ?></p></div><div class="az-switch"><input type="hidden" name="analenzor_enabled" value="0"><input id="az-enabled" type="checkbox" name="analenzor_enabled" value="1" <?php checked($enabled); ?>><span aria-hidden="true"></span></div></div>
                        <div class="az-field"><label for="az-tracking-id"><?php echo esc_html($copy(__('Tracking ID', 'analenzor-user-behavior-analytics'), 'شناسه رهگیری')); ?></label><input id="az-tracking-id" type="text" name="analenzor_tracking_id" value="<?php echo esc_attr($tracking_id); ?>" dir="ltr" autocomplete="off" spellcheck="false" aria-describedby="az-id-help"><p id="az-id-help"><?php echo esc_html($copy(__('Copy the tracking ID from your website settings in the Analenzor dashboard. Enter the ID only, not a script or URL.', 'analenzor-user-behavior-analytics'), 'شناسه را از تنظیمات وب‌سایت در داشبورد آنالنزور کپی کنید؛ فقط شناسه را وارد کنید، نه کد اسکریپت یا آدرس.')); ?></p></div>
                        <div class="az-privacy"><span class="dashicons dashicons-shield" aria-hidden="true"></span><p><?php echo esc_html($copy(__('Enabling tracking loads an external service. Review your privacy notice and obtain visitor consent where required before enabling it.', 'analenzor-user-behavior-analytics'), 'با فعال‌سازی رهگیری، سرویس خارجی بارگذاری می‌شود. پیش از فعال‌سازی، اطلاعیه حریم خصوصی و رضایت بازدیدکنندگان را مطابق الزامات مربوط به سایت خود بررسی کنید.')); ?></p></div>
                        <?php submit_button($copy(__('Save settings', 'analenzor-user-behavior-analytics'), 'ذخیره تنظیمات'), 'primary az-save'); ?>
                    </form>
                </section>
                <aside class="az-sidebar">
                    <section class="az-card"><div class="az-eyebrow">GET STARTED</div><h2><?php echo esc_html($copy(__('From connection to insight', 'analenzor-user-behavior-analytics'), 'از اتصال تا شناخت کاربر')); ?></h2><ol class="az-guide"><li><?php echo esc_html($copy(__('Create or select your website in Analenzor.', 'analenzor-user-behavior-analytics'), 'وب‌سایت خود را در آنالنزور بسازید یا انتخاب کنید.')); ?></li><li><?php echo esc_html($copy(__('Paste your tracking ID and enable tracking.', 'analenzor-user-behavior-analytics'), 'شناسه را وارد و رهگیری را فعال کنید.')); ?></li><li><?php echo esc_html($copy(__('Save settings, then explore your dashboard.', 'analenzor-user-behavior-analytics'), 'تنظیمات را ذخیره و داشبورد را بررسی کنید.')); ?></li></ol><a class="az-secondary" href="https://dashboard.analenzor.com" target="_blank" rel="noopener noreferrer"><?php echo esc_html($copy(__('Go to Analenzor', 'analenzor-user-behavior-analytics'), 'رفتن به آنالنزور')); ?> ↗</a></section>
                    <section class="az-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><p><?php echo esc_html($copy(__('This page configures tracking; it does not display live analytics or verify server connectivity. Reports are available in your Analenzor dashboard.', 'analenzor-user-behavior-analytics'), 'این صفحه برای تنظیم رهگیری است و اتصال سرور یا آمار زنده را تأیید نمی‌کند. گزارش‌ها در داشبورد آنالنزور در دسترس‌اند.')); ?></p></section>
                </aside>
            </div>
            <footer class="az-footer"><span>Analenzor · v<?php echo esc_html(self::VERSION); ?></span><div><a href="https://dashboard.analenzor.com/privacy" target="_blank" rel="noopener noreferrer"><?php echo esc_html($copy(__('Privacy', 'analenzor-user-behavior-analytics'), 'حریم خصوصی')); ?></a><a href="https://dashboard.analenzor.com/terms" target="_blank" rel="noopener noreferrer"><?php echo esc_html($copy(__('Terms', 'analenzor-user-behavior-analytics'), 'شرایط استفاده')); ?></a></div></footer>
        </div>
        <?php
    }

    public function enqueue_tracking() {
        if (!get_option('analenzor_enabled', false)) {
            return;
        }
        $tracking_id = trim((string) get_option('analenzor_tracking_id', ''));
        if ('' === $tracking_id) {
            return;
        }
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $id_json = wp_json_encode($tracking_id, $flags);
        $url_json = wp_json_encode('https://dashboard.analenzor.com/api/script/?id=' . rawurlencode($tracking_id), $flags);
        $inline = '(function(){var d=new Date();var v=[d.getFullYear(),d.getMonth(),d.getDate(),d.getHours()].join("-");window.analenzor_unique_id=' . $id_json . ';var s=document.createElement("script");s.async=true;s.crossOrigin="anonymous";s.src=' . $url_json . '+"&v="+encodeURIComponent(v);document.head.appendChild(s);}());';
        
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $roles = array_values((array) $user->roles);
            $identity = $user->user_login . (isset($roles[0]) ? ' (' . $roles[0] . ')' : '');
            $inline = 'window.analenzor_custom_user=' . wp_json_encode($identity, $flags) . ';' . $inline;
        }
		if (class_exists('WooCommerce')) {
			$commerce_config = array(
				'endpoint' => admin_url('admin-ajax.php', 'relative'),
				'storageKey' => 'analenzor_c',
				'product' => function_exists('is_product') && is_product(),
				'checkout' => function_exists('is_checkout')
					&& is_checkout()
					&& !is_wc_endpoint_url('order-received')
					&& !is_wc_endpoint_url('order-pay'),
				'orderId' => absint(
					get_query_var('order-received')
					?: get_query_var('order-pay')
				),
				'orderKey' => isset($_GET['key'])
					? wc_clean(wp_unslash($_GET['key']))
					: '',
			);

			wp_enqueue_script(
				'analenzor-commerce',
				plugin_dir_url(__FILE__) . 'assets/js/commerce.js',
				array('jquery'),
				self::VERSION,
				false
			);

			wp_add_inline_script(
				'analenzor-commerce',
				'window.analenzorCommerceConfig='
					. wp_json_encode($commerce_config, $flags)
					. ';',
				'before'
			);
		}


        wp_register_script('analenzor-tracking', false, array(), self::VERSION, false);
        wp_enqueue_script('analenzor-tracking');
        wp_add_inline_script('analenzor-tracking', $inline, 'after');
    }
	public function commerce_state() {
		nocache_headers();

		if (
			!get_option('analenzor_enabled', false) ||
			'' === trim((string) get_option('analenzor_tracking_id', '')) ||
			!function_exists('WC') ||
			!function_exists('wc_get_order')
		) {
			wp_send_json_error(null, 404);
		}

	
		$source = get_http_origin();

		if (!$source) {
			$source = wp_get_referer();
		}

		$source_host = $source ? wp_parse_url($source, PHP_URL_HOST) : '';
		$site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);

		if (
			!$source_host ||
			!$site_host ||
			strtolower($source_host) !== strtolower($site_host)
		) {
			wp_send_json_error(null, 403);
		}

		if (null === WC()->cart && function_exists('wc_load_cart')) {
			wc_load_cart();
		}

		$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		$cart_items = array();

		if (WC()->cart) {
			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
				$cart_items[] = array(
					'key' => (string) $cart_item_key,
					'quantity' => (int) $cart_item['quantity'],
				);
			}
		}
		$result = array(
		
			'visitor' => is_user_logged_in()
				? 'user:' . get_current_user_id()
				: 'guest',
			'cart_count' => (int) $count,
			'cart_empty' => 0 === (int) $count,
			'cart_hash' => WC()->cart ? WC()->cart->get_cart_hash() : '',
			'cart_items' => $cart_items,
			'order_id' => null,
			'order_status' => null,
			'order_paid' => false,
			'order_draft' => false,
		);

		$order_id = isset($_POST['order_id'])
			? absint($_POST['order_id'])
			: 0;

		$order_key = isset($_POST['order_key'])
			? wc_clean(wp_unslash($_POST['order_key']))
			: '';



		$session_order_id = WC()->session
			? absint(WC()->session->get('order_awaiting_payment'))
			: 0;

		if (!$order_id && $session_order_id) {
			$order_id = $session_order_id;
		}
		if ($order_id) {
			$order = wc_get_order($order_id);

			if ($order) {
				$owns_order = is_user_logged_in()
					&& (int) $order->get_customer_id() === get_current_user_id();

				$valid_key = '' !== $order_key
					&& hash_equals((string) $order->get_order_key(), $order_key);

				$session_access = $session_order_id > 0
					&& $session_order_id === $order->get_id()
					&& (
						0 === (int) $order->get_customer_id()
						|| $owns_order
					);

				if ($owns_order || $valid_key || $session_access) {

					$result['order_id'] = $order->get_id();
					$result['order_status'] = $order->get_status();
					$result['order_paid'] = (bool) $order->get_date_paid();
					$result['order_draft'] = $order->has_status('checkout-draft');
				}
			}
		}

		wp_send_json_success($result);
	}

}

new Analenzor_User_behavior_Analytics_Plugin();
