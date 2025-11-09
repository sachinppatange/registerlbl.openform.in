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

/**
 * Payment helpers
 *
 * These helpers operate on the payments table. They expect a payments table
 * with columns at least: id, player_id, order_id, payment_id, amount, currency, status, metadata, created_at, updated_at
 *
 * Convention: $amount is expected in paise (integer).
 */

/**
 * Mark a payment as pending (insert or update).
 * @param string $phone E.164 phone or stored mobile value
 * @param string $order_id Razorpay order id (or gateway order id)
 * @param int $amount_paise Amount in paise
 * @param string $currency optional currency, default 'INR'
 * @param array $metadata optional associative metadata
 * @return int|false Returns payment row id on success, false on failure
 */
function mark_payment_pending(string $phone, string $order_id, int $amount_paise, string $currency = 'INR', array $metadata = []) {
    try {
        $pdo = db();
        // Determine player_id if available
        $player = player_get_by_phone($phone);
        $player_id = $player['id'] ?? null;

        // Check if a payments row already exists for this order_id
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE order_id = ? LIMIT 1");
        $stmt->execute([$order_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $metaJson = !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;

        if ($row && isset($row['id'])) {
            // Update existing record to ensure it is pending
            $update = $pdo->prepare("UPDATE payments SET player_id = ?, amount = ?, currency = ?, status = 'pending', metadata = ?, updated_at = NOW() WHERE id = ?");
            $success = $update->execute([$player_id, $amount_paise, $currency, $metaJson, $row['id']]);
            return $success ? (int)$row['id'] : false;
        } else {
            // Insert new pending payment
            $ins = $pdo->prepare("INSERT INTO payments (player_id, order_id, amount, currency, status, metadata, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
            $ins->execute([$player_id, $order_id, $amount_paise, $currency, $metaJson]);
            return (int)$pdo->lastInsertId();
        }
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Mark a payment as success/paid.
 * Finds the payment row by order_id (preferable) or by phone->player_id.
 * @param string $phone
 * @param string $gatewayPaymentId gateway/payment id (e.g. razorpay_payment_id)
 * @param string $order_id
 * @return bool
 */
function mark_payment_success(string $phone, string $gatewayPaymentId, string $order_id) {
    try {
        $pdo = db();

        // Try to update by order_id first
        $stmt = $pdo->prepare("UPDATE payments SET payment_id = ?, status = 'paid', updated_at = NOW() WHERE order_id = ?");
        $stmt->execute([$gatewayPaymentId, $order_id]);
        if ($stmt->rowCount() > 0) return true;

        // Fallback: attempt to update by player_id if we have one
        $player = player_get_by_phone($phone);
        if ($player && isset($player['id'])) {
            $update = $pdo->prepare("UPDATE payments SET payment_id = ?, status = 'paid', updated_at = NOW() WHERE player_id = ? AND order_id = ? LIMIT 1");
            return (bool)$update->execute([$gatewayPaymentId, $player['id'], $order_id]);
        }

        // If nothing matched, return false
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Mark a payment as failed.
 * @param string $phone
 * @param string $order_id
 * @param string|null $gatewayPaymentId
 * @return bool
 */
function mark_payment_failed(string $phone, string $order_id, ?string $gatewayPaymentId = null) {
    try {
        $pdo = db();

        // Update by order_id
        $stmt = $pdo->prepare("UPDATE payments SET payment_id = ?, status = 'failed', updated_at = NOW() WHERE order_id = ?");
        $stmt->execute([$gatewayPaymentId, $order_id]);
        if ($stmt->rowCount() > 0) return true;

        // Fallback by player_id
        $player = player_get_by_phone($phone);
        if ($player && isset($player['id'])) {
            $update = $pdo->prepare("UPDATE payments SET payment_id = ?, status = 'failed', updated_at = NOW() WHERE player_id = ? AND order_id = ? LIMIT 1");
            return (bool)$update->execute([$gatewayPaymentId, $player['id'], $order_id]);
        }

        return false;
    } catch (Throwable $e) {
        return false;
    }
}
?>