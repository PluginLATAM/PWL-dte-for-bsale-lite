<?php
// src/Admin/Admin.php
namespace PwlDte\Admin;

defined("ABSPATH") || exit();

use UserDOMP\WpAdminDS\DesignSystem;

class Admin
{
	private Settings $settings;

	public function __construct()
	{
		$this->settings = new Settings();
	}

	public function register_hooks(): void
	{
		add_action("admin_menu",              [$this, "add_menu_pages"]);
		add_action("admin_enqueue_scripts",   [$this, "enqueue_assets"]);
		add_action("admin_notices",           [$this, "maybe_show_token_notice"]);
		add_action("admin_head",              [$this, "suppress_foreign_notices"]);
		add_action("wp_ajax_pwl_dte_get_price_lists", [$this, "ajax_get_price_lists"]);

		// DTE status column — classic orders (post-based)
		add_filter("manage_edit-shop_order_columns", [$this, "add_order_column"]);
		add_action("manage_shop_order_posts_custom_column", [$this, "render_order_column"], 10, 2);

		// DTE status column — HPOS orders
		add_filter("woocommerce_shop_order_list_table_columns", [$this, "add_order_column"]);
		add_action("woocommerce_shop_order_list_table_custom_column", [$this, "render_order_column_hpos"], 10, 2);
	}

	public function suppress_foreign_notices(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset($_GET["page"]) ? sanitize_key($_GET["page"]) : "";
		if (!str_starts_with($page, "pwl-dte-for-bsale")) return;

		// Remove all notices — our token alert is rendered inline inside the wads wrapper instead
		remove_all_actions("admin_notices");
	}

	public function add_menu_pages(): void
	{
		add_menu_page(
			__("PWL DTE", 'pwl-dte-for-bsale'),
			__("PWL DTE", 'pwl-dte-for-bsale'),
			"manage_woocommerce",
			'pwl-dte-for-bsale',
			[$this, "render_dashboard"],
			"dashicons-store",
			57,
		);

		add_submenu_page(
			'pwl-dte-for-bsale',
			__("Dashboard", 'pwl-dte-for-bsale'),
			__("Dashboard", 'pwl-dte-for-bsale'),
			"manage_woocommerce",
			'pwl-dte-for-bsale',
			[$this, "render_dashboard"],
		);

		add_submenu_page(
			'pwl-dte-for-bsale',
			__("Configuración", 'pwl-dte-for-bsale'),
			__("Configuración", 'pwl-dte-for-bsale'),
			"manage_options",
			"pwl-dte-for-bsale-settings",
			[$this->settings, "render"],
		);
	}

	public function enqueue_assets(string $hook): void
	{
		$is_plugin_page = str_contains($hook, 'pwl-dte-for-bsale');
		$is_order_page = in_array($hook, ['post.php', 'woocommerce_page_wc-orders'], true);

		if (!$is_plugin_page && !$is_order_page) {
			return;
		}

		$wads_url = PWL_DTE_URL . "vendor/userdomp/wp-admin-design-system/assets/";
		DesignSystem::enqueue($wads_url, DesignSystem::VERSION);

		if ($is_order_page && !$is_plugin_page) {
			return;
		}

		wp_enqueue_style("pwl-dte-for-bsale-admin", PWL_DTE_URL . "assets/admin/css/admin.css", ["wads"], PWL_DTE_VERSION);
		wp_add_inline_style("pwl-dte-for-bsale-admin",
			"#wpwrap,#wpcontent,#wpbody,#wpbody-content,#wpfooter{background:#F8F7F4;}"
			. "#wpcontent{padding-left:0;}"
			. ".wads-main{min-height:calc(100vh - 86px);}"
		);
		wp_enqueue_script("pwl-dte-for-bsale-admin", PWL_DTE_URL . "assets/admin/js/admin.js", ["jquery", "wads"], PWL_DTE_VERSION, true);
		wp_localize_script("pwl-dte-for-bsale-admin", "pwlDte", [
			"ajaxUrl"        => admin_url("admin-ajax.php"),
			"nonceApiFetch"  => wp_create_nonce("pwl_dte_api_fetch"),
			"nonceStockSync" => wp_create_nonce("pwl_dte_stock_sync"),
			"nonceImport"    => wp_create_nonce("pwl_dte_import"),
			"nonceDteActions" => wp_create_nonce("pwl_dte_dte_actions"),
		]);
		wp_add_inline_script("pwl-dte-for-bsale-admin", 'var ajaxurl = "' . admin_url("admin-ajax.php") . '";', "before");
	}

	// -------------------------------------------------------------------------
	// Shared status config — consumed by OrderMetabox and LogsPage
	// -------------------------------------------------------------------------

	/**
	 * Canonical map of document status → [label, badge variant].
	 * Single source of truth used by OrderMetabox, LogsPage and the dashboard table.
	 *
	 * @return array{label:string,variant:string}
	 */
	public static function status_config(string $status): array
	{
		static $map = null;
		$map ??= [
			"success"  => ["label" => __("Exitoso", 'pwl-dte-for-bsale'),      "variant" => "solid-success"],
			"error"    => ["label" => __("Error", 'pwl-dte-for-bsale'),         "variant" => "solid-danger"],
			"pending"  => ["label" => __("Pendiente", 'pwl-dte-for-bsale'),     "variant" => "warning"],
			"retrying" => ["label" => __("Reintentando", 'pwl-dte-for-bsale'),  "variant" => "info"],
		];
		return $map[$status] ?? ["label" => __("Desconocido", 'pwl-dte-for-bsale'), "variant" => "default"];
	}

	// -------------------------------------------------------------------------
	// Shared layout helpers
	// -------------------------------------------------------------------------

	public static function render_sidebar(string $active_page = "dashboard"): void
	{
		$pages = [
			"dashboard" => ["label" => __("Dashboard", 'pwl-dte-for-bsale'),       "url" => admin_url("admin.php?page=pwl-dte-for-bsale")],
			"settings"  => ["label" => __("Configuración", 'pwl-dte-for-bsale'),   "url" => admin_url("admin.php?page=pwl-dte-for-bsale-settings")],
			"sync"      => ["label" => __("Sincronización", 'pwl-dte-for-bsale'),  "url" => admin_url("admin.php?page=pwl-dte-for-bsale-sync")],
			"logs"      => ["label" => __("Logs DTE", 'pwl-dte-for-bsale'),        "url" => admin_url("admin.php?page=pwl-dte-for-bsale-logs")],
		];

		if (PWL_DTE_EDITION === "pro") {
			$pages["webhooks"] = ["label" => __("Webhooks", 'pwl-dte-for-bsale'), "url" => admin_url("admin.php?page=pwl-dte-for-bsale-webhooks")];
		}
		?>
		<aside class="wads-sidebar">
			<div class="wads-sidebar__brand">
				<span style="font-weight:700;font-size:16px;">PWL DTE</span>
				<?php if (PWL_DTE_EDITION === "pro"): ?>
					<span class="wads-badge wads-badge--solid-accent" style="margin-left:6px;font-size:10px;">Pro</span>
				<?php endif; ?>
			</div>
			<nav class="wads-sidebar__nav">
				<?php foreach ($pages as $key => $page): ?>
					<a
						href="<?php echo esc_url($page["url"]); ?>"
						class="wads-nav-item<?php echo $active_page === $key ? " is-active" : ""; ?>"
					>
						<?php echo esc_html($page["label"]); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php if (PWL_DTE_EDITION !== "pro"): ?>
			<div style="padding:12px 16px;border-top:1px solid var(--wads-border,#e8e3dc);margin-top:auto;">
				<a href="<?php echo esc_url(PWL_DTE_PRO_URL); ?>" target="_blank"
					style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--wads-accent,#7c5c3b);text-decoration:none;">
					<span style="font-size:14px;">✦</span>
					<?php esc_html_e("Upgrade to Pro", "pwl-dte-for-bsale"); ?>
				</a>
			</div>
			<?php endif; ?>
		</aside>
		<?php
	}

	public function render_dashboard(): void
	{
		if (!current_user_can("manage_woocommerce")) {
			wp_die(esc_html__("No autorizado.", 'pwl-dte-for-bsale'));
		}

		$db        = new \PwlDte\Core\Database();
		$documents = $db->get_recent_documents(20);
		$total     = $db->count_documents();
		$success   = $db->count_documents("success");
		$errors    = $db->count_documents("error");
		$pending   = $db->count_documents("pending") + $db->count_documents("retrying");
		?>
		<div class="wads">
		<div class="wads-main">
			<?php echo \UserDOMP\WpAdminDS\Components::page_header(
				__("PWL DTE for Bsale", 'pwl-dte-for-bsale'),
				["desc" => __("Documentos Tributarios Electrónicos", 'pwl-dte-for-bsale'), "badge" => PWL_DTE_EDITION === "pro" ? "Pro" : ""],
			); ?>

			<?php BasePage::render_token_alert(); ?>

			<div class="wads-stats-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
				<?php
				echo \UserDOMP\WpAdminDS\Components::stat(__("Total generados", 'pwl-dte-for-bsale'), (string) $total);
				echo \UserDOMP\WpAdminDS\Components::stat(__("Exitosos", 'pwl-dte-for-bsale'), (string) $success, ["variant" => "success"]);
				echo \UserDOMP\WpAdminDS\Components::stat(__("Con error", 'pwl-dte-for-bsale'), (string) $errors, ["variant" => "danger"]);
				echo \UserDOMP\WpAdminDS\Components::stat(__("Pendientes", 'pwl-dte-for-bsale'), (string) $pending, ["variant" => "warning"]);
				?>
			</div>

			<div class="wads-card">
				<div class="wads-card__header" style="display:flex;align-items:center;justify-content:space-between;">
					<h3 style="margin:0;font-size:15px;"><?php esc_html_e("Últimos 20 documentos", 'pwl-dte-for-bsale'); ?></h3>
					<?php echo \UserDOMP\WpAdminDS\Components::button(
						__("Ver todos los logs →", 'pwl-dte-for-bsale'),
						"ghost",
						["href" => admin_url("admin.php?page=pwl-dte-for-bsale-logs"), "size" => "sm"],
					); ?>
				</div>

				<?php if (empty($documents)): ?>
					<div class="wads-card__body">
						<?php echo \UserDOMP\WpAdminDS\Components::empty_state(
							__("Sin documentos aún", 'pwl-dte-for-bsale'),
							["desc" => __("Los DTEs se generarán automáticamente al completar un pedido en WooCommerce.", 'pwl-dte-for-bsale')],
						); ?>
					</div>
				<?php else: ?>
					<div class="wads-table-wrap">
						<table class="wads-table">
							<thead>
								<tr>
									<th><?php esc_html_e("Pedido", 'pwl-dte-for-bsale'); ?></th>
									<th><?php esc_html_e("Tipo", 'pwl-dte-for-bsale'); ?></th>
									<th><?php esc_html_e("Folio", 'pwl-dte-for-bsale'); ?></th>
									<th><?php esc_html_e("Estado", 'pwl-dte-for-bsale'); ?></th>
									<th><?php esc_html_e("Intentos", 'pwl-dte-for-bsale'); ?></th>
									<th><?php esc_html_e("Fecha", 'pwl-dte-for-bsale'); ?></th>
									<th><?php esc_html_e("Acciones", 'pwl-dte-for-bsale'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($documents as $doc):
									$cfg = self::status_config($doc["status"]);
								?>
									<tr>
										<td>
											<a href="<?php echo esc_url(admin_url("post.php?post=" . absint($doc["order_id"]) . "&action=edit")); ?>">
												#<?php echo absint($doc["order_id"]); ?>
											</a>
										</td>
										<td><?php echo esc_html(ucfirst($doc["document_type"])); ?></td>
										<td><?php echo $doc["folio"] ? esc_html($doc["folio"]) : "—"; ?></td>
										<td><?php echo \UserDOMP\WpAdminDS\Components::badge($cfg["label"], $cfg["variant"]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
										<td><?php echo absint($doc["attempts"]); ?></td>
										<td><?php echo esc_html(wp_date("d/m/Y H:i", strtotime($doc["created_at"]))); ?></td>
										<td>
											<?php if ($doc["pdf_url"]): ?>
												<?php echo \UserDOMP\WpAdminDS\Components::button(__("PDF", 'pwl-dte-for-bsale'), "ghost", ["size" => "sm", "href" => $doc["pdf_url"], "attrs" => ["target" => "_blank"]]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div><!-- /.wads-card -->

		<?php if (PWL_DTE_EDITION !== "pro") $this->render_pro_upgrade_card(); ?>

		</div><!-- /.wads-main -->
		</div><!-- /.wads -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Pro upgrade card (Lite only)
	// -------------------------------------------------------------------------

	private function render_pro_upgrade_card(): void
	{
		$url      = esc_url(PWL_DTE_PRO_URL);
		$badge    = esc_html__("Pro", "pwl-dte-for-bsale");
		$title    = esc_html__("Potencia tu facturación con PWL DTE Pro", "pwl-dte-for-bsale");
		$features = [
			__("Sincronización de stock automática (cron)", "pwl-dte-for-bsale"),
			__("Webhooks en tiempo real de Bsale", "pwl-dte-for-bsale"),
			__("Notas de crédito automáticas en devoluciones", "pwl-dte-for-bsale"),
			__("Multi-sucursal según método de envío", "pwl-dte-for-bsale"),
			__("Retry automático de DTEs fallidos", "pwl-dte-for-bsale"),
			__("Importador de productos desde Bsale", "pwl-dte-for-bsale"),
		];
		$items    = implode("", array_map(
			fn($f) => '<li style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--wads-text,#2c2825);">'
				. '<span style="color:var(--wads-accent,#7c5c3b);font-weight:700;">✓</span>'
				. esc_html($f)
				. "</li>",
			$features,
		));
		$cta      = esc_html__("Ver PWL DTE Pro →", "pwl-dte-for-bsale");
		echo <<<HTML
		<div class="wads-card" style="margin-top:24px;border:2px solid var(--wads-accent,#7c5c3b);">
			<div class="wads-card__header" style="display:flex;align-items:center;gap:10px;">
				<span class="wads-badge wads-badge--solid-accent">{$badge}</span>
				<h3 style="margin:0;font-size:15px;">{$title}</h3>
			</div>
			<div class="wads-card__body" style="display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;">
				<ul style="margin:0;padding:0;list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:8px 32px;">
					{$items}
				</ul>
				<!--<a href="{$url}" target="_blank" class="wads-btn wads-btn--primary">{$cta}</a>-->
			</div>
		</div>
		HTML;
	}

	// -------------------------------------------------------------------------
	// Order list column
	// -------------------------------------------------------------------------

	public function add_order_column(array $columns): array
	{
		$new_columns = [];
		foreach ($columns as $key => $value) {
			$new_columns[$key] = $value;
			if ($key === "order_status") {
				$new_columns["pwl_dte"] = __("DTE Bsale", 'pwl-dte-for-bsale');
			}
		}
		return $new_columns;
	}

	public function render_order_column(string $column, int $post_id): void
	{
		if ($column === "pwl_dte") {
			$this->render_dte_column_content($post_id);
		}
	}

	public function render_order_column_hpos(string $column, \WC_Order $order): void
	{
		if ($column === "pwl_dte") {
			$this->render_dte_column_content($order->get_id());
		}
	}

	private function render_dte_column_content(int $order_id): void
	{
		$db  = new \PwlDte\Core\Database();
		$doc = $db->get_document_by_order_id($order_id);

		if (!$doc) {
			echo '<span style="color: #999;" title="' . esc_attr__("Sin DTE", 'pwl-dte-for-bsale') . '">—</span>';
			return;
		}

		$icons = [
			"success"  => '<span style="color: #2e7d32; font-size: 16px;" title="' . esc_attr__("DTE generado", 'pwl-dte-for-bsale') . '">✓</span>',
			"error"    => '<span style="color: #c62d2d; font-size: 16px;" title="' . esc_attr__("Error al generar DTE", 'pwl-dte-for-bsale') . '">✗</span>',
			"pending"  => '<span style="color: #b45309; font-size: 14px;" title="' . esc_attr__("DTE pendiente", 'pwl-dte-for-bsale') . '">⏳</span>',
			"retrying" => '<span style="color: #1565c0; font-size: 14px;" title="' . esc_attr__("Reintentando", 'pwl-dte-for-bsale') . '">↻</span>',
		];

		echo wp_kses_post($icons[$doc["status"]] ?? "");

		if ($doc["status"] === "success" && !empty($doc["folio"])) {
			echo ' <small style="color: #555;">' . esc_html($doc["folio"]) . "</small>";
		}
	}

	// -------------------------------------------------------------------------
	// Admin notices
	// -------------------------------------------------------------------------

	public function maybe_show_token_notice(): void
	{
		if (!current_user_can("manage_woocommerce")) return;
		if (!empty(get_option("pwl_dte_api_token", ""))) return;

		// Don't show on the settings page — the user is already there
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (isset($_GET["page"]) && $_GET["page"] === "pwl-dte-for-bsale-settings") return;

		$url   = esc_url(admin_url("admin.php?page=pwl-dte-for-bsale-settings"));
		$label = esc_html__("Configurar token →", "pwl-dte-for-bsale");
		$msg   = esc_html__("PWL DTE no tiene un token de API configurado. Los documentos tributarios no se generarán hasta que lo ingreses.", "pwl-dte-for-bsale");
		echo <<<HTML
		<div class="notice notice-error">
			<p><strong>PWL DTE:</strong> {$msg} <a href="{$url}">{$label}</a></p>
		</div>
		HTML;
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	public function ajax_get_price_lists(): void
	{
		check_ajax_referer("pwl_dte_api_fetch", "nonce");

		if (!current_user_can("manage_woocommerce")) {
			wp_send_json_error(["message" => __("No autorizado", 'pwl-dte-for-bsale')], 403);
		}

		$token = get_option("pwl_dte_api_token", "");

		if (empty($token)) {
			wp_send_json_error(["message" => __("Token no configurado", 'pwl-dte-for-bsale')], 400);
		}

		$client = new \PwlDte\Api\BsaleClient($token);
		$result = $client->get_price_lists();

		if ($result["success"]) {
			$lists = array_map(
				fn($list) => ["id" => $list["id"], "name" => $list["name"]],
				$result["data"]["items"] ?? [],
			);
			wp_send_json_success($lists);
		} else {
			wp_send_json_error(["message" => $result["error"]], 500);
		}
	}
}
