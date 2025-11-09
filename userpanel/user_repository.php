<?php
require_once __DIR__ . '/../config/wa_config.php';

/**
 * Get a user by phone (E.164)
 */
function user_get(string $phone): ?array {
    $sql = "SELECT * FROM users WHERE phone = ?";
    $stmt = db()->prepare($sql);
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Create (insert) a user if not exists. Returns true if user exists/created.
 */
function user_create_if_not_exists(string $phone): bool {
    // Try insert; if duplicate, ignore
    $sql = "INSERT INTO users (phone) VALUES (?)";
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute([$phone]);
        return true;
    } catch (PDOException $e) {
        // Duplicate primary key -> already exists
        if ($e->getCode() === '23000') {
            return true;
        }
        throw $e;
    }
}

/**
 * Update user profile fields (all optional)
 * $data keys allowed: full_name, email, address_line_1, address_line_2, pincode, city, state
 */
function user_update_profile(string $phone, array $data): bool {
    $allowed = ['full_name','email','address_line_1','address_line_2','pincode','city','state'];
    $setParts = [];
    $params = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $data)) {
            $setParts[] = "$k = ?";
            $params[] = $data[$k];
        }
    }
    if (empty($setParts)) return true; // nothing to update

    $params[] = $phone;
    $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE phone = ?";
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}