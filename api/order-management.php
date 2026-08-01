<?php
/**
 * Order Management API - Update Orders
 * Handles order status updates and order management
 */

header('Content-Type: application/json');
require_once 'config.php';

$action = $_POST['action'] ?? '';

switch($action) {
    case 'update_status':
        updateOrderStatus();
        break;
    case 'update_notes':
        updateOrderNotes();
        break;
    case 'cancel_order':
        cancelOrder();
        break;
    case 'delete_order':
        deleteOrder();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

/**
 * Update order status
 */
function updateOrderStatus() {
    global $conn;
    
    $order_id = $_POST['order_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    
    if (empty($order_id)) {
        sendResponse(false, 'Order ID is required');
        return;
    }
    
    if (empty($status) || !in_array($status, $valid_statuses)) {
        sendResponse(false, 'Invalid status. Must be: ' . implode(', ', $valid_statuses));
        return;
    }

    // Check if order exists
    $sql_check = "SELECT id FROM orders WHERE order_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param('s', $order_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'Order not found');
        return;
    }

    // Update status
    $sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('ss', $status, $order_id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Order status updated successfully', [
            'order_id' => $order_id,
            'new_status' => $status
        ]);
    } else {
        sendResponse(false, 'Failed to update order: ' . $stmt->error);
    }
}

/**
 * Update order notes
 */
function updateOrderNotes() {
    global $conn;
    
    $order_id = $_POST['order_id'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($order_id)) {
        sendResponse(false, 'Order ID is required');
        return;
    }

    // Check if order exists
    $sql_check = "SELECT id FROM orders WHERE order_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param('s', $order_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'Order not found');
        return;
    }

    // Sanitize notes
    $notes = $conn->real_escape_string(trim($notes));

    // Update notes
    $sql = "UPDATE orders SET notes = ?, updated_at = NOW() WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('ss', $notes, $order_id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Order notes updated successfully', [
            'order_id' => $order_id,
            'notes' => $notes
        ]);
    } else {
        sendResponse(false, 'Failed to update notes: ' . $stmt->error);
    }
}

/**
 * Cancel order
 */
function cancelOrder() {
    global $conn;
    
    $order_id = $_POST['order_id'] ?? '';
    
    if (empty($order_id)) {
        sendResponse(false, 'Order ID is required');
        return;
    }

    // Check if order exists and get status
    $sql_check = "SELECT status FROM orders WHERE order_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param('s', $order_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'Order not found');
        return;
    }

    $order = $result->fetch_assoc();
    
    // Don't allow cancelling already cancelled or delivered orders
    if ($order['status'] === 'cancelled') {
        sendResponse(false, 'Order is already cancelled');
        return;
    }
    
    if ($order['status'] === 'delivered') {
        sendResponse(false, 'Cannot cancel a delivered order');
        return;
    }

    // Update status to cancelled
    $sql = "UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('s', $order_id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Order cancelled successfully', [
            'order_id' => $order_id,
            'status' => 'cancelled'
        ]);
    } else {
        sendResponse(false, 'Failed to cancel order: ' . $stmt->error);
    }
}

/**
 * Delete order (only pending orders)
 */
function deleteOrder() {
    global $conn;
    
    $order_id = $_POST['order_id'] ?? '';
    
    if (empty($order_id)) {
        sendResponse(false, 'Order ID is required');
        return;
    }

    // Check if order exists and get status
    $sql_check = "SELECT status FROM orders WHERE order_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param('s', $order_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'Order not found');
        return;
    }

    $order = $result->fetch_assoc();
    
    // Only allow deleting pending orders
    if ($order['status'] !== 'pending') {
        sendResponse(false, 'Can only delete pending orders. Current status: ' . $order['status']);
        return;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete order items first (due to foreign key constraint)
        $sql_items = "DELETE FROM order_items WHERE order_id = ?";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->bind_param('s', $order_id);
        $stmt_items->execute();

        // Delete order
        $sql = "DELETE FROM orders WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $order_id);
        $stmt->execute();

        $conn->commit();

        sendResponse(true, 'Order deleted successfully', ['order_id' => $order_id]);

    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, 'Error deleting order: ' . $e->getMessage());
    }
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
