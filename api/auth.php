<?php
/**
 * Authentication API
 * Handles signup, login, and forget password
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
    case 'signup':
        handleSignup();
        break;
    case 'login':
        handleLogin();
        break;
    case 'forget_password':
        handleForgetPassword();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function handleSignup()
{
    global $conn;

    $required = ['firstName', 'lastName', 'email', 'phone', 'address', 'city', 'state', 'password'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            sendResponse(false, "Field {$field} is required");
        }
    }

    $firstName = sanitize($_POST['firstName']);
    $lastName = sanitize($_POST['lastName']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email address');
    }

    $check = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    $check->bind_param('s', $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        sendResponse(false, 'An account with this email already exists');
    }

    $sql = "INSERT INTO customers (email, phone, first_name, last_name, address, city, state, password, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssss', $email, $phone, $firstName, $lastName, $address, $city, $state, $password);

    if ($stmt->execute()) {
        $userId = $conn->insert_id;

        sendResponse(true, 'Account created successfully', [
            'user' => [
                'id' => $userId,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'role' => 'user'
            ]
        ]);
    } else {
        sendResponse(false, 'Database error: ' . $stmt->error);
    }

    $stmt->close();
}

function handleLogin()
{
    global $conn;

    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        sendResponse(false, 'Email and password are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email address');
    }

    $sql = "SELECT * FROM customers WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'No account found with this email');
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        sendResponse(false, 'Invalid password');
    }

    $sqlUpdate = "UPDATE customers SET last_login = NOW() WHERE id = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param('i', $user['id']);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    sendResponse(true, 'Login successful', [
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'phone' => $user['phone'],
            'address' => $user['address'],
            'city' => $user['city'],
            'state' => $user['state'],
            'role' => 'user',
            'created_at' => $user['created_at']
        ]
    ]);

    $stmt->close();
}

function handleForgetPassword()
{
    global $conn;

    $email = sanitize($_POST['email'] ?? '');

    if (empty($email)) {
        sendResponse(false, 'Email is required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email address');
    }

    $sql = "SELECT * FROM customers WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'No account found with this email');
    }

    $user = $result->fetch_assoc();
    $token = bin2hex(random_bytes(32));

    $sqlToken = "INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())";
    $stmtToken = $conn->prepare($sqlToken);
    $stmtToken->bind_param('ss', $email, $token);
    $stmtToken->execute();
    $stmtToken->close();

    $resetLink = 'https://www.printsiv.com/reset-password?token=' . $token;

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'yourgmail@gmail.com';
        $mail->Password = 'your-app-password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('yourgmail@gmail.com', 'Printsiv');
        $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Printsiv - Password Reset Request';

        $mail->Body = "
            <h2>Password Reset Request</h2>
            <p>Hello {$user['first_name']},</p>
            <p>We received a request to reset your Printsiv account password.</p>
            <p>Click the link below to reset your password:</p>
            <p><a href='{$resetLink}' style='color:#3498db;'>Reset Password</a></p>
            <p>This link expires in 1 hour.</p>
            <p>If you did not request this, please ignore this email.</p>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
    }

    sendResponse(true, 'If an account with that email exists, a reset link has been sent');

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