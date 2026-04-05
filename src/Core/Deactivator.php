<?php
namespace PwlDte\Core;

defined('ABSPATH') || exit;

/** Plugin deactivation: clears scheduled crons and flushes rewrite rules. */
class Deactivator
{
	public static function deactivate(): void
	{
		wp_clear_scheduled_hook('pwl_dte_stock_sync_cron');
		flush_rewrite_rules();
	}
}
