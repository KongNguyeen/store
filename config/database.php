<?php
// Check if constants are not already defined
if (!defined('DB_HOST')) {
    // Database configuration for 42web hosting
    define('DB_HOST', 'sql303.infinityfree.com');
    define('DB_NAME', 'if0_41880438_store');
    define('DB_USER', 'if0_41880438');
    define('DB_PASS', 'wjHVNy96sKDqCD');
    define('DB_CHARSET', 'utf8mb4');
}

// Get PDO connection
function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed. Please try again later.');
        }
    }
    return $pdo;
}