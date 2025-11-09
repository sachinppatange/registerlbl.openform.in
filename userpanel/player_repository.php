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

// (rest of the file)

// ----------------------------

// (existing content above remains) - below add new helpers near top or bottom of file

require_once __DIR__ . '/../config/wa_config.php';

/**
 * Create a payments record (helper).
 * @param int|null $player_id
 * @param string $order_id
 * @param int $amount_paise
 * @param string $currency
 * @param array $metadata
 * @return int|false payment record id
 */
function create_payment_record($player_id, $order_id, $amount_paise, $currency = 'INR', $metadata = []) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO payments (player_id, order_id, amount, currency, status, metadata, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
        $metaJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        $stmt->execute([$player_id, $order_id, $amount_paise, $currency, $metaJson]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Mark payment as paid
 * @param int $paymentRowId
 * @param string $gatewayPaymentId
 * @return bool
 */
function mark_payment_paid($paymentRowId, $gatewayPaymentId) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE payments SET payment_id = ?, status = 'paid', updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$gatewayPaymentId, $paymentRowId]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Mark payment as failed
 * @param int $paymentRowId
 * @param string|null $gatewayPaymentId
 * @return bool
 */
function mark_payment_failed($paymentRowId, $gatewayPaymentId = null) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE payments SET payment_id = ?, status = 'failed', updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$gatewayPaymentId, $paymentRowId]);
    } catch (Throwable $e) {
        return false;
    }
}