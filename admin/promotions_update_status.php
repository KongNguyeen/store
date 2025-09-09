<?php
// admin/promotions_update_status.php
require_once '../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

$pdo = getPDO();
$promotion_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$active = isset($_POST['active']) ? intval($_POST['active']) : 0;

if ($promotion_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE promotions SET active = ? WHERE promotion_id = ?');
    $result = $stmt->execute([$active, $promotion_id]);
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cập nhật thất bại']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
}
