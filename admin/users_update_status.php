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
$status = (int)($_POST['status'] ?? -1);

if (!$user_id || $status === -1) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

// Không thể khóa tài khoản của chính mình
if ($user_id === $_SESSION['user_id']) {
    $response['message'] = 'Không thể thay đổi trạng thái tài khoản của chính mình';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getPDO();

    // Kiểm tra user tồn tại
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User không tồn tại');
    }

    // Cập nhật trạng thái
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_active = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->execute([$status, $user_id]);

    // Xác định hành động để hiển thị thông báo
    $action = $status ? 'mở khóa' : 'khóa';

    // TODO: Gửi email thông báo cho user
    // if ($status == 0) {
    //     $subject = "Tài khoản của bạn đã bị khóa";
    //     $message = "Xin chào {$user['full_name']},\n\n";
    //     $message .= "Tài khoản của bạn đã bị khóa. Vui lòng liên hệ admin để biết thêm chi tiết.";
    // } else {
    //     $subject = "Tài khoản của bạn đã được mở khóa";
    //     $message = "Xin chào {$user['full_name']},\n\n";
    //     $message .= "Tài khoản của bạn đã được mở khóa. Bạn có thể đăng nhập lại bình thường.";
    // }
    // mail($user['email'], $subject, $message);

    $response['success'] = true;
    $response['message'] = ucfirst($action) . ' tài khoản thành công';

} catch (Exception $e) {
    $response['message'] = 'Có lỗi xảy ra: ' . $e->getMessage();
}

echo json_encode($response);
?>