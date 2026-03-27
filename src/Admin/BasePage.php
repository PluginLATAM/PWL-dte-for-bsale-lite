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
			<?php echo Components::page_header($c["title"], $opts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
		$icon  = esc_html("⚠");
		$title = esc_html__("Token de API no configurado", "pwl-dte-for-bsale");
		$msg   = esc_html__("Los documentos tributarios no se generarán hasta que ingreses tu token de Bsale.", "pwl-dte-for-bsale");
		$cta   = esc_html__("Configurar ahora →", "pwl-dte-for-bsale");
		echo <<<HTML
		<div style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;margin-bottom:20px;background:#fff8f8;border:1px solid #f5c6c6;border-left:3px solid var(--wads-danger,#c62d2d);border-radius:6px;">
			<span style="color:var(--wads-danger,#c62d2d);font-size:15px;line-height:1.4;">{$icon}</span>
			<div style="flex:1;font-size:13px;color:var(--wads-text,#2c2825);line-height:1.5;">
				<strong>{$title}</strong><br>
				{$msg}
			</div>
			<a href="{$url}" style="flex-shrink:0;font-size:12px;font-weight:600;color:var(--wads-accent,#7c5c3b);text-decoration:none;white-space:nowrap;padding-top:2px;">{$cta}</a>
		</div>
		HTML;
	}
}
