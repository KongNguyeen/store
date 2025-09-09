<?php
// Simple admin chat API
require_once '../../config/config.php';
require_once '../../config/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Clear output buffer
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

$pdo = getPDO();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_chat_rooms':
            // Get all active chat rooms
            $sql = "
                SELECT cr.*, u.full_name as user_name, u.email,
                       (SELECT COUNT(*) FROM chat_messages cm WHERE cm.room_id = cr.room_id) as message_count,
                       (SELECT cm.message FROM chat_messages cm WHERE cm.room_id = cr.room_id ORDER BY cm.created_at DESC LIMIT 1) as last_message,
                       (SELECT cm.created_at FROM chat_messages cm WHERE cm.room_id = cr.room_id ORDER BY cm.created_at DESC LIMIT 1) as last_message_time
                FROM chat_rooms cr
                JOIN users u ON cr.user_id = u.user_id
                WHERE cr.status = 'active'
                ORDER BY cr.updated_at DESC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'rooms' => $rooms]);
            break;
            
        case 'get_room_messages':
            $room_id = (int)($_GET['room_id'] ?? 0);
            
            if (!$room_id) {
                throw new Exception('Room ID is required');
            }
            
            // Get messages for this room
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
            
        case 'send_message':
            $room_id = (int)($_POST['room_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $admin_id = $_SESSION['user_id'];
            
            if (!$message) {
                throw new Exception('Message cannot be empty');
            }
            
            if (!$room_id) {
                throw new Exception('Room ID is required');
            }
            
            // Send message
            $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$room_id, $admin_id, $message]);
            
            // Update room admin_id if not set
            $stmt = $pdo->prepare("UPDATE chat_rooms SET admin_id = ?, updated_at = NOW() WHERE room_id = ? AND admin_id IS NULL");
            $stmt->execute([$admin_id, $room_id]);
            
            // Update room timestamp
            $stmt = $pdo->prepare("UPDATE chat_rooms SET updated_at = NOW() WHERE room_id = ?");
            $stmt->execute([$room_id]);
            
            echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
            break;
            
        case 'get_chat_stats':
            // Get basic stats
            $stats = [];
            
            // Total rooms
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_rooms WHERE status = 'active'");
            $stmt->execute();
            $stats['total_rooms'] = (int)$stmt->fetchColumn();
            
            // Active rooms (same as total for now)
            $stats['active_rooms'] = $stats['total_rooms'];
            
            // Unassigned rooms (no admin)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_rooms WHERE status = 'active' AND admin_id IS NULL");
            $stmt->execute();
            $stats['unassigned_rooms'] = (int)$stmt->fetchColumn();
            
            // Unread messages (messages from users that admin hasn't replied to)
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT cr.room_id) 
                FROM chat_rooms cr 
                WHERE cr.status = 'active' 
                AND EXISTS (
                    SELECT 1 FROM chat_messages cm 
                    JOIN users u ON cm.sender_id = u.user_id 
                    WHERE cm.room_id = cr.room_id 
                    AND u.role_id != 1 
                    AND cm.created_at > COALESCE(
                        (SELECT MAX(cm2.created_at) 
                         FROM chat_messages cm2 
                         JOIN users u2 ON cm2.sender_id = u2.user_id 
                         WHERE cm2.room_id = cr.room_id AND u2.role_id = 1), 
                        '1970-01-01'
                    )
                )
            ");
            $stmt->execute();
            $stats['unread_messages'] = (int)$stmt->fetchColumn();
            
            // Total messages today
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE DATE(created_at) = CURDATE()");
            $stmt->execute();
            $stats['messages_today'] = (int)$stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'close_room':
            $room_id = (int)($_POST['room_id'] ?? 0);
            
            if (!$room_id) {
                throw new Exception('Room ID is required');
            }
            
            // Update room status to closed
            $stmt = $pdo->prepare("UPDATE chat_rooms SET status = 'closed', updated_at = CURRENT_TIMESTAMP WHERE room_id = ?");
            $stmt->execute([$room_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Room closed successfully']);
            } else {
                throw new Exception('Room not found or already closed');
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
