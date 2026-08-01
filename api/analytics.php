<?php
/**
 * Analytics API - Generate Sales Reports
 * Handles sales analytics and business metrics
 */

header('Content-Type: application/json');
require_once 'config.php';

$action = $_GET['action'] ?? '';

switch($action) {
    case 'sales_summary':
        getSalesSummary();
        break;
    case 'sales_by_month':
        getSalesByMonth();
        break;
    case 'top_products':
        getTopProducts();
        break;
    case 'revenue_by_payment':
        getRevenueByPaymentMethod();
        break;
    case 'order_status_summary':
        getOrderStatusSummary();
        break;
    case 'customer_analytics':
        getCustomerAnalytics();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

/**
 * Get overall sales summary
 */
function getSalesSummary() {
    global $conn;
    
    $sql = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(total) as total_revenue,
            AVG(total) as average_order_value,
            COUNT(DISTINCT email) as unique_customers,
            MAX(created_at) as latest_order_date
        FROM orders
        WHERE status != 'cancelled'
    ";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $summary = $result->fetch_assoc();
    
    // Calculate conversion metrics
    $summary['total_revenue'] = floatval($summary['total_revenue']);
    $summary['average_order_value'] = floatval($summary['average_order_value']);

    sendResponse(true, 'Sales summary retrieved', ['summary' => $summary]);
}

/**
 * Get sales by month
 */
function getSalesByMonth() {
    global $conn;
    
    $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
    
    $sql = "
        SELECT 
            DATE_TRUNC(created_at, MONTH) as month,
            COUNT(*) as order_count,
            SUM(total) as revenue
        FROM orders
        WHERE status != 'cancelled' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
        GROUP BY DATE_TRUNC(created_at, MONTH)
        ORDER BY month DESC
    ";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('i', $months);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['revenue'] = floatval($row['revenue']);
        $data[] = $row;
    }

    sendResponse(true, 'Monthly sales data retrieved', ['sales_by_month' => $data]);
}

/**
 * Get top selling products
 */
function getTopProducts() {
    global $conn;
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    $sql = "
        SELECT 
            oi.product_name,
            oi.product_id,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.subtotal) as total_revenue,
            COUNT(DISTINCT oi.order_id) as order_count,
            AVG(oi.price) as average_price
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.status != 'cancelled'
        GROUP BY oi.product_id, oi.product_name
        ORDER BY total_revenue DESC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($product = $result->fetch_assoc()) {
        $product['total_revenue'] = floatval($product['total_revenue']);
        $product['average_price'] = floatval($product['average_price']);
        $products[] = $product;
    }

    sendResponse(true, 'Top products retrieved', ['products' => $products]);
}

/**
 * Get revenue by payment method
 */
function getRevenueByPaymentMethod() {
    global $conn;
    
    $sql = "
        SELECT 
            payment_method,
            COUNT(*) as order_count,
            SUM(total) as total_revenue,
            AVG(total) as average_order_value,
            MIN(total) as minimum_order,
            MAX(total) as maximum_order
        FROM orders
        WHERE status != 'cancelled'
        GROUP BY payment_method
        ORDER BY total_revenue DESC
    ";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['total_revenue'] = floatval($row['total_revenue']);
        $row['average_order_value'] = floatval($row['average_order_value']);
        $row['minimum_order'] = floatval($row['minimum_order']);
        $row['maximum_order'] = floatval($row['maximum_order']);
        $data[] = $row;
    }

    sendResponse(true, 'Revenue by payment method retrieved', ['data' => $data]);
}

/**
 * Get order status summary
 */
function getOrderStatusSummary() {
    global $conn;
    
    $sql = "
        SELECT 
            status,
            COUNT(*) as order_count,
            SUM(total) as total_value,
            AVG(total) as average_value
        FROM orders
        GROUP BY status
        ORDER BY order_count DESC
    ";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['total_value'] = floatval($row['total_value']);
        $row['average_value'] = floatval($row['average_value']);
        $data[] = $row;
    }

    sendResponse(true, 'Order status summary retrieved', ['data' => $data]);
}

/**
 * Get customer analytics
 */
function getCustomerAnalytics() {
    global $conn;
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    $sql = "
        SELECT 
            email,
            customer_name,
            COUNT(*) as order_count,
            SUM(total) as lifetime_value,
            AVG(total) as average_order_value,
            MIN(created_at) as first_order_date,
            MAX(created_at) as last_order_date
        FROM orders
        WHERE status != 'cancelled'
        GROUP BY email, customer_name
        ORDER BY lifetime_value DESC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendResponse(false, 'Database error: ' . $conn->error);
        return;
    }

    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $customers = [];
    while ($customer = $result->fetch_assoc()) {
        $customer['lifetime_value'] = floatval($customer['lifetime_value']);
        $customer['average_order_value'] = floatval($customer['average_order_value']);
        $customers[] = $customer;
    }

    sendResponse(true, 'Customer analytics retrieved', ['customers' => $customers]);
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
