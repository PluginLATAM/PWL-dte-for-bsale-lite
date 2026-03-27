<?php
// src/Integration/CheckoutFields.php
namespace PwlDte\Integration;

defined("ABSPATH") || exit();

class CheckoutFields
{
	public function register_hooks(): void
	{
		add_filter("woocommerce_checkout_fields", [$this, "add_invoice_fields"]);
		add_action("woocommerce_after_checkout_validation", [$this, "validate_invoice_fields"], 10, 2);
		add_action("woocommerce_checkout_update_order_meta", [$this, "save_invoice_fields"]);

		add_action("woocommerce_init", [$this, "register_blocks_fields"], 20);
		add_action("woocommerce_validate_additional_field", [$this, "validate_blocks_fields"], 10, 3);
		add_action("woocommerce_store_api_checkout_order_processed", [$this, "map_blocks_fields_to_legacy_meta"]);

		add_action("wp_ajax_pwl_dte_validate_rut", [$this, "ajax_validate_rut"]);
		add_action("wp_ajax_nopriv_pwl_dte_validate_rut", [$this, "ajax_validate_rut"]);

		add_action("woocommerce_admin_order_data_after_billing_address", [$this, "display_admin_order_meta"]);
		add_action("woocommerce_email_after_order_table", [$this, "display_email_order_meta"], 10, 4);
		add_action("wp_enqueue_scripts", [$this, "enqueue_checkout_scripts"]);
	}

	public function add_invoice_fields(array $fields): array
	{
		$fields["billing"]["billing_needs_invoice"] = [
			"type"     => "checkbox",
			"label"    => __("Necesito factura electrónica", 'pwl-dte-for-bsale'),
			"required" => false,
			"class"    => ["form-row-wide"],
			"priority" => 25,
		];
		$fields["billing"]["billing_rut"] = [
			"type"              => "text",
			"label"             => __("RUT", 'pwl-dte-for-bsale'),
			"placeholder"       => __("Ej: 12.345.678-9", 'pwl-dte-for-bsale'),
			"required"          => false,
			"class"             => ["form-row-first", "pwl-dte-for-bsale-invoice-field"],
			"priority"          => 26,
			"custom_attributes" => ["data-validate" => "rut"],
		];
		$fields["billing"]["billing_company_name"] = [
			"type"        => "text",
			"label"       => __("Razón Social", 'pwl-dte-for-bsale'),
			"placeholder" => __("Nombre de la empresa", 'pwl-dte-for-bsale'),
			"required"    => false,
			"class"       => ["form-row-last", "pwl-dte-for-bsale-invoice-field"],
			"priority"    => 27,
		];
		$fields["billing"]["billing_activity"] = [
			"type"        => "text",
			"label"       => __("Giro", 'pwl-dte-for-bsale'),
			"placeholder" => __("Actividad comercial", 'pwl-dte-for-bsale'),
			"required"    => false,
			"class"       => ["form-row-wide", "pwl-dte-for-bsale-invoice-field"],
			"priority"    => 28,
		];
		return $fields;
	}

	public function register_blocks_fields(): void
	{
		if (!function_exists("woocommerce_register_additional_checkout_field")) {
			return;
		}

		woocommerce_register_additional_checkout_field([
			"id"       => "pwl-dte-for-bsale/needs-invoice",
			"label"    => __("Necesito factura electrónica", 'pwl-dte-for-bsale'),
			"location" => "contact",
			"type"     => "checkbox",
			"required" => false,
		]);
		woocommerce_register_additional_checkout_field([
			"id"         => "pwl-dte-for-bsale/rut",
			"label"      => __("RUT", 'pwl-dte-for-bsale'),
			"location"   => "contact",
			"type"       => "text",
			"required"   => false,
			"attributes" => ["autocomplete" => "off"],
		]);
		woocommerce_register_additional_checkout_field([
			"id"       => "pwl-dte-for-bsale/company-name",
			"label"    => __("Razón Social", 'pwl-dte-for-bsale'),
			"location" => "contact",
			"type"     => "text",
			"required" => false,
		]);
		woocommerce_register_additional_checkout_field([
			"id"       => "pwl-dte-for-bsale/activity",
			"label"    => __("Giro", 'pwl-dte-for-bsale'),
			"location" => "contact",
			"type"     => "text",
			"required" => false,
		]);
	}

	public function validate_invoice_fields(array $data, \WP_Error $errors): void
	{
		if (empty($data["billing_needs_invoice"]) || $data["billing_needs_invoice"] !== "1") {
			return;
		}

		$rut = trim($data["billing_rut"] ?? "");
		if (empty($rut)) {
			$errors->add("billing_rut", __("El RUT es obligatorio para emitir factura.", 'pwl-dte-for-bsale'));
		} elseif (!\PwlDte\Core\RutHelper::validate_rut($rut)) {
			$errors->add("billing_rut", __("El RUT ingresado no es válido.", 'pwl-dte-for-bsale'));
		}

		if (empty(trim($data["billing_company_name"] ?? ""))) {
			$errors->add("billing_company_name", __("La Razón Social es obligatoria para emitir factura.", 'pwl-dte-for-bsale'));
		}
	}

	public function validate_blocks_fields(\WP_Error $errors, string $field_key, mixed $field_value): void
	{
		if (!in_array($field_key, ["pwl-dte-for-bsale/rut", "pwl-dte-for-bsale/company-name"], true)) {
			return;
		}

		$body       = json_decode((string) file_get_contents("php://input"), true) ?? [];
		$needs_invoice = !empty($body["additional_fields"]["pwl-dte-for-bsale/needs-invoice"] ?? null);

		if (!$needs_invoice) {
			return;
		}

		if ($field_key === "pwl-dte-for-bsale/rut") {
			$rut = trim((string) $field_value);
			if (empty($rut)) {
				$errors->add("pwl-dte-for-bsale/rut", __("El RUT es obligatorio para emitir factura.", 'pwl-dte-for-bsale'));
			} elseif (!\PwlDte\Core\RutHelper::validate_rut($rut)) {
				$errors->add("pwl-dte-for-bsale/rut", __("El RUT ingresado no es válido.", 'pwl-dte-for-bsale'));
			}
		} elseif ($field_key === "pwl-dte-for-bsale/company-name" && empty(trim((string) $field_value))) {
			$errors->add("pwl-dte-for-bsale/company-name", __("La Razón Social es obligatoria para emitir factura.", 'pwl-dte-for-bsale'));
		}
	}

	public function save_invoice_fields(int $order_id): void
	{
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- protected by WooCommerce checkout nonce (woocommerce-process_checkout)
		$needs_invoice = sanitize_text_field(wp_unslash($_POST["billing_needs_invoice"] ?? "")) === "1";

		if (!$needs_invoice) {
			update_post_meta($order_id, "_billing_needs_invoice", "no");
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			return;
		}

		update_post_meta($order_id, "_billing_needs_invoice", "yes");

		$field_map = [
			"billing_rut"          => "_billing_rut",
			"billing_company_name" => "_billing_company_name",
			"billing_activity"     => "_billing_activity",
		];

		foreach ($field_map as $post_key => $meta_key) {
			if (empty($_POST[$post_key])) {
				continue;
			}
			$value = sanitize_text_field(wp_unslash($_POST[$post_key]));
			if ($post_key === "billing_rut") {
				$value = \PwlDte\Core\RutHelper::format_rut($value);
			}
			update_post_meta($order_id, $meta_key, $value);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public function map_blocks_fields_to_legacy_meta(\WC_Order $order): void
	{
		$needs_invoice = $order->get_meta("_wc_other/pwl-dte-for-bsale/needs-invoice");

		if (empty($needs_invoice) || $needs_invoice === "0") {
			$order->update_meta_data("_billing_needs_invoice", "no");
			$order->save_meta_data();
			return;
		}

		$order->update_meta_data("_billing_needs_invoice", "yes");

		$field_map = [
			"_wc_other/pwl-dte-for-bsale/rut"          => ["meta" => "_billing_rut",          "format_rut" => true],
			"_wc_other/pwl-dte-for-bsale/company-name"  => ["meta" => "_billing_company_name", "format_rut" => false],
			"_wc_other/pwl-dte-for-bsale/activity"      => ["meta" => "_billing_activity",     "format_rut" => false],
		];

		foreach ($field_map as $src => $cfg) {
			$value = sanitize_text_field((string) ($order->get_meta($src) ?? ""));
			if (empty($value)) {
				continue;
			}
			$order->update_meta_data($cfg["meta"], $cfg["format_rut"] ? \PwlDte\Core\RutHelper::format_rut($value) : $value);
		}

		$order->save_meta_data();
	}

	public function display_admin_order_meta(\WC_Order $order): void
	{
		if ($order->get_meta("_billing_needs_invoice") !== "yes") {
			return;
		}

		$fields = [
			"_billing_rut"          => __("RUT:", 'pwl-dte-for-bsale'),
			"_billing_company_name" => __("Razón Social:", 'pwl-dte-for-bsale'),
			"_billing_activity"     => __("Giro:", 'pwl-dte-for-bsale'),
		];

		echo '<div class="order_data_column" style="margin-top:12px;">';
		echo "<h3>" . esc_html__("Datos de Facturación", 'pwl-dte-for-bsale') . "</h3>";
		foreach ($fields as $meta_key => $label) {
			$value = $order->get_meta($meta_key);
			if ($value) {
				printf("<p><strong>%s</strong> %s</p>", esc_html($label), esc_html($value));
			}
		}
		echo "</div>";
	}

	public function display_email_order_meta(\WC_Order $order, bool $sent_to_admin, bool $plain_text, \WC_Email $email): void
	{
		if ($order->get_meta("_billing_needs_invoice") !== "yes") {
			return;
		}

		$fields = [
			"_billing_rut"          => __("RUT:", 'pwl-dte-for-bsale'),
			"_billing_company_name" => __("Razón Social:", 'pwl-dte-for-bsale'),
			"_billing_activity"     => __("Giro:", 'pwl-dte-for-bsale'),
		];

		if ($plain_text) {
			echo "\n" . esc_html(strtoupper(__("Datos de Facturación", 'pwl-dte-for-bsale'))) . "\n";
			foreach ($fields as $meta_key => $label) {
				$value = $order->get_meta($meta_key);
				if ($value) {
					echo esc_html($label) . " " . esc_html($value) . "\n";
				}
			}
			return;
		}
		?>
		<h2><?php esc_html_e("Datos de Facturación", 'pwl-dte-for-bsale'); ?></h2>
		<ul>
			<?php foreach ($fields as $meta_key => $label): ?>
				<?php $value = $order->get_meta($meta_key); ?>
				<?php if ($value): ?>
					<li><strong><?php echo esc_html($label); ?></strong> <?php echo esc_html($value); ?></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	public function ajax_validate_rut(): void
	{
		check_ajax_referer("pwl_dte_validate_rut", "nonce");

		$rut = sanitize_text_field(wp_unslash($_POST["rut"] ?? ""));

		if (empty($rut)) {
			wp_send_json_error(["message" => __("RUT vacío", 'pwl-dte-for-bsale')]);
		}

		if (\PwlDte\Core\RutHelper::validate_rut($rut)) {
			wp_send_json_success([
				"message"   => __("RUT válido", 'pwl-dte-for-bsale'),
				"formatted" => \PwlDte\Core\RutHelper::format_rut($rut),
			]);
		} else {
			wp_send_json_error(["message" => __("RUT inválido", 'pwl-dte-for-bsale')]);
		}
	}

	public function enqueue_checkout_scripts(): void
	{
		if (!is_checkout()) {
			return;
		}

		wp_enqueue_style("pwl-dte-for-bsale-checkout", PWL_DTE_URL . "assets/public/css/checkout.css", [], PWL_DTE_VERSION);

		$localize_data = [
			"ajaxurl" => admin_url("admin-ajax.php"),
			"nonce"   => wp_create_nonce("pwl_dte_validate_rut"),
			"i18n"    => [
				"validating"  => __("Validando…", 'pwl-dte-for-bsale'),
				"rut_valid"   => __("RUT válido", 'pwl-dte-for-bsale'),
				"rut_invalid" => __("RUT inválido", 'pwl-dte-for-bsale'),
			],
		];

		$is_blocks = $this->is_blocks_checkout();
		$handle    = $is_blocks ? "pwl-dte-for-bsale-checkout-blocks" : "pwl-dte-for-bsale-checkout";
		$file      = $is_blocks ? "checkout-blocks.js" : "checkout.js";
		$deps      = $is_blocks ? [] : ["jquery"];
		$object    = $is_blocks ? "wooBsaleCheckoutBlocks" : "wooBsaleCheckout";

		wp_enqueue_script($handle, PWL_DTE_URL . "assets/public/js/{$file}", $deps, PWL_DTE_VERSION, true);
		wp_localize_script($handle, $object, $localize_data);
	}

	private function is_blocks_checkout(): bool
	{
		$page_id = wc_get_page_id("checkout");
		$page    = $page_id > 0 ? get_post($page_id) : null;
		return $page && has_block("woocommerce/checkout", $page);
	}
}
