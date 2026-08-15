<?php
/**
 * Shared authentication and authorization middleware.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax'
    ]);
    session_start();
}

function requireLogin(): int
{
    $customerId = (int)($_SESSION['customer_id'] ?? 0);
    if ($customerId <= 0) {
        sendAuthResponse(false, 'Authentication required', 401);
    }
    return $customerId;
}

function requireAdmin(): int
{
    $userId = (int)($_SESSION['admin_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';

    if ($userId <= 0 || !in_array($role, ['admin', 'manager'], true)) {
        sendAuthResponse(false, 'Administrator access required', 403);
    }

    return $userId;
}

function isAdmin(): bool
{
    return (int)($_SESSION['admin_id'] ?? 0) > 0
        && in_array($_SESSION['role'] ?? '', ['admin', 'manager'], true);
}

function sendAuthResponse(bool $success, string $message, int $status): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => []]);
    exit;
}
