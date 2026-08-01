<?php
/**
 * Checkout API - Process Orders (CLEAN VERSION)
 */

header('Content-Type: application/json');
require_once 'config.php';

/* =========================
   PHPMailer
========================= */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

/* =========================
   ROUTE REQUEST
========================= */
$action = $_POST['action'] ?? '';

if ($action === 'create_order') {
    createOrder();
} else {
    sendResponse(false, 'Invalid action');
}

/* =========================
   CREATE ORDER
========================= */
function createOrder()
{
    global $conn;

    $required = [
        'fullName', 'email', 'phone',
        'address', 'city', 'state',
        'paymentMethod', 'cart', 'total'
    ];

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            sendResponse(false, "Field {$field} is required");
        }
    }

    $fullName = sanitize($_POST['fullName']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $paymentMethod = sanitize($_POST['paymentMethod']);

    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $tax = floatval($_POST['tax'] ?? 0);
    $shipping = floatval($_POST['shipping'] ?? 0);
    $total = floatval($_POST['total']);

    $cartItems = json_decode($_POST['cart'], true);

    if (!is_array($cartItems) || empty($cartItems)) {
        sendResponse(false, 'Cart is empty');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email');
    }

    if (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
        sendResponse(false, 'Invalid phone');
    }

    $order_id = generateOrderId();

    $conn->begin_transaction();

    try {

        /* =========================
           INSERT ORDER
        ========================= */
        $sql = "INSERT INTO orders 
        (order_id, customer_name, email, phone, address, city, state, payment_method, subtotal, tax, shipping, total, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ssssssssddds",
            $order_id,
            $fullName,
            $email,
            $phone,
            $address,
            $city,
            $state,
            $paymentMethod,
            $subtotal,
            $tax,
            $shipping,
            $total
        );

        $stmt->execute();
        $stmt->close();

        /* =========================
           INSERT ORDER ITEMS
        ========================= */
        $sqlItem = "INSERT INTO order_items 
        (order_id, product_id, product_name, price, quantity, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)";

        $stmtItem = $conn->prepare($sqlItem);

        foreach ($cartItems as $item) {

            $product_id = intval($item['id']);
            $product_name = sanitize($item['name']);
            $price = floatval($item['price']);
            $qty = intval($item['quantity']);
            $subtotalItem = $price * $qty;

            $stmtItem->bind_param(
                "sisdid",
                $order_id,
                $product_id,
                $product_name,
                $price,
                $qty,
                $subtotalItem
            );

            $stmtItem->execute();
        }

        $stmtItem->close();

        $conn->commit();
		
		 /* =========================
           WHATSAPP NOTIFICATION
        ========================= */
		
		function sendWhatsAppNotification($order_id, $name, $total)
{
    $phone = "2348063758039"; // your admin number

    $message = urlencode("
	New Order Received:

Order ID: $order_id
Customer: $name
Total: ₦$total
");

    $url = "https://wa.me/$phone?text=$message";

    file_get_contents($url);
}

        /* =========================
           SEND EMAIL
        ========================= */
        sendConfirmationEmail($email, $fullName, $order_id, $cartItems, $total);

        sendResponse(true, 'Order placed successfully', [
            'order_id' => $order_id
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, $e->getMessage());
    }
}

/* =========================
   ORDER ID
========================= */
function generateOrderId()
{
    return 'ORD-' . time() . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/* =========================
   SANITIZE
========================= */
function sanitize($data)
{
    global $conn;
    return $conn->real_escape_string(trim(strip_tags($data)));
}

/* =========================
   RESPONSE
========================= */
function sendResponse($success, $message, $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/* =========================
   EMAIL (PHPMailer SMTP)
========================= */
function sendConfirmationEmail($email, $name, $order_id, $items, $total)
{
    $baseUrl = 'https://www.printsav.com.ng/';

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'yourgmail@gmail.com';
        $mail->Password = 'your-app-password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('yourgmail@gmail.com', 'PrintSav');
        $mail->addAddress($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Order Confirmation - $order_id";

        $body = "
        <h2>Order Confirmation</h2>
        <p>Hi " . htmlspecialchars($name) . "</p>
        <p>Your order has been received.</p>

        <p><strong>Order ID:</strong> $order_id</p>

        <table style='width:100%;border-collapse:collapse;'>
        ";

        foreach ($items as $item) {

            $image = !empty($item['image'])
                ? $baseUrl . ltrim($item['image'], '/')
                : $baseUrl . 'img/placeholder.jpg';

            $body .= "
            <tr>
                <td>
                    <img src='$image' width='60' height='60' style='object-fit:cover;border-radius:5px;'>
                </td>
                <td>
                    {$item['name']} x {$item['quantity']}
                </td>
                <td align='right'>
                    ₦" . number_format($item['price'] * $item['quantity'], 2) . "
                </td>
            </tr>
            ";
        }

        $body .= "
        <tr>
            <td colspan='2'><b>Total</b></td>
            <td align='right'><b>₦" . number_format($total, 2) . "</b></td>
        </tr>
        </table>
        ";

        $mail->Body = $body;

        $mail->send();

    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
    }
}
?>