<?php
// Player repository functions for user panel
// CRUD helpers for players table (fetch, update, create for logged-in user)

require_once __DIR__ . '/../config/wa_config.php';

/**
 * Get player profile by mobile (phone).
 * @param string $phone
 * @return array|null
 */
function player_get_by_phone($phone) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM players WHERE mobile = ? LIMIT 1");
        $stmt->execute([$phone]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Create or update player profile by mobile (upsert).
 * Handles new fields: blood_group, playing_years
 *
 * @param string $phone
 * @param array $data
 * @return bool
 */
function player_save_or_update($phone, array $data) {
    try {
        $pdo = db();
        // Check if player exists
        $existing = player_get_by_phone($phone);
        if ($existing) {
            // Update
            $fields = [
                'full_name', 'dob', 'age_group', 'village', 'court',
                'play_time', 'blood_group', 'playing_years',
                'mobile', 'aadhaar', 'aadhaar_card', 'photo', 'terms'
            ];
            $setParts = [];
            $params = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $setParts[] = "$f = ?";
                    $params[] = $data[$f];
                }
            }

            // Nothing to update
            if (empty($setParts)) {
                return true;
            }

            $params[] = $phone;
            $sql = "UPDATE players SET " . implode(', ', $setParts) . " WHERE mobile = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } else {
            // Insert (include new fields blood_group, playing_years)
            $sql = "INSERT INTO players (
                        full_name, dob, age_group, village, court,
                        play_time, blood_group, playing_years,
                        mobile, aadhaar, aadhaar_card, photo, terms, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                $data['full_name'] ?? '',
                $data['dob'] ?? '',
                $data['age_group'] ?? '',
                $data['village'] ?? '',
                $data['court'] ?? '',
                $data['play_time'] ?? '',
                $data['blood_group'] ?? '',
                $data['playing_years'] ?? '',
                $phone,
                $data['aadhaar'] ?? '',
                $data['aadhaar_card'] ?? '',
                $data['photo'] ?? '',
                $data['terms'] ?? 0
            ]);
        }
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Ensure DOB is on or before 1995-11-01
 */
function is_dob_allowed(?string $dob): bool {
    if (empty($dob)) return false;
    $ts = strtotime($dob);
    if ($ts === false) return false;
    $max = strtotime('1995-11-01');
    return $ts <= $max;
}

// ========== Payment Helper Functions ==========

/**
 * Create a new payment record in the database.
 * 
 * @param int $player_id Player ID
 * @param string $order_id Razorpay order ID
 * @param int $amount Amount in paise
 * @param string $currency Currency code
 * @param array $metadata Additional metadata to store
 * @return int|false Payment ID on success, false on failure
 */
function create_payment_record(int $player_id, string $order_id, int $amount, string $currency, array $metadata = []) {
    try {
        $pdo = db();
        $sql = "INSERT INTO payments (player_id, order_id, amount, currency, status, metadata, created_at) 
                VALUES (?, ?, ?, ?, 'pending', ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $metadata_json = !empty($metadata) ? json_encode($metadata) : null;
        $stmt->execute([$player_id, $order_id, $amount, $currency, $metadata_json]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('create_payment_record error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update payment record with Razorpay order ID.
 * 
 * @param int $payment_id Payment record ID
 * @param string $order_id Razorpay order ID
 * @return bool True on success, false on failure
 */
function update_payment_order_id(int $payment_id, string $order_id): bool {
    try {
        $pdo = db();
        $sql = "UPDATE payments SET order_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$order_id, $payment_id]);
    } catch (Throwable $e) {
        error_log('update_payment_order_id error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark payment as paid with Razorpay payment ID.
 * 
 * @param int $payment_id Payment record ID
 * @param string $payment_id_from_gateway Razorpay payment ID
 * @return bool True on success, false on failure
 */
function mark_payment_paid(int $payment_id, string $payment_id_from_gateway): bool {
    try {
        $pdo = db();
        $sql = "UPDATE payments SET status = 'paid', payment_id = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$payment_id_from_gateway, $payment_id]);
    } catch (Throwable $e) {
        error_log('mark_payment_paid error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark payment as failed with error message.
 * 
 * @param int $payment_id Payment record ID
 * @param string $error_message Error description
 * @return bool True on success, false on failure
 */
function mark_payment_failed(int $payment_id, string $error_message = ''): bool {
    try {
        $pdo = db();
        $metadata = !empty($error_message) ? json_encode(['error' => $error_message]) : null;
        $sql = "UPDATE payments SET status = 'failed', metadata = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$metadata, $payment_id]);
    } catch (Throwable $e) {
        error_log('mark_payment_failed error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get payment record by Razorpay order ID.
 * 
 * @param string $order_id Razorpay order ID
 * @return array|null Payment record or null if not found
 */
function get_payment_by_order_id(string $order_id): ?array {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? LIMIT 1");
        $stmt->execute([$order_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Throwable $e) {
        error_log('get_payment_by_order_id error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get payment record by ID.
 * 
 * @param int $payment_id Payment record ID
 * @return array|null Payment record or null if not found
 */
function get_payment_by_id(int $payment_id): ?array {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
        $stmt->execute([$payment_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Throwable $e) {
        error_log('get_payment_by_id error: ' . $e->getMessage());
        return null;
    }
}