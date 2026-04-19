<?php
// src/Admin/Settings.php
namespace PwlDte\Admin;

defined("ABSPATH") || exit();

use UserDOMP\WpAdminDS\Components;

/**
 * Settings page with tabs: Connection, Documents, Sync, (Offices — Pro only), Advanced.
 *
 * Each tab uses its own option_group so saving one tab never wipes another tab's options.
 * When all settings share a single option_group, WordPress's options.php handler calls
 * settings_fields() which covers ALL registered options in that group. When you save the
 * "Documents" tab, the form only contains documents fields; WordPress sees the token key
 * is registered in the group but missing from POST and saves an empty string, wiping the token.
 */
class Settings extends BasePage
{
	/** Pro build + valid license (tabs, options, AJAX that are Pro-only). */
	private function edition_has_pro_features_unlocked(): bool
	{
		if (PWL_DTE_EDITION !== "pro") {
			return false;
		}
		return class_exists(\PwlDte\Integration\Pro\ProFeatures::class)
			&& \PwlDte\Integration\Pro\ProFeatures::is_pro_license_active();
	}

	private array $option_groups = [
		"connection" => "pwl_dte_settings_connection",
		"documents"  => "pwl_dte_settings_documents",
		"sync"       => "pwl_dte_settings_sync",
		"offices"    => "pwl_dte_settings_offices",
		"advanced"   => "pwl_dte_settings_advanced",
	];

	private string $page_slug = "pwl-dte-for-bsale-settings";

	public function __construct()
	{
		add_action("admin_init", [$this, "register_settings"]);
		add_action("admin_post_pwl_dte_test_connection", [$this, "test_connection"]);
		add_action("admin_enqueue_scripts", [$this, "enqueue_scripts"]);

		if ($this->edition_has_pro_features_unlocked()) {
			add_action("wp_ajax_pwl_dte_get_offices", [$this, "ajax_get_offices"]);
		}
	}

	public function enqueue_scripts(string $hook): void
	{
		if (!str_contains($hook, 'pwl-dte-for-bsale-settings') || !$this->edition_has_pro_features_unlocked()) {
			return;
		}

		wp_enqueue_script(
			'pwl-dte-for-bsale-settings',
			PWL_DTE_URL . "src/Admin/js/settings-page.js",
			['jquery'],
			PWL_DTE_VERSION,
			true,
		);
		wp_localize_script('pwl-dte-for-bsale-settings', 'pwlDteSettings', [
			'nonce'  => wp_create_nonce('pwl_dte_api_fetch'),
			'labels' => [
				'loading'         => __('Loading…', 'pwl-dte-for-bsale'),
				'errorPrefix'     => __('Error: ', 'pwl-dte-for-bsale'),
				'unknownError'    => __('unknown error', 'pwl-dte-for-bsale'),
				'loaded'          => __('Offices loaded.', 'pwl-dte-for-bsale'),
				'connectionError' => __('Connection error.', 'pwl-dte-for-bsale'),
			],
		]);
	}

	// -------------------------------------------------------------------------
	// Settings registration — one group per tab
	// -------------------------------------------------------------------------

	public function register_settings(): void
	{
		$g = $this->option_groups;

		// ── Connection ────────────────────────────────────────────────────────
		register_setting($g["connection"], "pwl_dte_api_token", ["sanitize_callback" => "sanitize_text_field", "default" => ""]);
		register_setting($g["connection"], "pwl_dte_office_id", ["sanitize_callback" => [$this, "sanitize_office_id"], "default" => "1"]);

		// ── Documents ─────────────────────────────────────────────────────────
		register_setting($g["documents"], "pwl_dte_default_doc_type", ["sanitize_callback" => [$this, "sanitize_doc_type"], "default" => "boleta"]);
		register_setting($g["documents"], "pwl_dte_auto_declare_sii", ["sanitize_callback" => [$this, "sanitize_checkbox"], "default" => "1"]);
		register_setting($g["documents"], "pwl_dte_auto_send_email",  ["sanitize_callback" => [$this, "sanitize_checkbox"], "default" => "1"]);
		register_setting($g["documents"], "pwl_dte_auto_dispatch",    ["sanitize_callback" => [$this, "sanitize_checkbox"], "default" => "1"]);
		register_setting($g["documents"], "pwl_dte_price_list_id",    ["sanitize_callback" => "absint", "default" => ""]);

		// ── Sync (cron + webhooks are Pro-only) ───────────────────────────────
		if ($this->edition_has_pro_features_unlocked()) {
			register_setting($g["sync"], "pwl_dte_enable_stock_sync",   ["sanitize_callback" => [$this, "sanitize_checkbox"], "default" => "0"]);
			register_setting($g["sync"], "pwl_dte_stock_sync_interval", ["sanitize_callback" => [$this, "sanitize_sync_interval"], "default" => "hourly"]);
			register_setting($g["sync"], "pwl_dte_enable_webhooks",     ["sanitize_callback" => [$this, "sanitize_checkbox"], "default" => "0"]);
			register_setting($g["sync"], "pwl_dte_webhook_secret",      ["sanitize_callback" => "sanitize_text_field", "default" => wp_generate_password(32, false)]);

			// ── Offices ───────────────────────────────────────────────────────
			register_setting($g["offices"], "pwl_dte_stock_office_id", ["sanitize_callback" => [$this, "sanitize_optional_office_id"], "default" => ""]);
			register_setting($g["offices"], "pwl_dte_office_map",      ["sanitize_callback" => [$this, "sanitize_office_map"], "default" => "{}"]);

			register_setting($g["advanced"], "pwl_dte_max_retries", ["sanitize_callback" => "absint", "default" => "3"]);
		}

		// ── Advanced ──────────────────────────────────────────────────────────
		register_setting($g["advanced"], "pwl_dte_api_timeout",  ["sanitize_callback" => "absint", "default" => "30"]);
		register_setting($g["advanced"], "pwl_dte_log_level",    ["sanitize_callback" => [$this, "sanitize_log_level"], "default" => "errors"]);
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	protected function page_config(): array
	{
		static $c = null;
		return $c ??= [
			"title"        => __('Settings', 'pwl-dte-for-bsale'),
			"desc"         => __('Connection, documents, and sync with Bsale', 'pwl-dte-for-bsale'),
			"cap"          => "manage_options",
			"token_alert"  => false,
		];
	}

	protected function render_content(): void
	{
		$active_tab = sanitize_key($_GET["tab"] ?? "connection"); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection

		if (!array_key_exists($active_tab, $this->option_groups)) {
			$active_tab = "connection";
		}
		if ($active_tab === "offices" && !$this->edition_has_pro_features_unlocked()) {
			$active_tab = "connection";
		}
		?>

		<?php BasePage::echo_component($this->get_notices_html()); ?>

		<div class="wads-tabs" style="margin-bottom:24px;">
			<?php $this->render_tabs($active_tab); ?>
		</div>

		<form method="post" action="options.php">
			<?php
			settings_fields($this->option_groups[$active_tab]);

			match ($active_tab) {
				"connection" => $this->render_connection_tab(),
				"documents"  => $this->render_documents_tab(),
				"sync"       => $this->render_sync_tab(),
				"offices"    => $this->render_offices_tab(),
				default      => $this->render_advanced_tab(),
			};
			?>
			<div style="margin-top:24px;">
				<?php BasePage::echo_component(Components::button(__('Save settings', 'pwl-dte-for-bsale'), "primary", ["type" => "submit"])); ?>
			</div>
		</form>
		<?php
	}

	// -------------------------------------------------------------------------
	// Tab navigation
	// -------------------------------------------------------------------------

	private function render_tabs(string $active): void
	{
		$tabs = [
			"connection" => __('Connection', 'pwl-dte-for-bsale'),
			"documents"  => __('Documents', 'pwl-dte-for-bsale'),
			"sync"       => __('Sync', 'pwl-dte-for-bsale'),
			"advanced"   => __('Advanced', 'pwl-dte-for-bsale'),
		];

		if ($this->edition_has_pro_features_unlocked()) {
			$tabs = array_slice($tabs, 0, 3, true)
				+ ["offices" => __('Offices', 'pwl-dte-for-bsale')]
				+ array_slice($tabs, 3, null, true);
		}

		foreach ($tabs as $key => $label) {
			$url   = add_query_arg(["page" => $this->page_slug, "tab" => $key], admin_url("admin.php"));
			$class = $active === $key ? "wads-tab is-active" : "wads-tab";
			printf('<a href="%s" class="%s">%s</a>', esc_url($url), esc_attr($class), esc_html($label));
		}
	}

	// -------------------------------------------------------------------------
	// Tab renders
	// -------------------------------------------------------------------------

	private function render_connection_tab(): void
	{
		$token  = get_option("pwl_dte_api_token", "");
		$office = get_option("pwl_dte_office_id", "1");

		$test_row = Components::button(__('Test connection', 'pwl-dte-for-bsale'), "secondary", ["attrs" => ["id" => "pwl-dte-for-bsale-test-connection"]])
			. '<div id="connection-test-result" style="margin-top:8px;"></div>'
			. wp_nonce_field("pwl_dte_test_connection", "test_connection_nonce", true, false);

		BasePage::echo_component(Components::settings_section([
			"title" => __('Bsale API', 'pwl-dte-for-bsale'),
			"desc"  => __('Credentials to connect to your Bsale account.', 'pwl-dte-for-bsale'),
			"rows"  => [
				[
					"label"    => __('API Token', 'pwl-dte-for-bsale'),
					"desc"     => __('Bsale → Settings → Integrations → API Access Token', 'pwl-dte-for-bsale'),
					"required" => true,
					"control"  => Components::input("pwl_dte_api_token", ["type" => "password", "value" => $token, "attrs" => ["autocomplete" => "new-password"]]),
				],
				[
					"label"   => __("Office ID", "pwl-dte-for-bsale"),
					"desc"    => __('Bsale office ID (usually 1)', 'pwl-dte-for-bsale'),
					"control" => Components::input("pwl_dte_office_id", ["type" => "number", "value" => $office, "attrs" => ["min" => "1"]]),
				],
				[
					"label"   => __('Connection test', 'pwl-dte-for-bsale'),
					"control" => $test_row,
				],
			],
		]));
	}

	private function render_documents_tab(): void
	{
		$doc_type      = get_option("pwl_dte_default_doc_type", "boleta");
		$declare_sii   = get_option("pwl_dte_auto_declare_sii", "1");
		$send_email    = get_option("pwl_dte_auto_send_email", "1");
		$auto_dispatch = get_option("pwl_dte_auto_dispatch", "1");
		$price_list    = get_option("pwl_dte_price_list_id", "");

		$doc_type_control = '<label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">'
			. '<input type="radio" name="pwl_dte_default_doc_type" value="boleta"' . checked($doc_type, "boleta", false) . '>'
			. esc_html__('Boleta (receipt)', 'pwl-dte-for-bsale') . '</label>'
			. '<label style="display:flex;align-items:center;gap:8px;">'
			. '<input type="radio" name="pwl_dte_default_doc_type" value="factura"' . checked($doc_type, "factura", false) . '>'
			. esc_html__('Factura (invoice)', 'pwl-dte-for-bsale') . '</label>';

		$auto_control = Components::checkbox("pwl_dte_auto_declare_sii", __('Declare to SII automatically', 'pwl-dte-for-bsale'), ["checked" => $declare_sii === "1", "hint" => __('Bsale declares the document to the SII when it is issued.', 'pwl-dte-for-bsale')])
			. Components::checkbox("pwl_dte_auto_send_email",  __('Send by email automatically', 'pwl-dte-for-bsale'),   ["checked" => $send_email === "1",    "hint" => __('Bsale emails the DTE to the customer when it is issued.', 'pwl-dte-for-bsale')])
			. Components::checkbox("pwl_dte_auto_dispatch",    __('Deduct stock when issuing', 'pwl-dte-for-bsale'),           ["checked" => $auto_dispatch === "1", "hint" => __('Deducts stock in Bsale when the document is issued.', 'pwl-dte-for-bsale')]);

		$price_list_opts = ["" => __('-- Select --', 'pwl-dte-for-bsale')];
		if (!empty($price_list)) {
			/* translators: %s: price list ID number */
			$price_list_opts[$price_list] = sprintf(__('List #%s (click to reload)', 'pwl-dte-for-bsale'), $price_list);
		}

		BasePage::echo_component(Components::settings_section([
			"title" => __('Tax documents', 'pwl-dte-for-bsale'),
			"desc"  => __('Configure how boletas and electronic invoices are generated.', 'pwl-dte-for-bsale'),
			"rows"  => [
				["label" => __('Document type', 'pwl-dte-for-bsale'), "desc" => __('Default type when the customer does not request an invoice.', 'pwl-dte-for-bsale'), "control" => $doc_type_control],
				["label" => __('Automatic options', 'pwl-dte-for-bsale'), "control" => $auto_control],
				["label" => __('Price list', 'pwl-dte-for-bsale'), "desc" => __('Loaded automatically when you click.', 'pwl-dte-for-bsale'), "control" => Components::select("pwl_dte_price_list_id", $price_list_opts, ["selected" => $price_list])],
			],
		]));
	}

	private function render_sync_tab(): void
	{
		$last_sync = get_option("pwl_dte_last_stock_sync");
		$rows      = [];

		if ($this->edition_has_pro_features_unlocked()) {
			$enable_sync = get_option("pwl_dte_enable_stock_sync", "0");
			$interval    = get_option("pwl_dte_stock_sync_interval", "hourly");

			$rows[] = ["label" => __('Stock sync', 'pwl-dte-for-bsale'), "control" => Components::checkbox("pwl_dte_enable_stock_sync", __('Enable automatic synchronization', 'pwl-dte-for-bsale'), ["checked" => $enable_sync === "1"])];
			$rows[] = ["label" => __('Interval', 'pwl-dte-for-bsale'), "control" => Components::select("pwl_dte_stock_sync_interval", [
				"hourly"     => __('Every hour', 'pwl-dte-for-bsale'),
				"twicedaily" => __('Twice a day', 'pwl-dte-for-bsale'),
				"daily"      => __('Once a day', 'pwl-dte-for-bsale'),
			], ["selected" => $interval])];
		} elseif (PWL_DTE_EDITION === "pro") {
			$license_url = class_exists(\PwlDte\Integration\Pro\LicenseClient::class)
				? admin_url('admin.php?page=' . \PwlDte\Integration\Pro\LicenseClient::license_admin_page_slug())
				: admin_url('admin.php');
			$rows[] = [
				"label"   => __('Pro sync & webhooks', 'pwl-dte-for-bsale'),
				"control" => '<p class="description" style="margin:0;">'
					. esc_html__('Activate your Pro license to enable scheduled stock sync, Bsale webhooks, and related settings.', 'pwl-dte-for-bsale')
					. ' <a href="' . esc_url($license_url) . '">'
					. esc_html__('Open License', 'pwl-dte-for-bsale')
					. '</a></p>',
			];
		}

		$last_sync_text = $last_sync ? wp_date("Y-m-d H:i:s", $last_sync) : __('Never', 'pwl-dte-for-bsale');
		$rows[] = [
			"label"   => __('Manual sync', 'pwl-dte-for-bsale'),
			"desc"    => __('May take several minutes with large catalogs.', 'pwl-dte-for-bsale'),
			"control" => '<p id="last-sync-display" style="margin:0 0 8px;">' . esc_html($last_sync_text) . '</p>'
				. Components::button(__('Sync now', 'pwl-dte-for-bsale'), "secondary", ["attrs" => ["id" => "pwl-dte-for-bsale-manual-sync"]])
				. '<div id="pwl-dte-for-bsale-sync-progress" style="display:none;margin-top:12px;">'
				. '<div class="wads-progress" style="margin-bottom:4px;"><div id="pwl-dte-for-bsale-sync-bar" class="wads-progress__bar" style="width:0%;"></div></div>'
				. '<p id="pwl-dte-for-bsale-sync-status" style="margin:0;font-size:13px;color:#555;"></p>'
				. '</div>',
		];

		if ($this->edition_has_pro_features_unlocked()) {
			$webhooks    = get_option("pwl_dte_enable_webhooks", "0");
			$secret      = get_option("pwl_dte_webhook_secret", "");
			$webhook_url = rest_url("pwl-dte/v1/webhook");

			$rows[] = ["label" => __('Webhooks', 'pwl-dte-for-bsale'), "control" => Components::checkbox("pwl_dte_enable_webhooks", __('Enable Bsale webhooks', 'pwl-dte-for-bsale'), ["checked" => $webhooks === "1"])];
			$rows[] = [
				"label"   => __('Webhook Secret', 'pwl-dte-for-bsale'),
				"control" => Components::input("pwl_dte_webhook_secret", ["value" => $secret, "attrs" => ["readonly" => "readonly", "id" => "pwl_dte_webhook_secret"]])
					. ' ' . Components::button(__('Regenerate', 'pwl-dte-for-bsale'), "ghost", ["size" => "sm", "attrs" => ["id" => "pwl-dte-for-bsale-regenerate-secret"]]),
			];
			$rows[] = [
				"label"   => __('Webhook URL', 'pwl-dte-for-bsale'),
				"desc"    => sprintf(
					/* translators: %s: URL to the webhook debug page */
					wp_kses(__('Configure in Bsale → Webhooks. See the <a href="%s">debug page</a>.', 'pwl-dte-for-bsale'), ["a" => ["href" => []]]),
					esc_url(admin_url("admin.php?page=pwl-dte-for-bsale-webhook-debug")),
				),
				"control" => Components::input("webhook_url_display", ["value" => $webhook_url, "attrs" => ["id" => "webhook-url-display", "readonly" => "readonly"]])
					. ' ' . Components::button(__('Copy', 'pwl-dte-for-bsale'), "ghost", ["size" => "sm", "attrs" => ["id" => "copy-webhook-url"]]),
			];
		}

		BasePage::echo_component(Components::settings_section([
			"title" => __('Stock sync', 'pwl-dte-for-bsale'),
			"rows"  => $rows,
		]));

		if (PWL_DTE_EDITION !== "pro") {
			$link = '<a href="' . esc_url(PWL_DTE_PRO_URL) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html__('View plans & subscribe', 'pwl-dte-for-bsale')
				. "</a>";
			BasePage::echo_component(Components::callout(
				__('Advanced sync available in Pro', 'pwl-dte-for-bsale'),
				"info",
				wp_kses(
					sprintf(
						/* translators: %s: anchor link to the Pro product page (plans, purchase, trials). */
						__('The Pro edition adds cron stock sync, real-time webhooks, and a dedicated stock office. %s', 'pwl-dte-for-bsale'),
						$link,
					),
					["a" => ["href" => [], "target" => [], "rel" => []]],
				),
			));
		}
	}

	private function render_advanced_tab(): void
	{
		$rows = [];

		if ($this->edition_has_pro_features_unlocked()) {
			$rows[] = [
				"label"   => __('Automatic retries', 'pwl-dte-for-bsale'),
				"desc"    => __('Maximum attempts for the scheduled DTE retry job (0 disables automatic retries, 1–10).', 'pwl-dte-for-bsale'),
				"control" => Components::input("pwl_dte_max_retries", ["type" => "number", "value" => get_option("pwl_dte_max_retries", "3"), "attrs" => ["min" => "0", "max" => "10"]]),
			];
		}

		$rows[] = [
			"label"   => __('API timeout (seconds)', 'pwl-dte-for-bsale'),
			"desc"    => __('Maximum wait time for Bsale API responses (5–120).', 'pwl-dte-for-bsale'),
			"control" => Components::input("pwl_dte_api_timeout", ["type" => "number", "value" => get_option("pwl_dte_api_timeout", "30"), "attrs" => ["min" => "5", "max" => "120"]]),
		];
		$rows[] = [
			"label"   => __('Log level', 'pwl-dte-for-bsale'),
			"control" => Components::select("pwl_dte_log_level", [
				"none"   => __('None', 'pwl-dte-for-bsale'),
				"errors" => __('Errors only', 'pwl-dte-for-bsale'),
				"info"   => __('Information', 'pwl-dte-for-bsale'),
				"debug"  => __('Debug', 'pwl-dte-for-bsale'),
			], ["selected" => get_option("pwl_dte_log_level", "errors")]),
		];

		BasePage::echo_component(Components::settings_section([
			"title" => __('Advanced settings', 'pwl-dte-for-bsale'),
			"rows"  => $rows,
		]));
	}

	private function render_offices_tab(): void
	{
		$stock_office      = get_option("pwl_dte_stock_office_id", "");
		$map               = json_decode(get_option("pwl_dte_office_map", "{}"), true) ?: [];
		$default_office_id = get_option("pwl_dte_office_id", "1");
		/* translators: %s: office ID number */
		$default_label = sprintf(__('-- Same as Connection (office #%s) --', 'pwl-dte-for-bsale'), $default_office_id);

		$wc_methods = (function_exists("WC") && WC()->shipping()) ? WC()->shipping()->get_shipping_methods() : [];

		$method_descriptions = [
			"flat_rate"       => __('Flat rate (home delivery)', 'pwl-dte-for-bsale'),
			"free_shipping"   => __('Free shipping', 'pwl-dte-for-bsale'),
			"local_pickup"    => __('Local pickup (classic method)', 'pwl-dte-for-bsale'),
			"pickup_location" => __('Local pickup with location selection (WC 8.x+)', 'pwl-dte-for-bsale'),
		];

		BasePage::echo_component(Components::callout(
			__('Multi-office', 'pwl-dte-for-bsale'),
			"info",
			__('If you only have one office you do not need to configure anything here. Use this screen when you have a warehouse and a retail store in Bsale.', 'pwl-dte-for-bsale'),
		));
		?>
		<div style="margin-bottom:16px;">
			<?php BasePage::echo_component(Components::button(__('Load offices from Bsale', 'pwl-dte-for-bsale'), "secondary", ["attrs" => ["id" => "pwl-dte-for-bsale-load-offices"]])); ?>
			<span id="pwl-dte-for-bsale-offices-status" style="margin-left:10px;color:#666;"></span>
		</div>

		<table class="form-table">
			<tr>
				<th>
					<label for="pwl_dte_stock_office_id"><?php esc_html_e('Office for stock', 'pwl-dte-for-bsale'); ?></label>
				</th>
				<td>
					<select id="pwl_dte_stock_office_id" name="pwl_dte_stock_office_id" class="pwl-dte-for-bsale-office-select">
						<option value=""><?php echo esc_html($default_label); ?></option>
						<?php if (!empty($stock_office)): ?>
							<option value="<?php echo esc_attr($stock_office); ?>" selected>
								<?php
								/* translators: %s: office ID number */
								printf(esc_html__('Office #%s', 'pwl-dte-for-bsale'), esc_html($stock_office));
								?>
							</option>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e('Bsale office used to read stock for WooCommerce. Set your main warehouse here if it differs from the issuing office.', 'pwl-dte-for-bsale'); ?></p>
				</td>
			</tr>

			<?php if (!empty($wc_methods)): ?>
			<tr>
				<th colspan="2">
					<h3 style="margin:0;"><?php esc_html_e('DTE office by shipping method', 'pwl-dte-for-bsale'); ?></h3>
					<p style="font-weight:normal;margin-top:4px;color:#666;">
						<?php printf(
							/* translators: %s: office ID */
							esc_html__('Choose which office issues the DTE based on how the order was fulfilled. If unset, office #%s (from the Connection tab) is used.', 'pwl-dte-for-bsale'),
							esc_html($default_office_id),
						); ?>
					</p>
				</th>
			</tr>
			<?php foreach ($wc_methods as $method_id => $method_instance): ?>
			<tr>
				<th>
					<label for="pwl_dte_office_map_<?php echo esc_attr($method_id); ?>">
						<?php echo esc_html($method_instance->method_title); ?>
						<br><code style="font-weight:normal;font-size:11px;"><?php echo esc_html($method_id); ?></code>
						<?php if (isset($method_descriptions[$method_id])): ?>
							<br><small style="font-weight:normal;color:#666;font-size:11px;"><?php echo esc_html($method_descriptions[$method_id]); ?></small>
						<?php endif; ?>
					</label>
				</th>
				<td>
					<select
						id="pwl_dte_office_map_<?php echo esc_attr($method_id); ?>"
						name="pwl_dte_office_map[<?php echo esc_attr($method_id); ?>]"
						class="pwl-dte-for-bsale-office-select"
						data-current="<?php echo esc_attr($map[$method_id] ?? ""); ?>"
					>
						<option value=""><?php echo esc_html($default_label); ?></option>
						<?php if (!empty($map[$method_id])): ?>
							<option value="<?php echo esc_attr($map[$method_id]); ?>" selected>
								<?php
								/* translators: %s: office ID number */
								printf(esc_html__('Office #%s', 'pwl-dte-for-bsale'), esc_html($map[$method_id]));
								?>
							</option>
						<?php endif; ?>
					</select>
				</td>
			</tr>
			<?php endforeach; ?>
			<?php endif; ?>
		</table>

		<?php
	}

	// -------------------------------------------------------------------------
	// Connection test (admin-post handler)
	// -------------------------------------------------------------------------

	public function test_connection(): void
	{
		check_admin_referer("pwl_dte_test_connection", "test_connection_nonce");

		if (!current_user_can("manage_options")) {
			wp_die(esc_html__('Not authorized.', 'pwl-dte-for-bsale'));
		}

		$token = get_option("pwl_dte_api_token", "");

		if (empty($token)) {
			wp_safe_redirect(add_query_arg(
				["page" => $this->page_slug, "tab" => "connection", "connection_test" => "error", "message" => urlencode(__('Configure an API token first', 'pwl-dte-for-bsale'))],
				admin_url("admin.php"),
			));
			exit();
		}

		$client  = new \PwlDte\Api\BsaleClient($token);
		$result  = $client->test_connection();
		$message = $result["success"]
			? __('Successfully connected to Bsale', 'pwl-dte-for-bsale')
			: ($result["error"] ?? __('Unknown error', 'pwl-dte-for-bsale'));

		wp_safe_redirect(add_query_arg(
			["page" => $this->page_slug, "tab" => "connection", "connection_test" => $result["success"] ? "success" : "error", "message" => urlencode($message)],
			admin_url("admin.php"),
		));
		exit();
	}

	// -------------------------------------------------------------------------
	// Notices
	// -------------------------------------------------------------------------

	private function get_notices_html(): string
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of redirect params set by our own handler
		if (!isset($_GET["connection_test"])) {
			return "";
		}
		$status  = sanitize_key($_GET["connection_test"]);
		$message = isset($_GET["message"]) ? urldecode(sanitize_text_field(wp_unslash($_GET["message"]))) : "";
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return Components::notice($message, $status === "success" ? "success" : "danger", ["dismissible" => true]);
	}

	// -------------------------------------------------------------------------
	// AJAX: fetch offices from Bsale
	// -------------------------------------------------------------------------

	public function ajax_get_offices(): void
	{
		check_ajax_referer("pwl_dte_api_fetch", "nonce");

		if (!current_user_can("manage_options")) {
			wp_send_json_error(["message" => __('Not authorized', 'pwl-dte-for-bsale')], 403);
		}

		if (!$this->edition_has_pro_features_unlocked()) {
			wp_send_json_error(["message" => __('A valid Pro license is required for this action.', 'pwl-dte-for-bsale')], 403);
		}

		$cached = get_transient("pwl_dte_offices_cache");
		if ($cached !== false) {
			wp_send_json_success(["offices" => $cached]);
			return;
		}

		$result = (new \PwlDte\Api\BsaleClient(get_option("pwl_dte_api_token", "")))->get_offices();

		if (!$result["success"]) {
			wp_send_json_error(["message" => $result["error"] ?? __('Could not load offices', 'pwl-dte-for-bsale')]);
			return;
		}

		$offices = $result["data"]["items"] ?? [];
		set_transient("pwl_dte_offices_cache", $offices, HOUR_IN_SECONDS);
		wp_send_json_success(["offices" => $offices]);
	}

	// -------------------------------------------------------------------------
	// Sanitize callbacks
	// -------------------------------------------------------------------------

	/** @param mixed $value */
	public function sanitize_office_id($value): string
	{
		$value = absint($value);
		return $value >= 1 ? (string) $value : "1";
	}

	/** @param mixed $value */
	public function sanitize_checkbox($value): string
	{
		return $value === "1" ? "1" : "0";
	}

	/** @param mixed $value */
	public function sanitize_doc_type($value): string
	{
		return in_array($value, ["boleta", "factura"], true) ? $value : "boleta";
	}

	/** @param mixed $value */
	public function sanitize_sync_interval($value): string
	{
		return in_array($value, ["hourly", "twicedaily", "daily"], true) ? $value : "hourly";
	}

	/** @param mixed $value */
	public function sanitize_log_level($value): string
	{
		return in_array($value, ["none", "errors", "info", "debug"], true) ? $value : "errors";
	}

	/**
	 * Returns empty string (meaning "use default") if the value is 0 or empty.
	 *
	 * @param mixed $value
	 */
	public function sanitize_optional_office_id($value): string
	{
		$int = absint($value);
		return $int > 0 ? (string) $int : "";
	}

	/**
	 * Accepts an array of [ method_id => office_id ] from the form (or a JSON string)
	 * and stores it as a JSON-encoded string.
	 *
	 * @param mixed $value Array from form POST or existing JSON string.
	 */
	public function sanitize_office_map($value): string
	{
		if (is_string($value)) {
			$value = json_decode($value, true);
		}
		if (!is_array($value)) {
			return "{}";
		}

		$clean = array_filter(
			array_combine(
				array_map("sanitize_key", array_keys($value)),
				array_map("absint", $value),
			),
			fn($office_id, $method_id) => !empty($method_id) && $office_id > 0,
			ARRAY_FILTER_USE_BOTH,
		);

		return wp_json_encode($clean) ?: "{}";
	}
}
