<?php
namespace PwlDte\Integration;

defined("ABSPATH") || exit();

class StockSync
{
	/** Products fetched per DB query. Keeps memory bounded. */
	private const BATCH_SIZE = 200;

	/** Products processed per AJAX chunk (for progress bar). */
	private const CHUNK_SIZE = 25;

	protected \PwlDte\Api\BsaleClient $client;

	/**
	 * Error details collected during a chunk run.
	 * Each entry: [ 'sku' => string, 'name' => string, 'error' => string ]
	 *
	 * @var array<int,array{sku:string,name:string,error:string}>
	 */
	protected array $chunk_errors = [];

	public function __construct()
	{
		$this->client = new \PwlDte\Api\BsaleClient(\get_option("pwl_dte_api_token", ""));
	}

	public function register_hooks(): void
	{
		\add_action("wp_ajax_pwl_dte_manual_stock_sync", [$this, "ajax_manual_sync"]);
		\add_action("wp_ajax_pwl_dte_sync_chunk", [$this, "ajax_sync_chunk"]);
	}

	public function sync_stock_from_bsale(): array
	{
		$this->log("INFO", "Iniciando sincronización de stock desde Bsale");

		$synced = $errors = $skipped = 0;
		$page   = 1;

		while (true) {
			$product_ids = \wc_get_products([
				"status" => "publish",
				"limit"  => self::BATCH_SIZE,
				"page"   => $page,
				"return" => "ids",
			]);

			if (empty($product_ids)) {
				break;
			}

			foreach ($product_ids as $product_id) {
				try {
					[$s, $e, $sk] = $this->process_product_id($product_id);
					$synced  += $s;
					$errors  += $e;
					$skipped += $sk;
				} catch (\Throwable $t) {
					$errors++;
					$this->log("ERROR", "Excepción procesando producto #{$product_id}: " . $t->getMessage());
				}
			}

			if (\count($product_ids) < self::BATCH_SIZE) {
				break;
			}

			$page++;
		}

		$this->log("INFO", "Sincronización completada — Sincronizados: {$synced}, Errores: {$errors}, Omitidos: {$skipped}");
		\update_option("pwl_dte_last_stock_sync", \time());

		return \compact("synced", "errors", "skipped");
	}

	/** @return bool|null true=synced, false=error, null=skipped */
	protected function process_simple_product(\WC_Product $product): ?bool
	{
		$sku = $product->get_sku();

		if (empty($sku) || !$product->get_manage_stock()) {
			$this->log("DEBUG", "Producto #{$product->get_id()} sin SKU o sin gestión de stock — omitido");
			return null;
		}

		return $this->sync_single_product($sku, $product);
	}

	/** @return array<bool|null> */
	protected function process_variable_product(\WC_Product_Variable $product): array
	{
		$results = [];

		foreach ($product->get_children() as $variation_id) {
			$variation = \wc_get_product($variation_id);

			if (!$variation) {
				continue;
			}

			$sku = $variation->get_sku();

			if (empty($sku)) {
				$this->log("DEBUG", "Variación #{$variation_id} sin SKU — omitida");
				$results[] = null;
				continue;
			}

			// A variation manages stock if it does so individually OR if the parent does.
			if (!$variation->get_manage_stock() && !$product->get_manage_stock()) {
				$this->log("DEBUG", "Variación #{$variation_id} (SKU: {$sku}) sin gestión de stock — omitida");
				$results[] = null;
				continue;
			}

			$results[] = $this->sync_single_product($sku, $variation);
		}

		return $results;
	}

	/**
	 * Fetch stock from Bsale for one SKU and update WooCommerce.
	 * Clamps negative quantityAvailable to 0 (Bsale can report negative when
	 * reservations exceed physical stock).
	 */
	public function sync_single_product(string $sku, \WC_Product $product): bool
	{
		$result = $this->client->get_stock($sku, $this->resolve_stock_office_id());

		if (!$result["success"]) {
			$error_msg = $result["error"] ?? "Error desconocido";
			$this->log("ERROR", "SKU '{$sku}': error Bsale — " . $error_msg);
			$this->chunk_errors[] = ["sku" => $sku, "name" => $product->get_name(), "error" => $error_msg];
			return false;
		}

		$this->log("DEBUG", "SKU '{$sku}': respuesta Bsale — " . \wp_json_encode($result["data"]));

		$items = $result["data"]["items"] ?? [];

		if (empty($items)) {
			$this->log("ERROR", "SKU '{$sku}': no encontrado en Bsale (items vacío)");
			$this->chunk_errors[] = ["sku" => $sku, "name" => $product->get_name(), "error" => "SKU no encontrado en Bsale"];
			return false;
		}

		$raw_qty = (float) ($items[0]["quantityAvailable"] ?? ($items[0]["quantity"] ?? 0));
		$qty     = \max(0, $raw_qty);

		if ($raw_qty < 0) {
			$this->log("INFO", "SKU '{$sku}': stock negativo en Bsale ({$raw_qty}) → normalizado a 0");
		}

		$old_stock = $product->get_stock_quantity();
		\wc_update_product_stock($product, (int) $qty, "set");
		$this->log("INFO", "SKU '{$sku}': stock {$old_stock} → {$qty}");

		return true;
	}

	/** Sync by SKU string only (called from WebhookHandler). */
	public function sync_by_sku(string $sku): bool
	{
		$product_id = \wc_get_product_id_by_sku($sku);

		if (!$product_id) {
			$this->log("INFO", "SKU '{$sku}' no encontrado en WooCommerce");
			return false;
		}

		$product = \wc_get_product($product_id);

		return $product ? $this->sync_single_product($sku, $product) : false;
	}

	public function ajax_manual_sync(): void
	{
		\check_ajax_referer("pwl_dte_stock_sync", "nonce");

		if (!\current_user_can("manage_woocommerce")) {
			\wp_send_json_error(["message" => \__('Not authorized', 'pwl-dte-for-bsale')], 403);
		}

		$counts    = $this->sync_stock_from_bsale();
		$last_sync = \get_option("pwl_dte_last_stock_sync");

		\wp_send_json_success([
			"message" => \sprintf(
				/* translators: 1: updated count, 2: error count, 3: skipped count */
				\__('Sync finished: %1$d updated, %2$d errors, %3$d skipped', 'pwl-dte-for-bsale'),
				$counts["synced"],
				$counts["errors"],
				$counts["skipped"],
			),
			"last_sync" => $last_sync ? \wp_date("Y-m-d H:i:s", $last_sync) : \__('Never', 'pwl-dte-for-bsale'),
		]);
	}

	/**
	 * AJAX handler for chunked sync with progress reporting.
	 * Processes CHUNK_SIZE products starting at $offset and returns
	 * { synced, errors, skipped, processed, total, done, next_offset, last_sync }.
	 */
	public function ajax_sync_chunk(): void
	{
		\check_ajax_referer("pwl_dte_stock_sync", "nonce");

		if (!\current_user_can("manage_woocommerce")) {
			\wp_send_json_error(["message" => \__('Not authorized', 'pwl-dte-for-bsale')], 403);
		}

		$offset = \absint($_POST["offset"] ?? 0);
		$total  = \absint($_POST["total"] ?? 0);

		if ($total === 0) {
			$total = (int) \wc_get_products(["status" => "publish", "limit" => 1, "paginate" => true])->total;
		}

		$product_ids        = \wc_get_products(["status" => "publish", "limit" => self::CHUNK_SIZE, "offset" => $offset, "return" => "ids"]);
		$synced             = $errors = $skipped = 0;
		$this->chunk_errors = [];

		foreach ($product_ids as $product_id) {
			try {
				[$s, $e, $sk] = $this->process_product_id($product_id);
				$synced  += $s;
				$errors  += $e;
				$skipped += $sk;
			} catch (\Throwable $t) {
				$errors++;
				$this->chunk_errors[] = [
					"sku"   => "#{$product_id}",
					"name"  => "Producto #{$product_id}",
					"error" => $t->getMessage(),
				];
				$this->log("ERROR", "Excepción procesando producto #{$product_id}: " . $t->getMessage());
			}
		}

		$count       = \count($product_ids);
		$next_offset = $offset + $count;
		$done        = $count < self::CHUNK_SIZE;

		if ($done) {
			\update_option("pwl_dte_last_stock_sync", \time());
		}

		\wp_send_json_success([
			"synced"      => $synced,
			"errors"      => $errors,
			"skipped"     => $skipped,
			"error_items" => $this->chunk_errors,
			"processed"   => $next_offset,
			"total"       => $total,
			"done"        => $done,
			"next_offset" => $next_offset,
			"last_sync"   => $done ? \wp_date("Y-m-d H:i:s", \time()) : null,
		]);
	}

	/**
	 * Resolve the Bsale office to use for stock queries.
	 * Pro subclass overrides this to check pwl_dte_stock_office_id first.
	 */
	protected function resolve_stock_office_id(): int
	{
		return (int) \get_option("pwl_dte_office_id", 1);
	}

	/** @return array{0:int,1:int,2:int} [synced, errors, skipped] */
	private function process_product_id(int $product_id): array
	{
		$product = \wc_get_product($product_id);

		if (!$product) {
			return [0, 0, 1];
		}

		if ($product->is_type("simple")) {
			$outcomes = (array) $this->process_simple_product($product);
		} elseif ($product->is_type("variable")) {
			$outcomes = $this->process_variable_product($product);
		} else {
			return [0, 0, 1];
		}

		$synced = $errors = $skipped = 0;

		foreach ($outcomes as $outcome) {
			if ($outcome === true) {
				$synced++;
			} elseif ($outcome === false) {
				$errors++;
			} else {
				$skipped++;
			}
		}

		return [$synced, $errors, $skipped];
	}

	/** ERROR → always (unless 'none') · INFO → 'info'|'debug' · DEBUG → 'debug' only */
	protected function log(string $level, string $message): void
	{
		$setting = \get_option("pwl_dte_log_level", "errors");

		$should_log = match ($level) {
			"ERROR" => $setting !== "none",
			"INFO"  => \in_array($setting, ["info", "debug"], true),
			"DEBUG" => $setting === "debug",
			default => false,
		};

		if ($should_log) {
			$logger = \function_exists('wc_get_logger') ? \wc_get_logger() : null;
			$wc_level = match ($level) {
				"ERROR" => "error",
				"INFO"  => "info",
				default => "debug",
			};
			if ($logger) {
				$logger->log($wc_level, $message, ["source" => "pwl-dte-stock-sync"]);
			}
		}
	}
}
