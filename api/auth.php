<?php
/**
 * Authentication API
 * Handles signup, login, logout, account lookup, password reset request,
 * and password reset completion.
 */

header('Content-Type: application/json; charset=utf-8');
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
    case 'reset_password': handleResetPassword(); break;
    default: sendResponse(false, 'Invalid action', [], 400);
}

function handleSignup(): void
{
    global $conn;
    $required = ['firstName','lastName','email','phone','address','city','state','password'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) sendResponse(false, "Field {$field} is required", [], 422);
    }

    $firstName = clean($_POST['firstName']);
    $lastName  = clean($_POST['lastName']);
    $email     = strtolower(clean($_POST['email']));
    $phone     = clean($_POST['phone']);
    $address   = clean($_POST['address']);
    $city      = clean($_POST['city']);
    $state     = clean($_POST['state']);
    $password  = (string)$_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendResponse(false, 'Invalid email address', [], 422);
    if (strlen($password) < 8) sendResponse(false, 'Password must be at least 8 characters', [], 422);

    $check = $conn->prepare('SELECT id FROM customers WHERE email = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) sendResponse(false, 'An account with this email already exists', [], 409);
    $check->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "INSERT INTO customers
        (email, phone, first_name, last_name, address, city, state, password, role, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user', NOW())"
    );
    $stmt->bind_param('ssssssss', $email, $phone, $firstName, $lastName, $address, $city, $state, $hash);

    if (!$stmt->execute()) sendResponse(false, 'Unable to create account', [], 500);
    $userId = (int)$conn->insert_id;
    $stmt->close();

    sendResponse(true, 'Account created successfully', ['user' => [
        'id'=>$userId,'email'=>$email,'first_name'=>$firstName,'last_name'=>$lastName,
        'phone'=>$phone,'address'=>$address,'city'=>$city,'state'=>$state,'role'=>'user'
    ]]);
}

function handleLogin(): void
{
    global $conn;
    $email = strtolower(clean($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!$email || !$password) sendResponse(false, 'Email and password are required', [], 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) sendResponse(false, 'Invalid email address', [], 422);

    // Admin/manager accounts live in users; customer accounts live in customers.
    $adminStmt = $conn->prepare('SELECT id,email,password,first_name,last_name,role,status FROM users WHERE email = ? LIMIT 1');
    $adminStmt->bind_param('s', $email);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();

    if ($adminResult->num_rows) {
        $admin = $adminResult->fetch_assoc();
        $adminStmt->close();
        if (($admin['status'] ?? 'active') !== 'active' || !password_verify($password, $admin['password'])) {
            sendResponse(false, 'Invalid email or password', [], 401);
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['role'] = $admin['role'];
        $_SESSION['customer_id'] = 0;

        $u = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $u->bind_param('i', $admin['id']);
        $u->execute();
        $u->close();

        sendResponse(true, 'Login successful', ['user'=>[
            'id'=>$admin['id'],'email'=>$admin['email'],'first_name'=>$admin['first_name'],
            'last_name'=>$admin['last_name'],'role'=>$admin['role']
        ]]);
    }
    $adminStmt->close();

    $stmt = $conn->prepare('SELECT * FROM customers WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->num_rows) sendResponse(false, 'Invalid email or password', [], 401);

    $user = $result->fetch_assoc();
    $stmt->close();

    if (empty($user['password']) || !password_verify($password, $user['password'])) {
        sendResponse(false, 'Invalid email or password', [], 401);
    }

    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['admin_id'] = 0;

    $u = $conn->prepare('UPDATE customers SET last_login = NOW() WHERE id = ?');
    $u->bind_param('i', $user['id']);
    $u->execute();
    $u->close();

    sendResponse(true, 'Login successful', ['user'=>[
        'id'=>$user['id'],'email'=>$user['email'],'first_name'=>$user['first_name'],
        'last_name'=>$user['last_name'],'phone'=>$user['phone'],'address'=>$user['address'],
        'city'=>$user['city'],'state'=>$user['state'],'role'=>$user['role'] ?? 'user',
        'created_at'=>$user['created_at']
    ]]);
}

function handleLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    sendResponse(true, 'Logged out successfully');
}

function handleMe(): void
{
    global $conn;
    $id = requireLogin();
    $stmt = $conn->prepare('SELECT id,email,phone,first_name,last_name,address,city,state,role,created_at FROM customers WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->num_rows) sendResponse(false, 'Account not found', [], 404);
    sendResponse(true, 'Account retrieved', ['user'=>$result->fetch_assoc()]);
}

function handleForgetPassword(): void
{
    global $conn;
    $email = strtolower(clean($_POST['email'] ?? ''));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) sendResponse(false, 'Valid email is required', [], 422);

    $stmt = $conn->prepare('SELECT id,first_name,last_name FROM customers WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Always return the same message to prevent account enumeration.
    if ($result->num_rows) {
        $user = $result->fetch_assoc();
        $stmt->close();

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $old = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
        $old->bind_param('s', $email);
        $old->execute();
        $old->close();

        $save = $conn->prepare('INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())');
        $save->bind_param('ss', $email, $tokenHash);
        $save->execute();
        $save->close();

        $resetLink = 'https://www.printsiv.com/reset-password.html?token=' . urlencode($token);
        sendResetEmail($email, $user, $resetLink);
    } else {
        $stmt->close();
    }

    sendResponse(true, 'If an account with that email exists, a reset link has been sent');
}

function handleResetPassword(): void
{
    global $conn;
    $token = trim((string)($_POST['token'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) sendResponse(false, 'Invalid or expired reset link', [], 400);
    if (strlen($password) < 8) sendResponse(false, 'Password must be at least 8 characters', [], 422);
    if ($password !== $confirm) sendResponse(false, 'Passwords do not match', [], 422);

    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare(
        'SELECT id,email FROM password_resets
         WHERE token = ? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->num_rows) {
        $stmt->close();
        sendResponse(false, 'Invalid or expired reset link', [], 400);
    }

    $reset = $result->fetch_assoc();
    $stmt->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $conn->begin_transaction();

    try {
        $update = $conn->prepare('UPDATE customers SET password = ?, updated_at = NOW() WHERE email = ? LIMIT 1');
        $update->bind_param('ss', $hash, $reset['email']);
        if (!$update->execute() || $update->affected_rows < 1) {
            $update->close();
            throw new RuntimeException('Password update failed');
        }
        $update->close();

        $consume = $conn->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $consume->bind_param('i', $reset['id']);
        if (!$consume->execute() || $consume->affected_rows !== 1) {
            $consume->close();
            throw new RuntimeException('Reset token could not be consumed');
        }
        $consume->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Password reset error: '.$e->getMessage());
        sendResponse(false, 'Unable to reset password. Please request a new link.', [], 500);
    }

    // Do not retain an authenticated session after a password change.
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();

    sendResponse(true, 'Password reset successfully. Please log in with your new password.');
}

function sendResetEmail(string $email, array $user, string $resetLink): void
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
        $mail->setFrom($mail->Username ?: 'no-reply@printsiv.com', 'Printsiv');
        $mail->addAddress($email, trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? '')));
        $mail->isHTML(true);
        $mail->Subject = 'Printsiv - Password Reset Request';
        $mail->Body = '<h2>Password Reset Request</h2><p>Use the link below to choose a new password.</p><p><a href="'.htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8').'">Reset Password</a></p><p>This link expires in 1 hour and can only be used once.</p>';
        if ($mail->Username && $mail->Password) $mail->send();
    } catch (Exception $e) {
        error_log('Password reset mail error: '.$e->getMessage());
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
