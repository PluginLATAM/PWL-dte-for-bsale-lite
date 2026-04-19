<?php
// src/Admin/OrderMetabox.php
namespace PwlDte\Admin;

defined("ABSPATH") || exit();

use UserDOMP\WpAdminDS\Components;

/**
 * Metabox on the WooCommerce order edit screen.
 * Shows DTE status, folio, PDF links and action buttons.
 * Supports both legacy (post-based) and HPOS (WC_Order) order screens.
 */
class OrderMetabox
{
	private \PwlDte\Core\Database $db;

	public function __construct()
	{
		$this->db = new \PwlDte\Core\Database();
	}

	public function register_hooks(): void
	{
		add_action("add_meta_boxes", [$this, "add_metabox"]);
		add_action("admin_enqueue_scripts", [$this, "enqueue_scripts"]);
	}

	public function enqueue_scripts(string $hook): void
	{
		$screen = get_current_screen();
		if (!$screen || !in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'], true)) {
			return;
		}

		wp_enqueue_script(
			'pwl-dte-for-bsale-metabox',
			PWL_DTE_URL . "src/Admin/js/order-metabox.js",
			['jquery'],
			PWL_DTE_VERSION,
			true,
		);
		wp_localize_script('pwl-dte-for-bsale-metabox', 'pwlDteMetabox', [
			'labels' => [
				'confirm'         => __('Confirm this action?', 'pwl-dte-for-bsale'),
				'processing'      => __('Processing…', 'pwl-dte-for-bsale'),
				'success'         => __('Operation successful', 'pwl-dte-for-bsale'),
				'errorPrefix'     => __('Error: ', 'pwl-dte-for-bsale'),
				'unknownError'    => __('Unknown error', 'pwl-dte-for-bsale'),
				'connectionError' => __('Connection error', 'pwl-dte-for-bsale'),
			],
		]);
	}

	public function add_metabox(): void
	{
		foreach (["shop_order", "woocommerce_page_wc-orders"] as $screen) {
			add_meta_box(
				"pwl_dte_dte",
				__('Bsale — Tax document', 'pwl-dte-for-bsale'),
				$screen === "shop_order" ? [$this, "render_metabox_post"] : [$this, "render_metabox_hpos"],
				$screen,
				"side",
				"high",
			);
		}
	}

	public function render_metabox_post(\WP_Post $post): void
	{
		$this->render($post->ID);
	}

	public function render_metabox_hpos(\WC_Order $order): void
	{
		$this->render($order->get_id());
	}

	private function render(int $order_id): void
	{
		$doc   = $this->db->get_document_by_order_id($order_id);
		$nonce = wp_create_nonce("pwl_dte_dte_actions");

		if (!$doc) {
			$this->render_no_document($order_id, $nonce);
			return;
		}

		$this->render_document_info($doc, $order_id, $nonce);
	}

	private function render_no_document(int $order_id, string $nonce): void
	{
		echo '<div class="wads">';
		BasePage::echo_component(Components::notice(
			__('No DTE has been generated for this order.', 'pwl-dte-for-bsale'),
			"warning",
		));
		echo '<div style="margin-top:10px;">';
		printf(
			'<button type="button" class="wads-btn wads-btn--primary pwl-dte-for-bsale-dte-action" data-action="pwl_dte_regenerate_dte" data-order-id="%d" data-nonce="%s" data-label-done="%s">%s</button>',
			absint($order_id),
			esc_attr($nonce),
			esc_attr__('Generate DTE', 'pwl-dte-for-bsale'),
			esc_html__('Generate DTE', 'pwl-dte-for-bsale'),
		);
		echo '</div></div>';

	}

	private function render_document_info(array $doc, int $order_id, string $nonce): void
	{
		$cfg          = Admin::status_config($doc["status"]);
		$status       = $doc["status"];
		$status_badge = Components::badge($cfg["label"], $cfg["variant"]);

		// Build KV pairs
		$kv = [__('Status', 'pwl-dte-for-bsale') => $status_badge];

		if ($status === "success") {
			$kv[__('Type', 'pwl-dte-for-bsale')]  = esc_html(ucfirst($doc["document_type"]));
			$kv[__('Folio', 'pwl-dte-for-bsale')] = esc_html($doc["folio"] ?: "—");
		}

		if ($status === "error") {
			$kv[__('Attempts', 'pwl-dte-for-bsale')] = absint($doc["attempts"]);
		}

		/* translators: %s: datetime */
		$kv[__('Updated', 'pwl-dte-for-bsale')] = '<span style="font-size:11px;color:#999;">'
			. esc_html(wp_date("d/m/Y H:i", strtotime($doc["updated_at"])))
			. '</span>';

		echo '<div class="wads">';
		BasePage::echo_component(Components::kv_list($kv));

		if ($status === "error" && !empty($doc["error_message"])) {
			BasePage::echo_component(Components::notice(esc_html($doc["error_message"]), "danger"));
		} elseif ($status === "pending") {
			BasePage::echo_component(Components::notice(__('The DTE is being processed…', 'pwl-dte-for-bsale'), "warning"));
		}

		echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px;">';

		if ($status === "success") {
			if (!empty($doc["pdf_url"])) {
				BasePage::echo_component(Components::button(__('View PDF', 'pwl-dte-for-bsale'), "ghost", ["size" => "sm", "href" => $doc["pdf_url"], "attrs" => ["target" => "_blank"]]));
			}
			if (!empty($doc["public_url"])) {
				BasePage::echo_component(Components::button(__('View online', 'pwl-dte-for-bsale'), "ghost", ["size" => "sm", "href" => $doc["public_url"], "attrs" => ["target" => "_blank"]]));
			}
			printf(
				'<button type="button" class="wads-btn wads-btn--secondary wads-btn--sm pwl-dte-for-bsale-dte-action"
					data-action="pwl_dte_send_dte_email"
					data-order-id="%d"
					data-nonce="%s"
					data-label-done="%s">%s</button>',
				absint($order_id),
				esc_attr($nonce),
				esc_attr__('Resend email', 'pwl-dte-for-bsale'),
				esc_html__('Resend email', 'pwl-dte-for-bsale'),
			);
		} elseif ($status === "error") {
			printf(
				'<button type="button" class="wads-btn wads-btn--primary wads-btn--sm pwl-dte-for-bsale-dte-action"
					data-action="pwl_dte_regenerate_dte"
					data-order-id="%d"
					data-nonce="%s"
					data-label-done="%s">%s</button>',
				absint($order_id),
				esc_attr($nonce),
				esc_attr__('Retry', 'pwl-dte-for-bsale'),
				esc_html__('Retry', 'pwl-dte-for-bsale'),
			);
		}

		echo '</div></div>';
	}

}
