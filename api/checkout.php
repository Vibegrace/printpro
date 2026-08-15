<?php
/**
 * Checkout API - server-authoritative order creation.
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

$action = $_POST['action'] ?? '';
if ($action === 'create_order') createOrder();
sendResponse(false, 'Invalid action', [], 400);

function createOrder(): void
{
    global $conn;

    $required = ['fullName','email','phone','address','city','state','paymentMethod','cart'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) sendResponse(false, "Field {$field} is required", [], 422);
    }

    $fullName = clean($_POST['fullName']);
    $email = strtolower(clean($_POST['email']));
    $phone = clean($_POST['phone']);
    $address = clean($_POST['address']);
    $city = clean($_POST['city']);
    $state = clean($_POST['state']);
    $paymentMethod = clean($_POST['paymentMethod']);
    $cartItems = json_decode($_POST['cart'], true);

    if (!is_array($cartItems) || empty($cartItems)) sendResponse(false, 'Cart is empty', [], 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendResponse(false, 'Invalid email', [], 422);
    if (!preg_match('/^[0-9+\-\s()]+$/', $phone)) sendResponse(false, 'Invalid phone', [], 422);
    if (count($cartItems) > 100) sendResponse(false, 'Cart contains too many items', [], 422);

    $items = [];
    $subtotal = 0.0;
    $lookup = $conn->prepare('SELECT id, product_name, price, status FROM products WHERE id = ? LIMIT 1');

    foreach ($cartItems as $item) {
        $productId = (int)($item['id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 0);
        if ($productId <= 0 || $quantity < 1 || $quantity > 10000) {
            $lookup->close();
            sendResponse(false, 'Invalid cart item', [], 422);
        }

        $lookup->bind_param('i', $productId);
        $lookup->execute();
        $result = $lookup->get_result();
        if (!$result->num_rows) {
            $lookup->close();
            sendResponse(false, 'A product in your cart is no longer available', [], 409);
        }

        $product = $result->fetch_assoc();
        if (($product['status'] ?? 'active') !== 'active') {
            $lookup->close();
            sendResponse(false, 'A product in your cart is no longer available', [], 409);
        }

        $price = (float)$product['price'];
        $lineTotal = round($price * $quantity, 2);
        $subtotal += $lineTotal;
        $items[] = [
            'id' => $productId,
            'name' => $product['product_name'],
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => $lineTotal
        ];
    }
    $lookup->close();

    // Keep these business rules server-side so clients cannot alter the amount charged/stored.
    $tax = round($subtotal * 0.05, 2);
    $shipping = 500.00;
    $total = round($subtotal + $tax + $shipping, 2);
    $orderId = generateOrderId();

    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO orders
            (order_id, customer_name, email, phone, address, city, state, payment_method,
             subtotal, tax, shipping, total, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssssssssdddd',
            $orderId, $fullName, $email, $phone, $address, $city, $state, $paymentMethod,
            $subtotal, $tax, $shipping, $total
        );
        if (!$stmt->execute()) throw new RuntimeException('Unable to create order');
        $stmt->close();

        $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($items as $item) {
            $itemStmt->bind_param('sisdid', $orderId, $item['id'], $item['name'], $item['price'], $item['quantity'], $item['subtotal']);
            if (!$itemStmt->execute()) throw new RuntimeException('Unable to create order items');
        }
        $itemStmt->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Checkout error: '.$e->getMessage());
        sendResponse(false, 'Unable to place order. Please try again.', [], 500);
    }

    sendConfirmationEmail($email, $fullName, $orderId, $items, $total);
    sendResponse(true, 'Order placed successfully', ['order_id' => $orderId, 'total' => $total]);
}

function generateOrderId(): string
{
    return 'ORD-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function sendConfirmationEmail(string $email, string $name, string $orderId, array $items, float $total): void
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('MAIL_USERNAME') ?: '';
        $mail->Password = getenv('MAIL_PASSWORD') ?: '';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        if ($mail->Username) $mail->setFrom($mail->Username, 'Printsiv');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = "Order Confirmation - {$orderId}";

        $body = '<h2>Order Confirmation</h2><p>Hi '.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</p>';
        $body .= '<p>Your order has been received.</p><p><strong>Order ID:</strong> '.htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8').'</p><table style="width:100%;border-collapse:collapse;">';
        foreach ($items as $item) {
            $body .= '<tr><td>'.htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8').' x '.(int)$item['quantity'].'</td><td align="right">₦'.number_format($item['subtotal'], 2).'</td></tr>';
        }
        $body .= '<tr><td><b>Total</b></td><td align="right"><b>₦'.number_format($total, 2).'</b></td></tr></table>';
        $mail->Body = $body;
        if ($mail->Username && $mail->Password) $mail->send();
    } catch (Exception $e) {
        error_log('Order confirmation mail error: '.$e->getMessage());
    }
}

function clean($data): string
{
    global $conn;
    return $conn->real_escape_string(trim(strip_tags((string)$data)));
}

function sendResponse(bool $success, string $message, array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data]);
    exit;
}
?>
