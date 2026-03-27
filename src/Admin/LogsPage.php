<?php
// src/Admin/LogsPage.php
namespace PwlDte\Admin;

defined("ABSPATH") || exit();

use UserDOMP\WpAdminDS\Components;

/**
 * Logs page for DTE documents.
 * Shows a filterable, paginated list of all DTE records with retry actions.
 * Registered as a WooCommerce submenu page.
 */
class LogsPage extends BasePage
{
	private \PwlDte\Core\Database $db;

	private int $per_page = 20;

	public function __construct()
	{
		$this->db = new \PwlDte\Core\Database();
	}

	public function register_hooks(): void
	{
		add_action("admin_menu", [$this, "add_menu_page"]);
		add_action("admin_enqueue_scripts", [$this, "enqueue_scripts"]);
	}

	public function enqueue_scripts(string $hook): void
	{
		if (!str_contains($hook, 'pwl-dte-for-bsale-logs')) {
			return;
		}

		wp_register_script('pwl-dte-for-bsale-logs', false, ['jquery'], PWL_DTE_VERSION, true);
		wp_localize_script('pwl-dte-for-bsale-logs', 'pwlDteLogs', [
			'labels' => [
				'confirmRetry'    => __('¿Reintentar generación del DTE para esta orden?', 'pwl-dte-for-bsale'),
				'processing'      => __('Procesando...', 'pwl-dte-for-bsale'),
				'success'         => __('DTE regenerado exitosamente', 'pwl-dte-for-bsale'),
				'errorPrefix'     => __('Error: ', 'pwl-dte-for-bsale'),
				'unknownError'    => __('Error desconocido', 'pwl-dte-for-bsale'),
				'connectionError' => __('Error de conexión', 'pwl-dte-for-bsale'),
				'retry'           => __('Reintentar', 'pwl-dte-for-bsale'),
			],
		]);

		$js = <<<'JS'
		(function($){
			$(document).on('click','.pwl-dte-for-bsale-logs-retry',function(){
				var $btn=$(this),orderId=$btn.data('order-id'),nonce=$btn.data('nonce');
				if(!confirm(pwlDteLogs.labels.confirmRetry))return;
				$btn.prop('disabled',true).text(pwlDteLogs.labels.processing);
				$.ajax({
					url:ajaxurl,method:'POST',
					data:{action:'pwl_dte_regenerate_dte',nonce:nonce,order_id:orderId},
					success:function(r){
						if(r.success){alert(r.data.message||pwlDteLogs.labels.success);location.reload();}
						else{
							alert(pwlDteLogs.labels.errorPrefix+(r.data&&r.data.message?r.data.message:pwlDteLogs.labels.unknownError));
							$btn.prop('disabled',false).text(pwlDteLogs.labels.retry);
						}
					},
					error:function(){
						alert(pwlDteLogs.labels.connectionError);
						$btn.prop('disabled',false).text(pwlDteLogs.labels.retry);
					}
				});
			});
		})(jQuery);
		JS;

		wp_add_inline_script('pwl-dte-for-bsale-logs', $js);
		wp_enqueue_script('pwl-dte-for-bsale-logs');
	}

	public function add_menu_page(): void
	{
		add_submenu_page(
			"pwl-dte-for-bsale",
			__("Logs DTE Bsale", "pwl-dte-for-bsale"),
			__("Logs DTE", "pwl-dte-for-bsale"),
			"manage_woocommerce",
			"pwl-dte-for-bsale-logs",
			[$this, "render_page"],
		);
	}

	protected function page_config(): array
	{
		static $c = null;
		return $c ??= [
			"title" => __("Logs DTE", "pwl-dte-for-bsale"),
			"desc"  => __("Historial de documentos tributarios electrónicos generados.", "pwl-dte-for-bsale"),
			"cap"   => "manage_woocommerce",
		];
	}

	public function render_page(): void
	{
		$this->render();
	}

	protected function render_content(): void
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter/pagination params
		$status_filter = sanitize_key($_GET["status"] ?? "");
		$current_page  = max(1, absint($_GET["paged"] ?? 1));
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$offset      = ($current_page - 1) * $this->per_page;
		$logs        = $this->db->get_documents_with_filter($status_filter ?: null, $this->per_page, $offset);
		$total       = $this->db->count_documents($status_filter ?: null);
		$total_pages = (int) ceil($total / $this->per_page);
		$nonce       = wp_create_nonce("pwl_dte_dte_actions");
		?>

			<?php $this->render_summary_counts(); ?>

			<div class="wads-card">
				<div class="wads-card__header">
					<span style="font-size:13px;color:#666;">
						<?php printf(
      	/* translators: 1: current count, 2: total count */
      	esc_html__('Mostrando %1$d de %2$d registros', "pwl-dte-for-bsale"),
      	count($logs),
      	absint($total),
      ); ?>
					</span>
				</div>
				<div class="wads-table-wrap">
					<table class="wads-table">
						<thead>
							<tr>
								<th><?php esc_html_e("Orden", "pwl-dte-for-bsale"); ?></th>
								<th><?php esc_html_e("Tipo", "pwl-dte-for-bsale"); ?></th>
								<th><?php esc_html_e("Folio", "pwl-dte-for-bsale"); ?></th>
								<th><?php esc_html_e("Estado", "pwl-dte-for-bsale"); ?></th>
								<th><?php esc_html_e("Error", "pwl-dte-for-bsale"); ?></th>
								<th><?php esc_html_e("Intentos", "pwl-dte-for-bsale"); ?></th>
								<th><?php esc_html_e("Fecha", "pwl-dte-for-bsale"); ?></th>
								<th><?php esc_html_e("Acciones", "pwl-dte-for-bsale"); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($logs)): ?>
								<tr>
									<td colspan="8">
										<?php echo Components::empty_state(__("Sin registros", "pwl-dte-for-bsale"), [
          	"desc" => __(
          		"No hay documentos que coincidan con el filtro aplicado.",
          		"pwl-dte-for-bsale",
          	),
          ]); ?>
									</td>
								</tr>
							<?php
       	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
       	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
       	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
       	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
       	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
       	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
       	else: ?>
								<?php foreach ($logs as $log): ?>
									<tr>
										<td>
											<a href="<?php echo esc_url(
           	admin_url(
           		"post.php?post=" . absint($log["order_id"]) . "&action=edit",
           	),
           ); ?>">
												#<?php echo absint($log["order_id"]); ?>
											</a>
										</td>
										<td><?php echo esc_html(ucfirst($log["document_type"])); ?></td>
										<td><?php echo $log["folio"] ? esc_html($log["folio"]) : "—"; ?></td>
										<td><?php echo $this->get_status_badge($log["status"]);
        	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
        	?></td>
										<td style="font-size:12px;color:var(--wads-danger,#c62d2d);word-break:break-word;">
											<?php echo $log["error_message"] ? esc_html($log["error_message"]) : ""; ?>
										</td>
										<td><?php echo absint($log["attempts"]); ?></td>
										<td><?php echo esc_html(
          	wp_date("d/m/Y H:i", strtotime($log["created_at"])),
          ); ?></td>
										<td>
											<?php if ($log["status"] === "success" && !empty($log["pdf_url"])): ?>
												<?php echo Components::button(__("PDF", "pwl-dte-for-bsale"), "ghost", [
            	"size" => "sm",
            	"href" => $log["pdf_url"],
            	"attrs" => ["target" => "_blank"],
            ]);
           	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
           	?>
											<?php endif; ?>
											<?php if (in_array($log["status"], ["error", "pending"], true)): ?>
												<?php echo Components::button(__("Reintentar", "pwl-dte-for-bsale"), "secondary", [
            	"size" => "sm",
            	"attrs" => [
            		"class" =>
            			"wads-btn wads-btn--secondary wads-btn--sm pwl-dte-for-bsale-logs-retry",
            		"data-order-id" => absint($log["order_id"]),
            		"data-nonce" => esc_attr($nonce),
            	],
            ]);
           	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
           	?>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div><!-- /.wads-table-wrap -->

				<?php if ($total_pages > 1): ?>
					<div class="wads-card__footer">
						<?php echo $this->render_wads_pagination(
      	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method escapes internally
      	$current_page,
      	$total_pages,
      	admin_url("admin.php"),
      	["page" => "pwl-dte-for-bsale-logs", "status" => $status_filter],
      ); ?>
					</div>
				<?php endif; ?>
			</div><!-- /.wads-card -->

		<?php
		<?php
	}

	// -------------------------------------------------------------------------
	// Private render helpers
	// -------------------------------------------------------------------------

	private function render_summary_counts(): void
	{
		// Badge variants for the summary row differ from the main status badge (dot- prefix for counts)
		$dot_variants = [
			"success" => "dot-success",
			"error" => "dot-danger",
			"pending" => "dot-warning",
			"retrying" => "default",
		];

		// Labels for count links re-use the status config but pull only the label
		$labels = [
			"success" => __("Exitosos", "pwl-dte-for-bsale"),
			"error" => __("Errores", "pwl-dte-for-bsale"),
			"pending" => __("Pendientes", "pwl-dte-for-bsale"),
			"retrying" => __("Reintentando", "pwl-dte-for-bsale"),
		];
		?>
		<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center;">
			<?php foreach ($dot_variants as $key => $badge_variant):
   	$url = add_query_arg(
   		["page" => "pwl-dte-for-bsale-logs", "status" => $key],
   		admin_url("admin.php"),
   	);
   	$count = $this->db->count_documents($key);
   	echo '<a href="' .
   		esc_url($url) .
   		'" style="text-decoration:none;">' .
   		Components::badge(
   			esc_html($labels[$key]) . ": " . absint($count),
   			$badge_variant,
   		) .
   		"</a>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes badge internals
   endforeach; ?>
			<?php echo '<a href="' .
   	esc_url(
   		add_query_arg(["page" => "pwl-dte-for-bsale-logs"], admin_url("admin.php")),
   	) .
   	'" style="text-decoration:none;">' .
   	Components::badge(__("Ver todos", "pwl-dte-for-bsale"), "default") .
   	"</a>";// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
		?>
		</div>
		<?php
	}


	/**
	 * Builds a wads-pagination nav for the given page/total/args combo.
	 *
	 * @param int    $current     Current page (1-indexed).
	 * @param int    $total_pages Total number of pages.
	 * @param string $base_url    Base admin URL.
	 * @param array  $extra_args  Query args preserved in every link.
	 */
	private function render_wads_pagination(
		int $current,
		int $total_pages,
		string $base_url,
		array $extra_args = [],
	): string {
		if ($total_pages <= 1) {
			return "";
		}

		$page_url = fn(int $p) => esc_url(
			add_query_arg(array_merge($extra_args, ["paged" => $p]), $base_url),
		);

		$prev =
			$current > 1
				? '<a class="wads-page-item" href="' .
					$page_url($current - 1) .
					'">← ' .
					esc_html__("Anterior", "pwl-dte-for-bsale") .
					"</a>"
				: '<span class="wads-page-item is-disabled">← ' .
					esc_html__("Anterior", "pwl-dte-for-bsale") .
					"</span>";

		$next =
			$current < $total_pages
				? '<a class="wads-page-item" href="' .
					$page_url($current + 1) .
					'">' .
					esc_html__("Siguiente", "pwl-dte-for-bsale") .
					" →</a>"
				: '<span class="wads-page-item is-disabled">' .
					esc_html__("Siguiente", "pwl-dte-for-bsale") .
					" →</span>";

		$delta = 2;
		$range = range(
			max(1, $current - $delta),
			min($total_pages, $current + $delta),
		);
		$pages = $range[0] > 1 ? array_merge([1], $range) : $range;
		$last = end($range);
		if ($last < $total_pages) {
			$pages[] = $total_pages;
		}
		$pages = array_unique($pages);

		$numbers = "";
		$prev_p = 0;
		foreach ($pages as $p) {
			if ($p - $prev_p > 1) {
				$numbers .=
					'<span class="wads-page-item wads-page-item--ellipsis">…</span>';
			}
			$class =
				$p === $current ? "wads-page-item is-active" : "wads-page-item";
			$numbers .=
				'<a class="' .
				$class .
				'" href="' .
				$page_url($p) .
				'">' .
				$p .
				"</a>";
			$prev_p = $p;
		}

		return '<nav class="wads-pagination">' .
			$prev .
			$numbers .
			$next .
			"</nav>";
	}

	/** Delegates to the canonical status map in Admin to avoid duplicating label/variant definitions. */
	private function get_status_badge(string $status): string
	{
		$cfg = Admin::status_config($status);
		return Components::badge($cfg["label"], $cfg["variant"]);
	}

}
