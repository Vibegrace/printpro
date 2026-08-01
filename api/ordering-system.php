<?php
/**
 * Ordering System API
 * Handles order placement via WhatsApp checkout
 */

header('Content-Type: application/json');
require_once '../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'place_order':
        placeOrder();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function placeOrder()
{
    global $conn;

    $required = ['order_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'total',
                 'phone', 'customer_name', 'email', 'address', 'city', 'state', 'paymentMethod'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            sendResponse(false, "Field {$field} is required");
        }
    }

    $orderId = sanitize($_POST['order_id']);
    $productId = intval($_POST['product_id']);
    $productName = sanitize($_POST['product_name']);
    $quantity = intval($_POST['quantity']);
    $unitPrice = floatval($_POST['unit_price']);
    $total = floatval($_POST['total']);
    $phone = sanitize($_POST['phone']);
    $customerName = sanitize($_POST['customer_name']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $paymentMethod = sanitize($_POST['paymentMethod']);
    $paperType = sanitize($_POST['paper_type'] ?? 'standard');
    $finishType = sanitize($_POST['finish_type'] ?? 'matte');
    $printSide = sanitize($_POST['print_side'] ?? 'single');
    $colorOption = sanitize($_POST['color_option'] ?? 'full_color');
    $specialInstructions = sanitize($_POST['special_instructions'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email address');
    }

    if (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
        sendResponse(false, 'Invalid phone number');
    }

    $conn->begin_transaction();

    try {
        $sql = "INSERT INTO orders 
        (order_id, customer_name, email, phone, address, city, state, payment_method, subtotal, tax, shipping, total, status, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";

        $tax = $total * 0.05;
        $shipping = 500;
        $subtotal = $total - $tax - $shipping;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssddds",
            $orderId,
            $customerName,
            $email,
            $phone,
            $address,
            $city,
            $state,
            $paymentMethod,
            $subtotal,
            $tax,
            $shipping,
            $total,
            $specialInstructions
        );
        $stmt->execute();
        $stmt->close();

        $sqlItem = "INSERT INTO order_items 
        (order_id, product_id, product_name, price, quantity, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)";

        $stmtItem = $conn->prepare($sqlItem);
        $stmtItem->bind_param("sisdid", $orderId, $productId, $productName, $unitPrice, $quantity, $total);
        $stmtItem->execute();
        $stmtItem->close();

        $conn->commit();

        sendWhatsAppNotification($orderId, $customerName, $total, $phone, $productName, $quantity, $email, $address, $city, $state, $specialInstructions);

        sendResponse(true, 'Order placed successfully', [
            'order_id' => $orderId
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, $e->getMessage());
    }
}

function sendWhatsAppNotification($orderId, $name, $total, $phone, $productName, $quantity, $email, $address, $city, $state, $instructions)
{
    $adminPhone = "2348063758039";

    $message = urlencode(
        "New Order Received!\n\n" .
        "Order ID: {$orderId}\n" .
        "Customer: {$name}\n" .
        "Product: {$productName}\n" .
        "Quantity: {$quantity}\n" .
        "Total: ₦{$total}\n" .
        "Phone: {$phone}\n" .
        "Email: {$email}\n" .
        "Address: {$address}, {$city}, {$state}\n" .
        "Instructions: {$instructions}"
    );

    $url = "https://wa.me/{$adminPhone}?text={$message}";
    file_get_contents($url);
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