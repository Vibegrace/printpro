<?php
/**
 * Orders API - Retrieve Order Information
 * Handles fetching orders by ID, status, or customer email
 */

header('Content-Type: application/json');
require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'get_order':
        getOrder();
        break;
    case 'get_orders_by_email':
        getOrdersByEmail();
        break;
    case 'get_all_orders':
        getAllOrders();
        break;
    case 'get_order_items':
        getOrderItems();
        break;
    case 'get_orders_by_status':
        getOrdersByStatus();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

/**
 * Get single order by order ID
 */
function getOrder() {
    global $conn;
    
    $order_id = $_GET['order_id'] ?? '';
    
    if (empty($order_id)) {
        sendResponse(false, 'Order ID is required');
        return;
    }

    $sql = "SELECT * FROM orders WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('s', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'Order not found');
        return;
    }

    $order = $result->fetch_assoc();
    
    // Get order items
    $sql_items = "SELECT * FROM order_items WHERE order_id = ?";
    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param('s', $order_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    
    $order['items'] = $items;
    
    sendResponse(true, 'Order retrieved successfully', ['order' => $order]);
}

/**
 * Get all orders for a customer by email
 */
function getOrdersByEmail() {
    global $conn;
    
    $email = $_GET['email'] ?? '';
    
    if (empty($email)) {
        sendResponse(false, 'Email is required');
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email format');
        return;
    }

    $sql = "SELECT * FROM orders WHERE email = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($order = $result->fetch_assoc()) {
        // Get item count for each order
        $sql_count = "SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bind_param('s', $order['order_id']);
        $stmt_count->execute();
        $count_result = $stmt_count->get_result();
        $count = $count_result->fetch_assoc();
        $order['item_count'] = $count['item_count'];
        
        $orders[] = $order;
    }

    sendResponse(true, 'Orders retrieved successfully', ['orders' => $orders]);
}

/**
 * Get all orders (with pagination for admin)
 */
function getAllOrders() {
    global $conn;
    
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = ($page - 1) * $limit;

    // Get total count
    $sql_count = "SELECT COUNT(*) as total FROM orders";
    $count_result = $conn->query($sql_count);
    $count_row = $count_result->fetch_assoc();
    $total = $count_row['total'];

    // Get orders
    $sql = "SELECT * FROM orders ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($order = $result->fetch_assoc()) {
        // Get item count
        $sql_count = "SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bind_param('s', $order['order_id']);
        $stmt_count->execute();
        $count_result = $stmt_count->get_result();
        $count = $count_result->fetch_assoc();
        $order['item_count'] = $count['item_count'];
        
        $orders[] = $order;
    }

    $total_pages = ceil($total / $limit);

    sendResponse(true, 'Orders retrieved successfully', [
        'orders' => $orders,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_orders' => $total,
            'orders_per_page' => $limit
        ]
    ]);
}

/**
 * Get items for an order
 */
function getOrderItems() {
    global $conn;
    
    $order_id = $_GET['order_id'] ?? '';
    
    if (empty($order_id)) {
        sendResponse(false, 'Order ID is required');
        return;
    }

    $sql = "SELECT * FROM order_items WHERE order_id = ? ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('s', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($item = $result->fetch_assoc()) {
        $items[] = $item;
    }

    sendResponse(true, 'Order items retrieved successfully', ['items' => $items]);
}

/**
 * Get orders by status
 */
function getOrdersByStatus() {
    global $conn;
    
    $status = $_GET['status'] ?? '';
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    
    if (empty($status) || !in_array($status, $valid_statuses)) {
        sendResponse(false, 'Invalid or missing status');
        return;
    }

    $sql = "SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($order = $result->fetch_assoc()) {
        $orders[] = $order;
    }

    sendResponse(true, 'Orders retrieved successfully', [
        'status' => $status,
        'orders' => $orders,
        'count' => count($orders)
    ]);
}

/**
 * Send JSON response
 */
function sendResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if ($data) {
        $response = array_merge($response, $data);
    }

    echo json_encode($response);
    exit;
}

?>
