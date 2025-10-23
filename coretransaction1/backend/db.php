<?php
/**
 * Local XAMPP Database Configuration
 * Enhanced with JOIN support via db_select_advanced()
 */

if (!defined('APP_LOADED') && php_sapi_name() !== 'cli') {
    die('Direct access not allowed.');
}

$host = "localhost";
$port = 3306;
$dbname = "corepm";
$user = "root";
$password = "";

$pdo = null;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("MySQL Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact support.");
}

/**
 * Escape identifier (table/column names)
 */
if (!function_exists('db_escape_identifier')) {
    function db_escape_identifier($identifier) {
        return "`" . str_replace("`", "``", $identifier) . "`";
    }
}

// =============== EXISTING HELPERS (UNCHANGED) ===============

function db_select($table, $conditions = [], $options = []) {
    global $pdo;
    $columns = $options['columns'] ?? '*';
    $sql = "SELECT $columns FROM " . db_escape_identifier($table);
    $params = [];

    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $col => $val) {
            $where[] = db_escape_identifier($col) . " = ?";
            $params[] = $val;
        }
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    if (!empty($options['order_by'])) {
        // Basic order_by (no expressions)
        $sql .= " ORDER BY " . $options['order_by'];
    }

    if (isset($options['limit'])) {
        $sql .= " LIMIT " . (int)$options['limit'];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("db_select Error: " . $e->getMessage());
        return [];
    }
}

function db_count($table, $conditions = []) {
    global $pdo;
    $sql = "SELECT COUNT(*) as count FROM " . db_escape_identifier($table);
    $params = [];

    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $col => $val) {
            $where[] = db_escape_identifier($col) . " = ?";
            $params[] = $val;
        }
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    } catch (PDOException $e) {
        error_log("db_count Error: " . $e->getMessage());
        return 0;
    }
}

function db_insert($table, $data) {
    global $pdo;
    $columns = array_keys($data);
    $values = array_values($data);

    $sql = "INSERT INTO " . db_escape_identifier($table) . " (" .
        implode(", ", array_map('db_escape_identifier', $columns)) .
        ") VALUES (" . implode(", ", array_fill(0, count($values), "?")) . ")";

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    } catch (PDOException $e) {
        error_log("db_insert Error: " . $e->getMessage());
        return false;
    }
}

function db_update($table, $data, $conditions) {
    global $pdo;
    $set = [];
    $params = [];

    foreach ($data as $col => $val) {
        $set[] = db_escape_identifier($col) . " = ?";
        $params[] = $val;
    }

    $sql = "UPDATE " . db_escape_identifier($table) . " SET " . implode(", ", $set);

    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $col => $val) {
            $where[] = db_escape_identifier($col) . " = ?";
            $params[] = $val;
        }
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("db_update Error: " . $e->getMessage());
        return false;
    }
}

function db_delete($table, $conditions) {
    global $pdo;
    $sql = "DELETE FROM " . db_escape_identifier($table);
    $params = [];

    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $col => $val) {
            $where[] = db_escape_identifier($col) . " = ?";
            $params[] = $val;
        }
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("db_delete Error: " . $e->getMessage());
        return false;
    }
}

// =============== NEW: SAFE ADVANCED SELECT (FOR JOINS) ===============

/**
 * Execute a custom SELECT query with parameters (for JOINs, complex ORDER BY, etc.)
 * Example:
 *   $trips = db_select_advanced("
 *       SELECT t.trip_id, u.name AS driver_name 
 *       FROM trips t
 *       LEFT JOIN drivers d ON t.driver_id = d.drivers_id
 *       LEFT JOIN users u ON d.user_id = u.user_id
 *       WHERE t.status = ?
 *       ORDER BY t.start_time DESC
 *   ", ['ongoing']);
 */
function db_select_advanced($sql, $params = []) {
    global $pdo;
    
    // Security: Only allow SELECT queries
    $trimmed = ltrim($sql);
    if (stripos($trimmed, 'SELECT') !== 0) {
        error_log("db_select_advanced: Only SELECT queries allowed.");
        return [];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("db_select_advanced Error: " . $e->getMessage());
        return [];
    }
}
?>