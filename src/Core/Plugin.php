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

		/*
		 * load_plugin_textdomain must not be scheduled from another plugins_loaded:0 callback
		 * (Plugin::run runs inside plugins_loaded from the bootstrap file); a nested same-priority
		 * hook may never run this request. Load immediately.
		 */
		add_filter('load_textdomain_mofile', [$this, 'fallback_spanish_mofile'], 10, 2);
		$this->load_plugin_textdomain();

		add_filter('cron_schedules', [$this, 'register_cron_schedules']);

		if (PWL_DTE_EDITION === 'pro') {
			$this->boot(\PwlDte\Integration\Pro\LicenseBootstrap::class);
			if (class_exists(\PwlDte\Integration\Pro\ProFeatures::class)) {
				\PwlDte\Integration\Pro\ProFeatures::silence_stale_pro_runtime();
			}
		}

		foreach ([
			\PwlDte\Admin\Admin::class,
			\PwlDte\Admin\LogsPage::class,
			\PwlDte\Admin\OrderMetabox::class,
			\PwlDte\Integration\CheckoutFields::class,
			\PwlDte\Integration\DTEShortcode::class,
		] as $class) {
			$this->boot($class);
		}

		$use_pro_integrations = PWL_DTE_EDITION === 'pro'
			&& class_exists(\PwlDte\Integration\Pro\ProFeatures::class)
			&& \PwlDte\Integration\Pro\ProFeatures::is_pro_license_active();

		$edition_classes = $use_pro_integrations
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

	/**
	 * If WordPress locale is Spanish (e.g. es_ES) but no matching .mo exists, use the bundled es_CL catalog.
	 */
	public function fallback_spanish_mofile($mofile, string $domain): mixed
	{
		if ($domain !== 'pwl-dte-for-bsale') {
			return $mofile;
		}
		if (is_string($mofile) && is_readable($mofile)) {
			return $mofile;
		}
		$locale = determine_locale();
		if (!is_string($locale) || !preg_match('/^es(?:$|[_-])/', $locale)) {
			return $mofile;
		}
		$fallback = \PWL_DTE_DIR . 'languages/pwl-dte-for-bsale-es_CL.mo';
		return is_readable($fallback) ? $fallback : $mofile;
	}

	/** Loads bundled translations from /languages (merges with language packs from translate.wordpress.org). */
	public function load_plugin_textdomain(): void
	{
		load_plugin_textdomain(
			'pwl-dte-for-bsale',
			false,
			dirname(plugin_basename(\PWL_DTE_FILE)) . '/languages'
		);
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

}
