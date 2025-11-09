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
?>

<?php
// (existing file content...)
// --- add near top ---

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

// Example usage in add_player/update_player:
// ... inside add_player():
// if (!is_dob_allowed($data['dob'] ?? null)) return false;

// ... inside update_player():
// if (array_key_exists('dob', $data) && !is_dob_allowed($data['dob'])) return false;

/**
 * Create a payment record in the database
 * 
 * @param int|null $player_id Player ID (nullable)
 * @param string $order_id Razorpay order ID
 * @param int $amount Amount in paise
 * @param string $currency Currency code
 * @param array|null $metadata Additional metadata (optional)
 * @return int|false Payment record ID or false on failure
 */
function create_payment_record(?int $player_id, string $order_id, int $amount, string $currency = 'INR', ?array $metadata = null) {
    try {
        $pdo = db();
        $sql = "INSERT INTO payments (player_id, order_id, amount, currency, status, metadata, created_at) 
                VALUES (?, ?, ?, ?, 'pending', ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $metadataJson = $metadata ? json_encode($metadata) : null;
        $result = $stmt->execute([$player_id, $order_id, $amount, $currency, $metadataJson]);
        return $result ? $pdo->lastInsertId() : false;
    } catch (Throwable $e) {
        error_log("Failed to create payment record: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark payment as paid
 * 
 * @param string $order_id Razorpay order ID
 * @param string $payment_id Razorpay payment ID
 * @return bool Success status
 */
function mark_payment_paid(string $order_id, string $payment_id): bool {
    try {
        $pdo = db();
        $sql = "UPDATE payments SET status = 'paid', payment_id = ?, updated_at = NOW() 
                WHERE order_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$payment_id, $order_id]);
    } catch (Throwable $e) {
        error_log("Failed to mark payment as paid: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark payment as failed
 * 
 * @param string $order_id Razorpay order ID
 * @return bool Success status
 */
function mark_payment_failed(string $order_id): bool {
    try {
        $pdo = db();
        $sql = "UPDATE payments SET status = 'failed', updated_at = NOW() 
                WHERE order_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$order_id]);
    } catch (Throwable $e) {
        error_log("Failed to mark payment as failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get payment by order ID
 * 
 * @param string $order_id Razorpay order ID
 * @return array|null Payment record
 */
function get_payment_by_order_id(string $order_id): ?array {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? LIMIT 1");
        $stmt->execute([$order_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Throwable $e) {
        error_log("Failed to fetch payment: " . $e->getMessage());
        return null;
    }
}

/**
 * Get payment by payment ID
 * 
 * @param string $payment_id Razorpay payment ID
 * @return array|null Payment record
 */
function get_payment_by_payment_id(string $payment_id): ?array {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_id = ? LIMIT 1");
        $stmt->execute([$payment_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Throwable $e) {
        error_log("Failed to fetch payment: " . $e->getMessage());
        return null;
    }
}

/**
 * Get payment by ID
 * 
 * @param int $id Payment ID
 * @return array|null Payment record
 */
function get_payment_by_id(int $id): ?array {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (Throwable $e) {
        error_log("Failed to fetch payment: " . $e->getMessage());
        return null;
    }
}

// (rest of the file)