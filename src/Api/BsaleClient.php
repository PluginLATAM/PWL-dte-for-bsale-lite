<?php
namespace PwlDte\Api;

defined('ABSPATH') || exit;

/**
 * Bsale API HTTP client.
 *
 * request() is public so WebhookHandler can call arbitrary endpoint paths.
 * request_url() accepts an absolute URL for v2 webhook resource paths.
 */
class BsaleClient
{
	private string $token;
	private string $base_url;
	private int    $timeout;
	private string $log_level;

	public function __construct(string $token)
	{
		$this->token     = $token;
		$this->base_url  = 'https://api.bsale.io/v1/';
		$this->timeout   = (int) get_option('pwl_dte_api_timeout', 30);
		$this->log_level = (string) get_option('pwl_dte_log_level', 'error');
	}

	/**
	 * Scheme + host of the configured base URL — avoids hardcoding the host.
	 * @return string e.g. "https://api.bsale.io"
	 */
	public function get_base_host(): string
	{
		$parts = wp_parse_url($this->base_url);
		return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'api.bsale.io');
	}

	// ── Named endpoints ───────────────────────────────────────────────────────

	public function test_connection(): array
	{
		return $this->get_offices();
	}

	public function get_offices(): array
	{
		return $this->request('GET', 'offices.json');
	}

	public function create_document(array $data): array
	{
		return $this->request('POST', 'documents.json', $data);
	}

	public function create_credit_note(array $data): array
	{
		return $this->request('POST', 'returns.json', $data);
	}

	public function get_stock(string $sku, int $office_id): array
	{
		return $this->request('GET', 'stocks.json', null, ['code' => $sku, 'officeid' => $office_id]);
	}

	public function get_variant_by_sku(string $sku): array
	{
		return $this->request('GET', 'variants.json', null, ['code' => $sku, 'state' => 0]);
	}

	public function get_variant_by_id(int $variant_id): array
	{
		return $this->request('GET', "variants/{$variant_id}.json");
	}

	public function get_price_lists(): array
	{
		return $this->request('GET', 'price_lists.json');
	}

	public function get_document(int $document_id): array
	{
		return $this->request('GET', "documents/{$document_id}.json");
	}

	public function get_document_details(int $document_id): array
	{
		return $this->request('GET', "documents/{$document_id}/details.json");
	}

	/**
	 * Fetch products via the v2 market_info endpoint.
	 *
	 * @param int      $limit      Max items per page (Bsale max = 50).
	 * @param int      $offset     Pagination offset.
	 * @param int|null $price_list Price list ID.
	 * @param int|null $office_id  Office ID for stock figures.
	 */
	public function get_market_info(
		int $limit = 50,
		int $offset = 0,
		?int $price_list = null,
		?int $office_id = null,
	): array {
		$params = ['limit' => $limit, 'offset' => $offset, 'expand' => '[variants,product_type]'];

		if ($price_list) {
			$params['priceListId'] = $price_list;
		}
		if ($office_id) {
			$params['officeId'] = $office_id;
		}

		$url = add_query_arg(
			$params,
			str_replace('/v1', '/v2', rtrim($this->base_url, '/')) . '/products/list/market_info.json',
		);

		return $this->execute('GET', $url, null);
	}

	/** List all product types (Bsale categories). */
	public function get_product_types(): array
	{
		return $this->request('GET', 'product_types.json', null, ['limit' => 50, 'state' => 0]);
	}

	/**
	 * List products via v1 endpoint (fallback when v2/market_info unavailable).
	 *
	 * @param int      $limit           Items per page.
	 * @param int      $offset          Pagination offset.
	 * @param int|null $product_type_id Filter by product type.
	 * @param string   $name            Partial name filter.
	 */
	public function get_products(
		int $limit = 50,
		int $offset = 0,
		?int $product_type_id = null,
		string $name = '',
	): array {
		$params = ['limit' => $limit, 'offset' => $offset, 'state' => 0, 'expand' => '[product_type,variants]'];

		if ($product_type_id) {
			$params['producttypeid'] = $product_type_id;
		}
		if ($name !== '') {
			$params['name'] = $name;
		}

		return $this->request('GET', 'products.json', null, $params);
	}

	// ── Core HTTP methods ─────────────────────────────────────────────────────

	/**
	 * Make a request relative to the API base URL.
	 * Public so WebhookHandler can pass arbitrary endpoint paths.
	 */
	public function request(
		string $method,
		string $endpoint,
		?array $body = null,
		array  $query_params = [],
	): array {
		$url = $this->base_url . ltrim($endpoint, '/');
		if (!empty($query_params)) {
			$url = add_query_arg($query_params, $url);
		}
		return $this->execute($method, $url, $body);
	}

	/**
	 * Make a request to an absolute URL.
	 * Used by WebhookHandler when the payload contains a full resource URL.
	 */
	public function request_url(string $method, string $url, ?array $body = null): array
	{
		return $this->execute($method, $url, $body);
	}

	// ── Internal ──────────────────────────────────────────────────────────────

	private function execute(string $method, string $url, ?array $body): array
	{
		$method = strtoupper($method);
		$args   = [
			'method'    => $method,
			'timeout'   => $this->timeout,
			'sslverify' => true,
			'headers'   => [
				'access_token' => $this->token,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
		];

		if (null !== $body && in_array($method, ['POST', 'PUT'], true)) {
			$args['body'] = wp_json_encode($body);
		}

		if ($this->log_level === 'debug') {
			$log_body = $body;
			if (is_array($log_body) && isset($log_body['client'])) {
				$log_body['client'] = ['[REDACTED]' => true]; // omit customer PII (RUT, name, email, address)
			}
			$this->write_log(
				'debug',
				sprintf('[PwlDte] %s %s | Body: %s', $method, $url, $log_body ? wp_json_encode($log_body) : 'null'),
			);
		}

		return $this->parse_response(wp_remote_request($url, $args), $method, $url);
	}

	private function parse_response(mixed $response, string $method, string $url): array
	{
		if (is_wp_error($response)) {
			$error = $response->get_error_message();
			$this->log_error($method, $url, $error);
			return ['success' => false, 'data' => null, 'error' => $error];
		}

		$code = wp_remote_retrieve_response_code($response);
		$data = json_decode(wp_remote_retrieve_body($response), true);

		if ($code < 200 || $code >= 300) {
			$error = $this->extract_error($data, $code);
			$this->log_error($method, $url, $error);
			return ['success' => false, 'data' => $data, 'error' => $error];
		}

		if (in_array($this->log_level, ['info', 'debug'], true)) {
			$this->write_log('info', "[PwlDte] OK: {$method} {$url}");
		}

		return ['success' => true, 'data' => $data, 'error' => null];
	}

	private function extract_error(?array $data, int $code): string
	{
		if (isset($data['error'])) {
			return is_array($data['error']) ? wp_json_encode($data['error']) : $data['error'];
		}
		if (isset($data['message'])) {
			return $data['message'];
		}
		/* translators: %d: HTTP error status code */
		return sprintf(__('Error HTTP %d', 'pwl-dte-for-bsale'), $code);
	}

	private function log_error(string $method, string $url, string $error): void
	{
		if ($this->log_level !== 'none') {
			$this->write_log('error', "[PwlDte] ERROR: {$method} {$url} | {$error}");
		}
	}

	private function write_log(string $level, string $message): void
	{
		if (!function_exists('wc_get_logger')) {
			return;
		}
		wc_get_logger()->log($level, $message, ['source' => 'pwl-dte-api']);
	}
}
