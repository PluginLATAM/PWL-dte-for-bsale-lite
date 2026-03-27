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

		wp_register_script('pwl-dte-for-bsale-metabox', false, ['jquery'], PWL_DTE_VERSION, true);
		wp_localize_script('pwl-dte-for-bsale-metabox', 'pwlDteMetabox', [
			'labels' => [
				'confirm'         => __('¿Confirmar acción?', 'pwl-dte-for-bsale'),
				'processing'      => __('Procesando...', 'pwl-dte-for-bsale'),
				'success'         => __('Operación exitosa', 'pwl-dte-for-bsale'),
				'errorPrefix'     => __('Error: ', 'pwl-dte-for-bsale'),
				'unknownError'    => __('Error desconocido', 'pwl-dte-for-bsale'),
				'connectionError' => __('Error de conexión', 'pwl-dte-for-bsale'),
			],
		]);

		$js = <<<'JS'
		(function($){
			$(document).on('click','.pwl-dte-for-bsale-dte-action',function(){
				var $btn=$(this),action=$btn.data('action'),label=$btn.data('label-done');
				if(!confirm(pwlDteMetabox.labels.confirm))return;
				$btn.prop('disabled',true).text(pwlDteMetabox.labels.processing);
				$.ajax({
					url:ajaxurl,method:'POST',
					data:{action:action,nonce:$btn.data('nonce'),order_id:$btn.data('order-id')},
					success:function(r){
						if(r.success){
							alert(r.data.message||pwlDteMetabox.labels.success);
							if(action==='pwl_dte_regenerate_dte')location.reload();
							else $btn.prop('disabled',false).text(label);
						}else{
							alert(pwlDteMetabox.labels.errorPrefix+(r.data&&r.data.message?r.data.message:pwlDteMetabox.labels.unknownError));
							$btn.prop('disabled',false).text(label);
						}
					},
					error:function(){
						alert(pwlDteMetabox.labels.connectionError);
						$btn.prop('disabled',false).text(label);
					}
				});
			});
		})(jQuery);
		JS;

		wp_add_inline_script('pwl-dte-for-bsale-metabox', $js);
		wp_enqueue_script('pwl-dte-for-bsale-metabox');
	}

	public function add_metabox(): void
	{
		foreach (["shop_order", "woocommerce_page_wc-orders"] as $screen) {
			add_meta_box(
				"pwl_dte_dte",
				__("Bsale — Documento Tributario", 'pwl-dte-for-bsale'),
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
		$order_id_safe = absint($order_id);
		$nonce_safe    = esc_attr($nonce);
		$label         = esc_attr__("Generar DTE", 'pwl-dte-for-bsale');
		$label_text    = esc_html__("Generar DTE", 'pwl-dte-for-bsale');

		echo '<div class="wads">';
		echo Components::notice(
			__("No se ha generado DTE para esta orden.", 'pwl-dte-for-bsale'),
			"warning",
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
		echo <<<HTML
		<div style="margin-top:10px;">
			<button type="button" class="wads-btn wads-btn--primary pwl-dte-for-bsale-dte-action"
				data-action="pwl_dte_regenerate_dte"
				data-order-id="{$order_id_safe}"
				data-nonce="{$nonce_safe}"
				data-label-done="{$label}">{$label_text}</button>
		</div></div>
		HTML;

	}

	private function render_document_info(array $doc, int $order_id, string $nonce): void
	{
		$cfg          = Admin::status_config($doc["status"]);
		$status       = $doc["status"];
		$status_badge = Components::badge($cfg["label"], $cfg["variant"]);

		// Build KV pairs
		$kv = [__("Estado", 'pwl-dte-for-bsale') => $status_badge];

		if ($status === "success") {
			$kv[__("Tipo", 'pwl-dte-for-bsale')]  = esc_html(ucfirst($doc["document_type"]));
			$kv[__("Folio", 'pwl-dte-for-bsale')] = esc_html($doc["folio"] ?: "—");
		}

		if ($status === "error") {
			$kv[__("Intentos", 'pwl-dte-for-bsale')] = absint($doc["attempts"]);
		}

		/* translators: %s: datetime */
		$kv[__("Actualizado", 'pwl-dte-for-bsale')] = '<span style="font-size:11px;color:#999;">'
			. esc_html(wp_date("d/m/Y H:i", strtotime($doc["updated_at"])))
			. '</span>';

		echo '<div class="wads">';
		echo Components::kv_list($kv); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally

		if ($status === "error" && !empty($doc["error_message"])) {
			echo Components::notice(esc_html($doc["error_message"]), "danger"); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
		} elseif ($status === "pending") {
			echo Components::notice(__("El DTE está siendo procesado...", 'pwl-dte-for-bsale'), "warning"); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
		}

		echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px;">';

		$order_id_safe = absint($order_id);
		$nonce_safe    = esc_attr($nonce);

		if ($status === "success") {
			if (!empty($doc["pdf_url"])) {
				echo Components::button(__("Ver PDF", 'pwl-dte-for-bsale'), "ghost", ["size" => "sm", "href" => $doc["pdf_url"], "attrs" => ["target" => "_blank"]]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
			}
			if (!empty($doc["public_url"])) {
				echo Components::button(__("Ver Online", 'pwl-dte-for-bsale'), "ghost", ["size" => "sm", "href" => $doc["public_url"], "attrs" => ["target" => "_blank"]]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Components escapes internally
			}
			$label      = esc_attr__("Reenviar Email", 'pwl-dte-for-bsale');
			$label_text = esc_html__("Reenviar Email", 'pwl-dte-for-bsale');
			printf(
				'<button type="button" class="wads-btn wads-btn--secondary wads-btn--sm pwl-dte-for-bsale-dte-action"
					data-action="pwl_dte_send_dte_email"
					data-order-id="%d"
					data-nonce="%s"
					data-label-done="%s">%s</button>',
				$order_id_safe,
				$nonce_safe,
				$label,
				$label_text,
			);
		} elseif ($status === "error") {
			$label      = esc_attr__("Reintentar", 'pwl-dte-for-bsale');
			$label_text = esc_html__("Reintentar", 'pwl-dte-for-bsale');
			printf(
				'<button type="button" class="wads-btn wads-btn--primary wads-btn--sm pwl-dte-for-bsale-dte-action"
					data-action="pwl_dte_regenerate_dte"
					data-order-id="%d"
					data-nonce="%s"
					data-label-done="%s">%s</button>',
				$order_id_safe,
				$nonce_safe,
				$label,
				$label_text,
			);
		}

		echo '</div></div>';
	}

}
