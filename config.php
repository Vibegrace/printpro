<?php
/**
 * Application configuration.
 * Production credentials must be supplied through environment variables.
 * The localhost defaults keep the existing XAMPP development setup working.
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'printsiv_db');

mysqli_report(MYSQLI_REPORT_OFF);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log('Database connection failed: ' . $conn->connect_error);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Database connection failed', 'data' => []]);
        exit;
    }

    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('Database exception: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Database connection failed', 'data' => []]);
    exit;
}
?>
