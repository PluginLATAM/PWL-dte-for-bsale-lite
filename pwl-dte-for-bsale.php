<?php
/**
 * Plugin Name:       PWL DTE for Bsale
 * Plugin URI:        https://github.com/PluginLATAM/pwl-dte-for-bsale
 * Description:       Integración WooCommerce con Bsale para facturación electrónica chilena
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            UserDOMP
 * Text Domain:       pwl-dte-for-bsale
 * Domain Path:       /languages
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || exit;

if (in_array('pwl-dte-for-bsale-pro/pwl-dte-for-bsale-pro.php', (array) get_option('active_plugins', []), true)) {
	return;
}

define('PWL_DTE_VERSION', '2.0.0');
define('PWL_DTE_EDITION', 'lite'); // injected by build.js
define('PWL_DTE_FILE',    __FILE__);
define('PWL_DTE_DIR',     plugin_dir_path(__FILE__));
define('PWL_DTE_URL',     plugin_dir_url(__FILE__));
define('PWL_DTE_PRO_URL', 'https://github.com/PluginLATAM/pwl-dte-for-bsale');

// ── Conflict check: Pro deactivates Lite when both are simultaneously active ──
if (PWL_DTE_EDITION === 'pro') {
	add_action('admin_init', static function () {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if (!is_plugin_active('pwl-dte-for-bsale/pwl-dte-for-bsale.php')) return;
		deactivate_plugins('pwl-dte-for-bsale/pwl-dte-for-bsale.php');
		set_transient('pwl_dte_lite_deactivated', true, 30);
	});
	add_action('admin_notices', static function () {
		if (!get_transient('pwl_dte_lite_deactivated')) return;
		delete_transient('pwl_dte_lite_deactivated');
		echo '<div class="notice notice-warning is-dismissible"><p>'
			. esc_html__('PWL DTE (Lite) was deactivated because PWL DTE Pro is already active.', 'pwl-dte-for-bsale')
			. '</p></div>';
	});
}

if (!in_array(
	'woocommerce/woocommerce.php',
	apply_filters('active_plugins', get_option('active_plugins')),
	true,
)) {
	add_action('admin_notices', static function () {
		echo '<div class="error"><p><strong>PWL DTE for Bsale</strong> '
			. esc_html__('requires WooCommerce to be installed and active.', 'pwl-dte-for-bsale')
			. '</p></div>';
	});
	return;
}

require_once PWL_DTE_DIR . 'vendor/autoload.php';

register_activation_hook(__FILE__,   ['PwlDte\Core\Activator',   'activate']);
register_deactivation_hook(__FILE__, ['PwlDte\Core\Deactivator', 'deactivate']);

PwlDte\Core\Plugin::run();
