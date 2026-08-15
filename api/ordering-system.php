<?php
/**
 * Ordering System API
 * Guest checkout is supported, but all pricing is calculated from the database.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$action = $_POST['action'] ?? '';
if ($action !== 'place_order') sendResponse(false, 'Invalid action', 400);
placeOrder();

function placeOrder(): void
{
    global $conn;

    foreach (['order_id','product_id','quantity','phone','customer_name','email','address','city','state','paymentMethod'] as $field) {
        if (!isset($_POST[$field]) || trim((string)$_POST[$field]) === '') sendResponse(false, "Field {$field} is required", 422);
    }

    $orderId = trim((string)$_POST['order_id']);
    $productId = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $phone = trim((string)$_POST['phone']);
    $customerName = trim((string)$_POST['customer_name']);
    $email = strtolower(trim((string)$_POST['email']));
    $address = trim((string)$_POST['address']);
    $city = trim((string)$_POST['city']);
    $state = trim((string)$_POST['state']);
    $paymentMethod = trim((string)$_POST['paymentMethod']);
    $specialInstructions = trim((string)($_POST['special_instructions'] ?? ''));

    if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $orderId)) sendResponse(false, 'Invalid order ID', 422);
    if ($productId <= 0 || $quantity < 1 || $quantity > 10000) sendResponse(false, 'Invalid product or quantity', 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendResponse(false, 'Invalid email address', 422);
    if (!preg_match('/^[0-9+\-\s()]{7,25}$/', $phone)) sendResponse(false, 'Invalid phone number', 422);

    // Never trust product name, unit price, or total supplied by the browser.
    $product = $conn->prepare('SELECT id, name, price FROM products WHERE id = ? AND (status IS NULL OR status = "active") LIMIT 1');
    if (!$product) sendResponse(false, 'Unable to validate product', 500);
    $product->bind_param('i', $productId);
    $product->execute();
    $result = $product->get_result();
    if (!$result->num_rows) { $product->close(); sendResponse(false, 'Product is unavailable', 404); }
    $row = $result->fetch_assoc();
    $product->close();

    $unitPrice = (float)$row['price'];
    $subtotal = round($unitPrice * $quantity, 2);
    $shipping = 500.00;
    $tax = round($subtotal * 0.05, 2);
    $total = round($subtotal + $tax + $shipping, 2);

    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO orders
            (order_id, customer_name, email, phone, address, city, state, payment_method,
             subtotal, tax, shipping, total, status, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new RuntimeException('Unable to prepare order');
        $stmt->bind_param('ssssssssdddss', $orderId, $customerName, $email, $phone, $address, $city, $state, $paymentMethod, $subtotal, $tax, $shipping, $total, $specialInstructions);
        if (!$stmt->execute()) throw new RuntimeException('Unable to create order');
        $stmt->close();

        $item = $conn->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$item) throw new RuntimeException('Unable to prepare order item');
        $itemSubtotal = $subtotal;
        $item->bind_param('sisdid', $orderId, $productId, $row['name'], $unitPrice, $quantity, $itemSubtotal);
        if (!$item->execute()) throw new RuntimeException('Unable to create order item');
        $item->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Ordering error: '.$e->getMessage());
        sendResponse(false, 'Unable to place order', 500);
    }

    sendWhatsAppNotification($orderId, $customerName, $total, $phone, $row['name'], $quantity, $email, $address, $city, $state, $specialInstructions);
    sendResponse(true, 'Order placed successfully', 200, ['order_id'=>$orderId]);
}

function sendWhatsAppNotification(string $orderId,string $name,float $total,string $phone,string $productName,int $quantity,string $email,string $address,string $city,string $state,string $instructions): void
{
    $adminPhone = getenv('ORDER_ADMIN_WHATSAPP') ?: '';
    if ($adminPhone === '') return;
    $message = urlencode("New Order Received!\n\nOrder ID: {$orderId}\nCustomer: {$name}\nProduct: {$productName}\nQuantity: {$quantity}\nTotal: ₦".number_format($total,2)."\nPhone: {$phone}\nEmail: {$email}\nAddress: {$address}, {$city}, {$state}\nInstructions: {$instructions}");
    $url = 'https://wa.me/'.rawurlencode($adminPhone).'?text='.$message;
    $context = stream_context_create(['http'=>['timeout'=>3,'ignore_errors'=>true]]);
    @file_get_contents($url, false, $context);
}

function sendResponse(bool $success,string $message,int $status=200,array $data=[]):void{http_response_code($status);echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data]);exit;}
?>
