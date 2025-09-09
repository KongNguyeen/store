<?php
// Include config to get session configuration
require_once '../config/config.php';
require_once '../config/functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

// Clear output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON header
header('Content-Type: application/json');

// Get database connection from functions
$pdo = getPDO();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_or_create_room':
            $user_id = $_SESSION['user_id'];
            
            // Check existing room
            $stmt = $pdo->prepare("SELECT room_id FROM chat_rooms WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $room = $stmt->fetch();
            
            if ($room) {
                $room_id = $room['room_id'];
            } else {
                // Create new room
                $stmt = $pdo->prepare("INSERT INTO chat_rooms (user_id, status) VALUES (?, 'active')");
                $stmt->execute([$user_id]);
                $room_id = $pdo->lastInsertId();
            }
            
            echo json_encode(['success' => true, 'room_id' => (int)$room_id]);
            break;
            
        case 'send_message':
            $room_id = (int)($_POST['room_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $sender_id = $_SESSION['user_id'];
            
            if (!$message) {
                throw new Exception('Tin nhắn không được để trống');
            }
            
            // Check room access
            $stmt = $pdo->prepare("SELECT * FROM chat_rooms WHERE room_id = ? AND (user_id = ? OR admin_id = ?)");
            $stmt->execute([$room_id, $sender_id, $sender_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Bạn không có quyền truy cập phòng chat này');
            }
            
            // Send message
            $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$room_id, $sender_id, $message]);
            
            // Update room timestamp
            $stmt = $pdo->prepare("UPDATE chat_rooms SET updated_at = NOW() WHERE room_id = ?");
            $stmt->execute([$room_id]);
            
            echo json_encode(['success' => true, 'message' => 'Tin nhắn đã được gửi']);
            break;
            
        case 'get_messages':
            $room_id = (int)($_POST['room_id'] ?? $_GET['room_id'] ?? 0);
            $sender_id = $_SESSION['user_id'];
            
            // Check room access
            $stmt = $pdo->prepare("SELECT * FROM chat_rooms WHERE room_id = ? AND (user_id = ? OR admin_id = ?)");
            $stmt->execute([$room_id, $sender_id, $sender_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Bạn không có quyền truy cập phòng chat này');
            }
            
            // Get messages
            $sql = "
                SELECT cm.*, u.full_name, u.role_id,
                       CASE WHEN u.role_id = 1 THEN 'admin' ELSE 'user' END as sender_type
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.user_id
                WHERE cm.room_id = ?
                ORDER BY cm.created_at ASC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$room_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        default:
            throw new Exception('Hành động không hợp lệ');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
