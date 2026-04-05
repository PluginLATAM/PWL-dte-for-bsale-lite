<?php
namespace PwlDte\Core;

defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

class Database
{
	private string $table;
	private string $webhook_table;

	public function __construct()
	{
		global $wpdb;
		$this->table         = $wpdb->prefix . 'pwl_dte_documents';
		$this->webhook_table = $wpdb->prefix . 'pwl_dte_webhook_events';
	}

	// ── Documents: read ───────────────────────────────────────────────────────

	public function get_document_by_order_id(int $order_id): ?array
	{
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$this->table} WHERE order_id = %d ORDER BY created_at DESC LIMIT 1", $order_id),
			ARRAY_A,
		) ?: null;
	}

	public function get_document_by_id(int $id): ?array
	{
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", $id),
			ARRAY_A,
		) ?: null;
	}

	public function get_pending_documents(int $limit = 10): array
	{
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status IN ('pending','retrying') ORDER BY created_at ASC LIMIT %d",
				$limit,
			),
			ARRAY_A,
		) ?: [];
	}

	/** Oldest-first so long-waiting orders get priority. */
	public function get_retryable_documents(int $max_attempts = 3, int $limit = 20): array
	{
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = 'error' AND attempts < %d ORDER BY created_at ASC LIMIT %d",
				$max_attempts,
				$limit,
			),
			ARRAY_A,
		) ?: [];
	}

	public function get_recent_documents(int $limit = 20): array
	{
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d", $limit),
			ARRAY_A,
		) ?: [];
	}

	public function get_documents_with_filter(?string $status, int $limit, int $offset): array
	{
		global $wpdb;
		$sql = $status
			? $wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$status, $limit, $offset,
			)
			: $wpdb->prepare(
				"SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit, $offset,
			);
		return $wpdb->get_results($sql, ARRAY_A) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function count_documents(?string $status = null): int
	{
		global $wpdb;
		return $status
			? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = %s", $status)) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// ── Documents: write ──────────────────────────────────────────────────────

	/** @return int|false Insert ID on success. */
	public function insert_document(array $data): int|false
	{
		global $wpdb;

		$data = wp_parse_args($data, [
			'order_id'          => 0,
			'bsale_document_id' => null,
			'document_number'   => null,
			'document_type'     => 'boleta',
			'code_sii'          => 39,
			'office_id'         => null,
			'folio'             => null,
			'status'            => 'pending',
			'error_message'     => null,
			'pdf_url'           => null,
			'public_url'        => null,
			'token'             => null,
			'attempts'          => 0,
		]);

		$inserted = $wpdb->insert(
			$this->table,
			[
				'order_id'          => absint($data['order_id']),
				'bsale_document_id' => $data['bsale_document_id'] ? absint($data['bsale_document_id']) : null,
				'document_number'   => $data['document_number']   ? absint($data['document_number'])   : null,
				'document_type'     => sanitize_text_field($data['document_type']),
				'code_sii'          => absint($data['code_sii']),
				'office_id'         => $data['office_id']         ? absint($data['office_id'])         : null,
				'folio'             => $data['folio']             ? sanitize_text_field($data['folio']) : null,
				'status'            => sanitize_key($data['status']),
				'error_message'     => $data['error_message']     ? sanitize_textarea_field($data['error_message']) : null,
				'pdf_url'           => $data['pdf_url']           ? esc_url_raw($data['pdf_url'])      : null,
				'public_url'        => $data['public_url']        ? esc_url_raw($data['public_url'])   : null,
				'token'             => $data['token']             ? sanitize_text_field($data['token']) : null,
				'attempts'          => absint($data['attempts']),
			],
			['%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'],
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	public function update_document(int $id, array $data): bool
	{
		global $wpdb;

		$allowed = [
			'bsale_document_id' => '%d',
			'document_number'   => '%d',
			'document_type'     => '%s',
			'code_sii'          => '%d',
			'office_id'         => '%d',
			'folio'             => '%s',
			'status'            => '%s',
			'error_message'     => '%s',
			'pdf_url'           => '%s',
			'public_url'        => '%s',
			'token'             => '%s',
			'attempts'          => '%d',
		];

		$update_data = array_intersect_key($data, $allowed);
		if (empty($update_data)) {
			return false;
		}

		// Derive formats in $update_data's key order (not $allowed's order).
		$formats = array_map(fn($key) => $allowed[$key], array_keys($update_data));

		return (bool) $wpdb->update($this->table, $update_data, ['id' => $id], $formats, ['%d']);
	}

	public function increment_attempts(int $id): bool
	{
		global $wpdb;
		return (bool) $wpdb->query(
			$wpdb->prepare("UPDATE {$this->table} SET attempts = attempts + 1 WHERE id = %d", $id),
		);
	}

	// ── Documents: maintenance ────────────────────────────────────────────────

	public function delete_old_successful_documents(int $days = 30): int
	{
		global $wpdb;
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE status = 'success' AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days,
			),
		);
	}

	// ── Webhook events ────────────────────────────────────────────────────────

	/** Insert a webhook event record; auto-prunes table to 300 rows after insert. */
	public function insert_webhook_event(
		string $topic,
		string $action,
		string $resource_id,
		string $status,
		string $message,
		array $payload,
		int $cpn_id = 0,
		int $send_ts = 0,
	): void {
		global $wpdb;
		$wpdb->insert(
			$this->webhook_table,
			[
				'cpn_id'      => $cpn_id  ?: null,
				'topic'       => sanitize_key($topic),
				'action'      => sanitize_key($action),
				'resource_id' => sanitize_text_field($resource_id),
				'status'      => sanitize_key($status),
				'message'     => sanitize_textarea_field($message),
				'payload'     => wp_json_encode($payload),
				'send_ts'     => $send_ts ?: null,
			],
			['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d'],
		);
		$this->prune_webhook_events(300);
	}

	/** Deduplication check — topic + action + resource_id + send_ts must be unique. */
	public function has_duplicate_webhook_event(
		string $topic,
		string $action,
		string $resource_id,
		int $send_ts,
	): bool {
		if (!$send_ts) {
			return false;
		}
		global $wpdb;
		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$this->webhook_table} WHERE topic = %s AND action = %s AND resource_id = %s AND send_ts = %d LIMIT 1",
				$topic, $action, $resource_id, $send_ts,
			),
		);
	}

	public function get_webhook_events(
		string $topic  = '',
		string $status = '',
		int    $limit  = 30,
		int    $offset = 0,
	): array {
		global $wpdb;
		$topic  = sanitize_key($topic);
		$status = sanitize_key($status);
		$table  = preg_replace('/[^A-Za-z0-9_]/', '', $this->webhook_table);

		if ($topic !== '' && $status !== '') {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE topic = %s AND status = %s ORDER BY received_at DESC LIMIT %d OFFSET %d",
					$topic,
					$status,
					$limit,
					$offset,
				),
				ARRAY_A,
			) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} elseif ($topic !== '') {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE topic = %s ORDER BY received_at DESC LIMIT %d OFFSET %d",
					$topic,
					$limit,
					$offset,
				),
				ARRAY_A,
			) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} elseif ($status !== '') {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s ORDER BY received_at DESC LIMIT %d OFFSET %d",
					$status,
					$limit,
					$offset,
				),
				ARRAY_A,
			) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY received_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset,
			),
			ARRAY_A,
		) ?: []; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function count_webhook_events(string $topic = '', string $status = ''): int
	{
		global $wpdb;
		$topic  = sanitize_key($topic);
		$status = sanitize_key($status);
		$table  = preg_replace('/[^A-Za-z0-9_]/', '', $this->webhook_table);

		if ($topic !== '' && $status !== '') {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE topic = %s AND status = %s",
					$topic,
					$status,
				),
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ($topic !== '') {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE topic = %s",
					$topic,
				),
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ($status !== '') {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status = %s",
					$status,
				),
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function delete_webhook_events(): bool
	{
		global $wpdb;
		$table = preg_replace('/[^A-Za-z0-9_]/', '', $this->webhook_table);
		return false !== $wpdb->query("TRUNCATE TABLE {$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// ── Internals ─────────────────────────────────────────────────────────────

	/** Keep webhook_events table under $max rows by deleting oldest entries. */
	private function prune_webhook_events(int $max): void
	{
		global $wpdb;
		$table = preg_replace('/[^A-Za-z0-9_]/', '', $this->webhook_table);
		$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ($count <= $max) {
			return;
		}
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id NOT IN (
					SELECT id FROM (
						SELECT id FROM {$table} ORDER BY received_at DESC LIMIT %d
					) AS keep
				)",
				$max,
			), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
