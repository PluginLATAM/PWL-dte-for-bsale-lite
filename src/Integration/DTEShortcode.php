<?php
// src/Integration/DTEShortcode.php
namespace PwlDte\Integration;

defined("ABSPATH") || exit();

class DTEShortcode
{
	private \PwlDte\Core\Database $db;

	public function __construct()
	{
		$this->db = new \PwlDte\Core\Database();
	}

	public function register_hooks(): void
	{
		add_shortcode("pwl_dte", [$this, "render_dte_shortcode"]);
		add_filter("woocommerce_thankyou_order_received_text", [$this, "add_dte_to_thankyou"], 20, 2);
	}

	public function render_dte_shortcode($atts): string
	{
		$atts     = shortcode_atts(["order_id" => 0], $atts, "pwl_dte");
		$order_id = absint($atts["order_id"]);

		if (!$order_id) {
			return "";
		}

		$doc = $this->db->get_document_by_order_id($order_id);

		if (!$doc || $doc["status"] !== "success") {
			return "";
		}

		return $this->render_dte_card($doc);
	}

	private function render_dte_card(array $doc): string
	{
		$labels       = [
			"boleta"       => __('Boleta (receipt)', 'pwl-dte-for-bsale'),
			"factura"      => __('Factura (invoice)', 'pwl-dte-for-bsale'),
			"nota_credito" => __('Credit note', 'pwl-dte-for-bsale'),
		];
		$type_display = $labels[$doc["document_type"]] ?? ucfirst($doc["document_type"]);

		ob_start();
		?>
		<div class="pwl-dte-for-bsale-dte-card">
			<div class="pwl-dte-for-bsale-dte-card__icon">📄</div>
			<div class="pwl-dte-for-bsale-dte-card__content">
				<h3 class="pwl-dte-for-bsale-dte-card__title">
					<?php printf(
						/* translators: %s = document type (Boleta / Factura) */
						esc_html__('%s issued', 'pwl-dte-for-bsale'),
						esc_html($type_display),
					); ?>
				</h3>
				<p class="pwl-dte-for-bsale-dte-card__folio">
					<strong><?php esc_html_e('Folio:', 'pwl-dte-for-bsale'); ?></strong>
					<?php echo esc_html($doc["folio"] ?? "—"); ?>
				</p>
				<div class="pwl-dte-for-bsale-dte-card__actions">
					<?php if (!empty($doc["pdf_url"])): ?>
						<a href="<?php echo esc_url($doc["pdf_url"]); ?>" class="pwl-dte-for-bsale-btn pwl-dte-for-bsale-btn--primary" target="_blank" rel="noopener noreferrer">
							📥 <?php esc_html_e('Download PDF', 'pwl-dte-for-bsale'); ?>
						</a>
					<?php endif; ?>
					<?php if (!empty($doc["public_url"])): ?>
						<a href="<?php echo esc_url($doc["public_url"]); ?>" class="pwl-dte-for-bsale-btn pwl-dte-for-bsale-btn--secondary" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e('View document online', 'pwl-dte-for-bsale'); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php return ob_get_clean();
	}

	public function add_dte_to_thankyou(string $text, ?\WC_Order $order): string
	{
		if (!$order) {
			return $text;
		}
		return $text . $this->render_dte_shortcode(["order_id" => $order->get_id()]);
	}
}
