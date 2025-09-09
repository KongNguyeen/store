<?php
require_once '../middleware/admin_auth.php';
require_once '../config/config.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $response['message'] = 'Invalid CSRF token';
    echo json_encode($response);
    exit;
}

// Validate input
$user_id = (int)($_POST['user_id'] ?? 0);
$role_id = (int)($_POST['role_id'] ?? 0);

if (!$user_id || !$role_id) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

// Không thể thay đổi quyền của chính mình
if ($user_id === $_SESSION['user_id']) {
    $response['message'] = 'Không thể thay đổi quyền của chính mình';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getPDO();

    // Kiểm tra user tồn tại
    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User không tồn tại');
    }

    // Kiểm tra role tồn tại
    $stmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_id = ?");
    $stmt->execute([$role_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Role không tồn tại');
    }

    // Cập nhật quyền
    $stmt = $pdo->prepare("
        UPDATE users 
        SET role_id = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->execute([$role_id, $user_id]);

    $response['success'] = true;
    $response['message'] = 'Cập nhật quyền thành công';

} catch (Exception $e) {
    $response['message'] = 'Có lỗi xảy ra: ' . $e->getMessage();
}

echo json_encode($response);
?>