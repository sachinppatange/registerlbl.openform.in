<?php
// User repository functions for admin panel
// CRUD helpers for users table

require_once __DIR__ . '/../config/db_config.php';

/**
 * Get list of all users.
 * @return array
 */
function get_all_users(): array {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->query("SELECT id, name, phone, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Get single user by id.
 * @param int $id
 * @return array|null
 */
function get_user_by_id(int $id): ?array {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare("SELECT id, name, phone, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Add a new user (admin creation).
 * @param string $name
 * @param string $phone
 * @return bool
 */
function add_user(string $name, string $phone): bool {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare("INSERT INTO users (name, phone, created_at) VALUES (?, ?, NOW())");
        return $stmt->execute([$name, $phone]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Delete a user by id.
 * @param int $id
 * @return bool
 */
function delete_user(int $id): bool {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (Throwable $e) {
        return false;
    }
}