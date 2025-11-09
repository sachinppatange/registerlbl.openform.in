<?php
// Latur Badminton League - Admin Player Repository
// CRUD helpers + status management for players table
// UPDATED: include blood_group and playing_years in all selects/inserts/updates/exports

require_once __DIR__ . '/../config/wa_config.php';
require_once __DIR__ . '/../config/player_config.php';
require_once __DIR__ . '/player_repository.php';


/**
 * Get list of all players.
 * @return array
 */
function get_all_players(): array {
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT id, full_name, dob, age_group, village, court, play_time, blood_group, playing_years, mobile, aadhaar, photo, aadhaar_card, status, created_at FROM players ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get single player by id.
 * @param int $id
 * @return array|null
 */
function get_player_by_id(int $id): ?array {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, full_name, dob, age_group, village, court, play_time, blood_group, playing_years, mobile, aadhaar, photo, aadhaar_card, status, created_at FROM players WHERE id = ?");
        $stmt->execute([$id]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        return $player ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Add a new player (admin creation).
 * @param array $data
 * @return bool
 */
function add_player(array $data): bool {
    try {
        $pdo = db();
        $sql = "INSERT INTO players (
                    full_name, dob, age_group, village, court, play_time,
                    blood_group, playing_years,
                    mobile, aadhaar, photo, aadhaar_card, status, created_at
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
            $data['mobile'] ?? '',
            $data['aadhaar'] ?? '',
            $data['photo'] ?? '',
            $data['aadhaar_card'] ?? '',
            $data['status'] ?? 'pending'
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Update player info.
 * @param int $id
 * @param array $data
 * @return bool
 */
function update_player(int $id, array $data): bool {
    try {
        $pdo = db();
        $fields = [
            'full_name','dob','age_group','village','court','play_time',
            'blood_group','playing_years',
            'mobile','aadhaar','photo','aadhaar_card','status'
        ];
        $setParts = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $setParts[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($setParts)) return true; // nothing to update
        $params[] = $id;
        $sql = "UPDATE players SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Delete a player by id.
 * @param int $id
 * @return bool
 */
function delete_player(int $id): bool {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Change player status (accept/reject/pending)
 * @param int $id
 * @param string $status
 * @return bool
 */
function set_player_status(int $id, string $status): bool {
    if (!in_array($status, ['accepted','rejected','pending'], true)) return false;
    try {
        $pdo = db();
        $stmt = $pdo->prepare("UPDATE players SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Export all players as array for reporting (Excel/PDF)
 * @param string $status (optional filter)
 * @return array
 */
function export_players(string $status = ''): array {
    try {
        $pdo = db();
        if ($status && in_array($status,['accepted','rejected','pending'],true)) {
            $stmt = $pdo->prepare("SELECT id, full_name, dob, age_group, village, court, play_time, blood_group, playing_years, mobile, aadhaar, status, created_at FROM players WHERE status = ? ORDER BY created_at DESC");
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->query("SELECT id, full_name, dob, age_group, village, court, play_time, blood_group, playing_years, mobile, aadhaar, status, created_at FROM players ORDER BY created_at DESC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}
?>