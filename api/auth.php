<?php
/**
 * Authentication API
 * Handles signup, login, and password reset requests.
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once __DIR__ . '/middleware.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'signup': handleSignup(); break;
    case 'login': handleLogin(); break;
    case 'logout': handleLogout(); break;
    case 'me': handleMe(); break;
    case 'forget_password': handleForgetPassword(); break;
    default: sendResponse(false, 'Invalid action');
}

function handleSignup()
{
    global $conn;
    $required = ['firstName','lastName','email','phone','address','city','state','password'];
    foreach ($required as $field) if (empty($_POST[$field])) sendResponse(false, "Field {$field} is required");

    $firstName = sanitize($_POST['firstName']);
    $lastName = sanitize($_POST['lastName']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendResponse(false, 'Invalid email address');
    if (strlen($password) < 8) sendResponse(false, 'Password must be at least 8 characters');

    $check = $conn->prepare('SELECT id FROM customers WHERE email = ?');
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) sendResponse(false, 'An account with this email already exists');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO customers (email, phone, first_name, last_name, address, city, state, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'user\', NOW())');
    $stmt->bind_param('ssssssss', $email, $phone, $firstName, $lastName, $address, $city, $state, $hash);

    if (!$stmt->execute()) sendResponse(false, 'Unable to create account');
    $userId = (int)$conn->insert_id;
    $check->close(); $stmt->close();

    sendResponse(true, 'Account created successfully', ['user' => [
        'id'=>$userId,'email'=>$email,'first_name'=>$firstName,'last_name'=>$lastName,
        'phone'=>$phone,'address'=>$address,'city'=>$city,'state'=>$state,'role'=>'user'
    ]]);
}

function handleLogin()
{
    global $conn;
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) sendResponse(false, 'Email and password are required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendResponse(false, 'Invalid email address');

    // Admin/manager accounts live in users; customer accounts live in customers.
    $adminStmt = $conn->prepare('SELECT id,email,password,first_name,last_name,role,status FROM users WHERE email = ? LIMIT 1');
    $adminStmt->bind_param('s', $email); $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    if ($adminResult->num_rows) {
        $admin = $adminResult->fetch_assoc();
        if (($admin['status'] ?? 'active') !== 'active' || !password_verify($password, $admin['password'])) sendResponse(false, 'Invalid email or password');
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['role'] = $admin['role'];
        $_SESSION['customer_id'] = 0;
        $u = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?'); $u->bind_param('i', $admin['id']); $u->execute(); $u->close();
        sendResponse(true, 'Login successful', ['user'=>['id'=>$admin['id'],'email'=>$admin['email'],'first_name'=>$admin['first_name'],'last_name'=>$admin['last_name'],'role'=>$admin['role']]]);
    }
    $adminStmt->close();

    $stmt = $conn->prepare('SELECT * FROM customers WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email); $stmt->execute(); $result = $stmt->get_result();
    if (!$result->num_rows) sendResponse(false, 'Invalid email or password');
    $user = $result->fetch_assoc();
    if (empty($user['password']) || !password_verify($password, $user['password'])) sendResponse(false, 'Invalid email or password');

    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['admin_id'] = 0;

    $u = $conn->prepare('UPDATE customers SET last_login = NOW() WHERE id = ?'); $u->bind_param('i', $user['id']); $u->execute(); $u->close();
    sendResponse(true, 'Login successful', ['user'=>[
        'id'=>$user['id'],'email'=>$user['email'],'first_name'=>$user['first_name'],'last_name'=>$user['last_name'],
        'phone'=>$user['phone'],'address'=>$user['address'],'city'=>$user['city'],'state'=>$user['state'],
        'role'=>$user['role'] ?? 'user','created_at'=>$user['created_at']
    ]]);
}

function handleLogout()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    sendResponse(true, 'Logged out successfully');
}

function handleMe()
{
    global $conn;
    $id = requireLogin();
    $stmt = $conn->prepare('SELECT id,email,phone,first_name,last_name,address,city,state,role,created_at FROM customers WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id); $stmt->execute(); $result = $stmt->get_result();
    if (!$result->num_rows) sendResponse(false, 'Account not found');
    sendResponse(true, 'Account retrieved', ['user'=>$result->fetch_assoc()]);
}

function handleForgetPassword()
{
    global $conn;
    $email = sanitize($_POST['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) sendResponse(false, 'Valid email is required');
    $stmt = $conn->prepare('SELECT * FROM customers WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email); $stmt->execute(); $result = $stmt->get_result();
    // Always return the same success message to avoid account enumeration.
    if ($result->num_rows) {
        $user = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $old = $conn->prepare('DELETE FROM password_resets WHERE email = ?'); $old->bind_param('s',$email); $old->execute(); $old->close();
        $save = $conn->prepare('INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW())');
        $save->bind_param('ss', $email, $tokenHash); $save->execute(); $save->close();

        $resetLink = 'https://www.printsiv.com/reset-password?token=' . urlencode($token);
        try {
            $mail = new PHPMailer(true); $mail->isSMTP(); $mail->Host='smtp.gmail.com'; $mail->SMTPAuth=true;
            $mail->Username=getenv('MAIL_USERNAME') ?: ''; $mail->Password=getenv('MAIL_PASSWORD') ?: '';
            $mail->SMTPSecure='tls'; $mail->Port=587; $mail->setFrom($mail->Username ?: 'no-reply@printsiv.com','Printsiv');
            $mail->addAddress($email, trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')));
            $mail->isHTML(true); $mail->Subject='Printsiv - Password Reset Request';
            $mail->Body='<h2>Password Reset Request</h2><p>Use the link below to reset your password.</p><p><a href="'.$resetLink.'">Reset Password</a></p><p>This link expires in 1 hour.</p>';
            if ($mail->Username && $mail->Password) $mail->send();
        } catch (Exception $e) { error_log('Password reset mail error: '.$e->getMessage()); }
    }
    sendResponse(true, 'If an account with that email exists, a reset link has been sent');
}

function sanitize($data) { global $conn; return $conn->real_escape_string(trim(strip_tags((string)$data))); }
function sendResponse($success,$message,$data=[]) { echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data]); exit; }
?>