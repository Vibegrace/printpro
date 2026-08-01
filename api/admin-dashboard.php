<?php
/**
 * Admin Dashboard API
 * Handles admin dashboard data retrieval and actions
 */

header('Content-Type: application/json');
require_once 'config.php';

session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'stats':
        getStats();
        break;
    case 'orders':
        getOrders();
        break;
    case 'products':
        getProducts();
        break;
    case 'customers':
        getCustomers();
        break;
    case 'messages':
        getMessages();
        break;
    case 'update_order_status':
        updateOrderStatus();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function getStats()
{
    global $conn;

    $stats = [];

    $sql = "SELECT COUNT(*) as total FROM orders";
    $result = $conn->query($sql);
    $stats['total_orders'] = $result->fetch_assoc()['total'];

    $sql = "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'";
    $result = $conn->query($sql);
    $stats['pending_orders'] = $result->fetch_assoc()['total'];

    $sql = "SELECT COALESCE(SUM(total), 0) as revenue FROM orders WHERE status != 'cancelled'";
    $result = $conn->query($sql);
    $stats['total_revenue'] = floatval($result->fetch_assoc()['revenue']);

    $sql = "SELECT COUNT(*) as total FROM customers";
    $result = $conn->query($sql);
    $stats['total_customers'] = $result->fetch_assoc()['total'];

    sendResponse(true, 'Stats retrieved', ['stats' => $stats]);
}

function getOrders()
{
    global $conn;

    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);

    $sql = "SELECT * FROM orders ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    sendResponse(true, 'Orders retrieved', ['orders' => $orders]);
    $stmt->close();
}

function getProducts()
{
    global $conn;

    $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC";
    $result = $conn->query($sql);

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    sendResponse(true, 'Products retrieved', ['products' => $products]);
}

function getCustomers()
{
    global $conn;

    $sql = "SELECT * FROM customers ORDER BY created_at DESC";
    $result = $conn->query($sql);

    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }

    sendResponse(true, 'Customers retrieved', ['customers' => $customers]);
}

function getMessages()
{
    global $conn;

    $sql = "SELECT * FROM contact_messages ORDER BY created_at DESC";
    $result = $conn->query($sql);

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }

    sendResponse(true, 'Messages retrieved', ['messages' => $messages]);
}

function updateOrderStatus()
{
    global $conn;

    $orderId = sanitize($_POST['order_id'] ?? '');
    $status = sanitize($_POST['status'] ?? '');

    $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        sendResponse(false, 'Invalid status');
    }

    $sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $status, $orderId);

    if ($stmt->execute()) {
        sendResponse(true, 'Order status updated');
    } else {
        sendResponse(false, 'Failed to update order');
    }

    $stmt->close();
}

function sanitize($data)
{
    global $conn;
    return $conn->real_escape_string(trim(strip_tags($data)));
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