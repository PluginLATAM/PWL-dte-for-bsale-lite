<?php
// src/Admin/BasePage.php
namespace PwlDte\Admin;

defined("ABSPATH") || exit();

use UserDOMP\WpAdminDS\Components;

/**
 * Abstract base for all plugin admin pages.
 *
 * Provides the shared wads layout wrapper, page header, and token alert so
 * subclasses only need to implement page_config() and render_content().
 *
 * Usage:
 *   class MyPage extends BasePage {
 *       protected function page_config(): array { ... }
 *       protected function render_content(): void { ... }
 *       public function render_page(): void { $this->render(); }
 *   }
 */
abstract class BasePage
{
	/**
	 * Shared allowlist for component-rendered HTML that includes required data-* attrs.
	 */
	public static function allowed_component_html(): array
	{
		static $allowed = null;
		if (null !== $allowed) {
			return $allowed;
		}

		$allowed = wp_kses_allowed_html("post");

		foreach (["a", "button", "input", "select", "option", "span", "div", "p", "pre", "code", "li"] as $tag) {
			if (!isset($allowed[$tag])) {
				$allowed[$tag] = [];
			}
			foreach ([
				"class",
				"id",
				"style",
				"target",
				"rel",
				"type",
				"name",
				"value",
				"checked",
				"selected",
				"readonly",
				"disabled",
				"min",
				"max",
				"placeholder",
				"for",
				"href",
				"data-action",
				"data-order-id",
				"data-nonce",
				"data-label-done",
				"data-target",
				"data-payload",
				"data-confirm",
				"data-enabled",
				"data-time",
				"data-current",
				"data-id",
				"aria-label",
				"aria-current",
				"aria-disabled",
			] as $attr) {
				$allowed[$tag][$attr] = true;
			}
		}

		return $allowed;
	}

	public static function sanitize_component_html(string $html): string
	{
		return wp_kses($html, self::allowed_component_html());
	}

	public static function echo_component(string $html): void
	{
		echo wp_kses($html, self::allowed_component_html());
	}

	/**
	 * @return array{
	 *   title: string,
	 *   desc: string,
	 *   badge: string,
	 *   cap: string,
	 *   token_alert: bool,
	 * }
	 */
	abstract protected function page_config(): array;

	abstract protected function render_content(): void;

	final public function render(): void
	{
		$c = $this->page_config();

		if (!current_user_can($c["cap"] ?? "manage_woocommerce")) {
			wp_die(esc_html__('Not authorized.', 'pwl-dte-for-bsale'));
		}

		$opts = array_filter([
			"desc"  => $c["desc"]  ?? "",
			"badge" => $c["badge"] ?? "",
		]);
		?>
		<div class="wads">
		<div class="wads-main">
			<?php self::echo_component(Components::page_header($c["title"], $opts)); ?>
			<?php if ($c["token_alert"] ?? true) self::render_token_alert(); ?>
			<?php self::maybe_render_pro_license_lock_notice(); ?>
			<?php $this->render_content(); ?>
		</div><!-- /.wads-main -->
		</div><!-- /.wads -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Pro license — all plugin admin screens (except the License screen itself)
	// -------------------------------------------------------------------------

	/**
	 * Warning when Pro edition runs without an active license (strict gate off).
	 */
	public static function maybe_render_pro_license_lock_notice(): void
	{
		if (PWL_DTE_EDITION !== "pro") {
			return;
		}
		if (!class_exists(\PwlDte\Integration\Pro\ProFeatures::class)
			|| !class_exists(\PwlDte\Integration\Pro\LicenseClient::class)) {
			return;
		}
		if (\PwlDte\Integration\Pro\ProFeatures::is_pro_license_active()) {
			return;
		}
		if (!current_user_can("manage_woocommerce") && !current_user_can("manage_options")) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only
		$page = isset($_GET["page"]) ? sanitize_key(wp_unslash($_GET["page"])) : "";
		if ($page === \PwlDte\Integration\Pro\LicenseClient::license_admin_page_slug()) {
			return;
		}

		$license_url = admin_url(
			"admin.php?page=" . \PwlDte\Integration\Pro\LicenseClient::license_admin_page_slug(),
		);

		$intro = esc_html__(
			'Boleta, factura, and manual stock sync stay available. Activate your Pro license to enable webhooks, multi-office, automatic retries, credit notes, and the product importer.',
			'pwl-dte-for-bsale',
		);

		$cta = Components::button(
			__('Activate license', 'pwl-dte-for-bsale'),
			'primary',
			[
				'href' => $license_url,
				'size' => 'lg',
				'attrs' => [
					'id' => 'pwl-dte-pro-license-cta-banner',
				],
			],
		);

		$html = '<div class="wads-mb-4" style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:16px;margin-bottom:20px;">'
			. '<div style="flex:1;min-width:240px;">'
			. Components::callout(
				__('Pro features are locked', 'pwl-dte-for-bsale'),
				'warning',
				$intro,
			)
			. '</div>'
			. '<div style="flex-shrink:0;display:flex;align-items:center;min-height:48px;">'
			. $cta
			. '</div>'
			. '</div>';

		self::echo_component($html);
	}

	// -------------------------------------------------------------------------
	// Token alert — shared across all pages and Admin::render_dashboard()
	// -------------------------------------------------------------------------

	public static function render_token_alert(): void
	{
		if (!current_user_can("manage_woocommerce")) return;
		if (!empty(get_option("pwl_dte_api_token", ""))) return;

		$url   = esc_url(admin_url("admin.php?page=pwl-dte-for-bsale-settings"));
		$icon  = "⚠";
		$title = __('API token not configured', 'pwl-dte-for-bsale');
		$msg   = __('Tax documents will not be generated until you enter your Bsale API token.', 'pwl-dte-for-bsale');
		$cta   = __('Configure now →', 'pwl-dte-for-bsale');
		?>
		<div style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;margin-bottom:20px;background:#fff8f8;border:1px solid #f5c6c6;border-left:3px solid var(--wads-danger,#c62d2d);border-radius:6px;">
			<span style="color:var(--wads-danger,#c62d2d);font-size:15px;line-height:1.4;"><?php echo esc_html($icon); ?></span>
			<div style="flex:1;font-size:13px;color:var(--wads-text,#2c2825);line-height:1.5;">
				<strong><?php echo esc_html($title); ?></strong><br>
				<?php echo esc_html($msg); ?>
			</div>
			<a href="<?php echo esc_url($url); ?>" style="flex-shrink:0;font-size:12px;font-weight:600;color:var(--wads-accent,#7c5c3b);text-decoration:none;white-space:nowrap;padding-top:2px;"><?php echo esc_html($cta); ?></a>
		</div>
		<?php
	}
}
