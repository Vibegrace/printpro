<?php
/**
 * Admin-only sales analytics API.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';
require_once __DIR__ . '/middleware.php';
requireAdmin();

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'sales_summary': getSalesSummary(); break;
    case 'sales_by_month': getSalesByMonth(); break;
    case 'top_products': getTopProducts(); break;
    case 'revenue_by_payment': getRevenueByPaymentMethod(); break;
    case 'order_status_summary': getOrderStatusSummary(); break;
    case 'customer_analytics': getCustomerAnalytics(); break;
    default: sendResponse(false, 'Invalid action', 400);
}

function getSalesSummary(): void {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) total_orders, COALESCE(SUM(total),0) total_revenue, COALESCE(AVG(total),0) average_order_value, COUNT(DISTINCT email) unique_customers, MAX(created_at) latest_order_date FROM orders WHERE status != 'cancelled'");
    if (!$result) sendResponse(false, 'Unable to retrieve sales summary', 500);
    $summary = $result->fetch_assoc();
    $summary['total_revenue'] = (float)$summary['total_revenue'];
    $summary['average_order_value'] = (float)$summary['average_order_value'];
    sendResponse(true, 'Sales summary retrieved', 200, ['summary'=>$summary]);
}

function getSalesByMonth(): void {
    global $conn;
    $months = max(1, min(60, (int)($_GET['months'] ?? 12)));
    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m-01') month, COUNT(*) order_count, COALESCE(SUM(total),0) revenue FROM orders WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY month DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) sendResponse(false, 'Unable to prepare sales report', 500);
    $stmt->bind_param('i', $months); $stmt->execute(); $result = $stmt->get_result();
    $data=[]; while($row=$result->fetch_assoc()){ $row['revenue']=(float)$row['revenue']; $data[]=$row; }
    $stmt->close(); sendResponse(true, 'Monthly sales data retrieved', 200, ['sales_by_month'=>$data]);
}

function getTopProducts(): void {
    global $conn;
    $limit=max(1,min(100,(int)($_GET['limit']??10)));
    $stmt=$conn->prepare("SELECT oi.product_name, oi.product_id, SUM(oi.quantity) total_quantity, SUM(oi.subtotal) total_revenue, COUNT(DISTINCT oi.order_id) order_count, AVG(oi.price) average_price FROM order_items oi JOIN orders o ON oi.order_id=o.order_id WHERE o.status!='cancelled' GROUP BY oi.product_id,oi.product_name ORDER BY total_revenue DESC LIMIT ?");
    if(!$stmt) sendResponse(false,'Unable to prepare product report',500);
    $stmt->bind_param('i',$limit);$stmt->execute();$result=$stmt->get_result();$products=[];
    while($row=$result->fetch_assoc()){ $row['total_revenue']=(float)$row['total_revenue'];$row['average_price']=(float)$row['average_price'];$products[]=$row; }
    $stmt->close();sendResponse(true,'Top products retrieved',200,['products'=>$products]);
}

function getRevenueByPaymentMethod(): void {
    global $conn;
    $result=$conn->query("SELECT payment_method,COUNT(*) order_count,COALESCE(SUM(total),0) total_revenue,COALESCE(AVG(total),0) average_order_value,MIN(total) minimum_order,MAX(total) maximum_order FROM orders WHERE status!='cancelled' GROUP BY payment_method ORDER BY total_revenue DESC");
    if(!$result) sendResponse(false,'Unable to retrieve payment analytics',500);$data=[];
    while($row=$result->fetch_assoc()){foreach(['total_revenue','average_order_value','minimum_order','maximum_order'] as $k)$row[$k]=(float)$row[$k];$data[]=$row;}
    sendResponse(true,'Revenue by payment method retrieved',200,['data'=>$data]);
}

function getOrderStatusSummary(): void {
    global $conn;
    $result=$conn->query("SELECT status,COUNT(*) order_count,COALESCE(SUM(total),0) total_value,COALESCE(AVG(total),0) average_value FROM orders GROUP BY status ORDER BY order_count DESC");
    if(!$result) sendResponse(false,'Unable to retrieve order status analytics',500);$data=[];
    while($row=$result->fetch_assoc()){$row['total_value']=(float)$row['total_value'];$row['average_value']=(float)$row['average_value'];$data[]=$row;}
    sendResponse(true,'Order status summary retrieved',200,['data'=>$data]);
}

function getCustomerAnalytics(): void {
    global $conn;
    $limit=max(1,min(100,(int)($_GET['limit']??10)));
    $stmt=$conn->prepare("SELECT email,customer_name,COUNT(*) order_count,COALESCE(SUM(total),0) lifetime_value,COALESCE(AVG(total),0) average_order_value,MIN(created_at) first_order_date,MAX(created_at) last_order_date FROM orders WHERE status!='cancelled' GROUP BY email,customer_name ORDER BY lifetime_value DESC LIMIT ?");
    if(!$stmt) sendResponse(false,'Unable to prepare customer analytics',500);$stmt->bind_param('i',$limit);$stmt->execute();$result=$stmt->get_result();$customers=[];
    while($row=$result->fetch_assoc()){$row['lifetime_value']=(float)$row['lifetime_value'];$row['average_order_value']=(float)$row['average_order_value'];$customers[]=$row;}
    $stmt->close();sendResponse(true,'Customer analytics retrieved',200,['customers'=>$customers]);
}

function sendResponse(bool $success,string $message,int $status=200,array $data=[]):void{http_response_code($status);echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data]);exit;}
?>
