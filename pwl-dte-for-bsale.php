<?php
/**
 * Plugin Name:       PWL DTE for Bsale
 * Plugin URI:        https://github.com/PluginLATAM/PWL-dte-for-bsale-lite
 * Source Code:       https://github.com/PluginLATAM/PWL-dte-for-bsale-lite
 * Description:       Integración WooCommerce con Bsale para facturación electrónica chilena
 * Version:           2.0.6
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
 * Author:            PluginLATAM
 * Author URI:        https://github.com/PluginLATAM
 * Text Domain:       pwl-dte-for-bsale
 * Domain Path:       /languages
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || exit;

if (in_array('pwl-dte-for-bsale-pro/pwl-dte-for-bsale-pro.php', (array) get_option('active_plugins', []), true)) {
	return;
}

define('PWL_DTE_VERSION', '2.0.6');
define('PWL_DTE_EDITION', 'lite'); // injected by build.js
define('PWL_DTE_FILE',    __FILE__);
define('PWL_DTE_DIR',     plugin_dir_path(__FILE__));
define('PWL_DTE_URL',     plugin_dir_url(__FILE__));
define('PWL_DTE_PRO_URL', 'https://github.com/PluginLATAM/PWL-dte-for-bsale-lite');

// ── Conflict check: if both editions are active, user must resolve manually ──
if (PWL_DTE_EDITION === 'pro') {
	$pwl_dte_lite_active = in_array(
		'pwl-dte-for-bsale/pwl-dte-for-bsale.php',
		(array) get_option('active_plugins', []),
		true,
	);
	if ($pwl_dte_lite_active) {
		add_action('admin_notices', static function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__('PWL DTE for Bsale Pro cannot run while PWL DTE for Bsale Lite is active. Please deactivate Lite manually and keep only one edition active.', 'pwl-dte-for-bsale')
				. '</p></div>';
		});
		return;
	}
}

if (!class_exists('WooCommerce')) {
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
