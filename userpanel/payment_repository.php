<?php
/**
 * Payment repository — DB helper for payments table.
 *
 * Assumes a PDO connection factory function db() exists in your project (e.g., from config/app_config.php).
 * This file provides simple CRUD and query helpers for the `payments` table created by the migrations.
 *
 * Table schema expectations (from migrations):
 * - id (BIGINT AUTO_INCREMENT)
 * - user_mobile (VARCHAR)
 * - order_id (VARCHAR)          -- razorpay order id
 * - payment_id (VARCHAR)        -- razorpay payment id
 * - signature (VARCHAR)         -- razorpay signature
 * - amount (BIGINT)             -- amount in smallest unit (paise)
 * - currency (VARCHAR)
 * - status (VARCHAR)            -- created|pending|paid|failed|refunded|cancelled
 * - meta (JSON)
 * - notes (TEXT)
 * - created_at, updated_at
 *
 * Return conventions:
 * - create_* returns inserted id (int) on success or false on failure.
 * - update_* returns true on success or false on failure.
 * - find_* returns associative array or null.
 * - list_payments returns array of associative rows.
 */

if (!function_exists('db')) {
    // If your project doesn't provide db(), throw a clear error to make debugging simple.
    throw new RuntimeException('Database function db() not found. Ensure config/app_config.php defines a db() returning PDO.');
}

/**
 * Safely encodes metadata to JSON or returns null.
 */
function _payments_json_encode_meta($meta): ?string {
    if ($meta === null) return null;
    if (is_string($meta)) {
        // assume already JSON or plain string
        // try to ensure it's valid JSON
        json_decode($meta);
        if (json_last_error() === JSON_ERROR_NONE) return $meta;
        // otherwise wrap as JSON string
        return json_encode(['raw' => $meta], JSON_UNESCAPED_UNICODE);
    }
    // array/object -> json
    $json = json_encode($meta, JSON_UNESCAPED_UNICODE);
    return $json === false ? null : $json;
}

/**
 * Create a new payment record when creating a Razorpay order.
 *
 * $data keys:
 *   - user_mobile (string) REQUIRED
 *   - order_id (string) REQUIRED
 *   - amount (int) REQUIRED (smallest unit, e.g., paise)
 *   - currency (string) optional default 'INR'
 *   - status (string) optional default 'created'
 *   - meta (mixed) optional
 *   - notes (string) optional
 *
 * Returns inserted id (int) on success, false on failure.
 */
function payment_create(array $data) {
    $pdo = db();
    $sql = "INSERT INTO `payments` (`user_mobile`, `order_id`, `amount`, `currency`, `status`, `meta`, `notes`, `created_at`, `updated_at`)
            VALUES (:user_mobile, :order_id, :amount, :currency, :status, :meta, :notes, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);

    $user_mobile = $data['user_mobile'] ?? null;
    $order_id = $data['order_id'] ?? null;
    $amount = isset($data['amount']) ? (int)$data['amount'] : null;
    $currency = $data['currency'] ?? 'INR';
    $status = $data['status'] ?? 'created';
    $meta = _payments_json_encode_meta($data['meta'] ?? null);
    $notes = $data['notes'] ?? null;

    if (empty($user_mobile) || empty($order_id) || $amount === null) {
        return false;
    }

    try {
        $ok = $stmt->execute([
            ':user_mobile' => $user_mobile,
            ':order_id' => $order_id,
            ':amount' => $amount,
            ':currency' => $currency,
            ':status' => $status,
            ':meta' => $meta,
            ':notes' => $notes,
        ]);
        if ($ok) {
            return (int)$pdo->lastInsertId();
        }
    } catch (Throwable $e) {
        error_log('payment_create error: ' . $e->getMessage());
    }
    return false;
}

/**
 * Find payment by Razorpay order_id.
 * Returns associative row or null.
 */
function payment_find_by_order_id(string $order_id): ?array {
    $pdo = db();
    $sql = "SELECT * FROM `payments` WHERE `order_id` = :order_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order_id' => $order_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Find payment by Razorpay payment_id.
 * Returns associative row or null.
 */
function payment_find_by_payment_id(string $payment_id): ?array {
    $pdo = db();
    $sql = "SELECT * FROM `payments` WHERE `payment_id` = :payment_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':payment_id' => $payment_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Update payment fields by order_id.
 * $fields is associative array of column => value; allowed columns: payment_id, signature, status, meta, notes, amount, currency, user_mobile
 *
 * Returns true on success, false on failure.
 */
function payment_update_by_order_id(string $order_id, array $fields): bool {
    if (empty($fields)) return false;
    $allowed = ['payment_id','signature','status','meta','notes','amount','currency','user_mobile'];
    $sets = [];
    $params = [':order_id' => $order_id];
    foreach ($fields as $col => $val) {
        if (!in_array($col, $allowed, true)) continue;
        if ($col === 'meta') {
            $val = _payments_json_encode_meta($val);
        }
        $param = ':' . $col;
        $sets[] = "`$col` = $param";
        $params[$param] = $val;
    }
    if (empty($sets)) return false;
    $sql = "UPDATE `payments` SET " . implode(', ', $sets) . ", `updated_at` = NOW() WHERE `order_id` = :order_id";
    $pdo = db();
    $stmt = $pdo->prepare($sql);
    try {
        return (bool)$stmt->execute($params);
    } catch (Throwable $e) {
        error_log('payment_update_by_order_id error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark payment as paid (set payment_id, signature, status='paid' and optional meta).
 * Returns true on success, false on failure.
 */
function payment_mark_paid(string $order_id, string $payment_id, string $signature, $meta = null): bool {
    $fields = [
        'payment_id' => $payment_id,
        'signature' => $signature,
        'status' => 'paid',
        'meta' => $meta,
    ];
    return payment_update_by_order_id($order_id, $fields);
}

/**
 * Mark payment as failed.
 */
function payment_mark_failed(string $order_id, $meta = null): bool {
    $fields = [
        'status' => 'failed',
        'meta' => $meta,
    ];
    return payment_update_by_order_id($order_id, $fields);
}

/**
 * List payments with optional filters.
 * Filters supported:
 *   - status: string or array
 *   - user_mobile: string
 *   - date_from: 'YYYY-MM-DD'
 *   - date_to: 'YYYY-MM-DD'
 *   - min_amount, max_amount (in smallest unit)
 *
 * Pagination:
 *   - limit (int), offset (int)
 *
 * Returns array of rows.
 */
function payment_list(array $filters = [], int $limit = 100, int $offset = 0): array {
    $pdo = db();
    $where = [];
    $params = [];

    if (!empty($filters['status'])) {
        if (is_array($filters['status'])) {
            $in = implode(',', array_fill(0, count($filters['status']), '?'));
            $where[] = "`status` IN ($in)";
            foreach ($filters['status'] as $v) $params[] = $v;
        } else {
            $where[] = "`status` = ?";
            $params[] = $filters['status'];
        }
    }
    if (!empty($filters['user_mobile'])) {
        $where[] = "`user_mobile` = ?";
        $params[] = $filters['user_mobile'];
    }
    if (!empty($filters['date_from'])) {
        $where[] = "`created_at` >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $where[] = "`created_at` <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if (isset($filters['min_amount'])) {
        $where[] = "`amount` >= ?";
        $params[] = (int)$filters['min_amount'];
    }
    if (isset($filters['max_amount'])) {
        $where[] = "`amount` <= ?";
        $params[] = (int)$filters['max_amount'];
    }

    $sql = "SELECT * FROM `payments`";
    if (!empty($where)) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY `created_at` DESC LIMIT ? OFFSET ?";

    try {
        $stmt = $pdo->prepare($sql);
        // bind parameters (append limit/offset)
        $execParams = $params;
        $execParams[] = $limit;
        $execParams[] = $offset;
        $stmt->execute($execParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('payment_list error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Count payments by status.
 * Returns associative array: [status => count, ...]
 */
function payment_count_by_status(): array {
    $pdo = db();
    $sql = "SELECT `status`, COUNT(*) AS cnt FROM `payments` GROUP BY `status`";
    try {
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[$r['status']] = (int)$r['cnt'];
        return $out;
    } catch (Throwable $e) {
        error_log('payment_count_by_status error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Verify Razorpay checkout signature (server-side).
 * Signature expected: hash_hmac('sha256', "$order_id|$payment_id", key_secret)
 *
 * Returns true if signature matches, false otherwise.
 */
function payment_verify_signature(string $order_id, string $payment_id, string $signature, string $key_secret): bool {
    $payload = $order_id . '|' . $payment_id;
    $expected = hash_hmac('sha256', $payload, $key_secret);
    // Use hash_equals for timing-attack safe comparison
    return hash_equals($expected, $signature);
}

/**
 * Helper: get recent payments
 */
function payment_get_recent(int $limit = 20): array {
    $pdo = db();
    $sql = "SELECT * FROM `payments` ORDER BY `created_at` DESC LIMIT :limit";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('payment_get_recent error: ' . $e->getMessage());
        return [];
    }
}