<?php
// src/Integration/DocumentEngine.php
namespace PwlDte\Integration;

defined("ABSPATH") || exit();

class DocumentEngine
{
	protected \PwlDte\Api\BsaleClient $client;
	protected \PwlDte\Core\Database $db;

	public function __construct()
	{
		$this->db = new \PwlDte\Core\Database();
		$this->client = new \PwlDte\Api\BsaleClient(get_option("pwl_dte_api_token", ""));
	}

	public function register_hooks(): void
	{
		add_action("woocommerce_order_status_completed", [$this, "generate_dte_for_order"], 10, 2);
		add_action("wp_ajax_pwl_dte_regenerate_dte", [$this, "ajax_regenerate_dte"]);
		add_action("wp_ajax_pwl_dte_send_dte_email", [$this, "ajax_send_dte_email"]);
	}

	public function generate_dte_for_order(int $order_id, $order = null): bool
	{
		$order = $order instanceof \WC_Order ? $order : wc_get_order($order_id);

		if (!$order) {
			$this->log_error($order_id, "Orden no encontrada");
			return false;
		}

		$existing = $this->db->get_document_by_order_id($order_id);
		if ($existing && $existing["status"] === "success") {
			$this->log_info($order_id, "Ya existe un DTE exitoso para esta orden");
			return true;
		}

		$doc_type  = $this->determine_document_type($order);
		$code_sii  = $doc_type === "factura" ? 33 : 39;
		$office_id = $this->resolve_office_for_order($order);
		$payload   = $this->build_document_payload($order, $code_sii, $office_id);

		if (!$payload) {
			$error_msg = $code_sii === 33
				? "Error al construir payload: faltan datos del receptor para factura (RUT, razón social)"
				: "Error al construir payload del documento";
			$this->save_failed_attempt($order_id, $doc_type, $code_sii, $error_msg);
			$this->log_error($order_id, $error_msg);
			return false;
		}

		$log_id = $this->prepare_document_record($order_id, $doc_type, $code_sii, $office_id);

		if (!$log_id) {
			$this->log_error($order_id, "Error al insertar/recuperar registro en DB");
			return false;
		}

		$result = $this->client->create_document($payload);

		if ($result["success"]) {
			$this->handle_success($log_id, $order, $result["data"]);
			return true;
		}

		$this->handle_error($log_id, $order_id, $result["error"] ?? "Error desconocido");
		return false;
	}

	protected function determine_document_type(\WC_Order $order): string
	{
		return $order->get_meta("_billing_needs_invoice") === "yes"
			? "factura"
			: get_option("pwl_dte_default_doc_type", "boleta");
	}

	protected function build_document_payload(
		\WC_Order $order,
		int $code_sii,
		int $office_id,
	): ?array {
		$payload = [
			"codeSii"       => $code_sii,
			"officeId"      => $office_id,
			"emissionDate"  => time(),
			"declareSii"    => get_option("pwl_dte_auto_declare_sii", "1") === "1" ? 1 : 0,
			"sendEmail"     => get_option("pwl_dte_auto_send_email", "1") === "1" ? 1 : 0,
			"dispatch"      => get_option("pwl_dte_auto_dispatch", "1") === "1" ? 1 : 0,
			"referenceId"   => "woo_" . $order->get_id(),
			"details"       => [],
		];

		$price_list_id = get_option("pwl_dte_price_list_id", "");
		if (!empty($price_list_id)) {
			$payload["priceListId"] = (int) $price_list_id;
		}

		if ($code_sii === 33) {
			$client_data = $this->build_client_data($order);
			if (!$client_data) {
				return null;
			}
			$payload["client"] = $client_data;
		}

		foreach ($order->get_items() as $item) {
			/** @var \WC_Order_Item_Product $item */
			$detail = $this->build_detail_from_item($item);
			if ($detail) {
				$payload["details"][] = $detail;
			}
		}

		if (empty($payload["details"])) {
			return null;
		}

		$shipping_total = (float) $order->get_shipping_total();
		if ($shipping_total > 0) {
			$payload["details"][] = [
				"netUnitValue" => round($shipping_total / 1.19, 2),
				"quantity"     => 1,
				"comment"      => $order->get_shipping_method() ?: __('Shipping', 'pwl-dte-for-bsale'),
				"taxes"        => [["code" => 14, "percentage" => 19]],
			];
		}

		return $payload;
	}

	protected function build_client_data(\WC_Order $order): ?array
	{
		$rut     = $order->get_meta("_billing_rut");
		$company = $order->get_meta("_billing_company_name");

		if (empty($rut) || empty($company) || !\PwlDte\Core\RutHelper::validate_rut($rut)) {
			return null;
		}

		return [
			"code"            => \PwlDte\Core\RutHelper::clean_rut($rut),
			"company"         => sanitize_text_field($company),
			"activity"        => sanitize_text_field($order->get_meta("_billing_activity") ?: "Comercio general"),
			"municipality"    => sanitize_text_field($order->get_billing_city()),
			"city"            => sanitize_text_field($order->get_billing_state()),
			"address"         => sanitize_text_field($order->get_billing_address_1()),
			"email"           => sanitize_email($order->get_billing_email()),
			"companyOrPerson" => 1,
		];
	}

	protected function build_detail_from_item(\WC_Order_Item_Product $item): ?array
	{
		$product = $item->get_product();
		if (!$product) {
			return null;
		}

		$qty    = (int) $item->get_quantity();
		$detail = [
			"netUnitValue" => $qty > 0 ? round((float) $item->get_total() / 1.19 / $qty, 2) : 0,
			"quantity"     => $qty,
			"comment"      => $item->get_name(),
			"discount"     => 0,
			"taxes"        => [["code" => 14, "percentage" => 19]],
		];

		$sku = $product->get_sku();
		if (!empty($sku)) {
			$detail["code"] = $sku;
		}

		return $detail;
	}

	protected function handle_success(int $log_id, \WC_Order $order, array $response): void
	{
		$this->db->update_document($log_id, [
			"bsale_document_id" => (int) ($response["id"] ?? 0),
			"document_number"   => (int) ($response["number"] ?? 0),
			"folio"             => (string) ($response["number"] ?? ""),
			"status"            => "success",
			"pdf_url"           => $response["urlPdf"] ?? null,
			"public_url"        => $response["urlPublicView"] ?? null,
			"token"             => $response["token"] ?? null,
		]);

		$order->update_meta_data("_pwl_dte_document_id",     $response["id"] ?? "");
		$order->update_meta_data("_pwl_dte_document_number", $response["number"] ?? "");
		$order->update_meta_data("_pwl_dte_pdf_url",         $response["urlPdf"] ?? "");
		$order->update_meta_data("_pwl_dte_public_url",      $response["urlPublicView"] ?? "");
		$order->save();

		$note = sprintf(
			/* translators: %s: document folio number */
			__('DTE generated successfully in Bsale. Folio: %s', 'pwl-dte-for-bsale'),
			$response["number"] ?? "—",
		);
		if (!empty($response["urlPdf"])) {
			$note .= "\n" . __('PDF:', 'pwl-dte-for-bsale') . " " . $response["urlPdf"];
		}
		$order->add_order_note($note);

		$this->log_info($order->get_id(), "DTE generado exitosamente. Folio: " . ($response["number"] ?? "?"));
	}

	protected function handle_error(int $log_id, int $order_id, string $error): void
	{
		$this->db->update_document($log_id, ["status" => "error", "error_message" => $error]);
		$this->db->increment_attempts($log_id);

		$order = wc_get_order($order_id);
		if ($order) {
			$order->add_order_note(sprintf(
				/* translators: %s: Bsale API or validation error message */
				__('Error generating DTE in Bsale: %s', 'pwl-dte-for-bsale'),
				$error,
			));
		}

		$this->log_error($order_id, "Error al generar DTE: " . $error);
	}

	protected function save_failed_attempt(
		int $order_id,
		string $doc_type,
		int $code_sii,
		string $error_msg,
	): void {
		$existing = $this->db->get_document_by_order_id($order_id);

		if ($existing) {
			$this->db->update_document($existing["id"], ["status" => "error", "error_message" => $error_msg]);
			$this->db->increment_attempts($existing["id"]);
		} else {
			$this->db->insert_document([
				"order_id"      => $order_id,
				"document_type" => $doc_type,
				"code_sii"      => $code_sii,
				"status"        => "error",
				"error_message" => $error_msg,
				"attempts"      => 1,
			]);
		}

		$order = wc_get_order($order_id);
		if ($order) {
			$order->add_order_note(sprintf(
				/* translators: %s: Bsale API or validation error message */
				__('Error generating DTE in Bsale: %s', 'pwl-dte-for-bsale'),
				$error_msg,
			));
		}
	}

	protected function send_dte_email(\WC_Order $order, array $document): void
	{
		wp_mail(
			$order->get_billing_email(),
			sprintf(
				/* translators: %s: order number */
				__('Tax document — Order #%s', 'pwl-dte-for-bsale'),
				$order->get_order_number(),
			),
			sprintf(
				/* translators: 1: first name, 2: document number, 3: public URL, 4: PDF URL */
				__(
					'Hello %1$s,

Your tax document has been generated successfully.

Document number: %2$s
View document: %3$s
Download PDF: %4$s

Thank you for your purchase.',
					'pwl-dte-for-bsale'
				),
				$order->get_billing_first_name(),
				$document["number"] ?? "—",
				$document["urlPublicView"] ?? "",
				$document["urlPdf"] ?? "",
			),
			["Content-Type: text/plain; charset=UTF-8"],
		);
	}

	public function ajax_regenerate_dte(): void
	{
		check_ajax_referer("pwl_dte_dte_actions", "nonce");

		if (!current_user_can("manage_woocommerce")) {
			wp_send_json_error(["message" => __('Not authorized', 'pwl-dte-for-bsale')], 403);
		}

		$order_id = absint($_POST["order_id"] ?? 0);
		if (!$order_id) {
			wp_send_json_error(["message" => __('Invalid order ID', 'pwl-dte-for-bsale')], 400);
		}

		$existing = $this->db->get_document_by_order_id($order_id);
		if ($existing && $existing["status"] === "success") {
			wp_send_json_error(["message" => __('A successful DTE already exists for this order', 'pwl-dte-for-bsale')], 409);
		}

		if ($this->generate_dte_for_order($order_id)) {
			wp_send_json_success(["message" => __('DTE regenerated successfully', 'pwl-dte-for-bsale')]);
		} else {
			wp_send_json_error(["message" => __('Could not regenerate DTE. Check the logs.', 'pwl-dte-for-bsale')]);
		}
	}

	public function ajax_send_dte_email(): void
	{
		check_ajax_referer("pwl_dte_dte_actions", "nonce");

		if (!current_user_can("manage_woocommerce")) {
			wp_send_json_error(["message" => __('Not authorized', 'pwl-dte-for-bsale')], 403);
		}

		$order_id = absint($_POST["order_id"] ?? 0);
		$order    = $order_id ? wc_get_order($order_id) : null;

		if (!$order) {
			wp_send_json_error(["message" => __('Order not found', 'pwl-dte-for-bsale')], 404);
		}

		$doc = $this->db->get_document_by_order_id($order_id);
		if (!$doc || $doc["status"] !== "success") {
			wp_send_json_error(["message" => __('There is no successful DTE for this order', 'pwl-dte-for-bsale')], 400);
		}

		$this->send_dte_email($order, [
			"number"        => $doc["document_number"],
			"urlPublicView" => $doc["public_url"],
			"urlPdf"        => $doc["pdf_url"],
		]);

		wp_send_json_success(["message" => __('Email sent', 'pwl-dte-for-bsale')]);
	}

	// Template method hooks — override in Pro subclass

	/**
	 * Resolve the Bsale office ID for a given order.
	 * Pro edition overrides to check pwl_dte_office_map (shipping method → office).
	 */
	protected function resolve_office_for_order(\WC_Order $order): int
	{
		return (int) get_option("pwl_dte_office_id", 1);
	}

	/**
	 * Insert a new document record.
	 * Pro edition overrides to reuse an existing 'error' record for the same order.
	 */
	protected function prepare_document_record(
		int $order_id,
		string $doc_type,
		int $code_sii,
		int $office_id,
	): int {
		return (int) $this->db->insert_document([
			"order_id"      => $order_id,
			"document_type" => $doc_type,
			"code_sii"      => $code_sii,
			"office_id"     => $office_id,
			"status"        => "pending",
			"attempts"      => 1,
		]);
	}

	protected function log_info(int $order_id, string $message): void
	{
		if (in_array(get_option("pwl_dte_log_level", "errors"), ["info", "debug"], true)) {
			$logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
			if ($logger) {
				$logger->info("Order #{$order_id}: {$message}", ["source" => "pwl-dte-document-engine"]);
			}
		}
	}

	protected function log_error(int $order_id, string $message): void
	{
		if (get_option("pwl_dte_log_level", "errors") !== "none") {
			$logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
			if ($logger) {
				$logger->error("Order #{$order_id}: {$message}", ["source" => "pwl-dte-document-engine"]);
			}
		}
	}
}
