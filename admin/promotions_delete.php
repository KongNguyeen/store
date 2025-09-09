<?php
// admin/promotions_delete.php
require_once '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

$pdo = getPDO();
$promotion_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($promotion_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM promotions WHERE promotion_id = ?');
    $result = $stmt->execute([$promotion_id]);
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Xóa thất bại']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
}
