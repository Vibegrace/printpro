<?php
/**
 * User Dashboard API
 * Handles user dashboard data retrieval
 */

header('Content-Type: application/json');
require_once '../config.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'stats':
        getUserStats();
        break;
    case 'orders':
        getUserOrders();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function getUserStats()
{
    global $conn;

    $email = $_GET['email'] ?? '';

    if (empty($email)) {
        sendResponse(false, 'Email is required');
    }

    $stats = [];

    $sql = "SELECT COUNT(*) as total FROM orders WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_orders'] = $result->fetch_assoc()['total'];
    $stmt->close();

    $sql = "SELECT COUNT(*) as total FROM orders WHERE email = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['pending_orders'] = $result->fetch_assoc()['total'];
    $stmt->close();

    $sql = "SELECT COALESCE(SUM(total), 0) as spent FROM orders WHERE email = ? AND status != 'cancelled'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_spent'] = floatval($result->fetch_assoc()['spent']);
    $stmt->close();

    sendResponse(true, 'Stats retrieved', ['stats' => $stats]);
}

function getUserOrders()
{
    global $conn;

    $email = $_GET['email'] ?? '';

    if (empty($email)) {
        sendResponse(false, 'Email is required');
    }

    $sql = "SELECT * FROM orders WHERE email = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    sendResponse(true, 'Orders retrieved', ['orders' => $orders]);
    $stmt->close();
}

function sendResponse($success, $message, $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
?>