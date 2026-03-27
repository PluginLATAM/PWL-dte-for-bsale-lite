<?php
namespace PwlDte\Core;

defined('ABSPATH') || exit;

/** Main plugin loader — singleton. */
final class Plugin
{
	private static ?self $instance = null;

	public static function run(): void
	{
		if (null !== self::$instance) {
			return;
		}
		self::$instance = new self();
		self::$instance->init();
	}

	private function init(): void
	{
		Activator::maybe_upgrade();

		add_action('init',           [$this, 'load_textdomain']);
		add_filter('cron_schedules', [$this, 'register_cron_schedules']);

		foreach ([
			\PwlDte\Admin\Admin::class,
			\PwlDte\Admin\LogsPage::class,
			\PwlDte\Admin\OrderMetabox::class,
			\PwlDte\Integration\CheckoutFields::class,
			\PwlDte\Integration\DTEShortcode::class,
		] as $class) {
			$this->boot($class);
		}

		$edition_classes = PWL_DTE_EDITION === 'pro'
			? [
				\PwlDte\Integration\Pro\DocumentEngine::class,
				\PwlDte\Integration\Pro\StockSync::class,
				\PwlDte\Integration\Pro\WebhookHandler::class,
				\PwlDte\Integration\Pro\CreditNoteEngine::class,
				\PwlDte\Integration\Pro\ProductImporter::class,
				\PwlDte\Admin\WebhookDebugPage::class,
			]
			: [
				\PwlDte\Integration\DocumentEngine::class,
				\PwlDte\Integration\StockSync::class,
			];

		foreach ($edition_classes as $class) {
			$this->boot($class);
		}
	}

	/** Instantiate $class and call register_hooks() if present. Silently skips missing classes. */
	private function boot(string $class): void
	{
		if (!class_exists($class)) {
			return;
		}
		$instance = new $class();
		if (method_exists($instance, 'register_hooks')) {
			$instance->register_hooks();
		}
	}

	/** Re-registers core cron schedules in case a third-party plugin stripped them. */
	public function register_cron_schedules(array $schedules): array
	{
		$defaults = [
			'hourly'     => [HOUR_IN_SECONDS,      'Once Hourly'],
			'twicedaily' => [12 * HOUR_IN_SECONDS, 'Twice Daily'],
			'daily'      => [DAY_IN_SECONDS,        'Once Daily'],
		];

		foreach ($defaults as $name => [$interval, $display]) {
			if (!isset($schedules[$name])) {
				$schedules[$name] = ['interval' => $interval, 'display' => $display];
			}
		}

		return $schedules;
	}

	/** WP 4.6+ loads translations automatically for plugins on wordpress.org. */
	public function load_textdomain(): void {}
}
