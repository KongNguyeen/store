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
$feedback_id = (int)($_POST['feedback_id'] ?? 0);
$reply = sanitize($_POST['reply'] ?? '');
$status = $_POST['status'] ?? '';

if (!$feedback_id || !$reply || !$status) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

// Validate status
$allowed_statuses = ['new', 'in_progress', 'resolved'];
if (!in_array($status, $allowed_statuses)) {
    $response['message'] = 'Invalid status value';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    // Lấy thông tin feedback và user
    $stmt = $pdo->prepare("
        SELECT f.*, u.email, u.full_name
        FROM feedbacks f 
        JOIN users u ON f.user_id = u.user_id
        WHERE f.feedback_id = ?
    ");
    $stmt->execute([$feedback_id]);
    $feedback = $stmt->fetch();

    if (!$feedback) {
        throw new Exception('Feedback không tồn tại');
    }

    // Cập nhật trạng thái
    $stmt = $pdo->prepare("
        UPDATE feedbacks 
        SET status = ?, updated_at = NOW()
        WHERE feedback_id = ?
    ");
    $stmt->execute([$status, $feedback_id]);

    // Lưu lịch sử trả lời
    $stmt = $pdo->prepare("
        INSERT INTO feedback_replies (
            feedback_id,
            admin_id,
            reply_message,
            created_at
        ) VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([
        $feedback_id,
        $_SESSION['user_id'],
        $reply
    ]);

    $pdo->commit();

    // Gửi email thông báo cho khách hàng
    $subject = "Phản hồi của bạn đã được trả lời";
    $message = "Xin chào {$feedback['full_name']},\n\n";
    $message .= "Phản hồi của bạn đã được trả lời:\n\n";
    $message .= "Nội dung phản hồi của bạn:\n{$feedback['message']}\n\n";
    $message .= "Trả lời từ admin:\n{$reply}\n\n";
    $message .= "Trạng thái: " . ($status == 'resolved' ? 'Đã xử lý' : 'Đang xử lý') . "\n\n";
    $message .= "Cảm ơn bạn đã gửi phản hồi cho chúng tôi!\n";
    $message .= "Trân trọng,\n";
    $message .= SITE_NAME;

    // Uncomment để gửi email thật
    // mail($feedback['email'], $subject, $message);

    $response['success'] = true;
    $response['message'] = 'Đã trả lời phản hồi thành công';

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Có lỗi xảy ra: ' . $e->getMessage();
}

echo json_encode($response);
?>