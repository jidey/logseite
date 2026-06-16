<?php
/**
 * DATABASE CONFIGURATION
 * Configuration PDO pour MySQL
 */

// Database credentials
$db_host = 'localhost';
$db_name = 'testcomplete';
$db_user = 'root';
$db_pass = '';

try {
    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        )
    );
    // Connection successful
} catch (PDOException $e) {
    error_log("PDO Connection Error: " . $e->getMessage());
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Vérifier que $pdo est bien défini
if (!isset($pdo) || $pdo === null) {
    error_log("ERROR: \$pdo is null after connection attempt");
    die("Database configuration error: \$pdo is not defined");
}

?>