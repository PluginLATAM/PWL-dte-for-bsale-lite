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

		wp_enqueue_script(
			'pwl-dte-for-bsale-logs',
			PWL_DTE_URL . "src/Admin/js/logs-page.js",
			['jquery'],
			PWL_DTE_VERSION,
			true,
		);
		wp_localize_script('pwl-dte-for-bsale-logs', 'pwlDteLogs', [
			'labels' => [
				'confirmRetry'    => __('Retry DTE generation for this order?', 'pwl-dte-for-bsale'),
				'processing'      => __('Processing…', 'pwl-dte-for-bsale'),
				'success'         => __('DTE regenerated successfully', 'pwl-dte-for-bsale'),
				'errorPrefix'     => __('Error: ', 'pwl-dte-for-bsale'),
				'unknownError'    => __('Unknown error', 'pwl-dte-for-bsale'),
				'connectionError' => __('Connection error', 'pwl-dte-for-bsale'),
				'retry'           => __('Retry', 'pwl-dte-for-bsale'),
			],
		]);
	}

	public function add_menu_page(): void
	{
		add_submenu_page(
			"pwl-dte-for-bsale",
			__('Bsale DTE logs', 'pwl-dte-for-bsale'),
			__('DTE logs', 'pwl-dte-for-bsale'),
			"manage_woocommerce",
			"pwl-dte-for-bsale-logs",
			[$this, "render_page"],
		);
	}

	protected function page_config(): array
	{
		static $c = null;
		return $c ??= [
			"title" => __('DTE logs', 'pwl-dte-for-bsale'),
			"desc"  => __('History of generated electronic tax documents.', 'pwl-dte-for-bsale'),
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
      	esc_html__('Showing %1$d of %2$d records', 'pwl-dte-for-bsale'),
      	count($logs),
      	absint($total),
      ); ?>
					</span>
				</div>
				<div class="wads-table-wrap">
					<table class="wads-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Order', 'pwl-dte-for-bsale'); ?></th>
								<th><?php esc_html_e('Type', 'pwl-dte-for-bsale'); ?></th>
								<th><?php esc_html_e('Folio', 'pwl-dte-for-bsale'); ?></th>
								<th><?php esc_html_e('Status', 'pwl-dte-for-bsale'); ?></th>
								<th><?php esc_html_e('Error', 'pwl-dte-for-bsale'); ?></th>
								<th><?php esc_html_e('Attempts', 'pwl-dte-for-bsale'); ?></th>
								<th><?php esc_html_e('Date', 'pwl-dte-for-bsale'); ?></th>
								<th><?php esc_html_e('Actions', 'pwl-dte-for-bsale'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($logs)): ?>
								<tr>
									<td colspan="8">
										<?php BasePage::echo_component(Components::empty_state(__('No records', 'pwl-dte-for-bsale'), [
          	"desc" => __(
          		'No documents match the current filter.',
          		'pwl-dte-for-bsale',
          	),
          ])); ?>
									</td>
								</tr>
							<?php else: ?>
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
										<td><?php BasePage::echo_component($this->get_status_badge($log["status"])); ?></td>
										<td style="font-size:12px;color:var(--wads-danger,#c62d2d);word-break:break-word;">
											<?php echo $log["error_message"] ? esc_html($log["error_message"]) : ""; ?>
										</td>
										<td><?php echo absint($log["attempts"]); ?></td>
										<td><?php echo esc_html(
          	wp_date("d/m/Y H:i", strtotime($log["created_at"])),
          ); ?></td>
										<td>
											<?php if ($log["status"] === "success" && !empty($log["pdf_url"])): ?>
												<?php BasePage::echo_component(Components::button(__("PDF", "pwl-dte-for-bsale"), "ghost", [
            	"size" => "sm",
            	"href" => $log["pdf_url"],
            	"attrs" => ["target" => "_blank"],
            ])); ?>
											<?php endif; ?>
											<?php if (in_array($log["status"], ["error", "pending"], true)): ?>
												<?php BasePage::echo_component(Components::button(__('Retry', 'pwl-dte-for-bsale'), "secondary", [
            	"size" => "sm",
            	"attrs" => [
            		"class" =>
            			"wads-btn wads-btn--secondary wads-btn--sm pwl-dte-for-bsale-logs-retry",
            		"data-order-id" => absint($log["order_id"]),
            		"data-nonce" => esc_attr($nonce),
            	],
            ])); ?>
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
						<?php echo wp_kses(
		$this->render_wads_pagination(
      	$current_page,
      	$total_pages,
      	admin_url("admin.php"),
      	["page" => "pwl-dte-for-bsale-logs", "status" => $status_filter],
      ),
		BasePage::allowed_component_html(),
	); ?>
					</div>
				<?php endif; ?>
			</div><!-- /.wads-card -->

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
			"success" => __('Successful', 'pwl-dte-for-bsale'),
			"error" => __('Errors', 'pwl-dte-for-bsale'),
			"pending" => __('Pending', 'pwl-dte-for-bsale'),
			"retrying" => __('Retrying', 'pwl-dte-for-bsale'),
		];
		?>
		<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center;">
			<?php foreach ($dot_variants as $key => $badge_variant):
   	$url = add_query_arg(
   		["page" => "pwl-dte-for-bsale-logs", "status" => $key],
   		admin_url("admin.php"),
   	);
   	$count = $this->db->count_documents($key);
			printf(
				'<a href="%s" style="text-decoration:none;">%s</a>',
				esc_url($url),
				wp_kses(
					Components::badge(
						esc_html($labels[$key]) . ": " . absint($count),
						sanitize_key($badge_variant),
					),
					BasePage::allowed_component_html(),
				),
			);
   endforeach; ?>
			<?php
			printf(
				'<a href="%s" style="text-decoration:none;">%s</a>',
				esc_url(add_query_arg(["page" => "pwl-dte-for-bsale-logs"], admin_url("admin.php"))),
				wp_kses(
					Components::badge(__('View all', 'pwl-dte-for-bsale'), "default"),
					BasePage::allowed_component_html(),
				),
			);
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
					esc_html__('Previous', 'pwl-dte-for-bsale') .
					"</a>"
				: '<span class="wads-page-item is-disabled">← ' .
					esc_html__('Previous', 'pwl-dte-for-bsale') .
					"</span>";

		$next =
			$current < $total_pages
				? '<a class="wads-page-item" href="' .
					$page_url($current + 1) .
					'">' .
					esc_html__('Next', 'pwl-dte-for-bsale') .
					" →</a>"
				: '<span class="wads-page-item is-disabled">' .
					esc_html__('Next', 'pwl-dte-for-bsale') .
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
