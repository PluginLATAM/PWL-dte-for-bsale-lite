<?php
namespace PwlDte\Core;

defined('ABSPATH') || exit;

/** Plugin activation: creates tables and seeds default options. */
class Activator
{
	public static function activate(): void
	{
		self::create_tables();
		self::set_default_options();
		flush_rewrite_rules();
	}

	/** dbDelta is idempotent for new tables — safe to call on every upgrade. */
	private static function create_tables(): void
	{
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$docs_table    = $wpdb->prefix . 'pwl_dte_documents';
		$webhook_table = $wpdb->prefix . 'pwl_dte_webhook_events';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta("CREATE TABLE IF NOT EXISTS {$docs_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			bsale_document_id INT(11) DEFAULT NULL,
			document_number INT(11) DEFAULT NULL,
			document_type VARCHAR(50) NOT NULL,
			code_sii INT(11) NOT NULL,
			office_id INT(11) DEFAULT NULL,
			folio VARCHAR(100) DEFAULT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'pending',
			error_message TEXT DEFAULT NULL,
			pdf_url VARCHAR(500) DEFAULT NULL,
			public_url VARCHAR(500) DEFAULT NULL,
			token VARCHAR(255) DEFAULT NULL,
			attempts INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY bsale_document_id (bsale_document_id),
			KEY status (status)
		) {$charset};");

		dbDelta("CREATE TABLE IF NOT EXISTS {$webhook_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			cpn_id INT(11) DEFAULT NULL,
			topic VARCHAR(50) NOT NULL DEFAULT '',
			action VARCHAR(50) NOT NULL DEFAULT '',
			resource_id VARCHAR(100) DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'processed',
			message TEXT DEFAULT NULL,
			payload LONGTEXT DEFAULT NULL,
			send_ts BIGINT(20) DEFAULT NULL,
			received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY topic (topic),
			KEY status (status),
			KEY received_at (received_at),
			KEY send_ts (send_ts)
		) {$charset};");

		update_option('pwl_dte_db_version', PWL_DTE_VERSION);
	}

	/** Hooked to 'init' — creates missing tables or adds columns from later versions. */
	public static function maybe_upgrade(): void
	{
		global $wpdb;
		$webhook_table = $wpdb->prefix . 'pwl_dte_webhook_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $webhook_table)) !== $webhook_table) {
			self::create_tables();
			return;
		}

		// dbDelta does not add columns to existing tables; use ALTER TABLE instead.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if (!$wpdb->get_var("SHOW COLUMNS FROM {$webhook_table} LIKE 'send_ts'")) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query("ALTER TABLE {$webhook_table} ADD COLUMN cpn_id INT(11) DEFAULT NULL AFTER id, ADD COLUMN send_ts BIGINT(20) DEFAULT NULL AFTER payload, ADD INDEX idx_send_ts (send_ts)");
			return;
		}

		// Migrate send_ts from INT(11) to BIGINT(20) to prevent 2038 overflow.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$col = $wpdb->get_row("SHOW COLUMNS FROM {$webhook_table} LIKE 'send_ts'");
		if ($col && stripos($col->Type, 'bigint') === false) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query("ALTER TABLE {$webhook_table} MODIFY COLUMN send_ts BIGINT(20) DEFAULT NULL");
		}
	}

	private static function set_default_options(): void
	{
		$defaults = [
			'pwl_dte_api_token'           => '',
			'pwl_dte_office_id'           => '1',
			'pwl_dte_stock_office_id'     => '',
			'pwl_dte_office_map'          => '{}',
			'pwl_dte_price_list_id'       => '',
			'pwl_dte_default_doc_type'    => 'boleta',
			'pwl_dte_auto_declare_sii'    => '1',
			'pwl_dte_auto_send_email'     => '1',
			'pwl_dte_auto_dispatch'       => '1',
			'pwl_dte_enable_stock_sync'   => '0',
			'pwl_dte_stock_sync_interval' => 'hourly',
			'pwl_dte_enable_webhooks'     => '0',
			'pwl_dte_webhook_secret'      => wp_generate_password(32, false),
		];

		foreach ($defaults as $key => $value) {
			if (false === get_option($key)) {
				add_option($key, $value);
			}
		}
	}
}
