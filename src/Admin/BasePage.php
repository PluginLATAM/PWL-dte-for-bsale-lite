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
			wp_die(esc_html__("No autorizado.", "pwl-dte-for-bsale"));
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
			<?php $this->render_content(); ?>
		</div><!-- /.wads-main -->
		</div><!-- /.wads -->
		<?php
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
		$title = __("Token de API no configurado", "pwl-dte-for-bsale");
		$msg   = __("Los documentos tributarios no se generarán hasta que ingreses tu token de Bsale.", "pwl-dte-for-bsale");
		$cta   = __("Configurar ahora →", "pwl-dte-for-bsale");
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
