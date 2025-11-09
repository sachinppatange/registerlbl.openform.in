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